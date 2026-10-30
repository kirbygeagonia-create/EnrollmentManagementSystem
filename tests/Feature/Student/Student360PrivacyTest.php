<?php

namespace Tests\Feature\Student;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\StudentType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Clinicrecords;
use App\Models\Courses;
use App\Models\Enrollments;
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
 * Prompt §10's two minimum-necessary clauses, on the screen that reaches every desk.
 *
 * The §10 read found both on the same page and both with a one-line cause. First, `StudentController::show()`
 * eager-loads `enrollments.clinicrecords` for every Student 360 and `Students/Show.jsx` prints the findings text,
 * so a desk that may look at a student — the widest right in the system, held by most roles — reads clinical notes
 * it was never meant to see. The prompt's own worked example is that "most desks need to know the clinic clearance
 * outcome, not read full clinical notes". Second, the whole `students` model is handed to Inertia, and unlike
 * `Staffusers`, `Students` declares no `$hidden`, so the student's bcrypt credential hash travels in the payload of
 * a screen opened by a search box.
 *
 * These cases are directional, not aggregate: the clinic holder must SEE the record and the students-viewer must
 * not. A test that only asserted absence would also pass if the Health tab were deleted for everybody, which is not
 * the requirement — the clinic desk has to be able to do its job.
 */
class Student360PrivacyTest extends TestCase
{
    use RefreshDatabase;

    private const FINDINGS = 'Palpable thyroid noted; referral to internal medicine advised.';

    private Students $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        Offices::firstOrCreate(['officeId' => 11], ['officeName' => 'Clinic']);
        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        $unit = Academicunits::create([
            'unitName' => 'College of Computer Studies',
            'unitType' => 'college',
        ]);
        $course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSIT',
            'courseName' => 'Information Technology',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);
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

        $this->student = Students::create([
            'schoolIdNumber' => 'PRIV-0001',
            'lastName' => 'Patient',
            'firstName' => 'Given',
            'middleName' => 'M',
            'suffix' => 'N/A',
            'gender' => 'female',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'email' => 'priv_'.uniqid().'@example.com',
            'username' => 'priv_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $enrollment = Enrollments::create([
            'studentId' => $this->student->studentId,
            'courseId' => $course->courseId,
            'termId' => $term->termId,
            'yearLevel' => 2,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => EnrollmentType::New,
            'enrollmentStatus' => EnrollmentStatus::Enrolled,
            // The column is NOT NULL with no default: an enrollment is credited to a department account the moment
            // it exists, so a fixture that wants a clinic record still has to name one.
            'evaluatedBy' => Staffusers::factory()->create(['officeId' => 4])->userId,
        ]);

        Clinicrecords::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'heightCm' => 152.0,
            'weightKg' => 48.0,
            'bloodPressure' => '110/70',
            'philhealthRegistered' => true,
            'assessmentNotes' => 'Annual physical.',
            'findings' => self::FINDINGS,
            'assessmentDate' => '2026-08-01',
            'status' => 'completed',
        ]);
    }

    /**
     * A desk account with a given set of rights, unbounded by college so the 360 itself is reachable.
     *
     * Permissions are granted individually rather than through a role name, because which role holds `clinic.view`
     * is exactly the question §28.5 asks the school, and this test must not answer it by accident.
     */
    private function staffWith(array $permissions): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => 1,
            'employeeNo' => 'EMP-PRIV-'.uniqid(),
            'username' => 'priv_desk_'.uniqid(),
            'email' => 'priv_desk_'.uniqid().'@example.com',
        ]);
        $staff->givePermissionTo($permissions);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    /** @return array<string, mixed> */
    private function open360(Staffusers $staff): array
    {
        return $this->actingAs($staff)
            ->get(route('students.show', $this->student))
            ->assertOk()
            ->viewData('page')['props'];
    }

    /** Every clinical findings string that reached the payload, found by walking it rather than by guessing a shape. */
    private function findingsIn(array $props): array
    {
        $found = [];
        array_walk_recursive($props, function ($value, string $key) use (&$found): void {
            if ($key === 'findings' && is_string($value)) {
                $found[] = $value;
            }
        });

        return $found;
    }

    #[Test]
    public function a_desk_that_may_open_the_record_does_not_read_clinical_notes(): void
    {
        $props = $this->open360($this->staffWith(['students.view']));

        // The whole point of the requirement: the widest student-facing right in the system must not carry the
        // clinic's text with it.
        $this->assertSame(
            [],
            $this->findingsIn($props),
            'a students.view desk received clinical findings in its Student 360 payload'
        );
    }

    #[Test]
    public function the_clinic_desk_still_reads_the_record_it_is_there_to_work_on(): void
    {
        $props = $this->open360($this->staffWith(['students.view', 'clinic.view']));

        // The mirror case. Without it, hiding the tab for everybody would pass the first case and fail the office.
        $this->assertContains(
            self::FINDINGS,
            $this->findingsIn($props),
            'the clinic can no longer see its own findings — the gate was widened past its purpose'
        );
    }

    #[Test]
    public function the_student_credential_hash_is_never_serialised_to_a_desk(): void
    {
        $viewer = $this->open360($this->staffWith(['students.view']));
        $clinic = $this->open360($this->staffWith(['students.view', 'clinic.view']));

        foreach (['a students.view desk' => $viewer, 'the clinic desk' => $clinic] as $who => $props) {
            $this->assertArrayNotHasKey('passwordHash', $props['student'], $who.' received the student credential hash');
        }

        // And the dash is not emptiness: the column really holds a hash, so the absence above is the guard working.
        $this->assertNotEmpty($this->student->fresh()->passwordHash);
    }

    #[Test]
    public function the_clinic_relation_is_not_even_handed_over_to_a_desk_that_may_not_read_it(): void
    {
        $props = $this->open360($this->staffWith(['students.view']));

        // Structural, not merely "no text": an empty findings column would satisfy the other case by accident, while
        // a key that never arrives can only mean the server declined to load it.
        $keys = array_column($props['student']['enrollments'], 'clinicrecords');
        foreach ($keys as $i => $records) {
            $this->assertNull(
                $records,
                "enrollment row {$i} of a non-clinic desk's payload still carries a clinicrecords key"
            );
        }
    }
}
