<?php

namespace Tests\Feature\Evaluation;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Enums\ExamType;
use App\Enums\OfficeId;
use App\Enums\StaffRole;
use App\Enums\StudentType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Examresults;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Concern #14, verbatim: "other students continuing takes the retention exam as a
 * proof that they really learned anything before proceeding to higher year level".
 *
 * The result was recorded, displayed and permission-gated — and read by nothing.
 * A continuing student in a program that examines retention could be forwarded to
 * the six desks behind Department Evaluation with a fail on file, or with no
 * examination at all, exactly like one who had passed. These tests pin the gate
 * where the owner put it: at the signature that forwards the form.
 */
class RetentionGateTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $evaluator;

    private Students $student;

    private int $termId;

    private int $otherTermId;

    private int $courseId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        foreach ([1, 2, 3, 4, 5, 6, 11, 22] as $officeId) {
            Offices::firstOrCreate(['officeId' => $officeId], ['officeName' => 'Office '.$officeId]);
        }

        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        $unit = Academicunits::create(['unitName' => 'College of Computer Studies', 'unitType' => 'college']);

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
        $otherTerm = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '2nd',
            'startDate' => '2026-11-01',
            'endDate' => '2027-03-31',
        ]);

        // A board program: it examines retention, which is what arms the gate.
        $course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCRIM',
            'courseName' => 'Bachelor of Science in Criminology',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => true,
        ]);

        $this->termId = $term->termId;
        $this->otherTermId = $otherTerm->termId;
        $this->courseId = $course->courseId;

        $this->student = Students::create([
            'schoolIdNumber' => 'RET-'.uniqid(),
            'lastName' => 'Retainer',
            'firstName' => 'Sam',
            'middleName' => 'Q',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2003-01-01',
            'birthplace' => 'Biñan, Laguna',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'semestersCompleted' => 4,
            'yearsInInstitution' => 2,
            'email' => 'ret_'.uniqid().'@example.com',
            'username' => 'ret_student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->evaluator = $this->staffInOffice(OfficeId::Guidance->value, 'DeptEvaluator');
    }

    private function staffInOffice(int $officeId, string $spatieRole): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => $officeId,
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-RET-'.uniqid(),
            'username' => 'ret_'.$officeId.'_'.uniqid(),
            'email' => 'ret_'.$officeId.'_'.uniqid().'@example.com',
        ]);
        $staff->assignRole($spatieRole);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    /**
     * An evaluated, fully captured enrollment — so the only thing that can refuse
     * the signature is the retention examination under test.
     */
    private function readyEnrollment(): Enrollments
    {
        $enrollment = Enrollments::create([
            'studentId' => $this->student->studentId,
            'courseId' => $this->courseId,
            'termId' => $this->termId,
            'yearLevel' => 3,
            'studentType' => StudentType::Continuing,
            'enrollmentType' => EnrollmentType::Old,
            'enrollmentStatus' => EnrollmentStatus::Evaluated,
            'academicStanding' => 'regular',
            'formIssuedDate' => now()->toDateString(),
            'evaluatedBy' => $this->evaluator->userId,
        ]);

        $this->actingAs($this->evaluator)
            ->put(route('evaluation.profile.capture', $enrollment), $this->completeProfile())
            ->assertSessionHasNoErrors();

        return $enrollment->fresh();
    }

    private function completeProfile(): array
    {
        return [
            'lastName' => 'Retainer',
            'firstName' => 'Sam',
            'middleName' => 'Q',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2003-01-01',
            'birthplace' => 'Biñan, Laguna',
            'citizenship' => 'Filipino',
            'religionId' => 1,
            'civilStatus' => 'single',
            'contactNumber' => '09171234567',
            'telephoneNumber' => null,
            'email' => 'ret_'.uniqid().'@example.com',
            'addresses' => [
                ['addressType' => 'home', 'houseBuildingNo' => '12', 'street' => 'Rizal St', 'sitioPurok' => '', 'barangay' => 'Poblacion', 'cityMunicipality' => 'Biñan', 'district' => '', 'province' => 'Laguna', 'region' => 'Region IV-A', 'zipCode' => '4024', 'country' => 'Philippines'],
                ['addressType' => 'current', 'houseBuildingNo' => '4', 'street' => 'Mabini St', 'sitioPurok' => '', 'barangay' => 'San Jose', 'cityMunicipality' => 'Santa Rosa', 'district' => '', 'province' => 'Laguna', 'region' => 'Region IV-A', 'zipCode' => '4026', 'country' => 'Philippines'],
            ],
            'guardians' => [
                ['relationship' => 'father', 'fullName' => 'Ana Retainer', 'contactNumber' => '09171234568', 'email' => 'ana@example.com', 'isEmergencyContact' => true, 'isAuthorizedToActOnBehalf' => true],
            ],
            'semestersCompleted' => 4,
            'yearsInInstitution' => 2,
            'academicStanding' => 'regular',
            'formIssuedDate' => now()->toDateString(),
        ];
    }

    private function recordRetention(string $result, ?int $termId = null): void
    {
        Examresults::create([
            'studentId' => $this->student->studentId,
            'courseId' => $this->courseId,
            'termId' => $termId ?? $this->termId,
            'examStage' => ExamStage::Retention,
            'examType' => ExamType::CourseSpecific,
            'examResult' => ExamResult::from($result),
            'examDate' => now(),
        ]);
    }

    #[Test]
    public function a_continuing_student_with_no_retention_examination_cannot_be_forwarded(): void
    {
        $enrollment = $this->readyEnrollment();

        $this->actingAs($this->evaluator)
            ->post(route('evaluation.sign', $enrollment))
            ->assertSessionHasErrors('retention');

        $enrollment->refresh();
        $this->assertNull($enrollment->formSignedDate, 'A refused signature must not stamp the form.');
        $this->assertNull($enrollment->enrollmentworkflow, 'A refused signature must not open the workflow.');
        $this->assertEquals(EnrollmentStatus::Evaluated, $enrollment->enrollmentStatus);
    }

    #[Test]
    public function the_desk_is_told_which_examination_is_missing_before_it_clicks(): void
    {
        $enrollment = $this->readyEnrollment();

        $this->actingAs($this->evaluator)
            ->get(route('evaluation.show', $enrollment))
            ->assertInertia(fn ($page) => $page
                ->has('profileGaps', 0)
                ->where('signBlockers.retention', 'No retention examination recorded for this term — a returning student in a program that examines retention must pass it before the form is forwarded.')
            );
    }

    #[Test]
    public function a_failed_retention_result_keeps_the_form_at_the_desk(): void
    {
        $enrollment = $this->readyEnrollment();
        $this->recordRetention('fail');

        $this->actingAs($this->evaluator)
            ->post(route('evaluation.sign', $enrollment))
            ->assertSessionHasErrors('retention');

        $this->assertNull($enrollment->fresh()->formSignedDate);

        $this->actingAs($this->evaluator)
            ->get(route('evaluation.show', $enrollment))
            ->assertInertia(fn ($page) => $page
                ->where('signBlockers.retention', 'The retention examination on file reads fail, not pass.')
            );
    }

    #[Test]
    public function a_passing_retention_result_for_this_term_lets_the_department_sign(): void
    {
        $enrollment = $this->readyEnrollment();
        $this->recordRetention('pass');

        $this->actingAs($this->evaluator)
            ->post(route('evaluation.sign', $enrollment))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($enrollment->fresh()->formSignedDate);
        $this->assertNotNull($enrollment->fresh()->enrollmentworkflow);
    }

    #[Test]
    public function a_pass_from_an_earlier_term_is_not_proof_for_this_one(): void
    {
        $enrollment = $this->readyEnrollment();
        $this->recordRetention('pass', $this->otherTermId);

        $this->actingAs($this->evaluator)
            ->post(route('evaluation.sign', $enrollment))
            ->assertSessionHasErrors('retention');

        $this->assertNull($enrollment->fresh()->formSignedDate);
    }

    #[Test]
    public function a_first_year_student_is_not_asked_to_retain_anything(): void
    {
        // The examination proves fitness to continue; a first-year has nothing yet
        // to retain, and gating them would strand every new applicant.
        $enrollment = Enrollments::create([
            'studentId' => $this->student->studentId,
            'courseId' => $this->courseId,
            'termId' => $this->termId,
            'yearLevel' => 1,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => EnrollmentType::New,
            'enrollmentStatus' => EnrollmentStatus::Evaluated,
            'academicStanding' => 'regular',
            'formIssuedDate' => now()->toDateString(),
            'evaluatedBy' => $this->evaluator->userId,
        ]);

        $this->actingAs($this->evaluator)
            ->put(route('evaluation.profile.capture', $enrollment), $this->completeProfile())
            ->assertSessionHasNoErrors();

        $this->actingAs($this->evaluator)
            ->post(route('evaluation.sign', $enrollment->fresh()))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($enrollment->fresh()->formSignedDate);
    }
}
