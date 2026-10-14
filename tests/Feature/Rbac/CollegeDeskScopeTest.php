<?php

namespace Tests\Feature\Rbac;

use App\Enums\AdmissionStatus;
use App\Enums\ApplicantType;
use App\Enums\EnrollmentStatus;
use App\Enums\ShiftRequestStatus;
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
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The college boundary: a department desk is given its own college's students and applicants, and only
 * those.
 *
 * Ruled 2026-10-13, the day after the six per-college desks were seated. The ruling has two halves and the
 * first is the one a filter usually fails to prove: "each college department must receive **every** student
 * or applicant related to their college". So every test below asserts the desk's own rows are all there
 * before asserting another college's are gone — a suite that only ever counted absences would pass on a
 * rule that hides everything, including the desk's own work.
 *
 * The reach is defined by `staffusers.unitId`, not by office or role, because that is what the ruling is
 * about. An account with no college is bounded by nothing, and that is a decision rather than an oversight:
 * `office7_head`, the three seeded instructors, Guidance and the Registrar all have `unitId = null`, and
 * `office4_head` in particular holds 29 enrollments on the installed database — 15 still in progress, in
 * three different colleges. Bounding it would leave those papers with no desk able to finish them. The test
 * that pins the unbounded reach is therefore as load-bearing as the boundary tests.
 */
class CollegeDeskScopeTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $deskA;

    private Staffusers $deskB;

    private Staffusers $deskNeither;

    private Staffusers $deskWithoutCollege;

    private Courses $courseA;

    private Courses $courseB;

    private Courses $courseNeither;

    private Academicterms $term;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ([1, 2, 3, 4, 5, 6, 7, 11, 22] as $officeId) {
            Offices::firstOrCreate(['officeId' => $officeId], ['officeName' => 'Office '.$officeId]);
        }

        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        $year = Academicyears::create([
            'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01',
            'endDate' => '2027-03-31',
        ]);

        $this->term = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ]);

        // Three colleges rather than two, so that "not mine" is not the only reason a row can be absent:
        // the shift tests need a desk on neither side of a move.
        $this->courseA = $this->course('College of Test Arts', 'BTA');
        $this->courseB = $this->course('College of Test Sciences', 'BTS');
        $this->courseNeither = $this->course('College of Test Shipping', 'BTSp');

        $this->deskA = $this->desk('dept.arts', $this->courseA->unitId);
        $this->deskB = $this->desk('dept.sci', $this->courseB->unitId);
        $this->deskNeither = $this->desk('dept.ship', $this->courseNeither->unitId);
        $this->deskWithoutCollege = $this->desk('office7_head', null, StaffRole::OfficeHead->value);

        // Two pending loads in college A, not one: the completeness half is only tested if there is a
        // second row the desk could be wrongly missing.
        $this->enrollment($this->courseA, 'Alzon');
        $this->enrollment($this->courseA, 'Baccay');
        $this->enrollment($this->courseB, 'Cruz');

        $this->applicant($this->courseA, 'Delfin');
        $this->applicant($this->courseB, 'Espinas');
    }

    #[Test]
    public function the_evaluation_queue_gives_the_desk_every_load_of_its_college_and_none_of_another(): void
    {
        $mine = $this->queue($this->deskA);
        $theirs = $this->queue($this->deskB);
        $all = $this->queue($this->deskWithoutCollege);

        $this->assertCount(2, $mine, 'a college desk must receive every pending load of its own college');
        $this->assertCount(1, $theirs);
        $this->assertCount(3, $all, 'the desk with no college is bounded by nothing');

        // Named by program rather than by count alone — a count would pass on rows from the wrong college.
        $this->assertSame(['BTA'], $this->courseCodesIn($mine));
        $this->assertSame(['BTS'], $this->courseCodesIn($theirs));
        $this->assertSame(['BTA', 'BTS'], $this->courseCodesIn($all));
    }

    #[Test]
    public function the_issue_dropdown_and_the_policy_answer_for_the_same_programs(): void
    {
        $listed = $this->props($this->deskA, 'evaluation.index')['courses'];

        $this->assertSame(['BTA'], array_column($listed, 'courseCode'));

        // The dropdown hiding a program is not the authority; the policy is asked separately, and the two
        // must agree, or a desk that types the id meets a refusal the screen never warned it of.
        $foreign = Students::where('lastName', 'Cruz')->firstOrFail();

        $this->assertTrue(
            $this->deskA->can('create', [Enrollments::class, $foreign, $this->courseA]),
            'the accepting case must be proven before the refusals mean anything'
        );
        $this->assertFalse($this->deskA->can('create', [Enrollments::class, $foreign, $this->courseB]));
        $this->assertTrue($this->deskWithoutCollege->can('create', [Enrollments::class, $foreign, $this->courseB]),
            'office 7 without a college keeps the reach the walkthrough is written against');

        $this->actingAs($this->deskA)
            ->post(route('evaluation.store'), [
                'studentId' => $foreign->studentId,
                'termId' => $this->term->termId,
                'courseId' => $this->courseB->courseId,
                'yearLevel' => 1,
                'studentType' => StudentType::Continuing->value,
            ])
            ->assertForbidden();
    }

    #[Test]
    public function a_desk_cannot_read_another_colleges_student_in_the_directory_or_by_search(): void
    {
        $own = Students::where('lastName', 'Alzon')->firstOrFail();
        $foreign = Students::where('lastName', 'Cruz')->firstOrFail();

        $this->actingAs($this->deskA)->get(route('students.show', $own))->assertOk();
        $this->actingAs($this->deskA)->get(route('students.show', $foreign))->assertForbidden();
        $this->actingAs($this->deskWithoutCollege)->get(route('students.show', $foreign))->assertOk();

        // A search result that leads to a 403 is a dead end the desk discovers by clicking, so the row is
        // not offered at all. 'Stu' is the given name every fixture row carries, and the endpoint answers
        // with `fullName` in "Surname, Given M." order — the surname is what identifies the person here.
        $hits = fn (Staffusers $who) => collect($this->actingAs($who)
            ->get(route('students.quick-search', ['query' => 'Stu']))
            ->json('results'))
            ->map(fn ($r) => trim(explode(',', $r['fullName'])[0]))
            ->sort()->values()->all();

        $this->assertSame(['Alzon', 'Baccay', 'Delfin'], $hits($this->deskA));
        $this->assertSame(['Cruz', 'Espinas'], $hits($this->deskB));
        $this->assertCount(5, $hits($this->deskWithoutCollege));
    }

    #[Test]
    public function the_applicant_queue_is_the_college_being_applied_to(): void
    {
        $mine = $this->applicants($this->deskA);
        $theirs = $this->applicants($this->deskB);

        // An applicant is related to a college only through the program applied for, so the names are the
        // proof: `students` carries no course of its own.
        $this->assertSame(['Delfin'], $mine);
        $this->assertSame(['Espinas'], $theirs);
        $this->assertSame(['Delfin', 'Espinas'], $this->applicants($this->deskWithoutCollege));
        $this->assertSame([], $this->applicants($this->deskNeither), 'a college with nobody applying is told so');
    }

    #[Test]
    public function a_shift_paper_reaches_the_department_on_either_side_of_the_move(): void
    {
        DB::table('shiftingrequests')->insert([
            'studentId' => Students::where('lastName', 'Alzon')->value('studentId'),
            'currentCourseId' => $this->courseA->courseId,
            'targetCourseId' => $this->courseB->courseId,
            'willStatement' => 'Wants to study sciences.',
            'requestStatus' => ShiftRequestStatus::Pending->value,
            'requestedBy' => $this->deskA->userId,
            'requestedAt' => now(),
        ]);

        $seenBy = fn (Staffusers $who) => $this->props($who, 'shift.index')['requests']['data'];

        $this->assertCount(1, $seenBy($this->deskA), 'the college the student leaves endorses the paper');
        $this->assertCount(1, $seenBy($this->deskB), 'the college receiving the student must see it too');
        $this->assertCount(0, $seenBy($this->deskNeither), 'a college on neither side has no business with it');

        // The header counters answer to the same rule as the list under them, or the screen reports work it
        // is not showing — the disagreement this audit keeps finding on desk headers.
        $this->assertSame(0, $this->props($this->deskNeither, 'shift.index')['stats']['pending']);
        $this->assertSame(1, $this->props($this->deskB, 'shift.index')['stats']['pending']);
    }

    #[Test]
    public function the_exam_roster_refuses_a_program_of_another_college(): void
    {
        $picker = $this->actingAs($this->deskA)
            ->get(route('exam.create', ['type' => 'courseSpecific']))
            ->assertOk()
            ->viewData('page')['props']['courses'];

        $this->assertSame(['BTA'], array_column($picker, 'courseCode'));

        // Refused rather than answered with an empty list: a blank roster would tell the desk its college
        // has nobody, when the truth is that it asked for someone else's. The reason is what the boundary
        // is for, and both ways a screen can reach this are pinned: the page a desk is sent to prints it
        // (lane 28 — errors/403.blade.php used to interpolate nothing) and the JSON Exam/Create.jsx reads
        // carries it in `message`.
        $this->actingAs($this->deskA)
            ->get(route('exam.students', [
                'courseId' => $this->courseB->courseId,
                'termId' => $this->term->termId,
                'type' => 'courseSpecific',
            ]))
            ->assertForbidden()
            ->assertSee('Program “BTS” belongs to another college', false);

        $this->actingAs($this->deskA)
            ->getJson(route('exam.students', [
                'courseId' => $this->courseB->courseId,
                'termId' => $this->term->termId,
                'type' => 'courseSpecific',
            ]))
            ->assertForbidden()
            ->assertJsonPath('message', 'Program “BTS” belongs to another college; a department records its own.');

        // The control on the same route, so the refusal above is the boundary and not a broken endpoint.
        $this->actingAs($this->deskA)
            ->get(route('exam.students', [
                'courseId' => $this->courseA->courseId,
                'termId' => $this->term->termId,
                'type' => 'courseSpecific',
            ]))
            ->assertOk();
    }

    #[Test]
    public function a_desk_files_a_shift_only_where_its_college_is_one_of_the_two_sides(): void
    {
        $own = Students::where('lastName', 'Alzon')->firstOrFail();
        $foreign = Students::where('lastName', 'Cruz')->firstOrFail();

        // The accepting case first, and it is the awkward one: my student leaving *for another college* is
        // legitimate, because the college on one side of the move is mine.
        $this->actingAs($this->deskA)
            ->post(route('shift.store'), [
                'studentId' => $own->studentId,
                'targetCourseId' => $this->courseB->courseId,
                'willStatement' => 'I would like to move to the sciences programme next term.',
            ])
            ->assertSessionHasNoErrors();

        // Neither side is mine, so the paper would be filed by a desk that can never see it again. The
        // pick-list already hides that student; this is the same rule asked of the route.
        $this->actingAs($this->deskA)
            ->post(route('shift.store'), [
                'studentId' => $foreign->studentId,
                'targetCourseId' => $this->courseNeither->courseId,
                'willStatement' => 'I would like to move to the shipping programme next term.',
            ])
            ->assertSessionHasErrors('studentId');

        // The desk that owns the student can file the same move, which is what makes the refusal above a
        // boundary rather than a broken route.
        $this->actingAs($this->deskB)
            ->post(route('shift.store'), [
                'studentId' => $foreign->studentId,
                'targetCourseId' => $this->courseNeither->courseId,
                'willStatement' => 'I would like to move to the shipping programme next term.',
            ])
            ->assertSessionHasNoErrors();
    }

    // ─── fixture and reading helpers ────────────────────────────────────────────────────────────────

    private function course(string $unitName, string $code): Courses
    {
        $unit = Academicunits::create(['unitName' => $unitName, 'unitType' => 'college']);

        return Courses::create([
            'unitId' => $unit->unitId,
            'courseName' => 'Bachelor of '.$unitName,
            'courseCode' => $code,
            'requiresEntranceExam' => true,
            'requiresCourseSpecificExam' => true,
            'requiresRetentionExam' => false,
        ]);
    }

    private function desk(string $username, ?int $unitId, string $role = StaffRole::Instructor->value): Staffusers
    {
        $user = Staffusers::create([
            'username' => $username,
            'employeeNo' => 'EMP-'.strtoupper(substr(md5($username), 0, 6)),
            'firstName' => 'Desk',
            'lastName' => 'Of'.substr(md5($username), 0, 5),
            'middleName' => '',
            'email' => $username.'@seait.edu.ph',
            'passwordHash' => bcrypt('password123'),
            'officeId' => 7,
            'unitId' => $unitId,
            'contactNo' => '',
            'role' => $role,
            'status' => 'active',
        ]);

        // DeptEvaluator is exactly the pairing the seeder gives a college desk: evaluation.view/.create/
        // .sign, admission.view, students.view, exam.record.courseSpecific, shift.request.create.
        $user->syncRoles(['DeptEvaluator']);

        return $user;
    }

    private function person(string $surname): Students
    {
        return Students::create([
            'schoolIdNumber' => 'COL-'.uniqid(),
            'lastName' => $surname,
            'firstName' => 'Student',
            'middleName' => 'S',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'semestersCompleted' => 1,
            'yearsInInstitution' => 1,
            'email' => 'col_'.uniqid().'@example.com',
            'username' => 'col_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);
    }

    private function enrollment(Courses $course, string $surname): Enrollments
    {
        $student = $this->person($surname);

        return Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $course->courseId,
            'termId' => $this->term->termId,
            'yearLevel' => 1,
            'studentType' => StudentType::FirstYear,
            'enrollmentStatus' => EnrollmentStatus::Pending,
            'evaluatedBy' => $this->deskWithoutCollege->userId,
        ]);
    }

    private function applicant(Courses $course, string $surname): void
    {
        DB::table('admissions')->insert([
            'studentId' => $this->person($surname)->studentId,
            'courseId' => $course->courseId,
            'termId' => $this->term->termId,
            'applicantType' => ApplicantType::FirstYear->value,
            'admissionStatus' => AdmissionStatus::Pending->value,
        ]);
    }

    /**
     * The pending loads a desk is actually shown, read out of the page the desk gets.
     *
     * @return list<array<string, mixed>>
     */
    private function queue(Staffusers $who): array
    {
        return $this->props($who, 'evaluation.index')['enrollments']['data'];
    }

    /**
     * An Inertia screen's props. The route answers with HTML until `X-Inertia` is sent, so `->json()`
     * reports "Invalid JSON was returned from the route" — the page array handed to the root view is where
     * the props actually are.
     *
     * @return array<string, mixed>
     */
    private function props(Staffusers $who, string $route): array
    {
        return $this->actingAs($who)->get(route($route))->assertOk()->viewData('page')['props'];
    }

    /** @return list<string> */
    private function courseCodesIn(array $rows): array
    {
        $codes = array_values(array_unique(array_filter(array_map(fn ($r) => $r['course']['courseCode'] ?? null, $rows))));
        sort($codes);

        return $codes;
    }

    /** @return list<string> */
    private function applicants(Staffusers $who): array
    {
        $names = array_map(fn ($r) => $r['student']['lastName'] ?? '?', $this->props($who, 'admission.index')['admissions']['data']);
        sort($names);

        return $names;
    }
}
