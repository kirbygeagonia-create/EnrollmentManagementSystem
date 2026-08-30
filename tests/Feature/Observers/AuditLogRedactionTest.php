<?php

namespace Tests\Feature\Observers;

use App\Models\Auditlogs;
use App\Models\Staffusers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Audit follow-up §A1: the global AuditLogObserver snapshots every model's
 * raw attributes. Sensitive keys (password hashes, tokens, secrets) must be
 * redacted at WRITE time so they never reach the audit-log storage — the
 * viewer permission model (audit.view) is a second, independent layer.
 */
class AuditLogRedactionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function created_staff_user_log_redacts_password_hash_and_tokens(): void
    {
        $user = Staffusers::factory()->create();

        $log = Auditlogs::query()
            ->where('entityTable', 'staffusers')
            ->where('entityId', $user->userId)
            ->orderByDesc('auditId')
            ->first();

        $this->assertNotNull($log, 'Audit log entry should exist for staffusers create');

        // Values are stored as JSON strings.
        $newValuesJson = (string) $log->newValues;
        $newValues = json_decode($newValuesJson, true);
        $this->assertIsArray($newValues);

        // The hash must be redacted at write time — the stored hash value
        // may not appear anywhere in the snapshot.
        $this->assertSame('[REDACTED]', $newValues['passwordHash']);
        $this->assertStringNotContainsString($user->passwordHash, $newValuesJson);

        if (array_key_exists('remember_token', $newValues)) {
            $this->assertSame('[REDACTED]', $newValues['remember_token']);
        }
    }

    #[Test]
    public function updated_staff_user_log_redacts_old_and_new_password_hashes(): void
    {
        $user = Staffusers::factory()->create();
        $originalHash = $user->passwordHash;

        $user->update(['passwordHash' => Hash::make('New-Secret-Pass-1!')]);
        $newHash = $user->fresh()->passwordHash;

        $log = Auditlogs::query()
            ->where('entityTable', 'staffusers')
            ->where('entityId', $user->userId)
            ->where('action', 'updated')
            ->orderByDesc('auditId')
            ->first();

        $this->assertNotNull($log, 'Audit log entry should exist for staffusers update');

        $oldValuesJson = (string) $log->oldValues;
        $newValuesJson = (string) $log->newValues;
        $oldValues = json_decode($oldValuesJson, true);
        $newValues = json_decode($newValuesJson, true);
        $this->assertIsArray($oldValues);
        $this->assertIsArray($newValues);

        $this->assertSame('[REDACTED]', $oldValues['passwordHash']);
        $this->assertSame('[REDACTED]', $newValues['passwordHash']);
        $this->assertStringNotContainsString($originalHash, $oldValuesJson);
        $this->assertStringNotContainsString($newHash, $newValuesJson);
    }

    #[Test]
    public function non_sensitive_attributes_are_still_logged_for_diffing(): void
    {
        $user = Staffusers::factory()->create(['status' => 'active']);

        $user->update(['status' => 'inactive']);

        $log = Auditlogs::query()
            ->where('entityTable', 'staffusers')
            ->where('entityId', $user->userId)
            ->where('action', 'updated')
            ->orderByDesc('auditId')
            ->first();

        $this->assertNotNull($log);

        $oldValues = json_decode((string) $log->oldValues, true);
        $newValues = json_decode((string) $log->newValues, true);
        $this->assertIsArray($oldValues);
        $this->assertIsArray($newValues);

        $this->assertSame('active', $oldValues['status']);
        $this->assertSame('inactive', $newValues['status']);
    }
}
