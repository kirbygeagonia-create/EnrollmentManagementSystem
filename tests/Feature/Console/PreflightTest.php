<?php

namespace Tests\Feature\Console;

use Carbon\Carbon;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ems:preflight turns DEPLOYMENT.md from something an operator reads into something they run.
 * Each test here is one of the traps that document records, and the assertion is on the fix
 * being named in the output, not only on the failure: an operator standing in front of a
 * server has to be told what to type.
 */
class PreflightTest extends TestCase
{
    use RefreshDatabase;

    private string $lastOutput = '';

    private function callPreflight(): int
    {
        // Artisan::output() drains the buffer, so the report is read once per run and kept.
        $code = Artisan::call('ems:preflight');
        $this->lastOutput = Artisan::output();

        return $code;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase hands over an empty schema, and an empty RBAC is one of the things
        // this command is supposed to fail on. Seed it so the passing tests mean "a server
        // that is actually ready", not "a server with nothing in it".
        $this->seed(RbacSeeder::class);
    }

    private function commandOutput(): string
    {
        return $this->lastOutput;
    }

    #[Test]
    public function a_server_that_meets_the_documented_requirements_passes(): void
    {
        $this->assertSame(0, $this->callPreflight());

        $out = $this->commandOutput();
        $this->assertStringNotContainsString('FAIL', $out);
        $this->assertStringContainsString('Database connection', $out);
        $this->assertStringContainsString('Migrations', $out);
        $this->assertStringContainsString('Browser for PDFs', $out);

        // The suite runs as `testing`, which is not the environment a school server should
        // report, so the honest verdict here is a warning, not a clean sheet: this command
        // cannot tell a laptop from the machine the desks will use, and says so.
        $this->assertStringContainsString('WARN  APP_ENV / APP_DEBUG', $out);
        $this->assertStringContainsString('check(s) worth a look', $out);
    }

    #[Test]
    public function a_database_that_stamps_its_own_hours_fails_and_names_the_string_to_fix(): void
    {
        // The two DATETIME columns with a MySQL default (auditlogs.createdAt,
        // enrollmentstatushistory.changedAt) get whatever string the database writes, and every other
        // timestamp gets whatever PHP writes. A DATETIME keeps no zone, so a session running in a different
        // one silently puts a record hours from its own siblings. Moving PHP's clock is how this suite can
        // create that split: the suite database answers in its own time either way.
        Carbon::setTestNow(Carbon::now()->addHours(11));

        try {
            $this->assertSame(1, $this->callPreflight());
        } finally {
            Carbon::setTestNow(null);
        }

        $out = $this->commandOutput();
        $this->assertStringContainsString('FAIL  Clocks agree', $out);
        $this->assertStringContainsString('apart in the column', $out);
        $this->assertStringContainsString('session time zone', $out);
        $this->assertStringContainsString('1 check(s) will break a desk', $out);
    }

    #[Test]
    public function the_server_that_agrees_on_one_clock_says_so_and_does_not_shout(): void
    {
        // The other direction matters as much: a rule that fails a healthy server teaches the operator to
        // ignore the report. SQLite answers datetime('now') in UTC and the app timezone is UTC, so the two
        // strings are the same instant and the line is a clean OK.
        $this->assertSame(0, $this->callPreflight());

        $out = $this->commandOutput();
        $this->assertStringContainsString('OK  Clocks agree', $out);
        $this->assertStringContainsString('write the same stamp', $out);
    }

    #[Test]
    public function a_documents_disk_that_exposes_a_url_fails_and_says_why(): void
    {
        config(['filesystems.disks.documents' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'url' => 'https://ems.example.invalid/storage',
            'throw' => false,
        ]]);

        $this->assertSame(1, $this->callPreflight());

        $out = $this->commandOutput();
        $this->assertStringContainsString('Upload disk', $out);
        $this->assertStringContainsString('must not be reachable by path', $out);
    }

    #[Test]
    public function a_server_without_a_documents_disk_has_nowhere_private_to_put_a_paper(): void
    {
        config(['filesystems.disks.documents' => null]);

        $this->assertSame(1, $this->callPreflight());
        $this->assertStringContainsString('declares no "documents" disk', $this->commandOutput());
    }

    #[Test]
    public function a_configured_browser_path_that_does_not_exist_fails_and_names_the_variable_to_fix(): void
    {
        // locate() honours a configured chrome_path strictly and does not fall back to a guess,
        // so a typo in EMS_CHROME_PATH is exactly the case that must not pass quietly.
        config(['services.chrome_path' => 'C:\\ems-preflight-test\\definitely-not-a-browser.exe']);

        $this->assertSame(1, $this->callPreflight());

        $out = $this->commandOutput();
        $this->assertStringContainsString('Browser for PDFs', $out);
        $this->assertStringContainsString('every download answers 503', $out);
        $this->assertStringContainsString('EMS_CHROME_PATH', $out);
    }

    #[Test]
    public function an_unseeded_database_fails_because_every_desk_would_be_locked_out(): void
    {
        DB::table('permissions')->delete();

        $this->assertSame(1, $this->callPreflight());

        $out = $this->commandOutput();
        $this->assertStringContainsString('every desk is locked out', $out);
        $this->assertStringContainsString('db:seed --class=RbacSeeder', $out);
    }

    #[Test]
    public function a_migration_file_that_never_ran_is_named_so_the_operator_knows_which_table_is_missing(): void
    {
        $last = DB::table('migrations')->orderByDesc('id')->first();
        $this->assertNotNull($last, 'The test database records no migrations.');

        DB::table('migrations')->where('id', $last->id)->delete();

        $this->assertSame(1, $this->callPreflight());

        $out = $this->commandOutput();
        $this->assertStringContainsString('not applied', $out);
        $this->assertStringContainsString($last->migration, $out);
        $this->assertStringContainsString('php artisan migrate', $out);
    }

    #[Test]
    public function jobs_sitting_in_the_table_with_no_worker_proven_fail_the_run(): void
    {
        config(['queue.default' => 'database']);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'SendNotification', 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time(),
            'created_at' => time(),
        ]);

        $this->assertSame(1, $this->callPreflight());

        $out = $this->commandOutput();
        $this->assertStringContainsString('desks will not see their notifications', $out);
    }

    #[Test]
    public function a_database_left_on_a_non_sync_queue_without_waiting_jobs_is_a_warning_not_a_fault(): void
    {
        config(['queue.default' => 'redis']);

        $this->assertSame(0, $this->callPreflight());

        $out = $this->commandOutput();
        $this->assertStringContainsString('WARN', $out);
        $this->assertStringContainsString('a worker has to be running', $out);
        $this->assertStringNotContainsString('FAIL', $out);
    }

    #[Test]
    public function an_absent_print_folder_is_not_a_fault_because_the_print_driver_makes_it(): void
    {
        $prints = base_path('storage/app/prints');
        $moved = base_path('storage/app/prints-preflight-test');

        if (! File::isDirectory($prints)) {
            $this->markTestSkipped('This checkout has no storage/app/prints to move aside.');
        }

        File::moveDirectory($prints, $moved);

        try {
            $this->assertSame(0, $this->callPreflight());

            $out = $this->commandOutput();
            $this->assertStringContainsString('Print folder', $out);
            $this->assertStringContainsString('PrintService makes it on the first download', $out);
            $this->assertStringNotContainsString('FAIL', $out);
        } finally {
            File::moveDirectory($moved, $prints);
        }

        $this->assertTrue(File::isDirectory($prints), 'The sample folder was not put back.');
    }
}
