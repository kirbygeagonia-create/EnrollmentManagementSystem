<?php

namespace Tests\Feature\Rbac;

use App\Enums\OfficeId;
use App\Enums\StaffRole;
use App\Models\Staffusers;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * An account's roles must not depend on which seeder ran first.
 *
 * Found on 2026-10-13 while seating the per-college Academic Department desks ruled under #89. Measured on
 * a fresh MySQL database driven in the order DEPLOYMENT.md §3 and `.github/workflows/ci.yml` both use —
 * `db:seed`, then `db:seed --class=DevReferenceDataSeeder` — **10 of the 17 seeded accounts came home with
 * no Spatie roles at all**: every `officeN_head` and `staff8`. The reason is sequencing, not the mapping:
 * RbacSeeder step 3 re-derives roles from each account's `role` enum, and it runs before the accounts that
 * `DevReferenceDataSeeder` creates exist to be looked at. The nine office heads were therefore unable to
 * open the desks they are named for — the measured cause behind the UAT rows §2.8 (`head11_only`,
 * `head22_only`) that no office could perform. Live `ems` hides it because its pivot rows predate the order.
 *
 * The fix is `RbacSeeder::assignDeskRolesFromEnums()`, called from step 3 and again at the end of
 * `DevReferenceDataSeeder`, so the mapping is one authority and the seed order stops mattering.
 */
class SeededAccountRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function staffWithEnum(StaffRole $enum, OfficeId $office): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'role' => $enum,
            'officeId' => $office->value,
            'employeeNo' => 'EMP-ORDER-'.uniqid(),
            'username' => 'order_'.uniqid(),
            'email' => 'order_'.uniqid().'@example.com',
        ]);

        // Deliberately no assignRole(): this is the state an account is in the moment a seeder
        // creates it after RbacSeeder has already run its pass.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    #[Test]
    public function an_account_seated_after_the_role_pass_still_gets_the_roles_its_enum_stands_for(): void
    {
        $head = $this->staffWithEnum(StaffRole::OfficeHead, OfficeId::Academic);

        $this->assertSame([], $head->getRoleNames()->all(),
            'The fixture is wrong: it was expected to start with no roles, which is the state being tested.');

        RbacSeeder::assignDeskRolesFromEnums();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = $head->fresh()->getRoleNames()->all();
        sort($roles);

        // Office 7 is the owning department's desk (Item 4), so its head evaluates and signs; an
        // `officeHead` in any other office gets that office's own desk role instead.
        $this->assertSame(['DeptEvaluator', 'OfficeHead'], $roles,
            'An officeHead seated at office 7 after the pass must end with the pairing live ems shows: '
            .implode('/', $roles));
        $this->assertTrue($head->fresh()->checkPermissionTo('evaluation.sign'));
    }

    #[Test]
    public function running_the_mapping_twice_leaves_the_same_assignment(): void
    {
        $head = $this->staffWithEnum(StaffRole::OfficeHead, OfficeId::Registrar);

        RbacSeeder::assignDeskRolesFromEnums();
        $first = $head->fresh()->getRoleNames()->sort()->values()->all();
        $this->assertNotSame([], $first,
            'The first pass granted nothing at all, so comparing two passes would compare two failures.');

        // Spatie caches role lookups per process; without this, the read after the second pass can come
        // back empty in a full-suite run while passing on its own — which is exactly what it did.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        RbacSeeder::assignDeskRolesFromEnums();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $second = $head->fresh()->getRoleNames()->sort()->values()->all();

        $this->assertSame($first, $second,
            'DevReferenceDataSeeder calls this after RbacSeeder already did, so a second pass must be a no-op.');
        $this->assertContains('RegistrarApprover', $second,
            'Idempotence is not the same as emptiness: syncRoles on a second pass must still leave the desk role.');
    }

    #[Test]
    public function the_dev_seeder_re_derives_roles_after_it_creates_accounts(): void
    {
        // The fix is only durable if the second call stays put. Nothing in the suite executes
        // DevReferenceDataSeeder (it is a dev/CI-only reconstruction), so this pins its source.
        $source = file_get_contents(base_path('database/seeders/DevReferenceDataSeeder.php'));

        $this->assertStringContainsString('RbacSeeder::assignDeskRolesFromEnums();', $source,
            'The dev seeder must re-derive roles after seating its accounts, or a fresh install ships '
            .'office heads that cannot open their own desks.');

        $seatsAt = strpos($source, "role' => 'officeHead'");
        $derivesAt = strpos($source, 'RbacSeeder::assignDeskRolesFromEnums();');
        $this->assertGreaterThan($seatsAt, $derivesAt,
            'The derivation must come after the accounts are created — before them it sees nothing.');
    }
}
