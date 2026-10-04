<?php

namespace Tests\Feature\Assessment;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\OfficeId;
use App\Enums\ScholarshipStatus;
use App\Enums\StaffRole;
use App\Enums\StudentType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Enrollmentstatushistory;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Scholarshiptypes;
use App\Models\Staffusers;
use App\Models\Studentassessments;
use App\Models\Students;
use App\Models\Studentscholarships;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Ruling 15: the Scholarship desk can take a grant back — revoke it for cause, or let it
 * expire — and the consequence is arithmetic, not a status label.
 *
 * `revoked` and `expired` already existed in the enum and nothing could write them, which
 * meant a withdrawn grant kept discounting the student's fee sheet forever. These tests pin
 * what the withdrawal has to move: coverage recomputed from the grants still standing, the
 * balance reopened, and an account that now owes pulled back out of `paid` — past the
 * Registrar's signature, which stays signed, because this desk reverses a figure and not
 * another office's act.
 *
 * One choice is deliberate and pinned below: the recomputed coverage comes from the standing
 * grant's own percentage of the assessed total, not from the amount it was credited with at
 * award time. A grant carries no amount of its own (`studentscholarships` has no coverage
 * column), so the percentage is the only reproducible figure — and it makes the result
 * independent of the order the grants happened to be awarded in.
 */
class ScholarshipWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $officer;

    private Academicterms $term;

    private Courses $course;

    private Scholarshiptypes $fullGrant;

    private Scholarshiptypes $quarterGrant;

    private Scholarshiptypes $familyGrant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        foreach ([1, 2, 3, 4, 5, 11, 22] as $officeId) {
            Offices::firstOrCreate(['officeId' => $officeId], ['officeName' => 'Office '.$officeId]);
        }

        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        $unit = Academicunits::create([
            'unitName' => 'College of Computer Studies',
            'unitType' => 'college',
        ]);

        $year = Academicyears::create([
            'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01',
            'endDate' => '2027-03-31',
        ]);

        $this->term = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ]);

        $this->course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCS',
            'courseName' => 'Bachelor of Science in Computer Science',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);

        $this->fullGrant = Scholarshiptypes::create([
            'scholarshipName' => 'School Grant (Full Tuition)',
            'coverageType' => 'full',
            'coveragePercent' => 100,
        ]);

        $this->quarterGrant = Scholarshiptypes::create([
            'scholarshipName' => 'Honor Scholarship',
            'coverageType' => 'partial',
            'coveragePercent' => 25,
        ]);

        $this->familyGrant = Scholarshiptypes::create([
            'scholarshipName' => "Founder's Family Discount",
            'coverageType' => 'partial',
            'coveragePercent' => 80,
        ]);

        $this->officer = $this->staffInOffice(OfficeId::Scholarship->value, 'ScholarshipOfficer');
    }

    private function staffInOffice(int $officeId, string $spatieRole): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => $officeId,
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-WDR-'.uniqid(),
            'username' => 'wdr_'.$officeId.'_'.uniqid(),
            'email' => 'wdr_'.$officeId.'_'.uniqid().'@example.com',
        ]);
        $staff->assignRole($spatieRole);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    private function student(): Students
    {
        return Students::create([
            'schoolIdNumber' => 'WDR-'.uniqid(),
            'lastName' => 'Withdraw',
            'firstName' => 'Student',
            'middleName' => 'A',
            'suffix' => 'N/A',
            'gender' => 'female',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
            'email' => 'wdr_student_'.uniqid().'@example.com',
            'username' => 'wdr_student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);
    }

    /**
     * A ₱5,000 fee sheet for one student in one term.
     */
    private function assess(?Academicterms $term = null, ?Students $student = null): Studentassessments
    {
        $student = $student ?? $this->student();

        $enrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $this->course->courseId,
            'termId' => ($term ?? $this->term)->termId,
            'yearLevel' => 1,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => EnrollmentType::New,
            'academicStanding' => 'regular',
            'enrollmentStatus' => EnrollmentStatus::Assessed,
            'evaluatedBy' => $this->officer->userId,
            'enrolledDate' => now(),
            'formIssuedDate' => now()->toDateString(),
        ]);

        return Studentassessments::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'totalAssessedAmount' => 5000,
            'totalScholarshipCoverage' => 0,
            'totalWaived' => 0,
            'remainingBalance' => 5000,
            'assessmentDate' => now()->toDateString(),
        ]);
    }

    /**
     * Award a grant the way the screen does, and hand back the row it filed.
     */
    private function award(Studentassessments $assessment, Scholarshiptypes $type): Studentscholarships
    {
        $this->actingAs($this->officer)
            ->post(route('assessment.scholarships.apply', $assessment), [
                'scholarshipTypeId' => $type->scholarshipTypeId,
            ])
            ->assertSessionHasNoErrors();

        return Studentscholarships::where('scholarshipTypeId', $type->scholarshipTypeId)->sole();
    }

    private function withdraw(Studentscholarships $grant, string $decision = 'revoke', string $reason = 'Awarded in error; the student did not meet the retention standing.'): TestResponse
    {
        return $this->actingAs($this->officer)
            ->post(route('assessment.scholarships.withdraw', $grant), [
                'decision' => $decision,
                'reason' => $reason,
            ]);
    }

    #[Test]
    public function revoking_a_grant_recomputes_the_coverage_and_reopens_the_balance(): void
    {
        $assessment = $this->assess();
        $grant = $this->award($assessment, $this->quarterGrant);

        $this->assertEquals(1250, (float) $assessment->fresh()->totalScholarshipCoverage);
        $this->assertEquals(3750, (float) $assessment->fresh()->remainingBalance);

        $this->withdraw($grant)->assertRedirect()->assertSessionHas('success');

        $assessment->refresh();
        $this->assertEquals(0, (float) $assessment->totalScholarshipCoverage);
        $this->assertEquals(5000, (float) $assessment->remainingBalance);
        $this->assertEquals(5000, $assessment->outstandingBalance());
    }

    #[Test]
    public function the_withdrawal_is_written_onto_the_grant_rather_than_deleting_it(): void
    {
        $assessment = $this->assess();
        $grant = $this->award($assessment, $this->quarterGrant);

        $this->withdraw($grant, 'expire', 'The grant covered this term only and has run out.')->assertSessionHas('success');

        $grant->refresh();
        $this->assertSame(ScholarshipStatus::Expired, $grant->status);
        $this->assertSame($this->officer->userId, $grant->statusChangedBy);
        $this->assertNotNull($grant->statusChangedAt);
        $this->assertSame('The grant covered this term only and has run out.', $grant->statusReason);

        // The award was a real act by a real desk: the row stays, with the decision on it,
        // so the student's record still shows what they were given and what took it back.
        $this->assertSame($this->officer->userId, $grant->approvedBy);
        $this->assertSame(1, Studentscholarships::count());
    }

    #[Test]
    public function revoking_the_grant_that_covered_everything_unpays_the_account(): void
    {
        $assessment = $this->assess();
        $enrollment = $assessment->enrollment;
        $grant = $this->award($assessment, $this->fullGrant);

        // Accounting closed it: nothing owed, so the record reached `paid`.
        $enrollment->update(['enrollmentStatus' => EnrollmentStatus::Paid]);

        $this->withdraw($grant, 'revoke', 'Full tuition grant revoked after the standing review.')->assertSessionHas('success');

        $assessment->refresh();
        $this->assertEquals(5000, (float) $assessment->remainingBalance);
        $this->assertSame(EnrollmentStatus::Assessed, $enrollment->fresh()->enrollmentStatus);

        $history = Enrollmentstatushistory::where('enrollmentId', $enrollment->enrollmentId)->latest('historyId')->firstOrFail();
        $this->assertSame('paid', $history->fromStatus);
        $this->assertSame('assessed', $history->toStatus);
        $this->assertStringContainsString('School Grant (Full Tuition)', (string) $history->remarks);
        $this->assertStringContainsString('revoked', (string) $history->remarks);
        $this->assertStringContainsString('standing review', (string) $history->remarks);
    }

    #[Test]
    public function a_withdrawal_reaches_past_the_registrar_signature_without_un_signing_it(): void
    {
        $assessment = $this->assess();
        $enrollment = $assessment->enrollment;
        $grant = $this->award($assessment, $this->fullGrant);

        $enrollment->update(['enrollmentStatus' => EnrollmentStatus::Enrolled]);

        $this->withdraw($grant, 'expire', 'Term ended and the grant was not renewed.')->assertSessionHas('success');

        // Ruling 15's money consequence does not stop at the Registrar's box: an approved
        // student who owes again has to sit where Accounting can see it.
        $this->assertSame(EnrollmentStatus::Assessed, $enrollment->fresh()->enrollmentStatus);
        $this->assertEquals(5000, (float) $assessment->fresh()->remainingBalance);
    }

    #[Test]
    public function what_removes_coverage_is_the_grant_taken_back_not_the_award_left_behind(): void
    {
        $assessment = $this->assess();
        $quarter = $this->award($assessment, $this->quarterGrant);

        // 25% then 80% of ₱5,000 is ₱5,250, so the second award is clamped to what was
        // still owing: ₱3,750 credited against a grant whose own terms are ₱4,000.
        $this->award($assessment, $this->familyGrant);
        $this->assertEquals(5000, (float) $assessment->fresh()->totalScholarshipCoverage);

        $this->withdraw($quarter, 'revoke', 'Honor scholarship revoked; the family discount stands.')->assertSessionHas('success');

        $assessment->refresh();
        // The remaining grant is re-read at its own 80% (₱4,000), not at the ₱3,750 it was
        // credited with — the clamp was caused by the grant that has now gone. So the
        // student owes ₱1,000, which is what an 80% discount on ₱5,000 leaves.
        $this->assertEquals(4000, (float) $assessment->totalScholarshipCoverage);
        $this->assertEquals(1000, (float) $assessment->remainingBalance);
        $this->assertSame(2, Studentscholarships::count());
    }

    #[Test]
    public function a_withdrawal_has_to_name_its_decision_and_give_a_reason(): void
    {
        $assessment = $this->assess();
        $grant = $this->award($assessment, $this->quarterGrant);

        $this->actingAs($this->officer)
            ->post(route('assessment.scholarships.withdraw', $grant), [
                'decision' => 'cancel',
                'reason' => 'The award was made against the wrong standing requirement.',
            ])
            ->assertSessionHasErrors('decision');

        $this->actingAs($this->officer)
            ->post(route('assessment.scholarships.withdraw', $grant), [
                'decision' => 'revoke',
                'reason' => 'typo',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame(ScholarshipStatus::Active, $grant->fresh()->status);
        $this->assertEquals(1250, (float) $assessment->fresh()->totalScholarshipCoverage);
        $this->assertEquals(3750, (float) $assessment->fresh()->remainingBalance);
    }

    #[Test]
    public function a_grant_that_is_already_withdrawn_cannot_be_withdrawn_again(): void
    {
        $assessment = $this->assess();
        $grant = $this->award($assessment, $this->quarterGrant);

        $this->withdraw($grant)->assertSessionHas('success');

        // Policy::withdrawScholarship reads an Active grant as the only thing withdrawable,
        // so a second click cannot rewrite a closed decision or double-count the removal.
        $this->withdraw($grant, 'expire', 'A later desk changes its mind about the same grant.')
            ->assertForbidden();

        $grant->refresh();
        $this->assertSame(ScholarshipStatus::Revoked, $grant->status);
        $this->assertStringContainsString('retention standing', (string) $grant->statusReason);
        $this->assertEquals(0, (float) $assessment->fresh()->totalScholarshipCoverage);
    }

    #[Test]
    public function only_the_scholarship_office_may_take_a_grant_back(): void
    {
        $assessment = $this->assess();
        $grant = $this->award($assessment, $this->quarterGrant);

        // Same role, different office: the permission alone is not the guard.
        $elsewhere = $this->staffInOffice(OfficeId::Accounting->value, 'ScholarshipOfficer');
        $this->assertTrue($elsewhere->hasPermissionTo('assessment.scholarships.withdraw'));

        $this->actingAs($elsewhere)
            ->post(route('assessment.scholarships.withdraw', $grant), [
                'decision' => 'revoke',
                'reason' => 'The award was made against the wrong standing requirement.',
            ])
            ->assertForbidden();

        $this->assertSame(ScholarshipStatus::Active, $grant->fresh()->status);
        $this->assertEquals(1250, (float) $assessment->fresh()->totalScholarshipCoverage);
    }

    #[Test]
    public function a_desk_head_without_the_withdrawal_permission_cannot_take_a_grant_back(): void
    {
        $assessment = $this->assess();
        $grant = $this->award($assessment, $this->quarterGrant);

        $head = $this->staffInOffice(OfficeId::Scholarship->value, 'OfficeHead');
        $this->assertFalse($head->hasPermissionTo('assessment.scholarships.withdraw'));

        $this->actingAs($head)
            ->post(route('assessment.scholarships.withdraw', $grant), [
                'decision' => 'revoke',
                'reason' => 'The award was made against the wrong standing requirement.',
            ])
            ->assertForbidden();

        $this->assertSame(ScholarshipStatus::Active, $grant->fresh()->status);
    }

    #[Test]
    public function withdrawing_a_grant_for_a_term_that_never_billed_anything_recomputes_nothing(): void
    {
        $student = $this->student();

        // A grant awarded before the student enrolled, in a term they never registered for:
        // there is no fee sheet for it to have discounted, so the withdrawal is only the
        // record of the decision.
        $grant = Studentscholarships::create([
            'studentId' => $student->studentId,
            'scholarshipTypeId' => $this->quarterGrant->scholarshipTypeId,
            'termId' => $this->term->termId,
            'status' => ScholarshipStatus::Active,
            'approvedBy' => $this->officer->userId,
            'awardedBeforeEnrollment' => true,
        ]);

        $this->withdraw($grant, 'expire', 'The student did not enrol this term, so the grant lapsed.')
            ->assertSessionHas('success');

        $this->assertSame(ScholarshipStatus::Expired, $grant->fresh()->status);
        $this->assertSame(0, Studentassessments::count());
    }

    #[Test]
    public function the_assessment_screen_offers_the_withdrawal_only_to_the_scholarship_desk(): void
    {
        $assessment = $this->assess();
        $this->award($assessment, $this->quarterGrant);

        $this->actingAs($this->officer)
            ->get(route('assessment.show', $assessment))
            ->assertInertia(fn ($page) => $page
                ->component('Assessment/Show')
                ->where('can.withdrawScholarship', true)
            );

        // Accounting may read the fee sheet but not undo a grant on it, so the button is
        // not drawn for the desk that cannot use it.
        $accountant = $this->staffInOffice(OfficeId::Accounting->value, 'AccountingStaff');

        $this->actingAs($accountant)
            ->get(route('assessment.show', $assessment))
            ->assertInertia(fn ($page) => $page
                ->where('can.withdrawScholarship', false)
            );
    }
}
