<?php

namespace Tests\Feature\Files;

use App\Enums\AdmissionStatus;
use App\Enums\ApplicantType;
use App\Enums\EnrollmentStatus;
use App\Enums\IdRequestReason;
use App\Enums\IdRequestStatus;
use App\Enums\StudentType;
use App\Enums\SubmissionStatus;
use App\Enums\UnitType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Admissionrequirements;
use App\Models\Admissions;
use App\Models\Courses;
use App\Models\Documents;
use App\Models\Enrollments;
use App\Models\Idrequests;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Studentrequirementsubmissions;
use App\Models\Students;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Uploaded papers live on the named private documents disk, which no URL reaches.
 * These tests pin the two routes that answer on the owning module's policy instead, so
 * a desk can open what an applicant or an ID operator actually uploaded.
 */
class StoredFileAccessTest extends TestCase
{
    private int $courseId;

    private int $termId;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite']);
        $this->seedReferenceData();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RbacSeeder', '--database' => 'sqlite']);

        Storage::fake('documents');
    }

    private function seedReferenceData(): void
    {
        Offices::insert([
            ['officeId' => 1, 'officeName' => 'Registrar'],
            ['officeId' => 2, 'officeName' => 'Accounting'],
            ['officeId' => 3, 'officeName' => 'Assessment'],
            ['officeId' => 4, 'officeName' => 'Academic Department'],
            ['officeId' => 5, 'officeName' => 'Blocking'],
            ['officeId' => 6, 'officeName' => 'Admission'],
            ['officeId' => 22, 'officeName' => 'ID Office'],
        ]);

        Religions::create(['religionId' => 1, 'religionName' => 'Roman Catholic']);

        $unit = Academicunits::create([
            'unitCode' => 'CCS',
            'unitName' => 'College of Computer Studies',
            'unitType' => UnitType::College,
        ]);

        $this->courseId = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCS',
            'courseName' => 'Bachelor of Science in Computer Science',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ])->courseId;

        $year = Academicyears::create([
            'yearStart' => 2024,
            'yearEnd' => 2025,
            'yearLabel' => '2024-2025',
            'startDate' => '2024-06-01',
            'endDate' => '2025-05-31',
        ]);

        $this->termId = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2024-06-01',
            'endDate' => '2024-10-31',
        ])->termId;
    }

    private function staffInOffice(int $officeId, ?string $role): Staffusers
    {
        $staff = Staffusers::factory()->make([
            'officeId' => $officeId,
            'role' => 'staff',
            'employeeNo' => 'EMP-'.uniqid(),
            'username' => 'user_'.uniqid(),
            'email' => 'user_'.uniqid().'@example.com',
        ]);
        unset($staff->remember_token);
        $staff->save();

        if ($role !== null) {
            $staff->assignRole($role);
        }

        return $staff;
    }

    private function makeStudent(): Students
    {
        return Students::create([
            'schoolIdNumber' => 'TEST-'.uniqid(),
            'lastName' => 'Dela Cruz',
            'firstName' => 'Juan',
            'middleName' => 'M',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'email' => 'student_'.uniqid().'@example.com',
            'username' => 'student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);
    }

    /**
     * An admission with one requirement submitted and one paper stored on disk.
     *
     * @return array{Admissions, Studentrequirementsubmissions, Documents, string}
     */
    private function makeAdmissionWithDocument(string $contents = 'PDF-BYTES'): array
    {
        $admission = Admissions::create([
            'studentId' => $this->makeStudent()->studentId,
            'termId' => $this->termId,
            'courseId' => $this->courseId,
            'applicantType' => ApplicantType::FirstYear,
            'admissionStatus' => AdmissionStatus::Pending,
        ]);

        $requirement = Admissionrequirements::create([
            'requirementName' => 'Form 138',
            'appliesTo' => 'firstYear',
            'isRequired' => true,
        ]);

        $submission = Studentrequirementsubmissions::create([
            'admissionId' => $admission->admissionId,
            'requirementId' => $requirement->requirementId,
            'submissionStatus' => SubmissionStatus::Submitted,
            'submittedDate' => now()->toDateString(),
            'remarks' => 'Scanned copy uploaded at intake.',
        ]);

        $path = 'admission-documents/form138.pdf';
        Storage::disk('documents')->put($path, $contents);

        $document = Documents::create([
            'submissionId' => $submission->submissionId,
            'fileUrl' => $path,
            'fileType' => 'application/pdf',
            'uploadedDate' => now()->toDateString(),
        ]);

        return [$admission, $submission, $document, $path];
    }

    private function makeIdRequest(?string $photoPath): Idrequests
    {
        $student = $this->makeStudent();

        $evaluator = Staffusers::where('officeId', 4)->first() ?? $this->staffInOffice(4, null);

        $enrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $this->courseId,
            'termId' => $this->termId,
            'yearLevel' => 1,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => 'new',
            'academicStanding' => 'regular',
            'enrollmentStatus' => EnrollmentStatus::Enrolled,
            'evaluatedBy' => $evaluator->userId,
        ]);

        return Idrequests::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'requestReason' => IdRequestReason::NewStudent,
            'emergencyContactName' => 'Emergency Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
            'cardPhotoPath' => $photoPath,
            'requestDate' => now(),
            'status' => IdRequestStatus::Pending,
        ]);
    }

    #[Test]
    public function the_admission_desk_can_open_an_uploaded_requirement_document(): void
    {
        [, , $document, $path] = $this->makeAdmissionWithDocument('THE-SEALED-PDF');
        $this->actingAs($this->staffInOffice(6, 'AdmissionOfficer'));

        $response = $this->get(route('admission.documents.show', $document));

        $response->assertOk();
        $this->assertEquals('THE-SEALED-PDF', $response->streamedContent());
        Storage::disk('documents')->assertExists($path);
    }

    #[Test]
    public function a_document_whose_file_is_gone_reports_404_rather_than_500(): void
    {
        [, , $document] = $this->makeAdmissionWithDocument();
        Storage::disk('documents')->delete($document->fileUrl);

        $this->actingAs($this->staffInOffice(6, 'AdmissionOfficer'))
            ->get(route('admission.documents.show', $document))
            ->assertNotFound();
    }

    #[Test]
    public function nobody_who_cannot_view_the_admission_may_open_its_document(): void
    {
        [, , $document] = $this->makeAdmissionWithDocument();

        // No role at all: the paper must not be readable just by guessing an id.
        $this->actingAs($this->staffInOffice(22, null))
            ->get(route('admission.documents.show', $document))
            ->assertForbidden();
    }

    #[Test]
    public function the_admission_screen_lists_the_document_attached_to_each_submission(): void
    {
        [$admission] = $this->makeAdmissionWithDocument();
        $this->actingAs($this->staffInOffice(6, 'AdmissionOfficer'));

        $this->get(route('admission.show', $admission))
            ->assertInertia(fn ($page) => $page
                ->component('Admission/Show')
                ->has('admission.requirementSubmissions.0.documents', 1)
                ->where('admission.requirementSubmissions.0.documents.0.fileType', 'application/pdf')
            );
    }

    #[Test]
    public function the_id_desk_can_see_the_captured_face_photo(): void
    {
        $path = 'id-photos/capture.jpg';
        Storage::disk('documents')->put($path, 'THE-FACE');
        $idRequest = $this->makeIdRequest($path);

        $this->actingAs($this->staffInOffice(22, 'IdOfficer'));

        $response = $this->get(route('id.photo.view', $idRequest));

        $response->assertOk();
        $this->assertEquals('THE-FACE', $response->streamedContent());
    }

    #[Test]
    public function an_id_request_without_a_stored_photo_reports_404(): void
    {
        $idRequest = $this->makeIdRequest(null);

        $this->actingAs($this->staffInOffice(22, 'IdOfficer'))
            ->get(route('id.photo.view', $idRequest))
            ->assertNotFound();
    }

    #[Test]
    public function the_guest_cannot_reach_either_stored_file_route(): void
    {
        [, , $document] = $this->makeAdmissionWithDocument();

        $this->get(route('admission.documents.show', $document))->assertRedirect('/login');
        $this->get(route('id.photo.view', $this->makeIdRequest(null)))->assertRedirect('/login');
    }
}
