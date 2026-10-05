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
 * G-2, ruled 2026-10-04: the year level comes from the student's record.
 *
 * Admission used to write `yearLevel => 1` for every enrollment it created and nothing
 * revisited it, so a student returning after a year anywhere was placed one year short —
 * and the year level is what the curriculum lookup, the load band and the fee sheet are all
 * read against. The rule now counts completed YEARS rather than records, because a normal
 * year is two semesters: two finished terms in one academic year are one year finished, not
 * two. A term still short of `enrolled` has not been completed and a dropped one never
 * happened, matching the seat guard's reading; the term being entered is excluded because it
 * is the year ahead, not one behind.
 *
 * The desk still overrides it — `decideStanding` takes a level, and the issue form simply
 * opens on the computed number instead of a blank.
 */
class YearLevelDerivationTest extends TestCase
{
    use RefreshDatabase;

    private Courses $course;

    private Students $student;

    private Staffusers $evaluator;

    /** @var array<string, Academicterms> */
    private array $terms = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        foreach ([1, 2, 3, 4, 5, 6, 11, 22] as $officeId) {
            Offices::firstOrCreate(['officeId' => $officeId], ['officeName' => 'Office '.$officeId]);
        }

        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        $unit = Academicunits::create(['unitName' => 'College of Computer Studies', 'unitType' => 'college']);

