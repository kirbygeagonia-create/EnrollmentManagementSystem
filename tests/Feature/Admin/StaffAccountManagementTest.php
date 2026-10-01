<?php

namespace Tests\Feature\Admin;

use App\Models\Academicunits;
use App\Models\Offices;
use App\Models\Permissions;
use App\Models\Roles;
use App\Models\Settings;
use App\Models\Staffusers;
use App\Providers\SettingsServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Admin → User Management decides what every other desk can do, and none of its
 * mutating routes were tested — which is how the role permission matrix could ship
 * writing its ticks into model_has_permissions (the table for permissions granted
 * directly to a model row) instead of role_permissions (the pivot spatie resolves a
 * staff account through). The screen answered every save with a badge that matched
 * the ticks, because the badge read the same wrong relation: the matrix looked
 * healthy while granting and revoking nothing in either direction.
 *
 * These tests pin what an admin can lose from here: who may sit in a college versus
 * an office, what a password has to survive before it is hashed, which permissions a
 * role actually carries, and the two deletions the pivot cascades would otherwise
 * perform in silence.
 */
class StaffAccountManagementTest extends TestCase
{
    use DatabaseTransactions;

    private const STRONG_PASSWORD = 'Passw0rd!2026';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RbacSeeder', '--database' => 'sqlite']);

