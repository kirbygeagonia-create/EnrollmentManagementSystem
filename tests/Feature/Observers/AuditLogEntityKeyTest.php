<?php

namespace Tests\Feature\Observers;

use App\Models\Auditlogs;
use App\Models\Settings;
use App\Models\Staffusers;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The audit trail has to be able to name a row that has no number.
 *
 * `auditlogs.entityId` was a NOT NULL INT, and the observer read `Model::getKey()` into
 * it, so a table keyed by a name — settings is keyed by `settingKey` — had no legal
 * value to write. The observer skipped those rows and logged a warning instead, which
 * meant the screen that answers "who changed this" was blind to exactly the settings an
 * intruder or an honest mistake would touch: the school name, the address, the logo.
 */
class AuditLogEntityKeyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    private function setting(string $key, string $value): Settings
    {
        // `settingKey` is the primary key and is deliberately not fillable, so the row
        // is built attribute by attribute rather than through mass assignment.
        $setting = new Settings;
        $setting->settingKey = $key;
        $setting->settingValue = $value;
        $setting->save();

        return $setting;
    }

    #[Test]
    public function editing_a_setting_by_its_string_key_leaves_an_audit_row(): void
    {
        $setting = $this->setting('schoolName', 'Southeast Asian Institute of Technology');

        $setting->settingValue = 'Southeast Asian Institute of Technology — revised';
        $setting->save();

        $logs = Auditlogs::query()
            ->where('entityTable', 'settings')
            ->where('entityId', 'schoolName')
            ->orderBy('auditId')
            ->get();

        $this->assertCount(2, $logs, 'Both the create and the update must be on the trail.');
        $this->assertSame('created', $logs[0]->action);
        $this->assertSame('updated', $logs[1]->action);
    }

    #[Test]
    public function a_numeric_key_is_still_recorded_so_existing_trail_reads_are_unchanged(): void
    {
        $staff = Staffusers::factory()->create();

        $this->assertDatabaseHas('auditlogs', [
            'entityTable' => 'staffusers',
            'entityId' => (string) $staff->userId,
            'action' => 'created',
        ]);
    }
}
