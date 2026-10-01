<?php

namespace Tests\Feature\Evaluation;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\OfficeId;
use App\Enums\StaffRole;
use App\Enums\StudentType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
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
 * BR32: "The enrollment form cannot be forwarded while any required demographic
 * field is missing."
 *
 * The capture endpoint and its policy existed, but no screen called them, and
 * nothing stopped a signature over an empty profile — so an enrollment could
 * reach the other six desks with no address, no guardian and no birthdate on
 * file. These tests pin both halves: the desk is told exactly which fields are
 * missing, and the signature is refused until they are captured.
 */
class ProfileCaptureTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $evaluator;

    private Students $student;

    private int $termId;

    private int $courseId;

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

        $term = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ]);

        $course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCS',
            'courseName' => 'Bachelor of Science in Computer Science',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);

        $this->termId = $term->termId;
        $this->courseId = $course->courseId;

        $this->student = $this->makeStudent();

        // Department Evaluation shares office 4 with Guidance — the box the
        // evaluation step signs.
        $this->evaluator = $this->staffInOffice(OfficeId::Guidance->value, 'DeptEvaluator');
    }

    private function staffInOffice(int $officeId, string $spatieRole): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => $officeId,
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-PROFILE-'.uniqid(),
            'username' => 'profile_'.$officeId.'_'.uniqid(),
            'email' => 'profile_'.$officeId.'_'.uniqid().'@example.com',
        ]);
        $staff->assignRole($spatieRole);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    /**
     * A student whose person row is complete but whose family record, addresses
     * and enrollment-form fields have never been captured on this desk.
     */
    private function makeStudent(): Students
    {
        return Students::create([
            'schoolIdNumber' => 'PROFILE-'.uniqid(),
            'lastName' => 'Incomplete',
            'firstName' => 'Student',
            'middleName' => 'Q',
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
            'email' => 'profile_'.uniqid().'@example.com',
            'username' => 'profile_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);
    }

    private function makeEnrollment(array $overrides = []): Enrollments
    {
        return Enrollments::create(array_merge([
            'studentId' => $this->student->studentId,
            'courseId' => $this->courseId,
            'termId' => $this->termId,
            'yearLevel' => 2,
            'studentType' => StudentType::Continuing,
            'enrollmentType' => EnrollmentType::Old,
            'enrollmentStatus' => EnrollmentStatus::Evaluated,
            'evaluatedBy' => $this->evaluator->userId,
        ], $overrides));
    }

    /**
     * The payload the desk form posts — the same required set the capture
     * endpoint validates, so a complete profile means no gap left to list.
     */
    private function completeProfilePayload(array $overrides = []): array
    {
        return array_merge([
            'lastName' => 'Complete',
            'firstName' => 'Student',
            'middleName' => 'Q',
            'suffix' => 'N/A',
            'gender' => 'female',
            'birthdate' => '2004-05-12',
            'birthplace' => 'Biñan, Laguna',
            'citizenship' => 'Filipino',
            'religionId' => 1,
            'civilStatus' => 'single',
            'contactNumber' => '09171234567',
            'telephoneNumber' => null,
            'email' => 'profile_'.uniqid().'@example.com',
            'addresses' => [
                ['addressType' => 'home', 'houseBuildingNo' => '12', 'street' => 'Rizal St', 'sitioPurok' => '', 'barangay' => 'Poblacion', 'cityMunicipality' => 'Biñan', 'district' => '', 'province' => 'Laguna', 'region' => 'Region IV-A', 'zipCode' => '4024', 'country' => 'Philippines'],
                ['addressType' => 'current', 'houseBuildingNo' => '4', 'street' => 'Mabini St', 'sitioPurok' => '', 'barangay' => 'San Jose', 'cityMunicipality' => 'Santa Rosa', 'district' => '', 'province' => 'Laguna', 'region' => 'Region IV-A', 'zipCode' => '4026', 'country' => 'Philippines'],
            ],
            'guardians' => [
                ['relationship' => 'mother', 'fullName' => 'Ana Complete', 'contactNumber' => '09171234568', 'email' => 'ana@example.com', 'isEmergencyContact' => true, 'isAuthorizedToActOnBehalf' => true],
            ],
            'semestersCompleted' => 2,
            'yearsInInstitution' => 1,
            'academicStanding' => 'regular',
            'formIssuedDate' => now()->toDateString(),
        ], $overrides);
    }

    #[Test]
    public function the_evaluation_screen_lists_every_required_field_the_desk_has_not_captured(): void
    {
        $enrollment = $this->makeEnrollment();

        $this->actingAs($this->evaluator)
            ->get(route('evaluation.show', $enrollment))
            ->assertInertia(fn ($page) => $page
                ->component('Evaluation/Show')
                ->where('profileGaps.0', 'Home and current address')
                ->where('profileGaps.1', 'Parent or guardian')
                ->where('profileGaps.2', 'Academic standing')
                ->where('profileGaps.3', 'Form issued date')
                ->where('can.captureProfile', true)
            );
    }

    #[Test]
    public function the_evaluation_cannot_be_signed_while_the_profile_is_incomplete(): void
    {
        $enrollment = $this->makeEnrollment();

        $this->actingAs($this->evaluator)
            ->post(route('evaluation.sign', $enrollment))
            ->assertSessionHasErrors('profile');

        $enrollment->refresh();
        $this->assertNull($enrollment->formSignedDate);
        $this->assertNull($enrollment->enrollmentworkflow, 'A refused signature must not open the workflow form.');
        $this->assertEquals(EnrollmentStatus::Evaluated, $enrollment->enrollmentStatus);
    }

    #[Test]
    public function capturing_the_profile_writes_the_real_records_and_clears_every_gap(): void
    {
        $enrollment = $this->makeEnrollment();

        $this->actingAs($this->evaluator)
            ->put(route('evaluation.profile.capture', $enrollment), $this->completeProfilePayload())
            ->assertSessionHasNoErrors();

        $student = $this->student->fresh();
        $this->assertSame('Complete', $student->lastName);
        $this->assertSame('female', $student->gender->value);
        $this->assertEquals(2, $student->addresses()->count(), 'Both addresses the desk captured must be on file.');
        $this->assertEquals(1, $student->guardians()->count());

        $enrollment->refresh();
        $this->assertEquals('regular', $enrollment->academicStanding->value);
        $this->assertEquals(now()->toDateString(), $enrollment->formIssuedDate->toDateString());

        $this->actingAs($this->evaluator)
            ->get(route('evaluation.show', $enrollment))
            ->assertInertia(fn ($page) => $page->has('profileGaps', 0));
    }

    #[Test]
    public function signing_succeeds_once_the_profile_is_complete(): void
    {
        $enrollment = $this->makeEnrollment();

        $this->actingAs($this->evaluator)
            ->put(route('evaluation.profile.capture', $enrollment), $this->completeProfilePayload())
            ->assertSessionHasNoErrors();

        $this->actingAs($this->evaluator)
            ->post(route('evaluation.sign', $enrollment))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($enrollment->fresh()->formSignedDate);
        $this->assertNotNull($enrollment->fresh()->enrollmentworkflow, 'Signing opens the workflow form.');
    }

    #[Test]
    public function one_address_is_not_a_profile_the_desk_can_save(): void
    {
        $enrollment = $this->makeEnrollment();

        $this->actingAs($this->evaluator)
            ->put(route('evaluation.profile.capture', $enrollment), $this->completeProfilePayload([
                'addresses' => [
                    ['addressType' => 'home', 'houseBuildingNo' => '12', 'street' => 'Rizal St', 'sitioPurok' => '', 'barangay' => 'Poblacion', 'cityMunicipality' => 'Biñan', 'district' => '', 'province' => 'Laguna', 'region' => 'Region IV-A', 'zipCode' => '4024', 'country' => 'Philippines'],
                ],
            ]))
            ->assertSessionHasErrors('addresses');

        $this->assertEquals(0, $this->student->fresh()->addresses()->count(), 'A refused capture must write nothing.');
    }

    #[Test]
    public function another_desk_cannot_capture_the_evaluation_profile(): void
    {
        $enrollment = $this->makeEnrollment();
        $cashier = $this->staffInOffice(OfficeId::Accounting->value, 'AccountingStaff');

        $this->actingAs($cashier)
            ->put(route('evaluation.profile.capture', $enrollment), $this->completeProfilePayload())
            ->assertForbidden();

        $this->student->refresh();
        $this->assertSame('Incomplete', $this->student->lastName);
        $this->assertEquals(0, $this->student->addresses()->count());
    }
}
