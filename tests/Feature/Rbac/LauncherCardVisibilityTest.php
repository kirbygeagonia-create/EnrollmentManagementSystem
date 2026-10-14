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
 * Audit lane 4, found 2026-10-09. The launcher's Campus Clearance card was gated on
 * `officeId: 8` — the legacy Clearance office that ruling 12 folded into Registrar. No
 * offices row carries 8 and no staff account sits in 8 (measured on live `ems`: 9 offices,
 * 0 accounts in office 8), so isAuthorized() refused the card for every account except the
 * one with role=admin, and the desk that twelve accounts may open had no door in the chrome.
 *
 * The card is now gated on can.clearanceDesk, which the middleware reads through the same
 * right ClearancePolicy::viewAny asks, so the launcher and the desk answer to one rule.
 */
class LauncherCardVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function staffInOffice(int $officeId, string $spatieRole): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => $officeId,
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-LAUNCH-'.uniqid(),
            'username' => 'launch_'.uniqid(),
            'email' => 'launch_'.uniqid().'@example.com',
        ]);
        $staff->assignRole($spatieRole);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    #[Test]
    public function the_shared_clearance_flag_answers_the_same_right_the_desk_gate_asks(): void
    {
        $registrar = $this->staffInOffice(OfficeId::Registrar->value, 'RegistrarDesk');
        $idOffice = $this->staffInOffice(OfficeId::IdOffice->value, 'IdOfficer');

        $this->assertTrue($registrar->hasPermissionTo('clearance.view'));
        $this->assertFalse($idOffice->hasPermissionTo('clearance.view'));

        $this->actingAs($registrar)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('can.clearanceDesk', true));

        $this->actingAs($idOffice)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('can.clearanceDesk', false));
    }

    #[Test]
    public function a_flagged_card_and_its_door_refuse_the_same_account(): void
    {
        $idOffice = $this->staffInOffice(OfficeId::IdOffice->value, 'IdOfficer');

        // The flag said "no card" and the policy says "no page" — the two must not disagree,
        // which is what made the office-8 gate invisible: the door was open for this account
        // class in some offices and the chrome never mentioned it.
        $this->actingAs($idOffice)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('can.clearanceDesk', false));

        $this->actingAs($idOffice)->get(route('clearance.index'))->assertForbidden();

        $registrar = $this->staffInOffice(OfficeId::Registrar->value, 'RegistrarDesk');
        $this->actingAs($registrar)->get(route('clearance.index'))->assertOk();
    }

    #[Test]
    public function the_two_academic_cards_answer_to_the_rights_behind_them(): void
    {
        // A Dean works in an office that owns no launcher card of its own, so the chrome used to grant that
        // role evaluation.index and blocking.index by route name. One of the two was a door that closes: the
        // Dean's roles hold evaluation.view but not block.view, which is what live `ems` measured of its only
        // dean account (dean_academic) on 2026-10-12 — a card that advertised a screen the policy refuses.
        $dean = $this->staffInOffice(OfficeId::Academic->value, 'Dean');

        $this->assertTrue($dean->hasPermissionTo('evaluation.view'));
        $this->assertFalse($dean->hasPermissionTo('block.view'));

        $this->actingAs($dean)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('can.evaluationDesk', true)
                ->where('can.blockingDesk', false));

        $this->actingAs($dean)->get(route('evaluation.index'))->assertOk();
        $this->actingAs($dean)->get(route('blocking.index'))->assertForbidden();

        // The desk that does hold the right keeps both the flag and the page, so the fix did not simply
        // switch a card off for somebody who could use it.
        $coordinator = $this->staffInOffice(OfficeId::Blocking->value, 'BlockingCoordinator');
        $this->assertTrue($coordinator->hasPermissionTo('block.view'));

        $this->actingAs($coordinator)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('can.blockingDesk', true));

        $this->actingAs($coordinator)->get(route('blocking.index'))->assertOk();
    }

    #[Test]
    public function the_launcher_asks_those_flags_instead_of_naming_the_two_routes(): void
    {
        // A PHP test cannot execute the component, so the pin here is on its source — the same reason this
        // file scans the chrome for office numbers nobody holds. The two cards must read the shared rights,
        // and the allow-list that granted them by route name must stay gone, or the dead end comes back with
        // the flags still correct and nothing left to fail.
        $src = file_get_contents(resource_path('js/Components/navigation/MegaAppLauncher.jsx'));

        $this->assertStringContainsString('props.can?.evaluationDesk', $src);
        $this->assertStringContainsString('props.can?.blockingDesk', $src);
        $this->assertStringContainsString("if (item.route === 'evaluation.index') return canEvaluationDesk;", $src);
        $this->assertStringContainsString("if (item.route === 'blocking.index') return canBlockingDesk;", $src);
        $this->assertStringNotContainsString("['evaluation.index', 'blocking.index', 'students.index'].includes(item.route)", $src);
    }

    #[Test]
    public function the_department_account_holds_the_right_and_the_desk_page_opens(): void
    {
        // Ruled 2026-10-13 on #89: the owning college's department performs Department evaluation, and the
        // seeder now seats one such account per college. Measured on live `ems` before the fix, office7_head
        // held evaluation.view AND evaluation.sign while the card was keyed `officeId: 4` — so the account
        // that signs the paper could not see the door.
        //
        // What this can prove, and what it cannot: the flag and the page were never the broken part (they
        // answered correctly even under the old office gate — measured by mutating the component back). The
        // card's visibility is decided in JSX this suite cannot execute, so the ordering pin in the next
        // test is what actually holds the fix. This test is the precondition that makes that pin meaningful:
        // an office-7 account that holds the right, at an office number the old descriptor refused.
        $head = $this->staffInOffice(OfficeId::Academic->value, 'DeptEvaluator');

        $this->assertTrue($head->hasPermissionTo('evaluation.view'));
        $this->assertTrue($head->hasPermissionTo('evaluation.sign'));

        $this->actingAs($head)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('can.evaluationDesk', true));

        $this->actingAs($head)->get(route('evaluation.index'))->assertOk();
    }

    #[Test]
    public function the_evaluation_card_names_no_office_and_asks_the_right_before_any_office_list(): void
    {
        $source = file_get_contents(base_path('resources/js/Components/navigation/MegaAppLauncher.jsx'));

        // Read the evaluation descriptor as its own block rather than matching a fixed number of lines:
        // a first attempt anchored `route:` to `officeId: null,` with a single optional comment line and
        // failed, because the reason for the change is written there as a five-line comment.
        $start = strpos($source, "name: 'Academic Evaluation'");
        $this->assertNotFalse($start, 'The launcher no longer carries an Academic Evaluation card at all.');

        $next = strpos($source, "name: '", $start + 1);
        $descriptor = substr($source, $start, ($next === false ? strlen($source) : $next) - $start);

        // Match property lines, not any occurrence: a first draft used assertStringNotContainsString and
        // failed on the comment above the change, which quotes the number it removed. The claim is about
        // the descriptor's fields, so the pattern is anchored at the start of a line.
        $this->assertSame(0, preg_match('/^\s*officeId:\s*\d/m', $descriptor),
            'The card is decided by where an account sits again; it must answer to can.evaluationDesk.');
        $this->assertStringContainsString('officeId: null', $descriptor,
            'The descriptor should say plainly that no office owns this desk.');

        // The ordering is the fix. The right-check used to live *inside* the dean/programHead/instructor
        // branch, so an officeHead account never reached it and fell through to the office list; an
        // account holding both evaluation rights was refused by a number. Asserting the hoist sits above
        // `if (item.officeId)` is the closest this suite can come to executing the component.
        $hoist = strpos($source, "if (item.route === 'evaluation.index') return canEvaluationDesk;");
        $officeBlock = strpos($source, 'if (item.officeId) {');
        $roleBranch = strpos($source, "['dean', 'programHead', 'instructor'].includes(user?.role)");

        $this->assertNotFalse($hoist, 'Nothing reads can.evaluationDesk for this card any more.');
        $this->assertNotFalse($officeBlock);
        $this->assertNotFalse($roleBranch);
        $this->assertLessThan($officeBlock, $hoist,
            'The right must be asked before any office list can refuse the account that holds it.');
        $this->assertLessThan($roleBranch, $hoist,
            'The check must not sit inside one role branch — that is how office7_head was skipped.');

        $this->assertStringContainsString("if (item.route === 'evaluation.index') return canEvaluationDesk;", $source);
    }

    #[Test]
    public function no_navigation_descriptor_names_an_office_that_does_not_exist(): void
    {
        // Ruling 12 retired office 8 in the database and the launcher kept speaking it, so the bug
        // was not a wrong number — it was a number with no owner. App\Enums\OfficeId is the code's
        // own list (Registrar=1 … IdOffice=22, no 8), and it is what every policy and seeder must
        // name an office with, so it is the authority this guard checks the chrome against.
        $cases = array_map(fn (OfficeId $c) => $c->value, OfficeId::cases());

        $offenders = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('js'), \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($files as $file) {
            if (! $file->isFile() || ! preg_match('/\.(jsx|js)$/', $file->getFilename())) {
                continue;
            }
            foreach (file($file->getPathname()) as $no => $line) {
                if (preg_match('/officeId\s*:\s*(\[[^\]]*\]|[^\s,]+)/', $line, $m) !== 1) {
                    continue;
                }
                // officeId accepts a scalar or an array (Item 4), so every number on the line is a claim.
                preg_match_all('/\d+/', $m[1], $dm);
                foreach ($dm[0] as $n) {
                    if (! in_array((int) $n, $cases, true)) {
                        $offenders[] = basename($file->getPathname()).':'.($no + 1)." officeId $n";
                    }
                }
            }
        }

        $this->assertSame([], $offenders,
            'The frontend gates on office ids nobody holds: '.implode('; ', $offenders));
    }
}
