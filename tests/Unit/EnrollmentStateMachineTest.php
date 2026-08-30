<?php

namespace Tests\Unit;

use App\Enums\EnrollmentStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Admissions;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use App\Services\EnrollmentStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EnrollmentStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private EnrollmentStateMachine $stateMachine;

    private Enrollments $enrollment;

    private Staffusers $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stateMachine = new EnrollmentStateMachine;
        $this->staff = Staffusers::factory()->create();

        Religions::create(['religionId' => 1, 'religionName' => 'Roman Catholic']);
        Academicunits::create([
            'unitId' => 1,
            'unitName' => 'College of Computing',
            'unitType' => 'college',
        ]);
        Academicyears::create([
            'academicYearId' => 1,
            'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01',
            'endDate' => '2027-03-31',
        ]);

        $student = Students::create([
            'schoolIdNumber' => '2026-0001',
            'lastName' => 'Test',
            'firstName' => 'Student',
            'middleName' => 'M',
            'suffix' => '',
            'gender' => 'male',
            'birthdate' => '2000-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'religionId' => 1,
            'civilStatus' => 'single',
            'contactNumber' => '09123456789',
            'email' => 'test@example.com',
            'username' => 'teststudent',
            'passwordHash' => bcrypt('password'),
            'status' => 'active',
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
        ]);

        $course = Courses::create([
            'courseId' => 1,
            'unitId' => 1,
            'courseName' => 'BS Computer Science',
            'courseCode' => 'BSCS',
            'requiresEntranceExam' => true,
            'requiresRetentionExam' => true,
        ]);

        $term = Academicterms::create([
            'termId' => 1,
            'academicYearId' => 1,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ]);

        $admission = Admissions::create([
            'studentId' => $student->studentId,
            'courseId' => $course->courseId,
            'termId' => $term->termId,
            'applicantType' => 'firstYear',
            'admissionStatus' => 'approved',
        ]);

        $this->enrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $course->courseId,
            'termId' => $term->termId,
            'admissionId' => $admission->admissionId,
            'yearLevel' => 1,
            'studentType' => 'firstYear',
            'enrollmentType' => 'new',
            'academicStanding' => 'regular',
            'enrollmentStatus' => EnrollmentStatus::Pending,
            'evaluatedBy' => $this->staff->userId,
        ]);
    }

    #[Test]
    public function it_allows_valid_transition_from_pending_to_evaluated(): void
    {
        $this->stateMachine->transition(
            $this->enrollment,
            EnrollmentStatus::Evaluated,
            $this->staff,
            'Evaluation completed'
        );

        $this->assertEquals('evaluated', $this->enrollment->fresh()->enrollmentStatus->value);
    }

    #[Test]
    public function it_allows_valid_transition_from_evaluated_to_assessed(): void
    {
        $this->enrollment->update(['enrollmentStatus' => EnrollmentStatus::Evaluated]);

        $this->stateMachine->transition(
            $this->enrollment,
            EnrollmentStatus::Assessed,
            $this->staff,
            'Assessment completed'
        );

        $this->assertEquals('assessed', $this->enrollment->fresh()->enrollmentStatus->value);
    }

    #[Test]
    public function it_allows_valid_transition_from_assessed_to_paid(): void
    {
        $this->enrollment->update(['enrollmentStatus' => EnrollmentStatus::Assessed]);

        $this->stateMachine->transition(
            $this->enrollment,
            EnrollmentStatus::Paid,
            $this->staff,
            'Payment received'
        );

        $this->assertEquals('paid', $this->enrollment->fresh()->enrollmentStatus->value);
    }

    #[Test]
    public function it_allows_valid_transition_from_paid_to_enrolled(): void
    {
        $this->enrollment->update(['enrollmentStatus' => EnrollmentStatus::Paid]);

        $this->stateMachine->transition(
            $this->enrollment,
            EnrollmentStatus::Enrolled,
            $this->staff,
            'Registrar approved'
        );

        $this->assertEquals('enrolled', $this->enrollment->fresh()->enrollmentStatus->value);
    }

    #[Test]
    public function it_allows_valid_transition_from_enrolled_to_dropped(): void
    {
        $this->enrollment->update(['enrollmentStatus' => EnrollmentStatus::Enrolled]);

        $this->stateMachine->transition(
            $this->enrollment,
            EnrollmentStatus::Dropped,
            $this->staff,
            'Student dropped'
        );

        $this->assertEquals('dropped', $this->enrollment->fresh()->enrollmentStatus->value);
    }

    #[Test]
    public function it_throws_exception_for_invalid_transition(): void
    {
        $this->expectException(InvalidStateTransitionException::class);

        $this->stateMachine->transition(
            $this->enrollment,
            EnrollmentStatus::Enrolled,
            $this->staff,
            'Invalid jump'
        );
    }

    #[Test]
    public function it_throws_exception_for_transition_from_dropped(): void
    {
        $this->enrollment->update(['enrollmentStatus' => EnrollmentStatus::Dropped]);

        $this->expectException(InvalidStateTransitionException::class);

        $this->stateMachine->transition(
            $this->enrollment,
            EnrollmentStatus::Enrolled,
            $this->staff,
            'Cannot re-enroll dropped'
        );
    }

    #[Test]
    public function it_creates_status_history_on_transition(): void
    {
        $this->stateMachine->transition(
            $this->enrollment,
            EnrollmentStatus::Evaluated,
            $this->staff,
            'Evaluation completed'
        );

        $history = $this->enrollment->enrollmentstatushistory()->first();

        $this->assertNotNull($history);
        $this->assertEquals('pending', $history->fromStatus);
        $this->assertEquals('evaluated', $history->toStatus);
        $this->assertEquals($this->staff->userId, $history->changedBy);
        $this->assertEquals('Evaluation completed', $history->remarks);
    }

    #[Test]
    public function it_returns_allowed_transitions(): void
    {
        $allowed = $this->stateMachine->allowedTransitions($this->enrollment);
        $this->assertEquals(['evaluated'], $allowed);

        $this->enrollment->update(['enrollmentStatus' => EnrollmentStatus::Enrolled]);
        $allowed = $this->stateMachine->allowedTransitions($this->enrollment);
        $this->assertEquals(['dropped'], $allowed);

        $this->enrollment->update(['enrollmentStatus' => EnrollmentStatus::Dropped]);
        $allowed = $this->stateMachine->allowedTransitions($this->enrollment);
        $this->assertEquals([], $allowed);
    }

    #[Test]
    public function it_correctly_checks_can_transition(): void
    {
        $this->assertTrue($this->stateMachine->canTransition($this->enrollment, EnrollmentStatus::Evaluated));
        $this->assertFalse($this->stateMachine->canTransition($this->enrollment, EnrollmentStatus::Enrolled));
        $this->assertFalse($this->stateMachine->canTransition($this->enrollment, EnrollmentStatus::Dropped));
    }
}
