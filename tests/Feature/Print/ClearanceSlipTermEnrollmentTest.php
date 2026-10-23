<?php

namespace Tests\Feature\Print;

use App\Enums\ClearanceOverallStatus;
use App\Enums\ClearancePeriodStatus;
use App\Enums\EnrollmentType;
use App\Enums\Semester;
use App\Enums\StudentType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Clearanceperiods;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Majors;
use App\Models\Staffusers;
use App\Models\Studentclearances;
use App\Models\Students;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * P-17. §25.4's P-10 row states that a clearance slip takes its course, year level and print-log
 * enrollment "from the enrollment of the clearance period's own term", and its consequence cell reads
 * "Nothing for the derivation." The code is not that clean: `Studentclearances::termEnrollment()`
 * returns the term's enrollment when one exists and otherwise falls back to the student's newest
 * enrollment by id — and the comment above that fallback asks the Registrar to decide which
 * enrollment a slip should name. Three print paths read the method (`ClearanceController.php:386`,
 * `PrintService.php:77`, `clearance-slip.blade.php:65`), so the answer the fallback gives is what
 * reaches paper.
 *
 * Measured on live `ems` before writing this: all four clearance rows hold an enrollment in their
 * period's term, so the fallback has never fired there — which is exactly why no instrument could see
 * it. These tests pin both branches so the choice is made deliberately rather than inherited, and pin
 * the three call sites so a fourth inline lookup cannot quietly re-derive the answer somewhere else.
 */
class ClearanceSlipTermEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private array $scene = [];

    /**
     * One student, two terms with an enrollment in each, and the ids ordered so "newest by
     * enrollmentId" and "latest by term" agree — the case the fallback answers today.
     *
     * @return array{student: Students, first: Enrollments, second: Enrollments, termA: Academicterms, termB: Academicterms, course: Courses}
     */
    private function scene(): array
    {
        if ($this->scene !== []) {
            return $this->scene;
        }

        try {
            DB::table('religions')->insert(['religionId' => 1, 'religionName' => 'Roman Catholic']);
        } catch (QueryException) {
            // Present already on a schema the seeders filled; the pinned id is what matters.
        }

        $unit = Academicunits::create(['unitName' => 'College of Education', 'unitType' => 'college']);
        $course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BED',
            'courseName' => 'Bachelor of Elementary Education',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);
        $major = Majors::create(['courseId' => $course->courseId, 'majorName' => 'Elementary']);

        $year = fn (string $label, string $start, string $end): int => DB::table('academicyears')->insertGetId([
            'yearLabel' => $label,
            'startDate' => $start,
            'endDate' => $end,
        ]);
        $term = fn (int $yearId, Semester $semester, string $start, string $end): Academicterms => Academicterms::create([
            'academicYearId' => $yearId,
            'semester' => $semester,
            'startDate' => $start,
            'endDate' => $end,
        ]);

        $termA = $term($year('2024-2025', '2024-08-01', '2025-05-31'), Semester::First, '2024-08-01', '2024-12-15');
        $termB = $term($year('2025-2026', '2025-08-01', '2026-05-31'), Semester::First, '2025-08-01', '2025-12-15');
        $termC = $term($year('2026-2027', '2026-08-01', '2027-05-31'), Semester::Second, '2027-01-05', '2027-05-15');

        $student = Students::create([
            'schoolIdNumber' => 'P17-'.uniqid(),
            'lastName' => 'Reyes',
            'firstName' => 'Maria',
            'middleName' => 'C',
            'suffix' => 'N/A',
            'gender' => 'female',
            'birthdate' => '2003-02-11',
            'birthplace' => 'Maribis',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'status' => 'active',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'telephoneNumber' => null,
            'semestersCompleted' => 2,
            'yearsInInstitution' => 2,
            'email' => 'p17_'.uniqid().'@example.com',
            'username' => 'p17_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
        ]);

        $deskStaff = fn (int $officeId, string $tag): Staffusers => Staffusers::factory()->create([
            'officeId' => $officeId,
            'role' => 'officeHead',
            'employeeNo' => $tag.'-'.uniqid(),
            'username' => $tag.'_'.uniqid(),
            'email' => $tag.'_'.uniqid().'@example.com',
        ]);

        $enroll = function (Academicterms $term, int $yearLevel) use ($student, $course, $major, $deskStaff): Enrollments {
            $evaluator = $deskStaff(4, 'p17_eval');
            $registrar = $deskStaff(1, 'p17_reg');

            return Enrollments::create([
                'studentId' => $student->studentId,
                'courseId' => $course->courseId,
                'majorId' => $major->majorId,
                'termId' => $term->termId,
                'yearLevel' => $yearLevel,
                'admissionId' => null,
                'studentType' => StudentType::FirstYear,
                'enrollmentType' => EnrollmentType::New,
                'academicStanding' => 'regular',
                'enrollmentStatus' => 'enrolled',
                'evaluatedBy' => $evaluator->userId,
                'registrarProcessedBy' => $registrar->userId,
                'enrolledDate' => now(),
                'formIssuedDate' => now()->toDateString(),
            ]);
        };

        $this->scene = [
            'student' => $student,
            'first' => $enroll($termA, 1),
            'second' => $enroll($termB, 2),
            'termA' => $termA,
            'termB' => $termB,
            'termC' => $termC,
            'course' => $course,
        ];

        return $this->scene;
    }

    private function clearanceOn(Academicterms $term): Studentclearances
    {
        $scene = $this->scene();

        $period = Clearanceperiods::create([
            'termId' => $term->termId,
            'clearanceStartDate' => '2026-06-01',
            'clearanceEndDate' => '2026-06-30',
            'periodStatus' => ClearancePeriodStatus::Open,
        ]);

        return Studentclearances::create([
            'studentId' => $scene['student']->studentId,
            'clearancePeriodId' => $period->clearancePeriodId,
            'overallStatus' => ClearanceOverallStatus::Pending,
        ]);
    }

    /**
     * P-10's claim, stated as a contract rather than as prose: when the period's term holds an
     * enrollment, that enrollment — not a later or earlier one — is what the slip prints.
     */
    #[Test]
    public function test_the_slip_names_the_enrollment_of_its_own_periods_term(): void
    {
        $clearance = $this->clearanceOn($this->scene()['termA']);

        $named = $clearance->termEnrollment();

        $this->assertNotNull($named, 'A clearance whose term holds an enrollment returned nothing, so the slip prints N/A over real records.');
        $this->assertSame(
            $this->scene['first']->enrollmentId,
            $named->enrollmentId,
            'The period is on the first term, so the slip must name that enrollment rather than the newer one.'
        );
    }

    /**
     * P-17: the other branch. The fallback is the answer the paper gets today, and it was never
     * chosen by anybody — the code says so in its own comment.
     */
    #[Test]
    public function test_a_slip_raised_after_its_term_closed_names_the_newest_enrollment_by_id(): void
    {
        $scene = $this->scene();
        $clearance = $this->clearanceOn($scene['termC']);

        $this->assertSame(
            0,
            Enrollments::where('studentId', $scene['student']->studentId)->where('termId', $scene['termC']->termId)->count(),
            'The fixture is wrong: the period’s term holds an enrollment, so this is not the fallback branch.'
        );

        $named = $clearance->termEnrollment();

        $this->assertNotNull($named, 'With no enrollment in the period’s term the slip prints N/A for course and year level.');
        $this->assertSame(
            $scene['second']->enrollmentId,
            $named->enrollmentId,
            'P-17 is open precisely because this choice is the developer’s: the method takes the highest enrollmentId, '
                .'not the enrollment of the term the student last attended, and no desk has ruled which the paper should say.'
        );
    }

    /**
     * The three print paths must keep asking the one method. If a fourth re-derives the answer inline
     * — or the blade stops calling this at all — the pinned fallback above stops describing the paper,
     * and that is how §25.4's rows come to disagree with the build without any test noticing.
     */
    #[Test]
    public function test_every_print_path_asks_the_one_method(): void
    {
        foreach ([
            'app/Http/Controllers/Clearance/ClearanceController.php',
            'app/Services/PrintService.php',
            'resources/views/prints/clearance-slip.blade.php',
        ] as $path) {
            $this->assertStringContainsString(
                'termEnrollment()',
                (string) file_get_contents(base_path($path)),
                $path.' no longer reads Studentclearances::termEnrollment(), so the slip’s enrollment is derived elsewhere and P-10/P-17 describe a path that is gone.'
            );
        }

        $this->assertStringContainsString(
            'PROVISIONAL',
            (string) file_get_contents(base_path('app/Models/Studentclearances.php')),
            'The fallback is no longer marked provisional in the code, so either P-17 has been answered — and §25.4 and the school packet must say so — or the marker was lost.'
        );
    }
}
