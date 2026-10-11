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
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\LocalFilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem as FlysystemFilesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter as LocalAdapter;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The documents disk is configured with throw => false, so a write the filesystem
 * refuses does not raise: store() comes back as false. Both upload routes used to carry
 * that false straight into the database, so the desk got a green confirmation over a
 * paper or a face photo that was never written.
 *
 * The refusing disk below is a real local adapter that raises the same
 * UnableToWriteFile a full volume raises, so the framework's own conversion to false
 * runs on the way. Each failing test is paired with a control that uploads into the
 * same folder through an ordinary disk, which is what keeps a green run from meaning
 * only that every upload is broken.
 */
class UploadFailureTest extends TestCase
{
    private int $courseId;

    private int $termId;

    private string $root = '';

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

        Storage::extend('refusing', function ($app, $config) {
            $inner = new LocalAdapter($config['root']);

            return (new LocalFilesystemAdapter(
                new FlysystemFilesystem(new RefusingWriteAdapter($inner)),
                $inner,
                $config
            ))->diskName('refusing')->shouldServeSignedUrls(false, fn () => $app['url']);
        });
    }

    protected function tearDown(): void
    {
        if ($this->root !== '' && is_dir($this->root)) {
            (new Filesystem)->deleteDirectory($this->root);
        }

        parent::tearDown();
    }

    /**
     * Put the documents disk on a fresh temporary folder — writable, or refusing — and
     * keep both named disks available so the fixture can be compared against them.
     */
    private function useTempDisk(bool $documentsRefuseWrites): void
    {
        $this->root = sys_get_temp_dir().'/ems-upload-'.uniqid();
        mkdir($this->root, 0777, true);

        $writable = [
            'driver' => 'local',
            'root' => $this->root,
            'throw' => false,
            'report' => false,
            'visibility' => Visibility::PRIVATE,
        ];

        $refusing = [
            'driver' => 'refusing',
            'root' => $this->root,
            'throw' => false,
            'report' => false,
            'visibility' => Visibility::PRIVATE,
        ];

        config([
            'filesystems.disks.writable-disk' => $writable,
            'filesystems.disks.refusing-disk' => $refusing,
            'filesystems.disks.documents' => $documentsRefuseWrites ? $refusing : $writable,
        ]);
    }

    private function seedReferenceData(): void
    {
        Offices::insert([
            ['officeId' => 4, 'officeName' => 'Academic Department'],
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
     * @return array{Admissions, Studentrequirementsubmissions}
     */
    private function makePendingAdmission(): array
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
            'submissionStatus' => SubmissionStatus::Pending,
            // The column is NOT NULL and AdmissionController::store stamps it at
            // registration, which is what an applicant's own record looks like here.
            'submittedDate' => now(),
            'remarks' => '',
        ]);

        return [$admission, $submission];
    }

    private function makeIdRequest(): Idrequests
    {
        $enrollment = Enrollments::create([
            'studentId' => $this->makeStudent()->studentId,
            'courseId' => $this->courseId,
            'termId' => $this->termId,
            'yearLevel' => 1,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => 'new',
            'academicStanding' => 'regular',
            'enrollmentStatus' => EnrollmentStatus::Enrolled,
            'evaluatedBy' => $this->staffInOffice(4, null)->userId,
        ]);

        return Idrequests::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'requestReason' => IdRequestReason::NewStudent,
            'emergencyContactName' => 'Emergency Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
            'requestDate' => now(),
            'status' => IdRequestStatus::Pending,
        ]);
    }

    private function aPaper(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'form138.pdf',
            "%PDF-1.4\n1 0 obj\nendobj\ntrailer\n%%EOF\n"
        );
    }

    #[Test]
    public function a_document_the_disk_refuses_to_write_is_not_recorded_as_submitted(): void
    {
        $this->useTempDisk(true);
        [$admission, $submission] = $this->makePendingAdmission();
        $this->actingAs($this->staffInOffice(6, 'AdmissionOfficer'));

        $response = $this->post(
            route('admission.requirements.submit', ['admission' => $admission->admissionId, 'requirement' => $submission->requirementId]),
            ['file' => $this->aPaper()]
        );

        $this->assertSame(0, Documents::count());
        $this->assertEquals(
            SubmissionStatus::Pending->value,
            Studentrequirementsubmissions::find($submission->submissionId)->submissionStatus->value
        );
        $response->assertSessionHas('error', 'Your file could not be saved, so nothing was submitted and this requirement is still waiting for it. Please attach the paper again. If it fails a second time the server is short of storage space — tell your office head so they can have it checked.');
    }

    #[Test]
    public function a_document_the_disk_accepts_still_submits_normally(): void
    {
        $this->useTempDisk(false);
        [$admission, $submission] = $this->makePendingAdmission();
        $this->actingAs($this->staffInOffice(6, 'AdmissionOfficer'));

        $response = $this->post(
            route('admission.requirements.submit', ['admission' => $admission->admissionId, 'requirement' => $submission->requirementId]),
            ['file' => $this->aPaper()]
        );

        $response->assertSessionHas('success', 'Document submitted successfully.');
        $this->assertSame(1, Documents::count());
        $this->assertTrue(filled(Documents::first()->fileUrl));
        $this->assertTrue(Storage::disk('writable-disk')->exists(Documents::first()->fileUrl));
        $this->assertEquals(
            SubmissionStatus::Submitted->value,
            Studentrequirementsubmissions::find($submission->submissionId)->submissionStatus->value
        );
    }

    #[Test]
    public function a_photo_the_disk_refuses_to_write_leaves_the_request_asking_for_one(): void
    {
        $this->useTempDisk(true);
        $idRequest = $this->makeIdRequest();
        $this->actingAs($this->staffInOffice(22, 'IdOfficer'));

        $response = $this->post(route('id.photo', ['idRequest' => $idRequest->idRequestId]), [
            'photo' => UploadedFile::fake()->image('face.png', 300, 360),
        ]);

        $this->assertNull(Idrequests::find($idRequest->idRequestId)->cardPhotoPath);
        $response->assertSessionHas('error', 'The photo could not be saved, so this request is unchanged and still needs a face photo. Capture or choose the picture again. If it fails a second time the server is short of storage space — tell your office head so they can have it checked.');
    }

    #[Test]
    public function a_photo_the_disk_accepts_is_still_attached(): void
    {
        $this->useTempDisk(false);
        $idRequest = $this->makeIdRequest();
        $this->actingAs($this->staffInOffice(22, 'IdOfficer'));

        $response = $this->post(route('id.photo', ['idRequest' => $idRequest->idRequestId]), [
            'photo' => UploadedFile::fake()->image('face.png', 300, 360),
        ]);

        $response->assertSessionHas('success', 'Face photo attached to the ID request.');
        $stored = Idrequests::find($idRequest->idRequestId)->cardPhotoPath;
        $this->assertTrue(filled($stored));
        $this->assertTrue(Storage::disk('writable-disk')->exists($stored));
    }

    #[Test]
    public function the_refusing_disk_really_does_hand_store_back_a_false(): void
    {
        $this->useTempDisk(true);

        $this->assertFalse(
            $this->aPaper()->store('admission-documents', 'refusing-disk'),
            'The fixture must fail the way a full volume does, or the guard in the routes is untested.'
        );
        $this->assertStringContainsString(
            'id-photos/',
            (string) $this->aPaper()->store('id-photos', 'writable-disk')
        );
    }
}

/**
 * A local adapter that raises the identical UnableToWriteFile a read-only or full
 * volume raises, and otherwise behaves like the disk it wraps.
 */
class RefusingWriteAdapter implements FilesystemAdapter
{
    public function __construct(private readonly LocalAdapter $inner) {}

    public function fileExists(string $path): bool
    {
        return $this->inner->fileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->inner->directoryExists($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        throw UnableToWriteFile::atLocation($path, 'the volume is full and the file could not be opened for writing');
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        throw UnableToWriteFile::atLocation($path, 'the volume is full and the file could not be opened for writing');
    }

    public function read(string $path): string
    {
        return $this->inner->read($path);
    }

    public function readStream(string $path)
    {
        return $this->inner->readStream($path);
    }

    public function delete(string $path): void
    {
        $this->inner->delete($path);
    }

    public function deleteDirectory(string $path): void
    {
        $this->inner->deleteDirectory($path);
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->inner->createDirectory($path, $config);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->inner->setVisibility($path, $visibility);
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->inner->visibility($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->inner->mimeType($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->inner->lastModified($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->inner->fileSize($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        return $this->inner->listContents($path, $deep);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->inner->move($source, $destination, $config);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->inner->copy($source, $destination, $config);
    }
}
