<?php

namespace Tests\Feature\Exam;

use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\UnitType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Examresults;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use App\Support\SchoolTime;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * §7.2 of the development prompt, ruled by the owner 2026-10-26: the Registrar's final
 * confirmation of an examination result is a recorded state.
 *
 * Before this batch the recorded pass was final the moment it was typed, so the file could
 * not say who adopted a score or when, and the Admission, Evaluation and department-desk
 * gates all read whatever the scoring office had entered. The ruling separates the two acts:
 * Guidance or the department records, the Registrar confirms, and every gate asks the
 * confirmed value.
 *
 * These cases pin the seams a later change is most likely to open, each one in both
 * directions where a single reading would prove nothing:
 *   - a row arrives provisional *and* carries no verifier, so the default cannot be accused
 *     of having stamped a signature nobody made;
 *   - the confirming act writes the desk and the school's civil day, and a desk without the
 *     right is refused outright (assertForbidden, never assertSessionHasNoErrors — a policy
 *     403 satisfies the second as happily as a green run);
 *   - the gates that ask the confirmed value say *provisional*, not *missing*, because the
 *     two conditions send different offices to work;
 *   - re-recording a result takes it back to provisional and drops the signature that
 *     covered the earlier figure;
 *   - a delete guard keeps counting every row, adopted or not, because a refusal still names
 *     the paper it refused.
 */
class ExamResultVerificationTest extends TestCase
{
    use DatabaseTransactions;

    private int $entranceCourseId;

