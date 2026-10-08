<?php

namespace Tests\Feature\Admission;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Enums\ExamType;
use App\Enums\StudentType;
use App\Enums\UnitType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Admissionrequirements;
use App\Models\Admissions;
use App\Models\Courses;
use App\Models\Curriculums;
use App\Models\Enrollments;
use App\Models\Examresults;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Studentrequirementsubmissions;
use App\Models\Students;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Concern #9: the admission approval gate.
 *
 * The gate itself was already correct — an application cannot be approved while
 * a required document is unverified or a board course has no passing General
 * Entrance Exam result. What it did not do is say so: every refusal came back as
 * the same bare 403, so the officer at the desk had to click and hope. These
 * tests pin both halves — the gate still refuses, and the desk now names the
 * exact document or exam standing in the way.
 */
class ApprovalReadinessTest extends TestCase
{
    use DatabaseTransactions;

    private int $boardCourseId;

    private int $termId;

    private int $curriculumId;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite']);

        Offices::insert([
            ['officeId' => 1, 'officeName' => 'Registrar'],
            ['officeId' => 4, 'officeName' => 'Department Evaluation'],
            ['officeId' => 6, 'officeName' => 'Admission Office'],
            ['officeId' => 7, 'officeName' => 'Guidance Office'],
            ['officeId' => 11, 'officeName' => 'Clinic'],
            ['officeId' => 22, 'officeName' => 'ID Office'],
        ]);

        Religions::create(['religionId' => 1, 'religionName' => 'Roman Catholic']);

        $unit = Academicunits::create([
            'unitCode' => 'CCS',
            'unitName' => 'College of Computer Studies',
            'unitType' => UnitType::College,
        ]);