        $this->course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCS',
            'courseName' => 'Bachelor of Science in Computer Science',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);

        $this->student = Students::create([
            'schoolIdNumber' => 'LEVEL-'.uniqid(),
            'lastName' => 'Returnee',
            'firstName' => 'Student',
            'middleName' => 'A',
            'suffix' => 'N/A',
            'gender' => 'female',
            'birthdate' => '2003-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
            'email' => 'level_'.uniqid().'@example.com',
            'username' => 'level_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->evaluator = $this->staffInOffice(OfficeId::Guidance->value, 'DeptEvaluator');
    }

    private function staffInOffice(int $officeId, string $spatieRole): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => $officeId,
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-LEVEL-'.uniqid(),
            'username' => 'level_office'.$officeId.'_'.uniqid(),
            'email' => 'level_office'.$officeId.'_'.uniqid().'@example.com',
        ]);
        $staff->assignRole($spatieRole);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    private function term(string $yearLabel, string $semester): Academicterms
    {
        $key = $yearLabel.'-'.$semester;

        if (isset($this->terms[$key])) {
            return $this->terms[$key];
        }

        $startYear = substr($yearLabel, 0, 4);
        $nextYear = (string) ((int) $startYear + 1);

        $year = Academicyears::firstOrCreate(
            ['yearLabel' => $yearLabel],
            ['startDate' => $startYear.'-06-01', 'endDate' => $nextYear.'-05-31']
        );

        return $this->terms[$key] = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => $semester,
            'startDate' => $startYear.'-06-01',
            'endDate' => $nextYear.'-03-31',
        ]);
    }

    private function enroll(Academicterms $term, int $yearLevel, EnrollmentStatus $status): Enrollments
    {
        return Enrollments::create([
            'studentId' => $this->student->studentId,
            'courseId' => $this->course->courseId,
            'termId' => $term->termId,
            'yearLevel' => $yearLevel,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => EnrollmentType::New,
            'enrollmentStatus' => $status,
            'evaluatedBy' => $this->evaluator->userId,
        ]);
    }

    #[Test]
    public function a_student_with_no_history_is_placed_at_year_one(): void
    {
        $this->assertSame(1, Enrollments::derivedYearLevel((int) $this->student->studentId));
    }

    #[Test]
    public function a_completed_year_places_the_student_in_the_next_one(): void
    {
        $this->enroll($this->term('2025-2026', '1st'), 1, EnrollmentStatus::Enrolled);

        $this->assertSame(2, Enrollments::derivedYearLevel((int) $this->student->studentId));
    }

    #[Test]
    public function two_semesters_of_one_year_are_one_year_completed_not_two(): void
    {
        // This is the difference between counting records and counting years. A normal
        // academic year is two terms, so a student who finished both is entering year 2.
        $this->enroll($this->term('2025-2026', '1st'), 1, EnrollmentStatus::Enrolled);
        $this->enroll($this->term('2025-2026', '2nd'), 1, EnrollmentStatus::Enrolled);

        $this->assertSame(2, Enrollments::derivedYearLevel((int) $this->student->studentId));
    }

    #[Test]
    public function each_completed_year_adds_one_level(): void
    {
        $this->enroll($this->term('2024-2025', '1st'), 1, EnrollmentStatus::Enrolled);
        $this->enroll($this->term('2025-2026', '1st'), 2, EnrollmentStatus::Enrolled);
        $this->enroll($this->term('2025-2026', '2nd'), 2, EnrollmentStatus::Enrolled);

        $this->assertSame(3, Enrollments::derivedYearLevel((int) $this->student->studentId));
    }

    #[Test]
    public function a_term_the_student_never_finished_has_not_been_completed(): void
    {
        // `assessed` means the record is still being worked on; only `enrolled` says the
        // student sat the year through. Placing them forward on an unfinished year would
        // price them against a load they were never signed into.
        $this->enroll($this->term('2025-2026', '1st'), 1, EnrollmentStatus::Assessed);
        $this->enroll($this->term('2025-2026', '2nd'), 1, EnrollmentStatus::Paid);

        $this->assertSame(1, Enrollments::derivedYearLevel((int) $this->student->studentId));
    }

    #[Test]
    public function a_dropped_year_does_not_count_toward_the_level(): void
    {
        $this->enroll($this->term('2025-2026', '1st'), 1, EnrollmentStatus::Dropped);
        $this->enroll($this->term('2025-2026', '2nd'), 1, EnrollmentStatus::Enrolled);

        $this->assertSame(2, Enrollments::derivedYearLevel((int) $this->student->studentId));
    }

    #[Test]
    public function the_term_being_entered_is_not_counted_as_one_left_behind(): void
    {
        $next = $this->term('2026-2027', '1st');

        $this->enroll($this->term('2025-2026', '1st'), 1, EnrollmentStatus::Enrolled);
        $this->enroll($next, 1, EnrollmentStatus::Enrolled);

        $this->assertSame(2, Enrollments::derivedYearLevel((int) $this->student->studentId, (int) $next->termId));
    }

    #[Test]
    public function a_long_history_stops_at_the_top_year_the_screens_offer(): void
    {
        foreach (['2021-2022', '2022-2023', '2023-2024', '2024-2025', '2025-2026'] as $year) {
            $this->enroll($this->term($year, '1st'), 1, EnrollmentStatus::Enrolled);
        }

        $this->assertSame(5, Enrollments::derivedYearLevel((int) $this->student->studentId));
    }

    #[Test]
    public function a_second_semester_of_the_same_year_does_not_advance_the_level(): void
    {
        // Measured against the live demo dataset, where two students seated this way read
        // as year 2 while still inside year 1. A year counts as completed only when it
        // closed before the year being entered opened.
        $first = $this->term('2025-2026', '1st');
        $second = $this->term('2025-2026', '2nd');

        $this->enroll($first, 1, EnrollmentStatus::Enrolled);

        $this->assertSame(
            1,
            Enrollments::derivedYearLevel((int) $this->student->studentId, (int) $second->termId),
            'Entering the 2nd semester of the year already begun stays in that year'
        );

        // The same record does carry a finished year once the next one opens.
        $this->assertSame(2, Enrollments::derivedYearLevel((int) $this->student->studentId, (int) $this->term('2026-2027', '1st')->termId));
    }

    #[Test]
    public function the_page_wide_read_agrees_with_the_single_record_read(): void
    {
        // The issue form reads every returning student at once, so the grouped query has
        // to give the same answer the per-student rule gives — including year 1 for a
        // student the query has no row for at all.
        $this->enroll($this->term('2024-2025', '1st'), 1, EnrollmentStatus::Enrolled);
        $this->enroll($this->term('2025-2026', '1st'), 2, EnrollmentStatus::Enrolled);

        $studentId = (int) $this->student->studentId;

        $this->assertSame(3, Enrollments::derivedYearLevel($studentId));
        $this->assertSame([$studentId => 3], Enrollments::derivedYearLevels([$studentId]));
        $this->assertSame(
            [$studentId => 3, $studentId + 1 => 1],
            Enrollments::derivedYearLevels([$studentId, $studentId + 1])
        );
        $this->assertSame([], Enrollments::derivedYearLevels([]));
    }

    #[Test]
    public function the_issue_form_opens_on_the_computed_level(): void
    {
        $this->enroll($this->term('2025-2026', '1st'), 1, EnrollmentStatus::Enrolled);
        $this->enroll($this->term('2025-2026', '2nd'), 1, EnrollmentStatus::Enrolled);

        $this->actingAs($this->evaluator)
            ->get(route('evaluation.index'))
            ->assertInertia(fn ($page) => $page
                ->where('returningStudents.0.studentId', $this->student->studentId)
                ->where('returningStudents.0.derivedYearLevel', 2)
            );
    }

    #[Test]
    public function issuing_a_returning_student_at_the_computed_level_creates_that_record(): void
    {
        $this->enroll($this->term('2025-2026', '1st'), 1, EnrollmentStatus::Enrolled);

        $next = $this->term('2026-2027', '1st');

        $this->actingAs($this->evaluator)
            ->post(route('evaluation.store'), [
                'studentId' => $this->student->studentId,
                'termId' => $next->termId,
                'courseId' => $this->course->courseId,
                'yearLevel' => Enrollments::derivedYearLevel((int) $this->student->studentId, (int) $next->termId),
                'studentType' => StudentType::Continuing->value,
            ])
            ->assertSessionHasNoErrors();

        $issued = Enrollments::where('studentId', $this->student->studentId)
            ->where('termId', $next->termId)
            ->sole();

        $this->assertSame(2, (int) $issued->yearLevel);
    }
}
