<?php

namespace Tests\Feature\Admin;

use App\Models\Permissions;
use App\Models\Roles;
use App\Models\Staffusers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Prompt §14's sorting control, on the two lists the shape census left as the last wireable screens
 * (`lane215-plan-remaining-screens.php` reads each owed page's rows expression and says whether its action
 * paginates or hands the browser a bounded set; these two paginate, which is why they carry headers at all).
 *
 * Both screens paint only stored columns, so the offer set is the header row: `permissions` holds
 * name, guard_name and module, and `roles` holds name and description. The role screen's Permissions cell is
 * counted from the eager-loaded relation in the browser, so it is refused a header — a control the server cannot
 * obey is the §28.3 filter defect reached from the sort side.
 *
 * Neither list seeds the RBAC catalogue on purpose. `RbacSeeder` writes 96 permissions and 17 roles, which would
 * make every sequence below a reading of page one of a queue nobody ordered; the admin here is given a hand-made
 * SysAdmin role, and `Gate::before` answers its abilities.
 */
class UserManagementListSortTest extends TestCase
{
    use RefreshDatabase;

    /** The harness account's own role: `Gate::before` only answers for SysAdmin, so this row is really on the list. */
    private const HARNESS_ROLE = 'SysAdmin';

    private Staffusers $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = $this->admin();
    }

    private function admin(): Staffusers
    {
        // Created once, because `roles` carries a unique key on (name, guard_name): a second call from a request
        // helper would die on its own fixture and every case would report a database error instead of a verdict.
        // Its description is written rather than left null so the one case about an absent value is about the
        // fixture's absence, not the harness's.
        Roles::create(['name' => self::HARNESS_ROLE, 'guard_name' => 'web', 'description' => 'the account this harness signs in with']);

        $staff = Staffusers::factory()->create([
            // A null office is a legitimate account and it keeps the factory's foreign-key hook from inserting an
            // `offices` row this test never asked for — the population read here is the population written here.
            'officeId' => null,
            'employeeNo' => 'EMP-UM-'.uniqid(),
            'username' => 'um_'.uniqid(),
            'email' => 'um_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('SysAdmin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $staff;
    }

    private function permission(string $name, string $module, string $guard = 'web'): Permissions
    {
        return Permissions::create(['name' => $name, 'module' => $module, 'guard_name' => $guard]);
    }

    private function role(string $name, ?string $description = null): Roles
    {
        return Roles::create(['name' => $name, 'description' => $description, 'guard_name' => 'web']);
    }

    /** @return array<string, mixed> */
    private function props(string $route, array $query = []): array
    {
        $response = $this->actingAs($this->staff)->get(route($route, $query));
        // A refusal renders as a page too, so every comparison case asks for a 200 before it reads a sequence.
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    /** @return list<string> */
    private function permissionNames(array $props): array
    {
        return array_map(static fn (array $r): string => (string) $r['name'], $props['permissions']['data']);
    }

    /** @return list<string> */
    private function roleNames(array $props): array
    {
        // The harness account's own role is a real row the directory lists, so it is named out of the compared
        // sequence rather than pretended away; every case still asserts the row count that the screen really holds.
        $names = [];
        foreach ($props['roles']['data'] as $r) {
            if ((string) $r['name'] === self::HARNESS_ROLE) {
                continue;
            }
            $names[] = (string) $r['name'];
        }

        return $names;
    }

    // ---------------------------------------------------------------- permissions

    #[Test]
    public function the_permission_list_opens_with_the_names_reading_a_to_z(): void
    {
        // Inserted alphabetically so the retired `id` descending order is the REVERSE of what the list now opens
        // with: a mutant that puts the old default back cannot satisfy this by accident.
        $this->permission('block.view', 'Blocking');
        $this->permission('enrollment.view', 'Enrollment');
        $this->permission('students.view', 'Students');

        $props = $this->props('admin.users.permissions');

        $this->assertCount(3, $props['permissions']['data'], 'the fixture is not the whole population this claim is about');
        // Asked before the column is read, so a handler that stopped reporting its order fails on this sentence
        // rather than on an "undefined array key" that names nothing about the promise.
        $this->assertArrayHasKey('sort', $props, 'the page was not told which order was applied');
        $this->assertSame('name', $props['sort']['column'], 'the echo names a column other than the one the list opens with');
        $this->assertSame('asc', $props['sort']['direction'], 'a catalogue with no direction asked for opens ascending');
        $this->assertSame(['block.view', 'enrollment.view', 'students.view'], $this->permissionNames($props),
            'the permission list did not open with the names ascending');
    }

    #[Test]
    public function each_permission_header_orders_by_its_own_stored_column(): void
    {
        $this->permission('a.read', 'Zeta');
        $this->permission('m.read', 'Alpha');
        $this->permission('z.read', 'Mu');

        $byModule = $this->props('admin.users.permissions', ['sort' => 'module', 'direction' => 'asc']);
        $byModuleDesc = $this->props('admin.users.permissions', ['sort' => 'module', 'direction' => 'desc']);
        $byNameDesc = $this->props('admin.users.permissions', ['sort' => 'name', 'direction' => 'desc']);

        $this->assertSame(['m.read', 'z.read', 'a.read'], $this->permissionNames($byModule),
            'the Module header did not order by the stored module');
        $this->assertSame(['a.read', 'z.read', 'm.read'], $this->permissionNames($byModuleDesc),
            'the second click did not reverse the module order');
        $this->assertSame(['z.read', 'm.read', 'a.read'], $this->permissionNames($byNameDesc),
            'the Permission Name header descending is not the name order');
    }

    /** @return list<string> */
    private function permissionGuards(array $props): array
    {
        return array_map(static fn (array $r): string => (string) $r['guard_name'], $props['permissions']['data']);
    }

    #[Test]
    public function the_guard_header_orders_the_column_the_screen_paints(): void
    {
        $this->permission('one.web', 'Module', 'web');
        $this->permission('two.api', 'Module', 'api');
        $this->permission('three.other', 'Module', 'admin');

        $props = $this->props('admin.users.permissions', ['sort' => 'guard_name', 'direction' => 'asc']);
        $reversed = $this->props('admin.users.permissions', ['sort' => 'guard_name', 'direction' => 'desc']);

        // The expectation is derived from what the server returned rather than typed from arithmetic done here — my
        // first draft of this case spelled the alphabetical order wrong and the run said so. What the case must prove
        // is directional: the guard values come back in one order ascending and the same order inverted descending,
        // and neither is the name order the fallback would answer.
        $ascending = $this->permissionGuards($props);
        $descending = $this->permissionGuards($reversed);
        $sortedAscending = $ascending;
        sort($sortedAscending);

        $this->assertSame($sortedAscending, $ascending, 'the Guard header did not return its own column in order');
        $this->assertSame(array_reverse($sortedAscending), $descending,
            'the second click did not reverse the guard order');
        $this->assertNotSame($this->permissionNames($props), ['one.web', 'two.api', 'three.other'],
            'the guard header answered the name order, which is the fallback wearing the header’s clothes');
        $this->assertSame('guard_name', $props['sort']['column']);
        $population = $ascending;
        sort($population);
        $this->assertSame(['admin', 'api', 'web'], $population,
            'the rows the guard order returned are not the three guards this fixture wrote');
    }

    #[Test]
    public function the_permission_offer_set_is_exactly_the_three_columns_the_desk_paints(): void
    {
        $this->permission('block.view', 'Blocking');

        $available = $this->props('admin.users.permissions')['sort']['available'];
        $sorted = $available;
        sort($sorted);

        // The named absents lead, so a plant that offers one of them fails on its own claim rather than on the set
        // arithmetic beside it.
        $this->assertNotContains('id', $available, 'the record key became an order on a screen that paints no id');
        $this->assertNotContains('created_at', $available,
            'an order was offered for a column this table does not carry at all');
        $this->assertSame(['guard_name', 'module', 'name'], $sorted,
            'an order was offered that the query cannot answer, or a painted column lost its header');
    }

    // ---------------------------------------------------------------- roles

    #[Test]
    public function the_role_list_opens_with_the_names_reading_a_to_z(): void
    {
        $this->role('Admission Officer');
        $this->role('Dean');
        $this->role('Cashier');

        $props = $this->props('admin.users.roles');

        $this->assertCount(4, $props['roles']['data'],
            'the list holds the three fixture rows plus the harness account’s own role, and no more');
        $this->assertArrayHasKey('sort', $props, 'the role page was not told which order was applied');
        $this->assertSame('name', $props['sort']['column']);
        $this->assertSame('asc', $props['sort']['direction']);
        $this->assertSame(['Admission Officer', 'Cashier', 'Dean'], $this->roleNames($props),
            'the role list did not open with the names ascending');
    }

    #[Test]
    public function the_role_description_header_orders_by_the_stored_text(): void
    {
        $this->role('alpha', 'third note');
        $this->role('bravo', 'first note');
        $this->role('charlie', 'second note');

        $asc = $this->props('admin.users.roles', ['sort' => 'description', 'direction' => 'asc']);
        $desc = $this->props('admin.users.roles', ['sort' => 'description', 'direction' => 'desc']);

        $this->assertSame(['bravo', 'charlie', 'alpha'], $this->roleNames($asc),
            'the Description header did not order by the stored text');
        $this->assertSame(['alpha', 'charlie', 'bravo'], $this->roleNames($desc),
            'the second click did not reverse the description order');
    }

    #[Test]
    public function a_role_with_no_description_stays_on_the_list_and_reads_first(): void
    {
        $this->role('Seated', 'has words');
        $this->role('Blank', null);

        $props = $this->props('admin.users.roles', ['sort' => 'description', 'direction' => 'asc']);

        $this->assertCount(3, $props['roles']['data'], 'the unworded row was dropped by the order that reads it');
        $this->assertSame('Blank', $this->roleNames($props)[0],
            'an absent value is not a row the directory may lose, and it sorts before every word');
    }

    #[Test]
    public function the_derived_permission_count_is_not_offered_as_an_order(): void
    {
        $this->role('Registrar', null);

        $available = $this->props('admin.users.roles')['sort']['available'];
        $sorted = $available;
        sort($sorted);

        $this->assertNotContains('permissionsCount', $available,
            'a header was offered for a count the screen computes from the loaded relation, which the query never holds');
        $this->assertNotContains('guard_name', $available,
            'the role screen paints no Guard column, so its offer set must not grow one');
        $this->assertSame(['description', 'name'], $sorted,
            'the role offer set moved away from the two stored columns the desk actually reads');
    }

    // ---------------------------------------------------------------- the pairing and the fallback

    #[Test]
    public function every_header_a_page_paints_is_a_key_its_action_obeys(): void
    {
        $this->permission('block.view', 'Blocking');
        $this->role('Registrar', null);

        $pages = [
            'resources/js/Pages/Admin/UserManagement/Permissions.jsx' => 'admin.users.permissions',
            'resources/js/Pages/Admin/UserManagement/Roles.jsx' => 'admin.users.roles',
        ];

        foreach ($pages as $jsxPath => $route) {
            $jsx = (string) file_get_contents(base_path($jsxPath));
            $found = preg_match_all("/sortKey: '([a-z_]+)'/", $jsx, $m) >= 1 ? $m[1] : [];
            $available = $this->props($route)['sort']['available'];

            $this->assertNotSame([], $found, "{$jsxPath} lists no sortable header at all, so this case would prove nothing");
            foreach ($found as $key) {
                $this->assertContains($key, $available,
                    "{$jsxPath} offers a header for [{$key}] and the action would not obey it");
            }
        }

        $permissionJsx = (string) file_get_contents(base_path('resources/js/Pages/Admin/UserManagement/Permissions.jsx'));
        $roleJsx = (string) file_get_contents(base_path('resources/js/Pages/Admin/UserManagement/Roles.jsx'));
        $this->assertSame(3, preg_match_all("/sortKey: '([a-z_]+)'/", $permissionJsx, $pm),
            'a painted permission column lost its header, so the desk cannot order what it can see');
        $this->assertSame(2, preg_match_all("/sortKey: '([a-z_]+)'/", $roleJsx, $rm),
            'a painted role column lost its header, so the desk cannot order what it can see');
        $this->assertStringNotContainsString("sortKey: 'permissionsCount'", $roleJsx,
            'the derived count was given a header the server cannot obey');
    }

    #[Test]
    public function a_column_the_desk_never_lists_falls_back_to_the_name_order(): void
    {
        $this->permission('block.view', 'Blocking');
        $this->permission('enrollment.view', 'Enrollment');

        $default = $this->props('admin.users.permissions');
        $forged = $this->props('admin.users.permissions', ['sort' => 'passwordHash']);

        $this->assertSame('name', $forged['sort']['column'],
            'an unlisted key did not fall back to the column the list is ordered by anyway');
        $this->assertSame($this->permissionNames($default), $this->permissionNames($forged),
            'the fallback answered a different sequence than the list opens with');
    }

    #[Test]
    public function the_direction_is_reduced_to_ascending_or_descending_and_never_passed_through(): void
    {
        $this->permission('block.view', 'Blocking');
        $this->permission('enrollment.view', 'Enrollment');

        $props = $this->props('admin.users.permissions', ['sort' => 'name', 'direction' => '; drop tables']);

        $this->assertSame('desc', $props['sort']['direction'], 'an unusable direction was answered as a direction');
        $this->assertSame(['enrollment.view', 'block.view'], $this->permissionNames($props),
            'the list still came back, ordered by the real column');
    }

    #[Test]
    public function both_actions_read_their_own_request_and_no_hard_coded_order_survives(): void
    {
        $src = (string) file_get_contents(base_path('app/Http/Controllers/Admin/UserManagementController.php'));

        foreach (['roles', 'permissions'] as $action) {
            $start = (int) strpos($src, "public function {$action}(Request \$request): Response");
            $this->assertGreaterThan(0, $start, "the {$action} action was renamed or moved");
            $end = (int) strpos(substr($src, $start + 10), "\n    public function ");
            $body = substr($src, $start, $end > 0 ? $end + 10 : 4000);

            $this->assertStringContainsString('ListSort::apply(', $body, "{$action}() does not ask the one mechanism");
            $this->assertStringContainsString("\$request->input('sort', '')", $body,
                "{$action}() reads the order from somewhere other than its own request");
            $this->assertStringContainsString("\$request->input('direction', 'asc')", $body,
                'a catalogue that defaults to descending opens at the end of the alphabet, which is a queue answer');
            $this->assertStringContainsString('ListSort::present(', $body,
                "{$action}() obeyed an order it never reported, so the header would reset while the list stayed narrowed");
            $this->assertStringNotContainsString("->orderByDesc('id')", $body,
                "the hard-coded newest-first order the mechanism replaced is back in {$action}()");
        }
    }
}