        $boardCourse = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCS',
            'courseName' => 'Bachelor of Science in Computer Science',
            'requiresEntranceExam' => true,
            'requiresRetentionExam' => false,
        ]);

        $academicYear = Academicyears::create([
            'yearStart' => 2026,
            'yearEnd' => 2027,
            'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01',
            'endDate' => '2027-05-31',
        ]);
        $term = Academicterms::create([
            'academicYearId' => $academicYear->academicYearId,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ]);

        $this->boardCourseId = (int) $boardCourse->courseId;
        $this->termId = (int) $term->termId;

        // The catalog version an approved applicant's enrollment is pinned to (item 7).
        $this->curriculumId = (int) Curriculums::create([
            'courseId' => $boardCourse->courseId,
            'effectiveYear' => '2026-06-01',
            'curriculumName' => 'BSCS 2026 curriculum',
        ])->curriculumId;

        Admissionrequirements::create([
            'requirementName' => 'Good Moral Certificate',
            'appliesTo' => 'firstYear',
            'isRequired' => true,
        ]);

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RbacSeeder', '--database' => 'sqlite']);
    }

    private function staffWithRole(string $spatieRole, int $officeId): Staffusers
    {
        $staff = Staffusers::factory()->make([
            'officeId' => $officeId,
            'role' => 'staff',
            'employeeNo' => 'EMP-READY-'.uniqid(),
            'username' => 'ready_'.strtolower($spatieRole).'_'.uniqid(),
            'email' => 'ready_'.strtolower($spatieRole).'_'.uniqid().'@example.com',
        ]);
        unset($staff->remember_token);
        $staff->save();

        $staff->assignRole($spatieRole);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    private function createStudent(): Students
    {
        return Students::create([
            'schoolIdNumber' => 'READY-'.uniqid(),
            'lastName' => 'Applicant',
            'firstName' => 'Test',
            'middleName' => 'A',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'email' => 'ready_student_'.uniqid().'@example.com',
            'username' => 'ready_student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);
    }

    private function createAdmission(Students $student): Admissions
    {
        return Admissions::create([
            'studentId' => $student->studentId,
            'courseId' => $this->boardCourseId,
            'termId' => $this->termId,
            'applicantType' => 'firstYear',
            'admissionStatus' => 'pending',
        ]);
    }

    private function requirementSubmission(Admissions $admission, string $status): Studentrequirementsubmissions
    {
        return Studentrequirementsubmissions::create([
            'admissionId' => $admission->admissionId,
            'requirementId' => Admissionrequirements::value('requirementId'),
            'submissionStatus' => $status,
            'submittedDate' => now(),
            'remarks' => '',
        ]);
    }

    private function generalExam(Students $student, string $result): void
    {
        Examresults::create([
            'studentId' => $student->studentId,
            'courseId' => $this->boardCourseId,
            'termId' => $this->termId,
            'examStage' => ExamStage::Entrance,
            'examType' => ExamType::General,
            'examResult' => ExamResult::from($result),
            'examDate' => now(),
        ]);
    }

    #[Test]
    public function the_desk_is_told_which_required_document_is_not_verified(): void
    {
        $desk = $this->staffWithRole('AdmissionOfficer', 6);
        $student = $this->createStudent();
        $admission = $this->createAdmission($student);
        $submission = $this->requirementSubmission($admission, 'submitted');

        $this->actingAs($desk)
            ->get(route('admission.show', $admission))
            ->assertInertia(fn ($page) => $page
                ->where('approvalBlockers.0', 'Required document "Good Moral Certificate" is submitted, not verified.')
            );

        // The gate still refuses — the wording only moved, the rule did not.
        $this->actingAs($desk)
            ->post(route('admission.approve', $admission))
            ->assertForbidden();

        $this->assertSame('pending', $admission->fresh()->admissionStatus->value);

        $this->actingAs($desk)
            ->post(route('admission.requirements.verify', ['admission' => $admission->admissionId, 'requirement' => $submission->requirementId]), [
                'approved' => true,
                'remarks' => 'Verified against the issued original.',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($desk)
            ->get(route('admission.show', $admission))
            ->assertInertia(fn ($page) => $page->has('approvalBlockers', 1))
            ->assertInertia(fn ($page) => $page
                ->where('approvalBlockers.0', 'No General Entrance Exam result on record for this applicant to '.$this->boardCourseCode().' for this term.')
            );
    }

    #[Test]
    public function a_failing_general_exam_is_named_as_the_blocker(): void
    {
        $desk = $this->staffWithRole('AdmissionOfficer', 6);
        $student = $this->createStudent();
        $admission = $this->createAdmission($student);
        $this->requirementSubmission($admission, 'verified');
        $this->generalExam($student, 'fail');

        $this->actingAs($desk)
            ->get(route('admission.show', $admission))
            ->assertInertia(fn ($page) => $page
                ->where('approvalBlockers.0', 'The General Entrance Exam result on file is fail, not pass.')
            );

        $this->actingAs($desk)
            ->post(route('admission.approve', $admission))
            ->assertForbidden();
    }

    #[Test]
    public function approval_opens_and_goes_through_once_the_documents_and_the_exam_clear(): void
    {
        $desk = $this->staffWithRole('AdmissionOfficer', 6);
        $student = $this->createStudent();
        $admission = $this->createAdmission($student);
        $this->requirementSubmission($admission, 'verified');
        $this->generalExam($student, 'pass');

        $this->actingAs($desk)
            ->get(route('admission.show', $admission))
            ->assertInertia(fn ($page) => $page->has('approvalBlockers', 0));

        $this->actingAs($desk)
            ->post(route('admission.approve', $admission))
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $admission->fresh()->admissionStatus->value);
        $this->assertDatabaseHas('enrollments', [
            'studentId' => $student->studentId,
            'admissionId' => $admission->admissionId,
            // Item 7: the enrollment is pinned to the catalog the applicant is admitted
            // into, so the load band and the fee sheet stay readable after the program is
            // amended. Unpinned, every later lookup falls through to "the newest one".
            'curriculumId' => $this->curriculumId,
        ]);
    }

    #[Test]
    public function an_already_decided_application_is_named_by_its_status(): void
    {
        $desk = $this->staffWithRole('AdmissionOfficer', 6);
        $student = $this->createStudent();
        $admission = $this->createAdmission($student);
        $admission->update(['admissionStatus' => 'rejected']);

        $this->actingAs($desk)
            ->get(route('admission.show', $admission))
            ->assertInertia(fn ($page) => $page
                ->where('approvalBlockers.0', 'Only a pending application can be approved — this one is rejected.')
            );
    }

    #[Test]
    public function a_program_that_also_examines_the_department_refuses_an_applicant_who_never_sat_it(): void
    {
        // §28 G-6/C-5: the departmental examination used to be checked only "if
        // one was administered", so the applicant who simply never sat it was
        // admitted on the strength of the general result alone.
        $desk = $this->staffWithRole('AdmissionOfficer', 6);
        $student = $this->createStudent();
        $admission = $this->createAdmission($student);
        $this->requirementSubmission($admission, 'verified');
        $this->generalExam($student, 'pass');

        Courses::where('courseId', $this->boardCourseId)->update(['requiresCourseSpecificExam' => true]);
        $admission->unsetRelation('course');

        $this->actingAs($desk)
            ->post(route('admission.approve', $admission))
            ->assertForbidden();

        $this->actingAs($desk)
            ->get(route('admission.show', $admission))
            ->assertInertia(fn ($page) => $page
                ->where('approvalBlockers.0', 'No Course-Specific Entrance Exam result on record for this applicant, and '.$this->boardCourseCode().' requires one.')
            );

        Examresults::create([
            'studentId' => $student->studentId,
            'courseId' => $this->boardCourseId,
            'termId' => $this->termId,
            'examStage' => ExamStage::Entrance,
            'examType' => ExamType::CourseSpecific,
            'examResult' => ExamResult::Pass,
            'examDate' => now(),
        ]);

        $this->actingAs($desk)
            ->get(route('admission.show', $admission))
            ->assertInertia(fn ($page) => $page->has('approvalBlockers', 0));

        $this->actingAs($desk)
            ->post(route('admission.approve', $admission))
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $admission->fresh()->admissionStatus->value);
    }

    #[Test]
    public function a_program_whose_only_paper_is_the_departmental_one_can_be_satisfied(): void
    {
        // C-5's shape on the live install: BSA, BSCE and BSEE require their board's
        // examination and administer no School Entrance paper at all. The blockers must
        // ask for the examination a program actually runs, and the one result the
        // department can produce must be enough to clear them.
        $desk = $this->staffWithRole('AdmissionOfficer', 6);
        $boardOnly = Courses::create([
            'unitId' => Academicunits::value('unitId'),
            'courseCode' => 'BSCE',
            'courseName' => 'Bachelor of Science in Civil Engineering',
            'requiresEntranceExam' => false,
            'requiresCourseSpecificExam' => true,
            'requiresRetentionExam' => false,
        ]);
        Curriculums::create([
            'courseId' => $boardOnly->courseId,
            'effectiveYear' => '2026-06-01',
            'curriculumName' => 'BSCE 2026 curriculum',
        ]);

        $student = $this->createStudent();
        $admission = Admissions::create([
            'studentId' => $student->studentId,
            'courseId' => $boardOnly->courseId,
            'termId' => $this->termId,
            'applicantType' => 'firstYear',
            'admissionStatus' => 'pending',
        ]);
        $this->requirementSubmission($admission, 'verified');

        $this->actingAs($desk)
            ->post(route('admission.approve', $admission))
            ->assertForbidden();

        $this->actingAs($desk)
            ->get(route('admission.show', $admission))
            ->assertInertia(fn ($page) => $page
                ->where('approvalBlockers.0',
                    'No Course-Specific Entrance Exam result on record for this applicant, and BSCE requires one.')
            );

        Examresults::create([
            'studentId' => $student->studentId,
            'courseId' => $boardOnly->courseId,
            'termId' => $this->termId,
            'examStage' => ExamStage::Entrance,
            'examType' => ExamType::CourseSpecific,
            'examResult' => ExamResult::Pass,
            'examDate' => now(),
        ]);

        $this->actingAs($desk)
            ->post(route('admission.approve', $admission))
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $admission->fresh()->admissionStatus->value);
    }

    #[Test]
    public function approving_does_not_create_a_second_active_enrollment_in_the_same_term(): void
    {
        // Ruling 3 (G-4): the seat is per student per term, not per application. The
        // lookup used to be by admissionId, so a student who already held an active
        // enrollment — issued by Department Evaluation under ruling 2, or by an earlier
        // application — was given a second one for the same seat, and both read as live.
        $desk = $this->staffWithRole('AdmissionOfficer', 6);
        $student = $this->createStudent();
        $admission = $this->createAdmission($student);
        $this->requirementSubmission($admission, 'verified');
        $this->generalExam($student, 'pass');

        $held = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $this->boardCourseId,
            'termId' => $this->termId,
            'yearLevel' => 1,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => 'new',
            'enrollmentStatus' => EnrollmentStatus::Pending,
            'evaluatedBy' => $desk->userId,
        ]);

        $this->actingAs($desk)
            ->post(route('admission.approve', $admission))
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $admission->fresh()->admissionStatus->value);
        $this->assertSame(1, Enrollments::where('studentId', $student->studentId)->where('termId', $this->termId)->count());
        $this->assertStringContainsString(
            "already holds enrollment #{$held->enrollmentId}",
            session('success')
        );
    }

    #[Test]
    public function a_dropped_enrollment_does_not_hold_the_seat_when_the_application_is_approved(): void
    {
        $desk = $this->staffWithRole('AdmissionOfficer', 6);
        $student = $this->createStudent();
        $admission = $this->createAdmission($student);
        $this->requirementSubmission($admission, 'verified');
        $this->generalExam($student, 'pass');

        Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $this->boardCourseId,
            'termId' => $this->termId,
            'yearLevel' => 1,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => 'new',
            'enrollmentStatus' => EnrollmentStatus::Dropped,
            'dropReason' => 'Withdrew before the term began.',
            'evaluatedBy' => $desk->userId,
        ]);

        $this->actingAs($desk)
            ->post(route('admission.approve', $admission))
            ->assertSessionHasNoErrors();

        // Ruling 17 releases the seat, so the approval here is the student coming back —
        // a fresh row, tied to this admission, not the closed one.
        $this->assertSame(2, Enrollments::where('studentId', $student->studentId)->where('termId', $this->termId)->count());
        $this->assertDatabaseHas('enrollments', [
            'studentId' => $student->studentId,
            'admissionId' => $admission->admissionId,
            'enrollmentStatus' => EnrollmentStatus::Pending->value,
        ]);
    }

    /**
     * An applicant arriving from another institution, for whom the program has
     * granted the §28.2 C-4 waiver.
     */
    private function transfereeAdmission(Students $student): Admissions
    {
        return Admissions::create([
            'studentId' => $student->studentId,
            'courseId' => $this->boardCourseId,
            'termId' => $this->termId,
            'applicantType' => 'transferee',
            'admissionStatus' => 'pending',
        ]);
    }

    #[Test]
    public function a_program_may_waive_the_general_exam_for_an_applicant_arriving_with_credit(): void
    {
        // §28.2 C-4, ruled 2026-10-07: the waiver is the program's to give. Where it
        // gives one, an arriving student is approved on their documents alone and the
        // enrollment still issues Irregular, because the type decides that (C-2).
        $desk = $this->staffWithRole('AdmissionOfficer', 6);
        $student = $this->createStudent();
        $admission = $this->transfereeAdmission($student);
        $this->requirementSubmission($admission, 'verified');

        Courses::where('courseId', $this->boardCourseId)->update([
            'requiresEntranceExam' => true,
            'entranceExamExemptsTransferee' => true,
        ]);

        $this->actingAs($desk)
            ->post(route('admission.approve', $admission))
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $admission->fresh()->admissionStatus->value);
        $this->assertDatabaseHas('enrollments', [
            'studentId' => $student->studentId,
            'admissionId' => $admission->admissionId,
            'enrollmentStatus' => EnrollmentStatus::Pending->value,
            'academicStanding' => 'irregular',
        ]);
    }

    #[Test]
    public function the_waiver_reaches_only_the_applicants_it_names(): void
    {
        // A first-year applying to the same program is unaffected: the exemption is
        // scoped to the applicant type, not to the program's examination as a whole.
        $desk = $this->staffWithRole('AdmissionOfficer', 6);
        $student = $this->createStudent();
        $admission = $this->createAdmission($student);
        $this->requirementSubmission($admission, 'verified');

        Courses::where('courseId', $this->boardCourseId)->update([
            'requiresEntranceExam' => true,
            'entranceExamExemptsTransferee' => true,
        ]);

        $this->actingAs($desk)
            ->post(route('admission.approve', $admission))
            ->assertForbidden();

        $this->actingAs($desk)
            ->get(route('admission.show', $admission))
            ->assertInertia(fn ($page) => $page
                ->where('approvalBlockers.0', 'No General Entrance Exam result on record for this applicant to '.$this->boardCourseCode().' for this term.')
            );
    }

    #[Test]
    public function a_program_that_grants_no_waiver_still_demands_the_general_exam_of_a_transferee(): void
    {
        // The column defaults false, so every program behaves exactly as it did
        // before the flag existed — this is the half that keeps the upgrade harmless.
        $desk = $this->staffWithRole('AdmissionOfficer', 6);
        $student = $this->createStudent();
        $admission = $this->transfereeAdmission($student);
        $this->requirementSubmission($admission, 'verified');

        $this->assertFalse(
            (bool) Courses::where('courseId', $this->boardCourseId)->value('entranceExamExemptsTransferee')
        );

        $this->actingAs($desk)
            ->post(route('admission.approve', $admission))
            ->assertForbidden();
    }

    #[Test]
    public function the_waiver_does_not_reach_the_departmental_examination(): void
    {
        // The program's own screen still screens. It also proves the flag ruling
        // C-5 put on the board programs does fire: with no course-specific result
        // on record the applicant is refused by name.
        $desk = $this->staffWithRole('AdmissionOfficer', 6);
        $student = $this->createStudent();
        $admission = $this->transfereeAdmission($student);
        $this->requirementSubmission($admission, 'verified');

        Courses::where('courseId', $this->boardCourseId)->update([
            'requiresEntranceExam' => true,
            'entranceExamExemptsTransferee' => true,
            'requiresCourseSpecificExam' => true,
        ]);

        $this->actingAs($desk)
            ->post(route('admission.approve', $admission))
            ->assertForbidden();

        $this->actingAs($desk)
            ->get(route('admission.show', $admission))
            ->assertInertia(fn ($page) => $page
                ->where('approvalBlockers.0', 'No Course-Specific Entrance Exam result on record for this applicant, and '
                    .$this->boardCourseCode().' requires one.')
            );
    }

    #[Test]
    public function an_examination_pass_answers_only_for_the_program_it_was_taken_under(): void
    {
        // Ruled 2026-10-08. examresults stores a courseId and a termId, but the
        // blockers joined on studentId alone, so a general pass earned against one
        // program cleared an application to another. The desk that approved on it
        // had never seen the paper it was relying on.
        $desk = $this->staffWithRole('AdmissionOfficer', 6);
        $other = Courses::create([
            'unitId' => Academicunits::value('unitId'),
            'courseCode' => 'BSAB',
            'courseName' => 'Bachelor of Science in Abstract Business',
            'requiresEntranceExam' => true,
            'requiresCourseSpecificExam' => false,
            'requiresRetentionExam' => false,
        ]);

        $student = $this->createStudent();
        $admission = $this->createAdmission($student);
        $this->requirementSubmission($admission, 'verified');

        Examresults::create([
            'studentId' => $student->studentId,
            'courseId' => $other->courseId,
            'termId' => $this->termId,
            'examStage' => ExamStage::Entrance,
            'examType' => ExamType::General,
            'examResult' => ExamResult::Pass,
            'examDate' => now(),
        ]);

        $this->actingAs($desk)
            ->post(route('admission.approve', $admission))
            ->assertForbidden();

        $this->actingAs($desk)
            ->get(route('admission.show', $admission))
            ->assertInertia(fn ($page) => $page
                ->where('approvalBlockers.0',
                    'No General Entrance Exam result on record for this applicant to '.$this->boardCourseCode().' for this term.')
            );

        // The same student and the same paper, this program's own row: now it answers.
        $this->generalExam($student, 'pass');

        $this->actingAs($desk)
            ->post(route('admission.approve', $admission))
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $admission->fresh()->admissionStatus->value);
    }

    private function boardCourseCode(): string
    {
        return Courses::where('courseId', $this->boardCourseId)->value('courseCode');
    }
}
