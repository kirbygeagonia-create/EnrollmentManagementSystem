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
 * §25.4 P-19. The school's own clearance slip — the student's copy — marks itself `(Student's Copy)` in its
 * title and leads the program with its code, "(BSIT) Bachelor of Science in Information Technology". The
 * paper this system printed did neither, so a student holding it could not tell whether it was the copy the
 * office keeps, and a window reading the program name could not see which program it was.
 *
 * Ruled 2026-10-23: keep the nine-office table — the desks sign against it — and add the copy designation and
 * the code to the face. Both print surfaces changed together, because the desk page and the generated PDF are
 * the same promise; the JSX half is pinned by reading its source, since this repository has no JS test runner.
 *
 * The body is read from `<body>` onward throughout: a `<title>` element is metadata, not paper, and it has
 * carried the words "Clearance Slip" the whole time — so an assertion that only asks whether the string is
 * present anywhere would pass on a slip that prints no designation at all.
 */
class ClearanceSlipCopyDesignationTest extends TestCase
{
    use RefreshDatabase;

    private const COPY_MARK = "Clearance Slip (Student's Copy)";

    private array $scene = [];

    /**
     * One student, one enrollment in one term, and a second term holding none — the term whose slip must
     * still say whose copy it is while printing N/A for a program it cannot name.
     *
     * @return array{student: Students, clearance: Studentclearances, emptyClearance: Studentclearances, course: Courses}
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

        $unit = Academicunits::create(['unitName' => 'College of Information and Communication Technology', 'unitType' => 'college']);
        $course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSIT',
            'courseName' => 'Bachelor of Science in Information Technology',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);
        $major = Majors::create(['courseId' => $course->courseId, 'majorName' => 'No Specialization']);

        $year = fn (string $label): int => DB::table('academicyears')->insertGetId([
            'yearLabel' => $label,
            'startDate' => '2025-08-01',
            'endDate' => '2026-05-31',
        ]);
        $term = fn (int $yearId, Semester $semester, string $start, string $end): Academicterms => Academicterms::create([
            'academicYearId' => $yearId,
            'semester' => $semester,
            'startDate' => $start,
            'endDate' => $end,
        ]);

        $yearId = $year('2025-2026');
        $clearedTerm = $term($yearId, Semester::Second, '2026-01-05', '2026-05-15');
        $bareTerm = $term($yearId, Semester::First, '2025-08-01', '2025-12-15');

        $student = Students::create([
            'schoolIdNumber' => 'P19-'.uniqid(),
            'lastName' => 'Villanueva',
            'firstName' => 'Ana',
            'middleName' => 'C',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-05-12',
            'birthplace' => 'Tupi',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'status' => 'active',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'telephoneNumber' => null,
            'semestersCompleted' => 4,
            'yearsInInstitution' => 2,
            'email' => 'p19_'.uniqid().'@example.com',
            'username' => 'p19_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
        ]);

        $desk = fn (int $officeId, string $tag): Staffusers => Staffusers::factory()->create([
            'officeId' => $officeId,
            'role' => 'officeHead',
            'employeeNo' => $tag.'-'.uniqid(),
            'username' => $tag.'_'.uniqid(),
            'email' => $tag.'_'.uniqid().'@example.com',
        ]);

        Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $course->courseId,
            'majorId' => $major->majorId,
            'termId' => $clearedTerm->termId,
            'yearLevel' => 2,
            'admissionId' => null,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => EnrollmentType::New,
            'academicStanding' => 'regular',
            'enrollmentStatus' => 'enrolled',
            'evaluatedBy' => $desk(4, 'p19_eval')->userId,
            'registrarProcessedBy' => $desk(1, 'p19_reg')->userId,
            'enrolledDate' => now(),
            'formIssuedDate' => now()->toDateString(),
        ]);

        $this->scene = [
            'student' => $student,
            'course' => $course,
            'clearance' => $this->clearanceOn($student->studentId, $clearedTerm),
            'emptyClearance' => $this->clearanceOn($student->studentId, $bareTerm),
        ];

        return $this->scene;
    }

    private function clearanceOn(int $studentId, Academicterms $term): Studentclearances
    {
        $period = Clearanceperiods::create([
            'termId' => $term->termId,
            'clearanceStartDate' => '2026-06-01',
            'clearanceEndDate' => '2026-06-30',
            'periodStatus' => ClearancePeriodStatus::Open,
        ]);

        return Studentclearances::create([
            'studentId' => $studentId,
            'clearancePeriodId' => $period->clearancePeriodId,
            'overallStatus' => ClearanceOverallStatus::Pending,
        ]);
    }

    /** The whole document: head and paper. Callers split on `<body` when they mean the page. */
    private function rendered(Studentclearances $clearance): string
    {
        $clearance->load(['student.enrollments.course', 'clearancePeriod.term.academicYear', 'approvals.requirement.office']);

        return (string) view('prints.clearance-slip', ['clearance' => $clearance])->render();
    }