        Offices::firstOrCreate(['officeId' => 1], ['officeName' => 'Office of the Registrar']);
        Academicunits::firstOrCreate(['unitId' => 1], ['unitName' => 'College of Engineering', 'unitType' => 'college']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function sysAdmin(): Staffusers
    {
        return $this->staffWithRole('SysAdmin');
    }

    private function staffWithRole(string $roleName): Staffusers
    {
        $staff = $this->plainStaff();
        $staff->assignRole($roleName);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    /**
     * A staff row carrying no spatie role at all, so a test can tell "this screen
     * granted it" apart from "the seeder had already granted it".
     */
    private function plainStaff(): Staffusers
    {
        $staff = Staffusers::factory()->make([
            'officeId' => 1,
            'role' => 'staff',
            'employeeNo' => 'EMP-'.uniqid(),
            'username' => 'user'.uniqid(),
            'email' => 'user'.uniqid().'@example.com',
        ]);
        unset($staff->remember_token);
        $staff->save();

        return $staff;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function staffPayload(array $overrides = []): array
    {
        $tag = uniqid();

        return array_merge([
            'employeeNo' => 'EMP-'.$tag,
            'firstName' => 'Rina',
            'lastName' => 'Geona',
            'username' => 'geona'.$tag,
            'email' => "geona{$tag}@example.com",
            'password' => self::STRONG_PASSWORD,
            'password_confirmation' => self::STRONG_PASSWORD,
            'role' => 'staff',
            'status' => 'active',
            'officeId' => 1,
        ], $overrides);
    }

    /**
     * @return list<int>
     */
    private function permissionIds(string ...$names): array
    {
        return Permissions::query()->whereIn('name', $names)->pluck('id')->all();
    }

    // ==================== THE STAFF FORM ====================

    #[Test]
    public function a_dean_or_program_head_is_seated_in_a_college_and_never_an_office(): void
    {
        $this->actingAs($this->sysAdmin());

        foreach (['dean', 'programHead'] as $deskRole) {
            $this->post(route('admin.users.store'), $this->staffPayload([
                'username' => 'head_'.$deskRole,
                'email' => "{$deskRole}@example.com",
                'employeeNo' => 'EMP-'.strtoupper($deskRole),
                'role' => $deskRole,
                'officeId' => 1,
                'unitId' => 1,
            ]))->assertRedirect()->assertSessionHasNoErrors();

            $seated = Staffusers::query()->where('role', $deskRole)->firstOrFail();

            $this->assertSame(1, (int) $seated->unitId);
            // The controller clears the office for college roles: a dean answers to a
            // college, and an officeId would seat them in two chains at once.
            $this->assertNull($seated->officeId);
        }
    }

    #[Test]
    public function a_role_that_needs_a_college_or_an_office_is_refused_without_one(): void
    {
        $this->actingAs($this->sysAdmin());

        $missing = [
            'dean' => 'unitId',
            'programHead' => 'unitId',
            'instructor' => 'unitId',
            'officeHead' => 'officeId',
        ];

        foreach ($missing as $deskRole => $field) {
            $this->post(route('admin.users.store'), $this->staffPayload([
                'username' => 'orphan_'.$deskRole,
                'email' => "{$deskRole}@example.com",
                'employeeNo' => 'EMP-'.strtoupper($deskRole),
                'role' => $deskRole,
                'officeId' => $field === 'officeId' ? null : 1,
                'unitId' => $field === 'unitId' ? null : 1,
            ]))->assertSessionHasErrors($field);
        }

        $this->assertSame(
            0,
            Staffusers::query()->whereIn('username', ['orphan_dean', 'orphan_programHead', 'orphan_instructor', 'orphan_officeHead'])->count()
        );
    }

    #[Test]
    public function a_password_has_to_survive_the_policy_before_it_is_hashed(): void
    {
        $this->actingAs($this->sysAdmin());

        // Each of these clears the old min:8 rule and breaks on exactly one clause of
        // the policy, which is the point: a length rule alone let 'password123' in.
        $weak = ['Sh0rt!A', 'alllowercase1!', 'ALLUPPERCASE1!', 'Password123', 'Passw0rdnodigit'];

        foreach ($weak as $candidate) {
            $slug = substr(md5($candidate), 0, 8);

            $this->post(route('admin.users.store'), $this->staffPayload([
                'username' => 'weak_'.$slug,
                'email' => "{$slug}@example.com",
                'employeeNo' => 'EMP-'.$slug,
                'password' => $candidate,
                'password_confirmation' => $candidate,
            ]))->assertSessionHasErrors('password');
        }

        $payload = $this->staffPayload();

        $this->post(route('admin.users.store'), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();

        $created = Staffusers::query()->where('username', $payload['username'])->firstOrFail();
        $this->assertNotSame(self::STRONG_PASSWORD, $created->passwordHash);
        $this->assertTrue(Hash::check(self::STRONG_PASSWORD, $created->passwordHash));
    }

    #[Test]
    public function an_account_can_be_moved_from_an_office_to_a_college_and_a_blank_name_field_cleared(): void
    {
        $sysAdmin = $this->sysAdmin();
        $clerk = $this->plainStaff();

        $this->assertNotNull($clerk->middleName, 'The factory seats a middle name to clear.');

        $this->actingAs($sysAdmin)->patch(route('admin.users.update', $clerk), [
            'employeeNo' => $clerk->employeeNo,
            'firstName' => $clerk->firstName,
            'middleName' => '',
            'lastName' => $clerk->lastName,
            'username' => $clerk->username,
            'email' => $clerk->email,
            'role' => 'dean',
            'status' => 'active',
            'unitId' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $clerk->refresh();

        $this->assertSame('dean', $clerk->role->value);
        $this->assertSame(1, (int) $clerk->unitId);
        $this->assertNull($clerk->officeId);
        // An empty box arrives as null through ConvertEmptyStringsToNull, so the screen
        // can undo a name it got wrong — not only set one.
        $this->assertNull($clerk->middleName);
    }

    #[Test]
    public function the_role_matrix_on_the_staff_form_grants_and_empties_in_the_same_click(): void
    {
        $this->actingAs($this->sysAdmin());

        $registrarDesk = Roles::query()->where('name', 'RegistrarDesk')->firstOrFail();

        $this->post(route('admin.users.store'), $this->staffPayload([
            'username' => 'matrix_user',
            'email' => 'matrix@example.com',
            'employeeNo' => 'EMP-MATRIX',
            'roleIds' => [$registrarDesk->id],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $account = Staffusers::query()->where('username', 'matrix_user')->firstOrFail();
        $this->assertSame([$registrarDesk->id], $account->roles()->pluck('id')->all());

        // Un-ticking every box posts an empty array. Honouring it is the only way the
        // matrix can take access back; treating it as "nothing sent" left the grant
        // permanent no matter what the screen showed.
        $this->patch(route('admin.users.update', $account), [
            'employeeNo' => $account->employeeNo,
            'firstName' => $account->firstName,
            'lastName' => $account->lastName,
            'username' => $account->username,
            'email' => $account->email,
            'role' => 'staff',
            'status' => 'active',
            'officeId' => 1,
            'roleIds' => [],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame([], $account->roles()->pluck('id')->all());
    }

    #[Test]
    public function a_role_id_that_no_longer_exists_is_refused_instead_of_crashing_the_save(): void
    {
        $this->actingAs($this->sysAdmin());

        $this->post(route('admin.users.store'), $this->staffPayload([
            'username' => 'stale_role',
            'email' => 'stale@example.com',
            'employeeNo' => 'EMP-STALE',
            'roleIds' => [999999],
        ]))->assertRedirect()->assertSessionHasErrors('roleIds.0');

        $this->assertSame(0, Staffusers::query()->where('username', 'stale_role')->count());
    }

    // ==================== THE ROLE MATRIX ====================

    #[Test]
    public function ticking_permissions_on_the_roles_screen_grants_what_it_shows(): void
    {
        $this->actingAs($this->sysAdmin());

        $granted = $this->permissionIds('clinic.reopen', 'block.capacity.check');
        $this->assertCount(2, $granted);

        $this->post(route('admin.users.roles.store'), [
            'name' => 'AuditClerk',
            'description' => 'Seated through the screen.',
            'permissionIds' => $granted,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $role = Roles::query()->where('name', 'AuditClerk')->firstOrFail();

        $this->assertSame(2, DB::table('role_permissions')->where('roleId', $role->id)->count());
        $this->assertSame(0, DB::table('model_has_permissions')
            ->where('model_type', Roles::class)
            ->where('model_id', $role->id)
            ->count());

        $this->assertEqualsCanonicalizing(
            ['block.capacity.check', 'clinic.reopen'],
            $role->permissions()->pluck('name')->all()
        );

        $account = $this->plainStaff();
        $account->syncRoles([$role->id]);

        $this->assertTrue($account->fresh()->hasPermissionTo('clinic.reopen'));
        $this->assertTrue($account->fresh()->hasPermissionTo('block.capacity.check'));
    }

    #[Test]
    public function un_ticking_a_permission_on_the_roles_screen_takes_it_back(): void
    {
        $this->actingAs($this->sysAdmin());

        $this->post(route('admin.users.roles.store'), [
            'name' => 'AuditClerk',
            'permissionIds' => $this->permissionIds('clinic.reopen', 'block.capacity.check'),
        ])->assertSessionHasNoErrors();

        $role = Roles::query()->where('name', 'AuditClerk')->firstOrFail();
        $account = $this->plainStaff();
        $account->syncRoles([$role->id]);

        $this->assertTrue($account->fresh()->hasPermissionTo('block.capacity.check'));

        $kept = $this->permissionIds('clinic.reopen');

        $this->patch(route('admin.users.roles.update', $role), [
            'name' => 'AuditClerk',
            'permissionIds' => $kept,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($kept, DB::table('role_permissions')->where('roleId', $role->id)->pluck('permissionId')->all());
        $this->assertTrue($account->fresh()->hasPermissionTo('clinic.reopen'));
        $this->assertFalse($account->fresh()->hasPermissionTo('block.capacity.check'));
    }

    #[Test]
    public function the_roles_screen_lists_the_permissions_each_role_actually_grants(): void
    {
        $sysAdmin = $this->sysAdmin();

        $this->actingAs($sysAdmin)->post(route('admin.users.roles.store'), [
            'name' => 'AuditClerk',
            'permissionIds' => $this->permissionIds('clinic.reopen', 'block.capacity.check'),
        ])->assertSessionHasNoErrors();

        $page = $this->actingAs($sysAdmin)->get(route('admin.users.roles'))->assertOk()->viewData('page');

        $rows = collect($page['props']['roles']['data']);

        // Every row has to read back the pivot it was saved to, or an admin opening the
        // editor on a seeded desk would see an empty matrix and save it empty.
        foreach ($rows as $row) {
            $this->assertSame(
                DB::table('role_permissions')->where('roleId', $row['id'])->count(),
                count($row['permissions']),
                "Role {$row['name']} shows a different count than it grants."
            );
        }

        $seeded = $rows->reject(fn (array $row) => $row['name'] === 'AuditClerk')->first();
        $this->assertNotEmpty($seeded['permissions'], 'A seeded desk role must read its grants back.');

        $this->assertCount(2, $rows->firstWhere('name', 'AuditClerk')['permissions']);
    }

    #[Test]
    public function a_role_an_account_still_holds_cannot_be_deleted(): void
    {
        $sysAdmin = $this->sysAdmin();
        $held = Roles::query()->where('name', 'SysAdmin')->firstOrFail();

        $this->actingAs($sysAdmin)->delete(route('admin.users.roles.destroy', $held))
            ->assertRedirect()->assertSessionHas('error');

        $this->assertNotNull(Roles::find($held->id), 'staff_roles cascades, so the delete would unseat every admin at once.');
        $this->assertTrue($sysAdmin->fresh()->hasRole('SysAdmin'));

        // With nothing holding it, the same click is allowed.
        $loose = Roles::create(['name' => 'DraftRole', 'guard_name' => 'web']);

        $this->actingAs($sysAdmin)->delete(route('admin.users.roles.destroy', $loose))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull(Roles::find($loose->id));
    }

    // ==================== THE PERMISSION LIST ====================

    #[Test]
    public function a_permission_nobody_grants_can_be_added_renamed_and_dropped(): void
    {
        $this->actingAs($this->sysAdmin());

        $this->post(route('admin.users.permissions.store'), [
            'name' => 'audit.custom',
            'module' => 'audit',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $permission = Permissions::query()->where('name', 'audit.custom')->firstOrFail();
        $this->assertSame('web', $permission->guard_name);
        $this->assertSame('audit', $permission->module);

        $this->patch(route('admin.users.permissions.update', $permission), [
            'name' => 'audit.custom.renamed',
            'module' => 'audit',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNotNull(Permissions::query()->where('name', 'audit.custom.renamed')->first());

        $this->delete(route('admin.users.permissions.destroy', $permission))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull(Permissions::find($permission->id));
    }

    #[Test]
    public function a_permission_a_role_still_grants_cannot_be_deleted_or_renamed(): void
    {
        $this->actingAs($this->sysAdmin());

        $permission = Permissions::query()->where('name', 'clinic.view')->firstOrFail();
        $this->assertGreaterThan(0, $permission->role_permissions()->count(), 'The seeder grants clinic.view to desks.');

        $this->delete(route('admin.users.permissions.destroy', $permission))
            ->assertRedirect()->assertSessionHas('error');
        $this->assertNotNull(Permissions::find($permission->id));

        // Policies ask for a permission by name, so renaming one that is granted turns
        // every desk that checks it into a PermissionDoesNotExist.
        $this->patch(route('admin.users.permissions.update', $permission), [
            'name' => 'clinic.view.old',
            'module' => 'clinic',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame('clinic.view', Permissions::find($permission->id)->name);

        // Editing the display module of a granted permission is still allowed.
        $this->patch(route('admin.users.permissions.update', $permission), [
            'name' => 'clinic.view',
            'module' => 'Clinic',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Clinic', Permissions::find($permission->id)->module);
    }

    // ==================== STATUS, SELF AND SETTINGS ====================

    #[Test]
    public function toggling_another_account_flips_its_status_both_ways(): void
    {
        $sysAdmin = $this->sysAdmin();
        $clerk = $this->plainStaff();

        $this->actingAs($sysAdmin)->post(route('admin.users.status.toggle', $clerk))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('inactive', $clerk->fresh()->status->value);

        $this->actingAs($sysAdmin)->post(route('admin.users.status.toggle', $clerk))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('active', $clerk->fresh()->status->value);
    }

    #[Test]
    public function the_three_self_actions_answer_with_a_message_rather_than_a_403(): void
    {
        $sysAdmin = $this->sysAdmin();

        // Each policy refuses acting on your own row and the screen still offers the
        // button, so the refusal has to land as a message on the desk rather than as a
        // status page with no way back.
        $this->actingAs($sysAdmin)->delete(route('admin.users.destroy', $sysAdmin))
            ->assertRedirect()->assertSessionHasErrors('user');
        $this->assertNotNull(Staffusers::find($sysAdmin->userId));

        $this->actingAs($sysAdmin)->post(route('admin.users.status.toggle', $sysAdmin))
            ->assertRedirect()->assertSessionHasErrors('user');
        $this->assertSame('active', $sysAdmin->fresh()->status->value);

        $this->actingAs($sysAdmin)->post(route('admin.users.roles.assign', $sysAdmin), ['roleIds' => []])
            ->assertRedirect()->assertSessionHasErrors('user');
        $this->assertTrue($sysAdmin->fresh()->hasRole('SysAdmin'));
    }

    #[Test]
    public function a_setting_value_edited_on_screen_is_what_the_next_request_reads(): void
    {
        $setting = Settings::forceCreate(['settingKey' => 'schoolName', 'settingValue' => 'Old Name']);

        $this->actingAs($this->sysAdmin())->patch(route('admin.users.settings.update', $setting), [
            'settingValue' => 'St. Theresa Institute of Technology',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('St. Theresa Institute of Technology', Settings::find('schoolName')->settingValue);

        // The bridge that makes the edit reach a print template: config('settings.*')
        // is hydrated from this table when the app boots.
        config(['settings.schoolName' => 'stale']);
        (new SettingsServiceProvider($this->app))->boot();

        $this->assertSame('St. Theresa Institute of Technology', config('settings.schoolName'));

        $this->actingAs($this->sysAdmin())->patch(route('admin.users.settings.update', $setting), [
            'settingValue' => '',
        ])->assertSessionHasErrors('settingValue');
    }

    #[Test]
    public function a_desk_without_user_authority_cannot_reach_any_of_these_routes(): void
    {
        $viewer = $this->staffWithRole('Staff');

        $this->actingAs($viewer)->post(route('admin.users.store'), $this->staffPayload())->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.users.roles.store'), ['name' => 'Sideloaded'])->assertForbidden();
        $this->actingAs($viewer)->post(
            route('admin.users.permissions.store'),
            ['name' => 'sideload.any', 'module' => 'x']
        )->assertForbidden();

        $role = Roles::query()->where('name', 'RegistrarDesk')->firstOrFail();
        $this->actingAs($viewer)->delete(route('admin.users.roles.destroy', $role))->assertForbidden();
        $this->assertNotNull(Roles::find($role->id));

        $setting = Settings::forceCreate(['settingKey' => 'audit.key', 'settingValue' => 'before']);
        $this->actingAs($viewer)->patch(route('admin.users.settings.update', $setting), [
            'settingValue' => 'after',
        ])->assertForbidden();
        $this->assertSame('before', Settings::find('audit.key')->settingValue);
    }
}
