<?php

namespace Tests\Feature\Evaluation;

use App\Enums\EnrollmentStatus;
use App\Enums\OfficeId;
use App\Enums\StaffRole;
use App\Enums\StudentType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Curriculums;
use App\Models\Enrollments;
use App\Models\Enrollmentworkflow;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use App\Models\Workflowsteps;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Ruling 2 (G-1): the returning student's cycle starts at Department Evaluation, and that
 * is the desk that issues the enrollment.
 *
 * §6.3: a returning student does not pass intake, the general entrance examination or the
 * admission decision again — those stages live on the student record permanently. What
 * repeats every term is stages 5-11, which begin at this desk. Until now the admission
 * decision was the only code in app/ that created an enrollments row, so the ladder could
 * only be demonstrated by writing the row into the database by hand, and the docx recorded
 * that a returning student reached the Evaluation queue "by accident of the seeded data".
 *
 * Ruling 3 (G-4) rides with it: one active enrollment per student per term, over ACTIVE
 * rows only — which is what lets the student ruling 17 dropped come back into the same
 * term. No unique index yet, as the ruling says; the guard is in the code that creates.
 */
class ReturningEnrollmentIssuingTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $evaluator;

    private Students $returning;

    private Students $fresh;

    private Courses $course;

    private Academicterms $term;

    private Academicterms $priorTerm;

    private Curriculums $oldCatalog;

    private Curriculums $currentCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        foreach ([1, 2, 3, 4, 5, 6, 11, 22] as $officeId) {
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

        $priorYear = Academicyears::create([
            'yearLabel' => '2025-2026',
            'startDate' => '2025-06-01',
            'endDate' => '2026-03-31',
        ]);

        $this->term = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ]);

        $this->priorTerm = Academicterms::create([
            'academicYearId' => $priorYear->academicYearId,
            'semester' => '2nd',
            'startDate' => '2025-12-01',
            'endDate' => '2026-05-31',
        ]);

        $this->course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCS',
            'courseName' => 'Bachelor of Science in Computer Science',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);

        // Two catalog versions, so "the one the desk issued it under" is a rule rather
        // than whichever row the database happens to return first.
        $this->oldCatalog = Curriculums::create([
            'courseId' => $this->course->courseId,
            'effectiveYear' => '2025-06-01',
            'curriculumName' => 'BSCS old curriculum',
        ]);

        $this->currentCatalog = Curriculums::create([
            'courseId' => $this->course->courseId,
            'effectiveYear' => '2026-06-01',
            'curriculumName' => 'BSCS revised curriculum',
        ]);

        $this->returning = $this->student('Returnee');
        $this->fresh = $this->student('Newbie');

        $this->evaluator = $this->staffInOffice(OfficeId::Guidance->value, 'DeptEvaluator');

        // The earlier term that makes the first student a returning one at all.
        Enrollments::create([
            'studentId' => $this->returning->studentId,
            'courseId' => $this->course->courseId,
            'termId' => $this->priorTerm->termId,
            'yearLevel' => 1,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => 'new',
            'enrollmentStatus' => EnrollmentStatus::Enrolled,
            'evaluatedBy' => $this->evaluator->userId,
        ]);
    }

    private function student(string $surname): Students
    {
        return Students::create([
            'schoolIdNumber' => 'ISSUE-'.uniqid(),
            'lastName' => $surname,
            'firstName' => 'Student',
            'middleName' => 'S',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'semestersCompleted' => 1,
            'yearsInInstitution' => 1,
            'email' => 'issue_'.uniqid().'@example.com',
            'username' => 'issue_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);
    }

    private function staffInOffice(int $officeId, string $spatieRole): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => $officeId,
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-ISSUE-'.uniqid(),
            'username' => 'issue_'.$officeId.'_'.uniqid(),
            'email' => 'issue_'.$officeId.'_'.uniqid().'@example.com',
        ]);
        $staff->assignRole($spatieRole);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'studentId' => $this->returning->studentId,
            'termId' => $this->term->termId,
            'courseId' => $this->course->courseId,
            'yearLevel' => 2,
            'studentType' => StudentType::Continuing->value,
        ], $overrides);
    }

    #[Test]
    public function the_evaluation_desk_issues_the_returning_student_s_enrollment(): void
    {
        $this->actingAs($this->evaluator)
            ->post(route('evaluation.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $enrollment = Enrollments::where('studentId', $this->returning->studentId)
            ->where('termId', $this->term->termId)
            ->sole();

        $this->assertSame('pending', $enrollment->enrollmentStatus->value);
        $this->assertSame('old', $enrollment->enrollmentType->value, 'BR31: a returnee updates the record the school holds — it is not a new admission.');
        $this->assertSame('continuing', $enrollment->studentType->value);
        $this->assertSame(2, (int) $enrollment->yearLevel);
        $this->assertNull($enrollment->admissionId, 'No application is filed again for an internal continuation.');
        $this->assertSame($this->evaluator->userId, (int) $enrollment->evaluatedBy);
        $this->assertNotNull($enrollment->formIssuedDate, 'Issuing the enrollment form is what this action does.');

        // Ruling 2: the workflow form is built on creation, not left to signing.
        $workflow = Enrollmentworkflow::where('enrollmentId', $enrollment->enrollmentId)->sole();
        $boxes = Workflowsteps::where('workflowId', $workflow->workflowId)->pluck('officeId')->sort()->values()->all();

        $this->assertCount(6, $boxes, '§6.5: a returning student\'s form has six boxes — Assessment is not part of it.');
        $this->assertNotContains(OfficeId::Scholarship->value, $boxes);
        $this->assertContains(OfficeId::Registrar->value, $boxes);
    }

    #[Test]
    public function the_issued_record_carries_the_catalog_version_it_was_issued_against(): void
    {
        // Item 7: the version is what the subjects, the load band and the fee sheet are all
        // read from. Left unwritten it resolves to "the newest", which with more than one
        // version on the shelf is a decision nobody made — and an amendment to the program
        // would silently re-grade a student years after they finished.
        $this->actingAs($this->evaluator)
            ->post(route('evaluation.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $enrollment = Enrollments::where('studentId', $this->returning->studentId)
            ->where('termId', $this->term->termId)
            ->sole();

        $this->assertSame(
            $this->currentCatalog->curriculumId,
            $enrollment->curriculumId,
            'A newly issued enrollment is pinned to the current catalog version.'
        );

        // The record the desk is continuing from keeps whatever it was created under — the
        // pin is fixed at issue and is not retrofitted onto history.
        $prior = Enrollments::where('termId', $this->priorTerm->termId)->sole();
        $this->assertNull($prior->curriculumId);
    }

    #[Test]
    public function a_shifter_can_be_issued_here_too_because_that_is_where_a_shift_starts(): void
    {
        $this->actingAs($this->evaluator)
            ->post(route('evaluation.store'), $this->payload(['studentType' => StudentType::Shifter->value]))
            ->assertSessionHasNoErrors();

        $enrollment = Enrollments::where('termId', $this->term->termId)->sole();

        $this->assertSame('shifter', $enrollment->studentType->value);
        $this->assertCount(6, Workflowsteps::where('workflowId', Enrollmentworkflow::where('enrollmentId', $enrollment->enrollmentId)->value('workflowId'))->get());
    }

    #[Test]
    public function a_student_with_no_earlier_term_is_sent_to_admission_rather_than_issued(): void
    {
        $this->actingAs($this->evaluator)
            ->from(route('evaluation.index'))
            ->post(route('evaluation.store'), $this->payload(['studentId' => $this->fresh->studentId]))
            ->assertSessionHasErrors('studentId');

        $this->assertStringContainsString(
            'first enrollment is created by the Admission decision',
            session('errors')->first('studentId')
        );
        $this->assertSame(1, Enrollments::count(), 'Only the prior-term row the fixture wrote.');
    }

    #[Test]
    public function one_active_enrollment_per_student_per_term_is_enforced_at_the_creation_point(): void
    {
        $first = $this->payload();

        $this->actingAs($this->evaluator)->post(route('evaluation.store'), $first)->assertSessionHasNoErrors();

        $held = Enrollments::where('termId', $this->term->termId)->sole();
        $held->update(['enrollmentStatus' => EnrollmentStatus::Paid]);

        // A second attempt for the same seat is refused by name, and nothing is written.
        $this->actingAs($this->evaluator)
            ->from(route('evaluation.index'))
            ->post(route('evaluation.store'), $first)
            ->assertSessionHasErrors('termId');

        $this->assertStringContainsString(
            "already holds enrollment #{$held->enrollmentId}",
            session('errors')->first('termId')
        );
        $this->assertSame(2, Enrollments::count(), 'The prior-term row plus one enrollment for this term.');
    }

    #[Test]
    public function a_dropped_enrollment_releases_the_seat_so_the_student_can_be_enrolled_in_the_term_again(): void
    {
        $this->actingAs($this->evaluator)->post(route('evaluation.store'), $this->payload())->assertSessionHasNoErrors();

        $dropped = Enrollments::where('termId', $this->term->termId)->sole();
        $dropped->update([
            'enrollmentStatus' => EnrollmentStatus::Dropped,
            'dropReason' => 'Withdrew, then returned to the program.',
        ]);

        // Rulings 17 and 3 together: dropping is terminal for the row, and the term is
        // free again for the student.
        $this->actingAs($this->evaluator)->post(route('evaluation.store'), $this->payload())->assertSessionHasNoErrors();

        $this->assertSame(
            ['dropped', 'pending'],
            Enrollments::where('termId', $this->term->termId)->orderBy('enrollmentId')->pluck('enrollmentStatus')->map(fn ($s) => $s->value)->all()
        );
        $this->assertSame(
            1,
            DB::table('enrollments')
                ->where('termId', $this->term->termId)
                ->where('studentId', $this->returning->studentId)
                ->whereNotIn('enrollmentStatus', [EnrollmentStatus::Dropped->value])
                ->count()
        );
    }

    #[Test]
    public function a_desk_without_the_right_to_issue_a_form_cannot_create_the_record(): void
    {
        // A plain Staff account holds evaluation.view but not evaluation.create.
        $viewer = $this->staffInOffice(OfficeId::Guidance->value, 'Staff');
        $this->assertFalse($viewer->hasPermissionTo('evaluation.create'));

        $this->actingAs($viewer)
            ->post(route('evaluation.store'), $this->payload())
            ->assertForbidden();

        $this->assertSame(1, Enrollments::count());
    }

    #[Test]
    public function the_issue_form_is_offered_only_to_a_desk_that_may_use_it(): void
    {
        $this->actingAs($this->evaluator)
            ->get(route('evaluation.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Evaluation/Index')
                ->where('canIssueEnrollment', true)
                ->has('returningStudents', 1)
                ->has('terms')
                ->has('courses')
            );

        $this->actingAs($this->staffInOffice(OfficeId::Guidance->value, 'Staff'))
            ->get(route('evaluation.index'))
            ->assertInertia(fn ($page) => $page
                ->where('canIssueEnrollment', false)
                ->has('returningStudents', 0)
            );
    }

    #[Test]
    public function the_type_and_level_a_returning_student_cannot_be_issued_with_are_refused(): void
    {
        foreach (['studentType' => 'firstYear', 'yearLevel' => 6, 'termId' => 99999] as $field => $bad) {
            $this->actingAs($this->evaluator)
                ->post(route('evaluation.store'), $this->payload([$field => $bad]))
                ->assertSessionHasErrors($field);
        }

        // A transferee arrives through admission, so this desk does not issue them either.
        $this->actingAs($this->evaluator)
            ->post(route('evaluation.store'), $this->payload(['studentType' => 'transferee']))
            ->assertSessionHasErrors('studentType');

        $this->assertSame(1, Enrollments::count());
    }
}