    private function printedBody(Studentclearances $clearance): string
    {
        $html = $this->rendered($clearance);
        $body = substr($html, (int) strpos($html, '<body'));

        $this->assertNotSame($html, $body, 'The slip renders no <body>, so there is no printed page to read a claim from.');

        return $body;
    }

    #[Test]
    public function test_the_printed_slip_says_whose_copy_it_is(): void
    {
        $html = $this->rendered($this->scene()['clearance']);
        $at = strpos($html, '<body');
        $this->assertNotSame(false, $at, 'The slip renders no <body>, so there is no printed page to read a claim from.');

        $this->assertStringContainsString(
            self::COPY_MARK,
            substr($html, (int) $at),
            'P-19 was ruled 2026-10-23: the school’s paper marks itself (Student\'s Copy), and a student holding an '
                .'unmarked slip cannot tell it from the copy the office keeps.'
        );

        $this->assertStringNotContainsString(
            "(Student's Copy)",
            substr($html, 0, (int) $at),
            'The designation now reaches the paper through the <title> as well, so the assertion above could be '
                .'satisfied by metadata alone — read it from <body> onward, which is what a window sees.'
        );
    }

    #[Test]
    public function test_the_program_is_led_by_its_course_code_the_way_the_school_writes_it(): void
    {
        $body = $this->printedBody($this->scene()['clearance']);

        $this->assertStringContainsString(
            '(BSIT) Bachelor of Science in Information Technology',
            $body,
            'The school’s slip leads the program with its code in parentheses; the paper this system prints named '
                .'the program without the code, so P-19’s ruling covers the code as well as the copy mark.'
        );
        $this->assertStringContainsString(
            'Year 2',
            $body,
            'The level vocabulary is the one formatYearLevel and the evaluator’s picker already agree on; the '
                .'ruling added the code, not a second spelling of the year level.'
        );
    }

    /**
     * The desk page and the generated PDF are one promise about one paper. Nothing shares their markup, so
     * this reads the JSX source — the only instrument that can see the screen half of the ruling.
     */
    #[Test]
    public function test_the_desk_page_marks_the_same_copy_and_names_the_same_program(): void
    {
        $jsx = (string) file_get_contents(base_path('resources/js/Pages/Clearance/PrintSlip.jsx'));

        $this->assertStringContainsString(
            'title="'.self::COPY_MARK.'"',
            $jsx,
            'The desk screen no longer carries the copy designation the generated PDF prints, so the two surfaces '
                .'of one paper disagree — which is the P-16 class of defect, on the slip this time.'
        );
        $this->assertMatchesRegularExpression(
            '/\(\$\{[^}]*courseCode\}\)[^`]*\$\{[^}]*courseName\}/',
            $jsx,
            'The desk screen no longer builds the program as "(CODE) Name", so the screen shows a different '
                .'program string from the PDF a student walks away with.'
        );
    }

    /**
     * P-17's branch, re-read against P-19: a slip whose term holds no enrollment prints N/A for the program
     * and must still say whose copy it is. A code prefix built unconditionally would print "( )" or leak the
     * null into the label, which is the empty-conditional class lane 67 found on the enrollment form.
     */
    #[Test]
    public function test_a_slip_that_names_no_program_still_marks_its_copy(): void
    {
        $body = $this->printedBody($this->scene()['emptyClearance']);

        $this->assertStringContainsString(self::COPY_MARK, $body);

        $this->assertSame(
            1,
            preg_match('/Course & Year:<\/span>\s*<span class="info-value">N\/A - Year N\/A<\/span>/', $body),
            'The program row does not read exactly "N/A - Year N/A". An unconditional code prefix is the shape '
                .'that breaks here — it prints "( )" or "(null)" over a term that holds no enrollment, which is '
                .'the empty-conditional class lane 67 found on the enrollment form.'
        );
        $this->assertStringNotContainsString('( )', $body);
        $this->assertStringNotContainsString('()', $body);
    }
}
