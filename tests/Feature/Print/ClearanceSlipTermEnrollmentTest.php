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
 * P-10 and P-17. §25.4's P-10 row states that a clearance slip takes its course, year level and
 * print-log enrollment "from the enrollment of the clearance period's own term"; its consequence
 * cell read "Nothing for the derivation." That was true only of the branch the code reached first:
 * `Studentclearances::termEnrollment()` returned the term's enrollment when one existed and
 * otherwise fell back to the student's newest enrollment by id, under a comment asking the
 * Registrar which enrollment the paper should name. Ruled 2026-10-23: "a clearance is issued when
 * a semester ends", so the slip names the enrollment of the period's own term and nothing else —
 * and a clearance once completed stays on the record.
 *
 * Live `ems` holds four clearance rows, every one of which does have an enrollment in its period's
 * term, so the fallback could never be reached by a rehearsal on this box — which is why no
 * instrument saw it. These tests pin both branches, pin the three print paths so a fourth cannot
 * quietly re-derive the answer elsewhere, and pin the retention the ruling states: nothing in the
 * application deletes a clearance, so the store of completed clearances is permanent.
 */
class ClearanceSlipTermEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private array $scene = [];

    /**
     * One student, two terms with an enrollment in each, and a third term holding none — the term
     * whose clearance period used to borrow one of the first two enrollments.
     *
     * @return array{student: Students, first: Enrollments, second: Enrollments, termA: Academicterms, termB: Academicterms, termC: Academicterms, course: Courses}
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
     * P-17, the other half of the ruling: a period whose term holds no enrollment names nothing.
     * Before 2026-10-23 this branch returned the highest enrollmentId instead, which put a course and
     * year level from an unrelated term on a paper that was about a term the student is not clearing.
     */
    #[Test]
    public function test_a_slip_raised_after_its_term_closed_names_no_enrollment(): void
    {
        $scene = $this->scene();
        $clearance = $this->clearanceOn($scene['termC']);

        $this->assertSame(
            0,
            Enrollments::where('studentId', $scene['student']->studentId)->where('termId', $scene['termC']->termId)->count(),
            'The fixture is wrong: the period’s term holds an enrollment, so this is not the branch P-17 was about.'
        );

        $this->assertNull(
            $clearance->termEnrollment(),
            'P-17 was ruled on 2026-10-23: a slip whose term holds no enrollment prints N/A. Returning the newest '
                .'enrollment by id would name a course from a term the clearance is not about.'
        );
    }

    /**
     * The three print paths must keep asking the one method. If a fourth re-derives the answer inline
     * — or the blade stops calling this at all — the pinned branch above stops describing the paper,
     * and that is how §25.4's rows come to disagree with the build without any test noticing.
     *
     * The same ruling's second half is that a completed clearance stays on the record, so this also
     * refuses the day somebody gives a desk a delete: an erased clearance is a student the school
     * cannot show ever cleared.
     */
    #[Test]
    public function test_every_print_path_asks_the_one_method_and_nothing_erases_a_clearance(): void
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

        $model = (string) file_get_contents(base_path('app/Models/Studentclearances.php'));
        $this->assertStringContainsString(
            'Ruled 2026-10-23 (P-17)',
            $model,
            'The method no longer records where its answer came from, so either P-17 has been ruled again — and §25.4 and the school packet must say so — or the marker was lost.'
        );
        $this->assertStringNotContainsString(
            'sortByDesc',
            $model,
            'P-17 was ruled 2026-10-23: a slip whose period’s term holds no enrollment names nothing. Sorting the student’s enrollments by id re-introduces the fallback that borrowed a course from a term the clearance is not about.'
        );

        $deleters = [];
        foreach (['app', 'routes'] as $dir) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if (! $file->isFile() || ! preg_match('/\.php$/i', $file->getFilename())) {
                    continue;
                }
                $bytes = (string) file_get_contents($file->getPathname());

                // A delete that reaches a clearance row is written four ways: the model's destroy, a
                // `Studentclearances::where(...)->delete()`, a relation delete, or `->delete()` on a
                // variable the file loaded from one of those. The window asks for a delete call near a
                // mention of the model rather than trusting one spelling.
                if (preg_match('/Studentclearances\b[\s\S]{0,300}(?:->(?:delete|forceDelete)\(\)|::destroy\()/i', $bytes)
                    || preg_match('/->(?:studentclearances|clearances)\(\)->(?:delete|forceDelete)\(\)/i', $bytes)
                    || preg_match('/\b(?:clearance|slip)\w*->(?:delete|forceDelete)\(\)/i', $bytes)) {
                    $deleters[] = ltrim(str_replace(base_path(), '', $file->getPathname()), '\\/');
                }
            }
        }

        $this->assertSame([], $deleters, 'P-17 rules that the school keeps every student who has completed a clearance, so a code path that deletes a studentclearances row erases that evidence: '
            .implode(', ', $deleters).'.');
    }

    /**
     * The retention the ruling states, measured as data rather than as prose: one clearance row per
     * student per period, the schema enforcing that key, and the period before untouched when the
     * next one issues its own slip.
     */
    #[Test]
    public function test_a_completed_clearance_survives_the_period_that_follows_it(): void
    {
        $scene = $this->scene();

        $cleared = $this->clearanceOn($scene['termA']);
        $cleared->update(['overallStatus' => ClearanceOverallStatus::Approved]);

        $next = $this->clearanceOn($scene['termB']);

        $stored = Studentclearances::orderBy('studentClearanceId')->get();

        $this->assertCount(2, $stored, 'One student clears one term, so a second period must add a row rather than replace the first.');
        $this->assertSame(
            [ClearanceOverallStatus::Approved, ClearanceOverallStatus::Pending],
            $stored->pluck('overallStatus')->all(),
            'The period before was answered by the desks; raising the next slip may not rewrite what it said.'
        );

        $this->assertSame($scene['first']->enrollmentId, $cleared->termEnrollment()->enrollmentId);
        $this->assertSame($scene['second']->enrollmentId, $next->fresh()->termEnrollment()->enrollmentId);

        try {
            Studentclearances::create([
                'studentId' => $scene['student']->studentId,
                'clearancePeriodId' => $next->clearancePeriodId,
                'overallStatus' => ClearanceOverallStatus::Pending,
            ]);
            $this->fail('A second clearance row for one student in one period slipped in, so "has this student cleared?" would answer with two verdicts.');
        } catch (QueryException $e) {
            // MySQL names the violated key in its error; SQLite names the columns. The suite runs on
            // the latter and the desk on the former, so the assertion follows whichever driver answered.
            $namesConstraint = DB::getDriverName() === 'sqlite'
                ? 'studentclearances.studentId, studentclearances.clearancePeriodId'
                : 'uq_student_clearanceperiod';

            $this->assertStringContainsStringIgnoringCase(
                $namesConstraint,
                $e->getMessage(),
                'The refusal came from something other than the unique key on (studentId, clearancePeriodId), so the rule above is not what stopped it.'
            );
        }

        $this->assertStringContainsString(
            'overallStatus !== ClearanceOverallStatus::Incomplete',
            (string) file_get_contents(base_path('app/Http/Controllers/Clearance/ClearanceController.php')),
            'generateSlip no longer refuses to re-issue over a clearance a desk has answered, so the store loses the verdict it already held.'
        );
    }
}
