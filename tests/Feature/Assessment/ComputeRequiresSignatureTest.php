<?php

namespace Tests\Feature\Assessment;

use App\Enums\EnrollmentStatus;
use App\Enums\UnitType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Letter C item 2: the fee sheet could be computed one click before the
 * department signed the load it prices.
 *
 * The Evaluation desk rendered "Compute & Proceed to Assessment" for any
 * enrollment in Evaluated, including while its own Sign button sat disabled for
 * missing profile fields — and nothing in the policy checked the signature. So
 * an unsigned, profile-less record could reach Phase 3 and the six desks behind
 * it, which is exactly the BR32 gate the capture work was supposed to close.
 * The signature is now a precondition of compute, at the gate and on the screen.
 */
class ComputeRequiresSignatureTest extends TestCase
{
    use DatabaseTransactions;

    private int $testCourseId;

    private int $testTermId;

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
            ['officeId' => 2, 'officeName' => 'Accounting Office'],
            ['officeId' => 3, 'officeName' => 'Assessment Office'],
            ['officeId' => 4, 'officeName' => 'Guidance Office'],
        ]);

        Religions::create(['religionId' => 1, 'religionName' => 'Roman Catholic']);

        $unit = Academicunits::create([
            'unitCode' => 'CCS',
            'unitName' => 'College of Computer Studies',
            'unitType' => UnitType::College,
        ]);

        $course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCS',
            'courseName' => 'Bachelor of Science in Computer Science',
            'requiresEntranceExam' => false,
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

        $this->testCourseId = (int) $course->courseId;
        $this->testTermId = (int) $term->termId;

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RbacSeeder', '--database' => 'sqlite']);
    }

    private function assessmentDesk(): Staffusers
    {
        $staff = Staffusers::factory()->make([
            'officeId' => 3,
            'role' => 'staff',
            'employeeNo' => 'EMP-C2-'.uniqid(),
            'username' => 'c2_scholarship_'.uniqid(),
            'email' => 'c2_scholarship_'.uniqid().'@example.com',
        ]);
        unset($staff->remember_token);
        $staff->save();
        $staff->assignRole('ScholarshipOfficer');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    private function evaluatedEnrollment(array $attributes = []): Enrollments
    {
        $student = Students::create([
            'schoolIdNumber' => 'C2-'.uniqid(),
            'lastName' => 'Signora',
            'firstName' => 'Bea',
            'middleName' => 'A',
            'suffix' => 'N/A',
            'gender' => 'female',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'email' => 'c2_'.uniqid().'@example.com',
            'username' => 'c2_student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        return Enrollments::create(array_merge([
            'studentId' => $student->studentId,
            'courseId' => $this->testCourseId,
            'termId' => $this->testTermId,
            'yearLevel' => 1,
            'studentType' => 'firstYear',
            'enrollmentType' => 'new',
            'academicStanding' => 'regular',
            'enrollmentStatus' => EnrollmentStatus::Evaluated,
            'evaluatedBy' => 1,
        ], $attributes));
    }

    #[Test]
    public function an_unsigned_evaluation_cannot_be_priced_at_the_assessment_desk(): void
    {
        $desk = $this->assessmentDesk();
        $enrollment = $this->evaluatedEnrollment();

        $this->assertNull($enrollment->formSignedDate);
        $this->assertFalse($desk->can('assessment.computeAtDesk', $enrollment));

        $this->actingAs($desk)
            ->post(route('assessment.compute', $enrollment))
            ->assertForbidden();

        $this->assertDatabaseMissing('studentassessments', [
            'enrollmentId' => $enrollment->enrollmentId,
        ]);
    }

    #[Test]
    public function the_signature_that_adopts_the_load_is_what_unlocks_the_fee_sheet(): void
    {
        $desk = $this->assessmentDesk();
        $enrollment = $this->evaluatedEnrollment(['formSignedDate' => now()]);

        $this->assertTrue($desk->can('assessment.computeAtDesk', $enrollment));

        $this->actingAs($desk)
            ->post(route('assessment.compute', $enrollment))
            ->assertRedirect();
    }
}
