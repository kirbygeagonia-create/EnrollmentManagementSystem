<?php

namespace Tests\Feature\Clearance;

use App\Enums\ClearanceApprovalStatus;
use App\Enums\ClearanceOverallStatus;
use App\Models\Academicterms;
use App\Models\Academicyears;
use App\Models\Clearanceapprovals;
use App\Models\Clearanceperiods;
use App\Models\Clearancerequirements;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Studentclearances;
use App\Models\Students;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A refused clearance line has to say why (development prompt §6.5).
 *
 * The office checklist line is the one place a desk marks a requirement `rejected` and the student is
 * sent away to fix something. Until this file the remark was `nullable|string`, so a line could be
 * refused with no reason recorded, the clearance could go `Rejected` on the strength of a blank cell,
 * and the desk's own page — which does print the remark at Clearance/Index.jsx:658-660 — had nothing
 * to show. The rule mirrors the crediting decision built for prompt §7.3: a reason is mandatory on the
 * refusal and stays optional where the box is a note rather than a verdict.
 *
 * The third case is the directional one: it proves the new rule did not break the act the desk performs
 * most, an approval with nothing typed.
 */
class ClearanceRequirementRejectionTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $head;

    private Studentclearances $clearance;

    private Clearancerequirements $requirement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        Offices::firstOrCreate(['officeId' => 1], ['officeName' => 'Registrar']);

        $this->head = Staffusers::factory()->create([
            'officeId' => 1,
            'role' => 'officeHead',
        ]);
        RbacSeeder::assignDeskRolesFromEnums();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $period = Clearanceperiods::create([
            'termId' => $this->term()->termId,
            'clearanceStartDate' => '2026-10-01',
            'clearanceEndDate' => '2026-10-31',
            'periodStatus' => 'open',
        ]);

        // No Students factory exists, so the person row is written the way the print
        // fixtures write it: every NOT NULL column named explicitly.
        // `students.religionId` is a real foreign key, and an :memory: schema holds no
        // religions row — read the id the insert hands back rather than assuming 1.
        $religion = Religions::firstOrCreate(['religionName' => 'Test Faith']);

        $student = Students::create([
            'schoolIdNumber' => 'CLR-'.uniqid(),
            'lastName' => 'Rejection',
            'firstName' => 'Clearance',
            'middleName' => 'T',
            'suffix' => 'N/A',
            'gender' => 'female',
            'birthdate' => '2004-03-04',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => $religion->religionId,
            'contactNumber' => '09171234567',
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
            'email' => 'clearance_'.uniqid().'@example.com',
            'username' => 'clearance_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->clearance = Studentclearances::create([
            'studentId' => $student->studentId,
            'clearancePeriodId' => $period->clearancePeriodId,
            'overallStatus' => ClearanceOverallStatus::Pending,
        ]);

        $this->requirement = Clearancerequirements::create([
            'requirementName' => 'Library clearance',
            'officeId' => 1,
        ]);
    }

    private function term(): Academicterms
    {
        $year = Academicyears::create([
            'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01',
            'endDate' => '2027-03-31',
        ]);

        return Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2027-03-31',
        ]);
    }

    private function line(): Clearanceapprovals
    {
        return Clearanceapprovals::create([
            'studentClearanceId' => $this->clearance->studentClearanceId,
            'clearanceRequirementId' => $this->requirement->clearanceRequirementId,
            'status' => ClearanceApprovalStatus::Pending,
        ]);
    }

    #[Test]
    public function a_refused_line_is_refused_again_when_it_carries_no_reason(): void
    {
        $approval = $this->line();

        $this->actingAs($this->head)
            ->post(route('clearance.approve', $approval), ['status' => 'rejected'])
            ->assertSessionHasErrors('remarks');

        $this->assertSame(
            ClearanceApprovalStatus::Pending->value,
            $approval->fresh()->status->value,
            'a refusal without a reason must not move the line at all',
        );
        $this->assertSame(
            ClearanceOverallStatus::Pending->value,
            $this->clearance->fresh()->overallStatus->value,
            'and must not push the whole clearance into Rejected on a blank cell',
        );
    }

    #[Test]
    public function a_refused_line_with_a_reason_is_recorded_and_the_clearance_goes_rejected(): void
    {
        $approval = $this->line();

        $this->actingAs($this->head)
            ->post(route('clearance.approve', $approval), [
                'status' => 'rejected',
                'remarks' => 'Borrowed book outstanding from second semester.',
            ])
            ->assertSessionHasNoErrors();

        $fresh = $approval->fresh();

        $this->assertSame(ClearanceApprovalStatus::Rejected->value, $fresh->status->value);
        $this->assertStringContainsString('Borrowed book', (string) $fresh->remarks);
        $this->assertSame(
            ClearanceOverallStatus::Rejected->value,
            $this->clearance->fresh()->overallStatus->value,
        );
    }

    #[Test]
    public function an_approved_line_still_needs_nothing_typed_in_the_remark_box(): void
    {
        $approval = $this->line();

        $this->actingAs($this->head)
            ->post(route('clearance.approve', $approval), [
                'status' => 'approved',
                'remarks' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(ClearanceApprovalStatus::Approved->value, $approval->fresh()->status->value);
    }

    #[Test]
    public function a_short_reason_is_refused_rather_than_accepted_as_a_placeholder(): void
    {
        $approval = $this->line();

        $this->actingAs($this->head)
            ->post(route('clearance.approve', $approval), ['status' => 'rejected', 'remarks' => 'late'])
            ->assertSessionHasErrors('remarks');

        $this->assertSame(ClearanceApprovalStatus::Pending->value, $approval->fresh()->status->value);
    }

    #[Test]
    public function a_desk_outside_the_lines_office_cannot_refuse_it_at_all(): void
    {
        Offices::firstOrCreate(['officeId' => 2], ['officeName' => 'Accounting']);
        $other = Staffusers::factory()->create(['officeId' => 2, 'role' => 'officeHead']);
        RbacSeeder::assignDeskRolesFromEnums();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->actingAs($other)
            ->post(route('clearance.approve', $this->line()), [
                'status' => 'rejected',
                'remarks' => 'Not this office’s line to refuse.',
            ])
            ->assertForbidden();
    }
}
