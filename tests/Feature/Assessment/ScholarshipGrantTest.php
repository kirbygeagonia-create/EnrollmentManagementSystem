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
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Scholarshiptypes;
use App\Models\Staffusers;
use App\Models\Studentassessments;
use App\Models\Students;
use App\Models\Studentscholarships;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The Scholarship desk's award action (BR19). Three defects lived here and each is
 * pinned below: studentscholarships carries a unique
 * (studentId, scholarshipTypeId, termId), so re-picking the same grant from the
 * modal died with a raw duplicate-key error page; the "one full grant" check ran
 * across the student's whole history instead of the term, so a continuing student
 * could never be granted again; and an award computed against an account that was
 * already covered filed an Active grant worth ₱0.
 *
 * The office scope is asserted too, because §12's permission table and the seeded
 * roles disagree: OfficeHead — the role every desk screen is opened with — does not
 * hold assessment.scholarships.apply, so awarding belongs to ScholarshipOfficer.
 */
class ScholarshipGrantTest extends TestCase
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
            'employeeNo' => 'EMP-SCHOL-'.uniqid(),
            'username' => 'schol_'.$officeId.'_'.uniqid(),
            'email' => 'schol_'.$officeId.'_'.uniqid().'@example.com',
        ]);
        $staff->assignRole($spatieRole);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    /**
     * A student, and one of their enrollments in one term with an assessment.
     *
     * @param  array<string, mixed>  $amounts
     * @return array{0: Students, 1: Studentassessments}
     */
    private function assess(?Academicterms $term = null, array $amounts = [], ?Students $student = null): array
    {
        $term = $term ?? $this->term;

        $student = $student ?? Students::create([
            'schoolIdNumber' => 'SCHOL-'.uniqid(),
            'lastName' => 'Grantee',
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
            'email' => 'schol_student_'.uniqid().'@example.com',
            'username' => 'schol_student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $enrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $this->course->courseId,
            'termId' => $term->termId,
            'yearLevel' => 1,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => EnrollmentType::New,
            'academicStanding' => 'regular',
            'enrollmentStatus' => EnrollmentStatus::Assessed,
            'evaluatedBy' => $this->officer->userId,
            'enrolledDate' => now(),
            'formIssuedDate' => now()->toDateString(),
        ]);

        $assessment = Studentassessments::create(array_merge([
            'enrollmentId' => $enrollment->enrollmentId,
            'totalAssessedAmount' => 5000,
            'totalScholarshipCoverage' => 0,
            'totalWaived' => 0,
            'remainingBalance' => 5000,
            'assessmentDate' => now()->toDateString(),
        ], $amounts));

        return [$student, $assessment];
    }

    #[Test]
    public function a_partial_grant_covers_its_percent_and_recomputes_the_balance(): void
    {
        [, $assessment] = $this->assess();

        $this->actingAs($this->officer)
            ->post(route('assessment.scholarships.apply', $assessment), [
                'scholarshipTypeId' => $this->quarterGrant->scholarshipTypeId,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $grant = Studentscholarships::sole();
        $this->assertSame($this->quarterGrant->scholarshipTypeId, $grant->scholarshipTypeId);
        $this->assertSame($assessment->enrollment->studentId, $grant->studentId);
        $this->assertSame($this->term->termId, $grant->termId);
        $this->assertSame(ScholarshipStatus::Active, $grant->status);
        $this->assertSame($this->officer->userId, $grant->approvedBy);

        $assessment->refresh();
        $this->assertEquals(1250, (float) $assessment->totalScholarshipCoverage);
        $this->assertEquals(3750, (float) $assessment->remainingBalance);
    }

    #[Test]
    public function partial_grants_stack_only_up_to_the_assessed_total(): void
    {
        [, $assessment] = $this->assess();

        $this->actingAs($this->officer)
            ->post(route('assessment.scholarships.apply', $assessment), [
                'scholarshipTypeId' => $this->quarterGrant->scholarshipTypeId,
            ])->assertSessionHasNoErrors();

        $third = Scholarshiptypes::create([
            'scholarshipName' => 'Sibling Discount',
            'coverageType' => 'partial',
            'coveragePercent' => 10,
        ]);

        // 25% + 80% of ₱5,000 is ₱5,250, but the second award is clamped to what
        // is left owing (₱3,750): §18's "partial grants stack only up to the
        // assessed total". The controller's own "cannot exceed 100%" error is
        // unreachable behind this clamp, so the clamp is what is asserted.
        $this->actingAs($this->officer)
            ->post(route('assessment.scholarships.apply', $assessment), [
                'scholarshipTypeId' => $this->familyGrant->scholarshipTypeId,
            ])->assertSessionHasNoErrors();

        $assessment->refresh();
        $this->assertEquals(5000, (float) $assessment->totalScholarshipCoverage);
        $this->assertEquals(0, (float) $assessment->remainingBalance);
        $this->assertSame(2, Studentscholarships::count());

        // Nothing is left to attach, so a third grant is refused rather than filed
        $this->actingAs($this->officer)
            ->post(route('assessment.scholarships.apply', $assessment), [
                'scholarshipTypeId' => $third->scholarshipTypeId,
            ])
            ->assertSessionHasErrors('scholarshipTypeId');

        $this->assertSame(2, Studentscholarships::count());
        $this->assertEquals(5000, (float) $assessment->fresh()->totalScholarshipCoverage);
    }

    #[Test]
    public function the_same_grant_cannot_be_awarded_twice_for_one_term(): void
    {
        [, $assessment] = $this->assess();

        $payload = ['scholarshipTypeId' => $this->quarterGrant->scholarshipTypeId];

        $this->actingAs($this->officer)
            ->post(route('assessment.scholarships.apply', $assessment), $payload)
            ->assertSessionHasNoErrors();

        // The modal keeps every catalog entry selectable, so this second post is the
        // double-click. studentscholarships' unique (studentId, scholarshipTypeId,
        // termId) used to answer it with a duplicate-key exception page.
        $this->actingAs($this->officer)
            ->post(route('assessment.scholarships.apply', $assessment), $payload)
            ->assertRedirect()
            ->assertSessionHasErrors('scholarshipTypeId');

        $this->assertSame(1, Studentscholarships::count());
        $this->assertEquals(1250, (float) $assessment->fresh()->totalScholarshipCoverage);
    }

    #[Test]
    public function a_full_grant_excludes_another_full_grant_in_the_same_term(): void
    {
        [, $assessment] = $this->assess();

        $this->actingAs($this->officer)
            ->post(route('assessment.scholarships.apply', $assessment), [
                'scholarshipTypeId' => $this->fullGrant->scholarshipTypeId,
            ])->assertSessionHasNoErrors();

        $this->assertEquals(5000, (float) $assessment->fresh()->totalScholarshipCoverage);
        $this->assertEquals(0, (float) $assessment->fresh()->remainingBalance);

        $otherFull = Scholarshiptypes::create([
            'scholarshipName' => 'Board Topper Full Scholarship',
            'coverageType' => 'full',
            'coveragePercent' => 100,
        ]);

        $this->actingAs($this->officer)
            ->post(route('assessment.scholarships.apply', $assessment), [
                'scholarshipTypeId' => $otherFull->scholarshipTypeId,
            ])
            ->assertSessionHasErrors('scholarshipTypeId');

        $this->assertSame(1, Studentscholarships::count());
    }

    #[Test]
    public function a_full_grant_from_the_previous_term_does_not_block_the_new_term(): void
    {
        [$student, $firstTermAssessment] = $this->assess();
        $this->actingAs($this->officer)
            ->post(route('assessment.scholarships.apply', $firstTermAssessment), [
                'scholarshipTypeId' => $this->fullGrant->scholarshipTypeId,
            ])->assertSessionHasNoErrors();

        $nextYear = Academicyears::create([
            'yearLabel' => '2027-2028',
            'startDate' => '2027-06-01',
            'endDate' => '2028-03-31',
        ]);

        $nextTerm = Academicterms::create([
            'academicYearId' => $nextYear->academicYearId,
            'semester' => '1st',
            'startDate' => '2027-06-01',
            'endDate' => '2027-10-31',
        ]);

        [, $secondTermAssessment] = $this->assess(term: $nextTerm, student: $student);

        // Same student, same grant, new term. The exclusivity rule is per term
        // (§18: "one full grant per student per term") and the unique key is too,
        // so a continuing grantee must be awardable again.
        $this->assertSame($student->studentId, $secondTermAssessment->enrollment->studentId);

        $this->actingAs($this->officer)
            ->post(route('assessment.scholarships.apply', $secondTermAssessment), [
                'scholarshipTypeId' => $this->fullGrant->scholarshipTypeId,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Studentscholarships::count());
        $this->assertEquals(5000, (float) $secondTermAssessment->fresh()->totalScholarshipCoverage);
    }

    #[Test]
    public function an_award_that_would_cover_nothing_is_refused_instead_of_filing_an_empty_grant(): void
    {
        [, $assessment] = $this->assess(amounts: [
            'totalScholarshipCoverage' => 5000,
            'remainingBalance' => 0,
        ]);

        // The account is already fully covered, so every coverage figure computes
        // to 0. Before this guard the desk still filed an Active grant row worth
        // nothing, which then appears on the student's record as an award.
        $this->actingAs($this->officer)
            ->post(route('assessment.scholarships.apply', $assessment), [
                'scholarshipTypeId' => $this->quarterGrant->scholarshipTypeId,
            ])
            ->assertSessionHasErrors('scholarshipTypeId');

        $this->assertSame(0, Studentscholarships::count());
        $this->assertEquals(5000, (float) $assessment->fresh()->totalScholarshipCoverage);
    }

    #[Test]
    public function only_the_scholarship_office_may_award_a_grant(): void
    {
        [, $assessment] = $this->assess();

        // Same role, different office: the permission alone is not the guard.
        $accountingOfficer = $this->staffInOffice(OfficeId::Accounting->value, 'ScholarshipOfficer');
        $this->assertTrue($accountingOfficer->hasPermissionTo('assessment.scholarships.apply'));

        $this->actingAs($accountingOfficer)
            ->post(route('assessment.scholarships.apply', $assessment), [
                'scholarshipTypeId' => $this->quarterGrant->scholarshipTypeId,
            ])
            ->assertForbidden();

        $this->assertSame(0, Studentscholarships::count());
    }

    #[Test]
    public function a_desk_head_without_the_award_permission_cannot_award(): void
    {
        [, $assessment] = $this->assess();

        $head = $this->staffInOffice(OfficeId::Scholarship->value, 'OfficeHead');

        // §12's table credits this screen to assessment.scholarships.apply, but the
        // seeded OfficeHead role does not carry it — so opening the desk is not the
        // same as being able to grant. Pinned here rather than assumed.
        $this->assertFalse($head->hasPermissionTo('assessment.scholarships.apply'));

        $this->actingAs($head)
            ->post(route('assessment.scholarships.apply', $assessment), [
                'scholarshipTypeId' => $this->quarterGrant->scholarshipTypeId,
            ])
            ->assertForbidden();

        $this->assertSame(0, Studentscholarships::count());
    }

    #[Test]
    public function an_unknown_scholarship_type_is_refused_before_anything_is_filed(): void
    {
        [, $assessment] = $this->assess();

        $this->actingAs($this->officer)
            ->post(route('assessment.scholarships.apply', $assessment), [
                'scholarshipTypeId' => 99999,
            ])
            ->assertSessionHasErrors('scholarshipTypeId');

        $this->assertSame(0, Studentscholarships::count());
    }
}
