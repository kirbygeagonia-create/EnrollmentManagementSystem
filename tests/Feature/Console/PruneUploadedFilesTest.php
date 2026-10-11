<?php

namespace Tests\Feature\Console;

use App\Enums\EnrollmentStatus;
use App\Enums\IdRequestReason;
use App\Enums\IdRequestStatus;
use App\Enums\StudentType;
use App\Enums\UnitType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Idrequests;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ems:uploads:prune answers a question nobody should have to answer by browsing the folder:
 * which of the stored face photos and applicant papers does no record point at any more?
 *
 * The rule that matters is the default. This reports on files holding signed forms and
 * pictures of real students, so it lists them and touches nothing unless an operator says
 * --delete — and the file a record does reference has to survive even that.
 */
class PruneUploadedFilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documents');
    }

    private function makeRequest(): Idrequests
    {
        Offices::insert([
            ['officeId' => 4, 'officeName' => 'Academic Department'],
            ['officeId' => 22, 'officeName' => 'ID Office'],
        ]);

        Religions::create(['religionId' => 1, 'religionName' => 'Roman Catholic']);

        $unit = Academicunits::create([
            'unitCode' => 'CCS',
            'unitName' => 'College of Computer Studies',
            'unitType' => UnitType::College,
        ]);

        $courseId = Courses::create([
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

        $termId = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2024-06-01',
            'endDate' => '2024-10-31',
        ])->termId;

        $student = Students::create([
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

        $staff = Staffusers::factory()->make([
            'officeId' => 4,
            'role' => 'staff',
            'employeeNo' => 'EMP-'.uniqid(),
            'username' => 'user_'.uniqid(),
            'email' => 'user_'.uniqid().'@example.com',
        ]);
        unset($staff->remember_token);
        $staff->save();

        $enrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $courseId,
            'termId' => $termId,
            'yearLevel' => 1,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => 'new',
            'academicStanding' => 'regular',
            'enrollmentStatus' => EnrollmentStatus::Enrolled,
            'evaluatedBy' => $staff->userId,
        ]);

        return Idrequests::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'requestReason' => IdRequestReason::NewStudent,
            'emergencyContactName' => 'Emergency Contact',
            'emergencyContactNumber' => '09171234569',
            'bloodType' => 'O+',
            'cardPhotoPath' => 'id-photos/on-record.png',
            'requestDate' => now(),
            'status' => IdRequestStatus::Pending,
        ]);
    }

    #[Test]
    public function it_reports_an_unreferenced_file_and_leaves_it_on_disk(): void
    {
        $request = $this->makeRequest();

        Storage::disk('documents')->put($request->cardPhotoPath, 'the face on file');
        Storage::disk('documents')->put('id-photos/left-behind.png', 'a superseded capture');

        $this->assertSame(0, Artisan::call('ems:uploads:prune'));
        $output = Artisan::output();

        $this->assertStringContainsString('left-behind.png', $output);
        $this->assertStringContainsString('Dry run', $output);
        Storage::disk('documents')->assertExists('id-photos/left-behind.png');
        Storage::disk('documents')->assertExists('id-photos/on-record.png');
    }

    #[Test]
    public function it_removes_the_unreferenced_file_only_when_told_to(): void
    {
        $this->makeRequest();

        Storage::disk('documents')->put('id-photos/left-behind.png', 'a superseded capture');

        $this->assertSame(0, Artisan::call('ems:uploads:prune', ['--delete' => true]));

        Storage::disk('documents')->assertMissing('id-photos/left-behind.png');
    }

    #[Test]
    public function a_file_a_record_points_at_survives_even_the_delete_flag(): void
    {
        $request = $this->makeRequest();

        Storage::disk('documents')->put($request->cardPhotoPath, 'the face on file');
        Storage::disk('documents')->put('id-photos/left-behind.png', 'a superseded capture');

        $this->assertSame(0, Artisan::call('ems:uploads:prune', ['--delete' => true]));

        $this->assertNotNull($request->fresh()->cardPhotoPath);
        Storage::disk('documents')->assertExists('id-photos/on-record.png');
        Storage::disk('documents')->assertMissing('id-photos/left-behind.png');
    }

    #[Test]
    public function it_never_touches_anything_outside_the_upload_folders(): void
    {
        Storage::disk('documents')->put('.gitignore', "*\n!.gitignore\n");
        Storage::disk('documents')->put('prints/not-an-upload.png', 'a rendered paper');

        $this->assertSame(0, Artisan::call('ems:uploads:prune', ['--delete' => true]));
        $output = Artisan::output();

        // The disk root holds the .gitignore that keeps this folder out of a public
        // repository. Measured: the first version of the command listed it as an orphan.
        $this->assertStringNotContainsString('.gitignore', $output);
        $this->assertStringNotContainsString('not-an-upload.png', $output);
        Storage::disk('documents')->assertExists('.gitignore');
        Storage::disk('documents')->assertExists('prints/not-an-upload.png');
    }

    #[Test]
    public function a_record_pointing_at_a_missing_file_is_reported_rather_than_silently_counted(): void
    {
        $request = $this->makeRequest();
        Storage::disk('documents')->delete($request->cardPhotoPath);

        $this->assertSame(0, Artisan::call('ems:uploads:prune'));
        $output = Artisan::output();

        // The read route already answers this with a 404 for the desk; the command names it
        // for the operator, because a record with no paper behind it is the defect the
        // silent-write lane exists to prevent.
        $this->assertStringContainsString('missing: id-photos/on-record.png', $output);
        $this->assertStringContainsString('Nothing to prune', $output);
    }
}
