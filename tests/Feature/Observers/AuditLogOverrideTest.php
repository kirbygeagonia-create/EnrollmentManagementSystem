<?php

namespace Tests\Feature\Observers;

use App\Models\Auditlogs;
use App\Models\Staffusers;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Item 3 admin write-boundary: the Admin role is read-everywhere (no direct
 * record mutation by permission). The audit observer flags writes from
 * Admin-role accounts that hold NO SysAdmin super-role — oversight-role
 * overrides. SysAdmin writes stay unflagged: Gate::before makes them the
 * super-role's first-class authority, and flagging every staff8 action (the
 * demo walks the whole pipeline as staff8) would dilute this marker into noise.
 */
class AuditLogOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    /**
     * Create an acting staff user with the given Spatie roles.
     *
     * @param  array<int, string>  $roles
     */
    private function staffWithRoles(array $roles): Staffusers
    {
        $user = Staffusers::factory()->create();
        $user->assignRole($roles);

        return $user;
    }

    private function overrideFlagFor(Staffusers $created): ?bool
    {
        $log = Auditlogs::query()
            ->where('entityTable', 'staffusers')
            ->where('entityId', $created->userId)
            ->orderByDesc('auditId')
            ->first();

        $this->assertNotNull($log, 'Audit log entry should exist for staffusers create');

        return $log->adminOverride;
    }

    #[Test]
    public function admin_only_writes_are_flagged_as_overrides(): void
    {
        // A pure-Admin account holds no mutation permissions, so a write that
        // succeeds anyway is exactly the oversight-role override the flag marks.
        Auth::login($this->staffWithRoles(['Admin']));
        $created = Staffusers::factory()->create();

        $this->assertTrue($this->overrideFlagFor($created));
    }

    #[Test]
    public function sysadmin_plus_admin_writes_are_not_flagged(): void
    {
        // staff8 pattern (RbacSeeder seeds admin accounts with both roles):
        // the write is the SysAdmin super-role's first-class authority.
        Auth::login($this->staffWithRoles(['SysAdmin', 'Admin']));
        $created = Staffusers::factory()->create();

        $this->assertFalse($this->overrideFlagFor($created));
    }

    #[Test]
    public function office_head_writes_are_not_flagged(): void
    {
        Auth::login($this->staffWithRoles(['OfficeHead']));
        $created = Staffusers::factory()->create();

        $this->assertFalse($this->overrideFlagFor($created));
    }

    #[Test]
    public function writes_without_a_session_are_not_flagged(): void
    {
        // System tasks (queues, seeders) run without a session.
        $created = Staffusers::factory()->create();

        $this->assertFalse($this->overrideFlagFor($created));
    }
}
