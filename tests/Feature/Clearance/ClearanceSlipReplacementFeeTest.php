<?php

namespace Tests\Feature\Clearance;

use App\Enums\ClearanceApprovalStatus;
use App\Enums\ClearanceOverallStatus;
use App\Enums\ClearancePeriodStatus;
use App\Enums\FeeUnitBasis;
use App\Enums\OfficeId;
use App\Enums\StaffRole;
use App\Models\Academicterms;
use App\Models\Academicyears;
use App\Models\Clearanceapprovals;
use App\Models\Clearanceperiods;
use App\Models\Clearancerequirements;
use App\Models\Feetypes;
use App\Models\Offices;
use App\Models\Payments;
use App\Models\Staffusers;
use App\Models\Studentclearances;
use App\Models\Students;
use Database\Seeders\RbacSeeder;
use Database\Seeders\StarterReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * What a lost clearance slip costs was written down twice: the slip's footer read a
 * `settings` row seeded at 100.00 while the payment the desk recorded read the
 * 'Clearance Slip Replacement' fee type (§25 P-12). Ruling 8 settled the amount as
 * Reference Data's, so the fee table is the only source and the settings key is gone.
 *
 * The branch that matters for a desk is the empty one: with no fee type on file the
 * replacement is refused and names the row to create, rather than charging a figure no
 * fee schedule holds.
 */
class ClearanceSlipReplacementFeeTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $accounting;

    private Clearanceperiods $period;

    private Studentclearances $clearance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        foreach ([1, OfficeId::Accounting->value] as $officeId) {
            Offices::firstOrCreate(['officeId' => $officeId], ['officeName' => 'Office '.$officeId]);
        }

        DB::table('religions')->insert([
            'religionId' => 1,
            'religionName' => 'Roman Catholic',
        ]);

        $year = Academicyears::create([
            'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01',
            'endDate' => '2027-03-31',
        ]);

        $term = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ]);

        $student = Students::create([
            'schoolIdNumber' => 'FEE-'.uniqid(),
            'lastName' => 'FeeTest',
            'firstName' => 'Student',
            'middleName' => 'F',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
            'email' => 'fee_student_'.uniqid().'@example.com',
            'username' => 'fee_student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->period = Clearanceperiods::create([
            'termId' => $term->termId,
            'clearanceStartDate' => '2026-09-01',
            'clearanceEndDate' => '2026-10-31',
            'periodStatus' => ClearancePeriodStatus::Open,
        ]);

        $this->clearance = Studentclearances::create([
            'studentId' => $student->studentId,
            'clearancePeriodId' => $this->period->clearancePeriodId,
            'overallStatus' => ClearanceOverallStatus::Pending,
        ]);

        $requirement = Clearancerequirements::create([
            'officeId' => OfficeId::Accounting->value,
            'requirementName' => 'Accounting clearance',
        ]);

        Clearanceapprovals::create([
            'studentClearanceId' => $this->clearance->studentClearanceId,
            'clearanceRequirementId' => $requirement->clearanceRequirementId,
            'status' => ClearanceApprovalStatus::Pending,
            'remarks' => '',
        ]);

        $this->accounting = Staffusers::factory()->create([
            'officeId' => OfficeId::Accounting->value,
            'role' => StaffRole::OfficeHead,
            'employeeNo' => 'EMP-FEE-'.uniqid(),
            'username' => 'fee_accounting_'.uniqid(),
            'email' => 'fee_accounting_'.uniqid().'@example.com',
        ]);
        $this->accounting->givePermissionTo('clearance.slip.replace');

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function replaceRoute(array $payload)
    {
        return $this->actingAs($this->accounting)->post(route('clearance.slip.replace'), $payload);
    }

    private function slipFooter(): string
    {
        $clearance = Studentclearances::with([
            'student.enrollments.course', 'clearancePeriod.term.academicYear',
            'approvals.requirement.office', 'approvals.approvedByUser', 'receivedByUser',
        ])->findOrFail($this->clearance->studentClearanceId);

        return view('prints.clearance-slip', [
            'clearance' => $clearance,
            'documentNumber' => 1,
        ])->render();
    }

    #[Test]
    public function the_amount_charged_is_the_replacement_fee_type_the_registrar_maintains(): void
    {
        $fee = Feetypes::create([
            'feeName' => Feetypes::CLEARANCE_SLIP_REPLACEMENT,
            'defaultAmount' => 175.00,
            'unitBasis' => FeeUnitBasis::Flat,
        ]);

        $this->replaceRoute([
            'studentId' => $this->clearance->studentId,
            'clearancePeriodId' => $this->period->clearancePeriodId,
            'orNumber' => 'OR-FEE-1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('payments', [
            'orNumber' => 'OR-FEE-1',
            'amount' => 175.00,
        ]);

        // Reprice it in Reference Data and the next replacement follows — no constant in
        // the controller or the slip holds the old figure.
        $fee->update(['defaultAmount' => 250.00]);

        $this->assertSame(250.00, Feetypes::clearanceSlipReplacementFee());

        $this->replaceRoute([
            'studentId' => $this->clearance->studentId,
            'clearancePeriodId' => $this->period->clearancePeriodId,
            'orNumber' => 'OR-FEE-2',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('payments', [
            'orNumber' => 'OR-FEE-2',
            'amount' => 250.00,
        ]);

        $this->assertStringContainsString('₱250.00', $this->slipFooter());
    }

    #[Test]
    public function a_replacement_without_a_fee_on_file_is_refused_and_names_the_row_to_create(): void
    {
        $this->assertNull(Feetypes::clearanceSlipReplacementFee());

        $this->replaceRoute([
            'studentId' => $this->clearance->studentId,
            'clearancePeriodId' => $this->period->clearancePeriodId,
            'orNumber' => 'OR-FEE-3',
        ])->assertSessionHasErrors('fee');

        // Nothing is charged and the slip is not reissued on an invented amount.
        $this->assertSame(0, Payments::count());
        $this->assertSame('pending', $this->clearance->fresh()->overallStatus->value);
        $this->assertStringContainsString(
            Feetypes::CLEARANCE_SLIP_REPLACEMENT,
            session('errors')->first('fee')
        );
    }

    #[Test]
    public function the_printed_slip_states_no_fee_until_the_fee_is_set(): void
    {
        $this->assertStringNotContainsString('replacement fee', $this->slipFooter());

        Feetypes::create([
            'feeName' => Feetypes::CLEARANCE_SLIP_REPLACEMENT,
            'defaultAmount' => 120.00,
            'unitBasis' => FeeUnitBasis::Flat,
        ]);

        $this->assertStringContainsString('₱120.00', $this->slipFooter());
    }

    #[Test]
    public function a_fresh_install_has_an_amount_and_no_second_place_to_set_it(): void
    {
        $this->seed(StarterReferenceDataSeeder::class);

        $this->assertSame(100.00, Feetypes::clearanceSlipReplacementFee());

        // The superseded settings key is retired by migration, so the fee table is the
        // only place the amount can be read from — a Settings row the Registrar could
        // edit but that decides nothing would be worse than having deleted it.
        $this->assertDatabaseMissing('settings', ['settingKey' => 'clearanceReplacementFee']);
    }
}
