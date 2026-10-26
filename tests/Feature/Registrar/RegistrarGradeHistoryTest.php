<?php

namespace Tests\Feature\Registrar;

use App\Enums\UnitType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Enrolledsubjects;
use App\Models\Enrollments;
use App\Models\Gradescale;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use App\Models\Subjects;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * §7.4 of the development prompt: the Registrar must be able to read a student's grade
 * records BY ACADEMIC YEAR AND SEMESTER, not only the term being signed — and a missing
 * grade must never count as a pass.
 *
 * The derived standing on the same page reads prior terms only, on purpose, because a
 * standing is decided before this term's grades exist. That left the desk holding the
 * conclusion with no way to see the trail it came from. These cases pin the trail.
 */
class RegistrarGradeHistoryTest extends TestCase
{
    use DatabaseTransactions;

    private int $courseId;

    private int $priorTermId;

    private int $currentTermId;

    private int $priorSubjectId;

    private int $secondSubjectId;

    private int $currentSubjectId;

    private ?Staffusers $registrarUser = null;

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
    }

    private function seedReferenceData(): void
    {
        Offices::insert([
            ['officeId' => 1, 'officeName' => 'Registrar'],
            ['officeId' => 4, 'officeName' => 'Department Evaluation'],
            ['officeId' => 6, 'officeName' => 'Admission Office'],
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

        $priorYear = Academicyears::create([
            'yearStart' => 2024,
            'yearEnd' => 2025,
            'yearLabel' => '2024-2025',
            'startDate' => '2024-06-01',
            'endDate' => '2025-05-31',
        ]);
        $currentYear = Academicyears::create([
            'yearStart' => 2025,
            'yearEnd' => 2026,
            'yearLabel' => '2025-2026',
            'startDate' => '2025-06-01',
            'endDate' => '2026-05-31',
        ]);

        $this->priorTermId = Academicterms::create([
            'academicYearId' => $priorYear->academicYearId,
            'semester' => '1st',
            'startDate' => '2024-06-01',
            'endDate' => '2024-10-31',
        ])->termId;
        $this->currentTermId = Academicterms::create([
            'academicYearId' => $currentYear->academicYearId,
            'semester' => '2nd',
            'startDate' => '2025-11-01',
            'endDate' => '2026-03-31',
        ])->termId;

        foreach (['CS101', 'CS102', 'CS103'] as $index => $code) {
            $subject = Subjects::create([
                'subjectCode' => $code,
                'subjectName' => 'Subject '.$code,
                'lectureUnits' => 3,
                'labUnits' => 1,
                'subjectType' => 'both',
            ]);

            if ($index === 0) {
                $this->priorSubjectId = $subject->subjectId;
            } elseif ($index === 1) {
                $this->secondSubjectId = $subject->subjectId;
            } else {
                $this->currentSubjectId = $subject->subjectId;
            }
        }

        $this->courseId = $course->courseId;
    }

    private function registrar(): Staffusers
    {
        if ($this->registrarUser !== null) {
            return $this->registrarUser;
        }

        $staff = Staffusers::factory()->make([
            'officeId' => 1,
            'role' => 'officeHead',
            'employeeNo' => 'EMP-GH-'.uniqid(),
            'username' => 'gh_registrar_'.uniqid(),
            'email' => 'gh_registrar_'.uniqid().'@example.com',
        ]);
        unset($staff->remember_token);
        $staff->save();
        $staff->assignRole('RegistrarApprover');

        return $this->registrarUser = $staff;
    }

    /**
     * A student with one finished enrollment in the prior year and the enrollment the
     * Registrar is now looking at, in the current year.
     *
     * @return array{0: Enrollments, 1: Enrollments}
     */
    private function twoTermStudent(): array
    {
        $student = Students::create([
            'schoolIdNumber' => 'GH-'.uniqid(),
            'lastName' => 'History',
            'firstName' => 'Student',
            'middleName' => 'T',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'telephoneNumber' => null,
            'email' => 'gh_'.uniqid().'@example.com',
            'username' => 'gh_student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        // enrollments.evaluatedBy is NOT NULL on this schema: a record only exists on a
        // desk's say-so, so the fixture states whose.
        $make = fn (int $termId, int $yearLevel, string $status) => Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $this->courseId,
            'termId' => $termId,
            'admissionId' => null,
            'yearLevel' => $yearLevel,
            'studentType' => 'continuing',
            'evaluatedBy' => $this->registrar()->userId,
            'enrollmentType' => $yearLevel === 1 ? 'new' : 'old',
            'academicStanding' => 'regular',
            'enrollmentStatus' => $status,
        ]);

        $prior = $make($this->priorTermId, 1, 'paid');
        $current = $make($this->currentTermId, 2, 'assessed');

        Enrolledsubjects::create([
            'enrollmentId' => $prior->enrollmentId,
            'subjectId' => $this->priorSubjectId,
            'status' => 'confirmed',
            'grade' => 1.5,
        ]);
        Enrolledsubjects::create([
            'enrollmentId' => $prior->enrollmentId,
            'subjectId' => $this->secondSubjectId,
            'status' => 'confirmed',
            'grade' => null,
        ]);
        Enrolledsubjects::create([
            'enrollmentId' => $current->enrollmentId,
            'subjectId' => $this->currentSubjectId,
            'status' => 'proposed',
            'grade' => null,
        ]);

        return [$prior, $current];
    }

    /**
     * @return array<string, mixed>
     */
    private function historyProps(Enrollments $enrollment, array $query = []): array
    {
        $response = $this->actingAs($this->registrar())->get(route('registrar.show', ['enrollment' => $enrollment->enrollmentId] + $query));
        $response->assertOk();

        return $response->viewData('page')['props']['gradeHistory'];
    }

    #[Test]
    public function the_registrar_desk_holds_the_read_the_panel_depends_on(): void
    {
        // Stated out loud because every case below assumes it: the record page is gated by
        // evaluation.view, and a test that quietly granted it would prove nothing about the
        // desk that actually sits there.
        $this->assertTrue($this->registrar()->hasPermissionTo('evaluation.view'));
    }

    #[Test]
    public function the_panel_lists_every_term_the_student_has_been_loaded_for_not_only_the_current_one(): void
    {
        [, $current] = $this->twoTermStudent();

        $history = $this->historyProps($current);

        $this->assertCount(3, $history['rows']);
        $this->assertSame(3, $history['counts']['subjects']);
        $this->assertSame(2, $history['counts']['terms'], 'both the prior year and the term under review are on the page');

        $termLabels = array_unique(array_column($history['rows'], 'termLabel'));
        $this->assertContains('1st 2024-2025', $termLabels);
        $this->assertContains('2nd 2025-2026', $termLabels);

        $underReview = array_filter($history['rows'], fn (array $r) => $r['isTermUnderReview']);
        $this->assertCount(1, $underReview, 'only the enrollment being signed is marked as this term');
    }

    #[Test]
    public function the_academic_year_filter_narrows_the_rows_and_the_value_applied_comes_back(): void
    {
        [, $current] = $this->twoTermStudent();
        $priorYearId = Academicterms::find($this->priorTermId)->academicYearId;

        $history = $this->historyProps($current, ['academicYearId' => $priorYearId]);

        $this->assertCount(2, $history['rows']);
        $this->assertSame($priorYearId, $history['applied']['academicYearId']);
        $this->assertNull($history['applied']['semester']);
        $this->assertSame(
            [$priorYearId],
            array_unique(array_column($history['rows'], 'academicYearId')),
            'a filter that returned another year would reset the control while leaving the list narrowed'
        );

        $semesters = array_unique(array_column($history['rows'], 'semester'));
        $this->assertSame(['1st'], $semesters);
    }

    #[Test]
    public function the_filters_only_offer_the_years_and_semesters_this_students_records_can_answer(): void
    {
        [, $current] = $this->twoTermStudent();

        $history = $this->historyProps($current);

        $offeredYears = array_column($history['academicYears'], 'value');
        $this->assertCount(2, $offeredYears);
        $this->assertSame(['1st', '2nd'], array_column($history['semesters'], 'value'));

        // A third year exists on the calendar and this student has no row in it, so it must
        // not be selectable — choosing it could only ever paint an empty table.
        $uninhabitedYear = Academicyears::create([
            'yearStart' => 2026,
            'yearEnd' => 2027,
            'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01',
            'endDate' => '2027-05-31',
        ]);
        $after = $this->historyProps($current);
        $this->assertNotContains($uninhabitedYear->academicYearId, array_column($after['academicYears'], 'value'));
    }

    #[Test]
    public function a_subject_with_no_grade_recorded_is_never_counted_as_a_pass(): void
    {
        [, $current] = $this->twoTermStudent();

        $history = $this->historyProps($current);

        $this->assertSame(1, $history['counts']['graded']);
        $this->assertSame(2, $history['counts']['ungraded']);
        $this->assertSame(1, $history['counts']['passed']);
        $this->assertSame(0, $history['counts']['failed']);

        $blank = array_values(array_filter(
            $history['rows'],
            fn (array $r) => $r['subjectCode'] === 'CS102'
        ));
        $this->assertCount(1, $blank);
        $this->assertNull($blank[0]['grade']);
        $this->assertNull($blank[0]['result'], 'an ungraded row carries no result at all');

        $proposed = array_values(array_filter(
            $history['rows'],
            fn (array $r) => $r['subjectCode'] === 'CS103'
        ));
        $this->assertSame('proposed', $proposed[0]['loadStatus'], 'a subject still proposed travels with its own status, never as an official grade');
    }

    #[Test]
    public function the_result_is_read_from_the_grade_scale_on_file_and_the_assumed_line_is_named_when_none_is(): void
    {
        [, $current] = $this->twoTermStudent();

        // No gradescale rows: the service falls back to 3.00 and must SAY it is not the
        // school's own line rather than implying that it is.
        $assumed = $this->historyProps($current);
        $this->assertFalse($assumed['hasGradeScale']);
        $this->assertSame(3.0, $assumed['passingCeiling']);

        $cs101Assumed = array_values(array_filter(
            $assumed['rows'],
            fn (array $r) => $r['subjectCode'] === 'CS101'
        ))[0];
        $this->assertSame('pass', $cs101Assumed['result']);

        // Philippine scale, inverted: 1.00 is best, and a passing band ending at 2.50 makes a
        // 2.70 a failure the table decides — not a constant in code.
        Gradescale::create(['minGrade' => 1.00, 'maxGrade' => 2.50, 'isPassing' => true, 'description' => 'Up to 2.50 passes']);
        Gradescale::create(['minGrade' => 2.60, 'maxGrade' => 5.00, 'isPassing' => false, 'description' => 'Above 2.50 fails']);
        Enrolledsubjects::where('subjectId', $this->priorSubjectId)->update(['grade' => 2.70]);

        $measured = $this->historyProps($current);
        $this->assertTrue($measured['hasGradeScale']);
        $this->assertSame(2.5, $measured['passingCeiling']);

        $cs101 = array_values(array_filter(
            $measured['rows'],
            fn (array $r) => $r['subjectCode'] === 'CS101'
        ))[0];
        $this->assertSame(2.7, $cs101['grade']);
        $this->assertSame('fail', $cs101['result']);
        $this->assertSame(0, $measured['counts']['passed']);
        $this->assertSame(1, $measured['counts']['failed']);
    }

    #[Test]
    public function an_evaluator_seated_in_another_college_cannot_open_the_record_by_typing_its_id(): void
    {
        [, $current] = $this->twoTermStudent();

        $otherUnit = Academicunits::create([
            'unitCode' => 'COE',
            'unitName' => 'College of Engineering',
            'unitType' => UnitType::College,
        ]);

        $staff = Staffusers::factory()->make([
            'officeId' => 4,
            'unitId' => $otherUnit->unitId,
            'role' => 'officeHead',
            'employeeNo' => 'EMP-GH2-'.uniqid(),
            'username' => 'gh_other_'.uniqid(),
            'email' => 'gh_other_'.uniqid().'@example.com',
        ]);
        unset($staff->remember_token);
        $staff->save();
        $staff->assignRole('DeptEvaluator');

        $this->assertTrue($staff->hasPermissionTo('evaluation.view'), 'the right is present, so the college boundary is what refuses');

        $this->actingAs($staff)
            ->get(route('registrar.show', ['enrollment' => $current->enrollmentId]))
            ->assertForbidden();
    }
}
