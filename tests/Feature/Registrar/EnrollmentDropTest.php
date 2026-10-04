<?php

namespace Tests\Feature\Registrar;

use App\Enums\EnrolledSubjectStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\OfficeId;
use App\Enums\StaffRole;
use App\Enums\StudentType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Enrolledsubjects;
use App\Models\Enrollments;
use App\Models\Enrollmentstatushistory;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use App\Models\Subjects;
use App\Services\EnrollmentStateMachine;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Ruling 17: an enrollment is dropped by the Registrar alone, and only with a reason.
 *
 * A drop is the one act that un-signs four desks at once — the department's evaluation,
 * Assessment's sheet, Accounting's receipt and this office's approval — so the office
 * that holds the final signature is the one that has to answer for undoing them, and the
 * record has to say why. It is also the rule that lets a student come back: dropping
 * retires the subject rows, which is what releases the seat in the term.
 */
class EnrollmentDropTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $registrar;

    private Students $student;

    private Courses $course;

    private Academicterms $term;

    private Subjects $subject;

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

        $this->subject = Subjects::create([
            'subjectCode' => 'CS101',
            'subjectName' => 'Introduction to Programming',
            'lectureUnits' => 3,
            'labUnits' => 0,
            'subjectType' => 'lecture',
        ]);

        $this->student = Students::create([
            'schoolIdNumber' => 'DROP-'.uniqid(),
            'lastName' => 'Dropout',
            'firstName' => 'Student',
            'middleName' => 'D',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'semestersCompleted' => 2,
            'yearsInInstitution' => 1,
            'email' => 'drop_'.uniqid().'@example.com',
            'username' => 'drop_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->registrar = $this->staffInOffice(OfficeId::Registrar->value, 'RegistrarApprover');
    }

    private function staffInOffice(int $officeId, string $spatieRole): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => $officeId,
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-DROP-'.uniqid(),
            'username' => 'drop_'.$officeId.'_'.uniqid(),
            'email' => 'drop_'.$officeId.'_'.uniqid().'@example.com',
        ]);
        $staff->assignRole($spatieRole);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    /**
     * A record at the point this desk is answerable for, carrying a confirmed load.
     */
    private function enrolledRecord(EnrollmentStatus $status = EnrollmentStatus::Paid): Enrollments
    {
        $enrollment = Enrollments::create([
            'studentId' => $this->student->studentId,
            'courseId' => $this->course->courseId,
            'termId' => $this->term->termId,
            'yearLevel' => 2,
            'studentType' => StudentType::Continuing,
            'enrollmentType' => EnrollmentType::Old,
            'enrollmentStatus' => $status,
            'evaluatedBy' => $this->registrar->userId,
        ]);

        Enrolledsubjects::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'subjectId' => $this->subject->subjectId,
            'status' => EnrolledSubjectStatus::Confirmed,
        ]);

        return $enrollment->fresh(['enrolledSubjects']);
    }

    #[Test]
    public function a_drop_without_a_reason_is_refused_and_the_record_stands(): void
    {
        $enrollment = $this->enrolledRecord();
        $route = route('registrar.drop', $enrollment);

        foreach (['', 'short', '         '] as $reason) {
            $this->actingAs($this->registrar)
                ->from(route('registrar.show', $enrollment))
                ->post($route, ['dropReason' => $reason])
                ->assertSessionHasErrors('dropReason');
        }

        $this->assertSame('paid', $enrollment->fresh()->enrollmentStatus->value);
        $this->assertNull($enrollment->fresh()->dropReason);
    }

    #[Test]
    public function a_dropped_record_carries_the_reason_and_the_history_row_that_pairs_it_with_who_and_when(): void
    {
        $enrollment = $this->enrolledRecord();

        $this->actingAs($this->registrar)
            ->post(route('registrar.drop', $enrollment), [
                'dropReason' => 'Student withdrew from the term; withdrawal letter on file.',
            ])
            ->assertSessionHasNoErrors();

        $enrollment->refresh();
        $this->assertSame('dropped', $enrollment->enrollmentStatus->value);
        $this->assertSame('Student withdrew from the term; withdrawal letter on file.', $enrollment->dropReason);

        // The reason the record shows and the history row that says who acted are one
        // decision written twice, not two accounts of it.
        $history = Enrollmentstatushistory::where('enrollmentId', $enrollment->enrollmentId)
            ->where('toStatus', 'dropped')
            ->sole();

        $this->assertSame($this->registrar->userId, (int) $history->changedBy);
        $this->assertStringContainsString('Student withdrew from the term', $history->remarks);
        $this->assertNotNull($history->changedAt);
    }

    #[Test]
    public function dropping_retires_the_load_so_the_seat_in_the_term_is_released(): void
    {
        $enrollment = $this->enrolledRecord();

        $this->actingAs($this->registrar)
            ->post(route('registrar.drop', $enrollment), ['dropReason' => 'Withdrew before the term began.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            [EnrolledSubjectStatus::Dropped->value],
            $enrollment->enrolledSubjects()->pluck('status')->map(fn ($status) => $status->value)->all(),
            'A dropped student must stop holding a seat in a block — Blocking counts the students who are not dropped.'
        );
    }

    #[Test]
    public function a_dropped_student_can_be_enrolled_in_the_same_term_again(): void
    {
        $dropped = $this->enrolledRecord();

        $this->actingAs($this->registrar)
            ->post(route('registrar.drop', $dropped), ['dropReason' => 'Withdrew, then changed their mind.'])
            ->assertSessionHasNoErrors();

        // Ruling 17's promise: the drop is terminal for this row, and the student comes
        // back on a new one in the very same term.
        $again = Enrollments::create([
            'studentId' => $this->student->studentId,
            'courseId' => $this->course->courseId,
            'termId' => $this->term->termId,
            'yearLevel' => 2,
            'studentType' => StudentType::Continuing,
            'enrollmentType' => EnrollmentType::Old,
            'enrollmentStatus' => EnrollmentStatus::Pending,
            'evaluatedBy' => $this->registrar->userId,
        ]);

        $this->assertNotSame($dropped->enrollmentId, $again->enrollmentId);
        $this->assertSame(
            1,
            Enrollments::where('studentId', $this->student->studentId)
                ->where('termId', $this->term->termId)
                ->whereNotIn('enrollmentStatus', [EnrollmentStatus::Dropped->value])
                ->count(),
            'Only the new record is active; the dropped one releases its seat (ruling 3).'
        );
    }

    #[Test]
    public function a_drop_is_terminal_for_the_record_that_was_dropped(): void
    {
        $enrollment = $this->enrolledRecord();

        $this->actingAs($this->registrar)
            ->post(route('registrar.drop', $enrollment), ['dropReason' => 'Withdrew from the term.'])
            ->assertSessionHasNoErrors();

        $dropped = $enrollment->fresh();

        foreach (EnrollmentStatus::cases() as $status) {
            $this->assertFalse(
                app(EnrollmentStateMachine::class)->canTransition($dropped, $status),
                'A dropped record must not be revived as '.$status->value.'.'
            );
        }

        // And it cannot be approved: the approval gate only reads assessed or paid.
        $this->actingAs($this->registrar)
            ->post(route('registrar.approve', $dropped), ['academicStanding' => 'regular'])
            ->assertForbidden();

        $this->assertSame('dropped', $dropped->fresh()->enrollmentStatus->value);
    }

    #[Test]
    public function only_the_registrar_desk_can_drop_and_only_from_the_point_it_owns_the_record(): void
    {
        $paid = $this->enrolledRecord(EnrollmentStatus::Paid);

        // The base Staff account in the Registrar office has no drop right.
        $plainStaff = $this->staffInOffice(OfficeId::Registrar->value, 'Staff');
        $this->actingAs($plainStaff)
            ->post(route('registrar.drop', $paid), ['dropReason' => 'A reason long enough to pass.'])
            ->assertForbidden();

        // A generalist office head holds enrollment.approve today but not enrollment.drop:
        // ruling 17 gives the act to this desk alone.
        $officeHead = $this->staffInOffice(OfficeId::Registrar->value, 'OfficeHead');
        $this->assertFalse($officeHead->hasPermissionTo('enrollment.drop'));
        $this->actingAs($officeHead)
            ->post(route('registrar.drop', $paid), ['dropReason' => 'A reason long enough to pass.'])
            ->assertForbidden();

        // Another office holding the permission by hand is still not this desk.
        $outsider = $this->staffInOffice(OfficeId::Accounting->value, 'RegistrarApprover');
        $this->actingAs($outsider)
            ->post(route('registrar.drop', $paid), ['dropReason' => 'A reason long enough to pass.'])
            ->assertForbidden();

        $this->assertSame('paid', $paid->fresh()->enrollmentStatus->value);
    }

    #[Test]
    public function the_desk_drops_from_the_assessed_load_upward_and_never_from_a_record_still_with_the_department(): void
    {
        foreach ([EnrollmentStatus::Pending, EnrollmentStatus::Evaluated, EnrollmentStatus::Assessed] as $status) {
            $enrollment = $this->enrolledRecord($status);

            // Assessed is the borderline the ruling leaves to this desk: it has a fee
            // sheet, so it is the Registrar's to close. Pending and Evaluated are not.
            $response = $this->actingAs($this->registrar)
                ->post(route('registrar.drop', $enrollment), ['dropReason' => 'Reason long enough to satisfy.']);

            if ($status === EnrollmentStatus::Assessed) {
                $response->assertSessionHasNoErrors();
                $this->assertSame('dropped', $enrollment->fresh()->enrollmentStatus->value);

                continue;
            }

            $response->assertForbidden();
            $this->assertSame($status->value, $enrollment->fresh()->enrollmentStatus->value);
        }
    }

    #[Test]
    public function an_enrolled_record_can_be_dropped_and_the_desk_says_why_on_the_page(): void
    {
        $enrollment = $this->enrolledRecord(EnrollmentStatus::Enrolled);

        $this->actingAs($this->registrar)
            ->post(route('registrar.drop', $enrollment), ['dropReason' => 'Credit transferred out of the program.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('dropped', $enrollment->fresh()->enrollmentStatus->value);

        $this->actingAs($this->registrar)
            ->get(route('registrar.show', $enrollment))
            ->assertInertia(fn ($page) => $page
                ->component('Registrar/Show')
                ->where('enrollment.dropReason', 'Credit transferred out of the program.')
            );
    }
}