    private int $termId;

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
            ['officeId' => 1, 'officeName' => 'Registrar'],
            ['officeId' => 4, 'officeName' => 'Department Evaluation'],
            ['officeId' => 6, 'officeName' => 'Admission Office'],
            ['officeId' => 7, 'officeName' => 'Guidance / Entrance Exam'],
        ]);

        Religions::create(['religionId' => 1, 'religionName' => 'Roman Catholic']);

        $unit = Academicunits::create([
            'unitCode' => 'CCS',
            'unitName' => 'College of Computer Studies',
            'unitType' => UnitType::College,
        ]);

        $academicYear = Academicyears::create([
            'yearStart' => 2026,
            'yearEnd' => 2027,
            'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01',
            'endDate' => '2027-05-31',
        ]);
        Academicterms::create([
            'academicYearId' => $academicYear->academicYearId,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ]);

        Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCRIM',
            'courseName' => 'BS Criminology (entrance exam required)',
            'requiresEntranceExam' => true,
            'requiresRetentionExam' => false,
        ]);
        Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSIT',
            'courseName' => 'BS Information Technology (no exams)',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);

        $this->entranceCourseId = (int) Courses::where('courseCode', 'BSCRIM')->value('courseId');
        $this->termId = (int) Academicterms::value('termId');

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RbacSeeder', '--database' => 'sqlite']);
    }

    private function staffWithRole(string $spatieRole, int $officeId): Staffusers
    {
        $staff = Staffusers::factory()->make([
            'officeId' => $officeId,
            'role' => 'staff',
            'employeeNo' => 'EMP-G4-'.uniqid(),
            'username' => 'g4_'.strtolower($spatieRole).'_'.uniqid(),
            'email' => 'g4_'.strtolower($spatieRole).'_'.uniqid().'@example.com',
        ]);
        unset($staff->remember_token);
        $staff->save();

        $staff->assignRole($spatieRole);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    private function createStudent(): Students
    {
        return Students::create([
            'schoolIdNumber' => 'G4-'.uniqid(),
            'lastName' => 'Examinee',
            'firstName' => 'Test',
            'middleName' => 'E',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'email' => 'g4_student_'.uniqid().'@example.com',
            'username' => 'g4_student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);
    }

    private function recordGeneral(Staffusers $guidance, Students $student, string $result): void
    {
        $this->actingAs($guidance)
            ->post(route('exam.general.record'), [
                'studentId' => $student->studentId,
                'courseId' => $this->entranceCourseId,
                'termId' => $this->termId,
                'examResult' => $result,
                'examDate' => now()->toDateString(),
            ])
            ->assertRedirect(route('exam.index'));
    }

    private function confirm(Staffusers $registrar, Examresults $row): TestResponse
    {
        return $this->actingAs($registrar)->put(route('exam.verify', $row));
    }

    private function generalRow(Students $student): Examresults
    {
        return Examresults::where('studentId', $student->studentId)
            ->where('examType', ExamType::General->value)
            ->sole();
    }

    #[Test]
    public function a_recorded_result_arrives_provisional_and_names_no_verifier(): void
    {
        $student = $this->createStudent();
        $this->recordGeneral($this->staffWithRole('GuidanceStaff', 7), $student, 'pass');

        $row = $this->generalRow($student);

        $this->assertSame(ExamStatus::Provisional, $row->resultStatus);
        $this->assertNull($row->verifiedBy, 'Nobody has adopted this row yet; the column cannot be invented.');
        $this->assertNull($row->verifiedDate);
    }

    #[Test]
    public function the_registrar_confirms_the_row_and_the_act_carries_that_desk_and_the_school_day(): void
    {
        $student = $this->createStudent();
        $registrar = $this->staffWithRole('RegistrarApprover', 1);
        $this->recordGeneral($this->staffWithRole('GuidanceStaff', 7), $student, 'pass');

        $this->confirm($registrar, $this->generalRow($student))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $row = $this->generalRow($student)->fresh();

        $this->assertSame(ExamStatus::Official, $row->resultStatus);
        $this->assertSame($registrar->userId, (int) $row->verifiedBy);
        // The day a Registrar confirms is the school's civil day, not the daemon's (lane 51).
        $this->assertSame(SchoolTime::today(), $row->verifiedDate->toDateString());
    }

    #[Test]
    public function the_desk_that_scored_the_paper_cannot_be_the_desk_that_adopts_it(): void
    {
        $student = $this->createStudent();
        $guidance = $this->staffWithRole('GuidanceStaff', 7);
        $this->recordGeneral($guidance, $student, 'pass');

        $this->confirm($guidance, $this->generalRow($student))->assertForbidden();

        $this->assertSame(ExamStatus::Provisional, $this->generalRow($student)->fresh()->resultStatus);
    }

    #[Test]
    public function a_paper_nobody_scored_cannot_be_made_official(): void
    {
        // examResult is a nullable enum: an unmarked paper is neither a passer nor a failure,
        // and adopting it would mint a school verdict out of an absence.
        $student = $this->createStudent();
        $row = Examresults::create([
            'studentId' => $student->studentId,
            'courseId' => $this->entranceCourseId,
            'termId' => $this->termId,
            'examStage' => ExamStage::Entrance,
            'examType' => ExamType::General,
            'examResult' => null,
            'examDate' => now()->toDateString(),
        ]);

        $this->confirm($this->staffWithRole('RegistrarApprover', 1), $row)
            ->assertSessionHasErrors('resultStatus');

        $this->assertSame(ExamStatus::Provisional, $row->fresh()->resultStatus);
    }

    #[Test]
    public function a_row_already_official_is_not_re_signed_and_says_so(): void
    {
        $student = $this->createStudent();
        $registrar = $this->staffWithRole('RegistrarApprover', 1);
        $this->recordGeneral($this->staffWithRole('GuidanceStaff', 7), $student, 'pass');
        $this->confirm($registrar, $this->generalRow($student));

        $row = $this->generalRow($student);
        $this->confirm($registrar, $row)->assertSessionHasErrors('resultStatus');

        $this->assertSame(
            $registrar->userId,
            (int) $row->fresh()->verifiedBy,
            'A second confirmation must not move the signature or its day.'
        );
    }

    #[Test]
    public function re_recording_a_result_sends_it_back_to_provisional_and_drops_the_signature(): void
    {
        $student = $this->createStudent();
        $guidance = $this->staffWithRole('GuidanceStaff', 7);
        $registrar = $this->staffWithRole('RegistrarApprover', 1);

        $this->recordGeneral($guidance, $student, 'pass');
        $this->confirm($registrar, $this->generalRow($student));
        $this->assertSame(ExamStatus::Official, $this->generalRow($student)->fresh()->resultStatus);

        // Re-recording corrects the same row (firstOrNew on the natural key) — and the figure
        // the Registrar signed is gone, so the signature has to go with it.
        $this->recordGeneral($guidance, $student, 'fail');

        $row = $this->generalRow($student)->fresh();
        $this->assertSame(ExamStatus::Provisional, $row->resultStatus);
        $this->assertNull($row->verifiedBy);
        $this->assertNull($row->verifiedDate);
        $this->assertSame(ExamResult::Fail, $row->examResult);
    }

    #[Test]
    public function the_departmental_record_refuses_an_unconfirmed_pass_and_names_the_pending_confirmation(): void
    {
        $student = $this->createStudent();
        $department = $this->staffWithRole('DeptEvaluator', 4);
        $registrar = $this->staffWithRole('RegistrarApprover', 1);
        $this->recordGeneral($this->staffWithRole('GuidanceStaff', 7), $student, 'pass');

        $this->actingAs($department)
            ->post(route('exam.course-specific.record'), [
                'studentId' => $student->studentId,
                'courseId' => $this->entranceCourseId,
                'termId' => $this->termId,
                'examResult' => 'pass',
                'examDate' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('generalExam');

        $this->assertSame(0, Examresults::where('examType', ExamType::CourseSpecific->value)->count());

        $this->confirm($registrar, $this->generalRow($student));

        $this->actingAs($department)
            ->post(route('exam.course-specific.record'), [
                'studentId' => $student->studentId,
                'courseId' => $this->entranceCourseId,
                'termId' => $this->termId,
                'examResult' => 'pass',
                'examDate' => now()->toDateString(),
            ])
            ->assertRedirect(route('exam.index'));

        $this->assertSame(1, Examresults::where('examType', ExamType::CourseSpecific->value)->count());
    }

    #[Test]
    public function the_passer_roster_states_which_passers_the_registrar_has_not_adopted(): void
    {
        $student = $this->createStudent();
        $department = $this->staffWithRole('DeptEvaluator', 4);
        $registrar = $this->staffWithRole('RegistrarApprover', 1);
        $this->recordGeneral($this->staffWithRole('GuidanceStaff', 7), $student, 'pass');

        // The row is listed even while provisional: hiding it would read as "this program has
        // no candidates", which is the defect C-4 was closed for.
        $this->actingAs($department)
            ->get(route('exam.students', [
                'type' => ExamType::CourseSpecific->value,
                'courseId' => $this->entranceCourseId,
                'termId' => $this->termId,
            ]))
            ->assertOk()
            ->assertJsonPath('students.0.resultStatus', ExamStatus::Provisional->value);

        $this->confirm($registrar, $this->generalRow($student));

        $this->actingAs($department)
            ->get(route('exam.students', [
                'type' => ExamType::CourseSpecific->value,
                'courseId' => $this->entranceCourseId,
                'termId' => $this->termId,
            ]))
            ->assertOk()
            ->assertJsonPath('students.0.resultStatus', ExamStatus::Official->value);
    }

    #[Test]
    public function the_confirming_desk_sees_every_row_and_is_offered_the_act(): void
    {
        $student = $this->createStudent();
        $this->recordGeneral($this->staffWithRole('GuidanceStaff', 7), $student, 'pass');
        Examresults::create([
            'studentId' => $student->studentId,
            'courseId' => $this->entranceCourseId,
            'termId' => $this->termId,
            'examStage' => ExamStage::Retention,
            'examType' => ExamType::CourseSpecific,
            'examResult' => ExamResult::Pass,
            'examDate' => now()->toDateString(),
        ]);

        $this->actingAs($this->staffWithRole('RegistrarApprover', 1))
            ->get(route('exam.index'))
            ->assertInertia(fn ($page) => $page
                ->where('can.verifyExam', true)
                ->has('exams.data', 2)
                ->where('stats.pending', 2)
            );

        // Guidance holds the view right but not the confirm right, and its own scope is the
        // School Entrance paper only — the retention row must not appear on its list.
        $this->actingAs($this->staffWithRole('GuidanceStaff', 7))
            ->get(route('exam.index'))
            ->assertInertia(fn ($page) => $page
                ->where('can.verifyExam', false)
                ->has('exams.data', 1)
                ->where('stats.pending', 1)
            );
    }

    #[Test]
    public function the_pending_tile_counts_the_whole_view_rather_than_the_page(): void
    {
        $registrar = $this->staffWithRole('RegistrarApprover', 1);
        $student = $this->createStudent();

        // paginate() answers 20 to a page, so a queue this long is the only way to see whether
        // the aggregate was cloned before it or after (lane 47).
        Examresults::insert(Collection::range(1, 25)->map(fn ($n) => [
            'studentId' => $student->studentId,
            'courseId' => $this->entranceCourseId,
            'termId' => $this->termId,
            'examStage' => ExamStage::Entrance->value,
            'examType' => ExamType::General->value,
            'examResult' => 'pass',
            'examDate' => now()->toDateString(),
            'resultStatus' => ExamStatus::Provisional->value,
            'verifiedBy' => null,
            'verifiedDate' => null,
        ])->all());

        $this->actingAs($registrar)
            ->get(route('exam.index'))
            ->assertInertia(fn ($page) => $page
                ->has('exams.data', 20)
                ->where('stats.total', 25)
                ->where('stats.pending', 25)
            );
    }

    #[Test]
    public function a_result_confirmed_before_this_column_existed_counts_and_shows_no_signer(): void
    {
        // The migration backfills the rows already on file so an admission approved on last
        // term's pass is not silently re-opened. Those rows are official with no verifier —
        // visible as a backfill, never disguised as a signature somebody made.
        $student = $this->createStudent();
        Examresults::create([
            'studentId' => $student->studentId,
            'courseId' => $this->entranceCourseId,
            'termId' => $this->termId,
            'examStage' => ExamStage::Entrance,
            'examType' => ExamType::General,
            'examResult' => ExamResult::Pass,
            'examDate' => now()->toDateString(),
            'resultStatus' => ExamStatus::Official,
        ]);

        $this->actingAs($this->staffWithRole('RegistrarApprover', 1))
            ->get(route('exam.index'))
            ->assertInertia(fn ($page) => $page
                ->where('exams.data.0.resultStatus', ExamStatus::Official->value)
                ->where('exams.data.0.verifiedByUser', null)
                ->where('stats.pending', 0)
            );
    }

    /**
     * The migration's data statement is the half that keeps five results already on file
     * working: an applicant approved on last term's pass must not wake up unexamined. The
     * column guards make `up()` safe to run again on a schema that already has them, which is
     * also how this case reaches a table that holds rows — the suite's own schemas are always
     * built empty, so nothing else in it could see the backfill at all.
     */
    #[Test]
    public function the_migration_adopts_the_rows_already_on_file_without_inventing_a_signer(): void
    {
        $student = $this->createStudent();
        $row = Examresults::create([
            'studentId' => $student->studentId,
            'courseId' => $this->entranceCourseId,
            'termId' => $this->termId,
            'examStage' => ExamStage::Entrance,
            'examType' => ExamType::General,
            'examResult' => ExamResult::Pass,
            'examDate' => now()->toDateString(),
        ]);
        $this->assertSame(ExamStatus::Provisional, $row->fresh()->resultStatus);

        $migration = require $this->migrationPath();
        $migration->up();

        $row = $this->generalRow($student)->fresh();
        $this->assertSame(ExamStatus::Official, $row->resultStatus);
        $this->assertNull($row->verifiedBy, 'The backfill is visible as a backfill, never as a signature.');
        $this->assertNull($row->verifiedDate);
    }

    private function migrationPath(): string
    {
        return dirname(__DIR__, 3).'/database/migrations/2026_10_26_1000000003_add_verification_to_examresults_table.php';
    }

    #[Test]
    public function a_program_referenced_only_by_an_unadopted_result_still_cannot_be_deleted(): void
    {
        // The delete guard counts EVERY examination row: a result the Registrar has not adopted
        // still names the program it was sat under, and dropping that program would leave the
        // refusal pointing at nothing.
        $guarding = Courses::create([
            'unitId' => Courses::find($this->entranceCourseId)->unitId,
            'courseCode' => 'G4A',
            'courseName' => 'BS Guarded By One Provisional Result',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);
        $loose = Courses::create([
            'unitId' => Courses::find($this->entranceCourseId)->unitId,
            'courseCode' => 'G4B',
            'courseName' => 'BS Nobody Has Sat',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);

        $student = $this->createStudent();
        Examresults::create([
            'studentId' => $student->studentId,
            'courseId' => $guarding->courseId,
            'termId' => $this->termId,
            'examStage' => ExamStage::Entrance,
            'examType' => ExamType::General,
            'examResult' => ExamResult::Pass,
            'examDate' => now()->toDateString(),
        ]);

        $admin = Staffusers::factory()->create(['role' => 'admin', 'unitId' => null]);
        $admin->assignRole('SysAdmin');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->actingAs($admin)
            ->delete(route('admin.reference-data.courses.destroy', $guarding))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('courses', ['courseId' => $guarding->courseId]);

        $this->actingAs($admin)
            ->delete(route('admin.reference-data.courses.destroy', $loose))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('courses', ['courseId' => $loose->courseId]);
    }
}
