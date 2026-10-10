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
