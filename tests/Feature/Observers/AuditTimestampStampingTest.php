<?php

namespace Tests\Feature\Observers;

use App\Enums\StaffRole;
use App\Models\Auditlogs;
use App\Models\Staffusers;
use Carbon\Carbon;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The audit trail is stamped by one process, on purpose.
 *
 * Ruled 2026-10-13 after measuring the two clocks: PHP writes `2026-10-13 14:52` while this install's
 * MySQL answers `NOW()` = `2026-10-13 03:52` for the same instant (session zone `SYSTEM`, and
 * `system_time_zone` reports UTC-11 against an app timezone of UTC). Both audit columns carried a
 * `CURRENT_TIMESTAMP` default, so a row written without a timestamp would have inherited the database's
 * answer and sat eleven hours from its own siblings in a plain DATETIME that stores no zone.
 *
 * Removing the default is only safe because the writers were read first: `AuditLogObserver` and
 * `EnrollmentStateMachine` already pass `now()`, but the two `Auditlogs::create` calls in
 * `UserManagementController` did not — they were the database-stamped path, and the migration would have
 * turned "assign roles" into a missing-default error. These tests pin both halves of that: the writer
 * stamps from PHP, and the schema no longer offers a second answer.
 *
 * The schema is asserted by reading it, not by attempting a bad insert: whether MySQL rejects a missing
 * value depends on `sql_mode`, which is the server's choice, not this repository's — a test that leaned
 * on it would be green on one machine and different on the runner.
 */
class AuditTimestampStampingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * The column's database-side default, read from whichever dictionary this connection has.
     *
     * `LOWER()` on MySQL is not decoration: information_schema comparisons are case-sensitive, and a
     * first attempt here matched nothing because it searched for lowercase `current_timestamp` against
     * the stored `CURRENT_TIMESTAMP` — which read as "this install has no defaults" and was false.
     */
    private function columnDefault(string $table, string $column): ?string
    {
        $connection = DB::connection();

        if ($connection->getDriverName() === 'sqlite') {
            $row = collect($connection->select("PRAGMA table_info('{$table}')"))
                ->firstWhere('name', $column);

            return $row === null ? null : $row->dflt_value;
        }

        $row = $connection->selectOne('
            SELECT COLUMN_DEFAULT AS dft
              FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
        ', [$table, $column]);

        return $row === null || $row->dft === null ? null : (string) $row->dft;
    }

    #[Test]
    public function neither_audit_column_defers_the_timestamp_to_the_database(): void
    {
        foreach ([['auditlogs', 'createdAt'], ['enrollmentstatushistory', 'changedAt']] as [$table, $column]) {
            $default = $this->columnDefault($table, $column);

            $this->assertNull($default,
                "{$table}.{$column} still carries a database default (".var_export($default, true).
                '), so a row written without a timestamp is stamped by the database clock, not the app\'s.');
        }
    }

    #[Test]
    public function a_writer_that_omits_the_timestamp_is_stamped_by_the_model_not_the_database(): void
    {
        // The case that actually broke first: `Auditlogs` has `$timestamps = false`, the column had no
        // default left, and a test fixture inserting a `login` row without `createdAt` went from
        // "stamped by MySQL, eleven hours out" to a NOT NULL violation. The guarantee therefore belongs
        // on the model, where a writer cannot forget it, rather than at each call site.
        Carbon::setTestNow('2026-10-13 04:00:00');

        try {
            $log = Auditlogs::create([
                'userId' => null,
                'action' => 'login',
                'entityTable' => 'staffusers',
                'entityId' => 1,
                'ipAddress' => '127.0.0.1',
            ]);
        } finally {
            Carbon::setTestNow(null);
        }

        $this->assertSame('2026-10-13 04:00:00', $log->fresh()->createdAt->toDateTimeString(),
            'The model did not fill createdAt with the application clock.');
    }

    #[Test]
    public function the_original_migrations_do_still_create_the_default_this_one_removes(): void
    {
        // The guard on the test above: if a fresh schema never had a default, `assertNull` would pass
        // without the migration doing anything. Scoped to the two create migrations by name, because a
        // first draft scanned every migration and counted this project's own `down()` — which restores
        // the default, so it matched three times and "failed" for a reason that proved nothing.
        $creates = [
            '2026_08_02_1000000012_create_auditlogs_table.php',
            '2026_08_02_1000000036_create_enrollmentstatushistory_table.php',
        ];

        foreach ($creates as $file) {
            $path = database_path('migrations/'.$file);
            $this->assertFileExists($path, "The create migration {$file} is gone, so this guard has nothing to check.");

            $found = preg_match('/\$table->dateTime\(\'(createdAt|changedAt)\'\)->useCurrent\(\)/', file_get_contents($path));

            $this->assertSame(1, $found,
                "{$file} no longer declares the column with useCurrent(); the removal this suite verifies "
                .'is either already done elsewhere or no longer needed, and the first test should change too.');
        }
    }

    #[Test]
    public function assigning_roles_stamps_the_audit_row_with_the_applications_clock(): void
    {
        $admin = Staffusers::factory()->create([
            'role' => StaffRole::Admin,
            'employeeNo' => 'EMP-STAMP-ADMIN-'.uniqid(),
            'username' => 'stamp_admin_'.uniqid(),
            'email' => 'stamp_admin_'.uniqid().'@example.com',
        ]);
        $admin->assignRole('SysAdmin');

        $target = Staffusers::factory()->create([
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-STAMP-TARGET-'.uniqid(),
            'username' => 'stamp_target_'.uniqid(),
            'email' => 'stamp_target_'.uniqid().'@example.com',
        ]);
        $target->assignRole('Staff');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roleId = DB::table('roles')->where('name', 'RegistrarDesk')->value('id');
        $this->assertNotNull($roleId, 'RbacSeeder did not create the RegistrarDesk role.');

        // Frozen so the assertion is about *who stamped the row*, not about how close two clocks are:
        // the database on this machine is eleven hours away from PHP, and that is the whole finding.
        Carbon::setTestNow('2026-10-13 04:00:00');

        try {
            $this->actingAs($admin)->post(route('admin.users.roles.assign', $target), [
                'roleIds' => [$roleId],
            ])->assertRedirect();
        } finally {
            Carbon::setTestNow(null);
        }

        $row = DB::table('auditlogs')
            ->where('action', 'assigned')
            ->where('entityId', $target->userId)
            ->orderByDesc('auditId')
            ->first();

        $this->assertNotNull($row, 'Assigning roles wrote no audit row at all.');
        $this->assertSame('2026-10-13 04:00:00', (string) $row->createdAt,
            'The audit row was not stamped by PHP; a database default would put the server clock in a '
            .'plain DATETIME and the row would read hours away from its own table.');
    }
}
