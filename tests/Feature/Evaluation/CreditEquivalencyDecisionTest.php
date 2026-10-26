<?php

namespace Tests\Feature\Evaluation;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Models\Creditedsubjects;
use App\Models\Enrollments;
use App\Models\Staffusers;
use App\Models\Students;
use App\Support\EnrollmentReadiness;
use App\Support\SchoolTime;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * G-3 (prompt §7.3): a transferred subject is credited only when the department decides so,
 * row by row, and the row says who decided, when, and — for a refusal — why.
 *
 * Before this batch the system had no decision on a credit. `processCredits()` filed a
 * `creditedsubjects` row for every prior subject whose grade fell inside the passing band, and
 * that row immediately covered a subject: it subtracted a mandatory subject from the study load
 * and satisfied a prerequisite. A matching title was therefore enough, which is the one thing
 * §7.3 forbids in terms.
 *
 * Ruled 2026-10-26 by the owner: the department decides. The account that records credits
 * already holds `evaluation.credits.process`, so the decision needs no new permission — which
 * is why these cases pin the boundary that did NOT change as well as the one that did: a desk
 * without that right still cannot flip a row.
 *
 * A proposal is refused once it succeeds, because the enrollment moves out of Pending — so each
 * case that shows both directions uses two enrollments rather than walking one back.
 *
 * One case sits deliberately outside what a single desk act can produce, and says so: the
 * mandatory-block exemption only bites for a *regular* student, while the crediting policy lets
 * only a transferee or a shifter record a row. That state is reached by a later correction of
 * the student's type; it is still the rule the validator promises, so it is pinned rather than
 * left unreachable.
 */
class CreditEquivalencyDecisionTest extends TestCase
{
    use RefreshDatabase;

    private int $termId;

    private int $courseId;

    private int $majorId;

    private int $curriculumId;

    private int $religionId;

    private Staffusers $evaluator;

    /** @var array<string, int> */
    private array $subjects = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $year = DB::table('academicyears')->insertGetId([
            'yearLabel' => '2024-2025',
            'startDate' => '2024-08-01',
            'endDate' => '2025-05-31',
        ]);

        $this->termId = DB::table('academicterms')->insertGetId([
            'academicYearId' => $year,
            'semester' => '1st',
            'startDate' => '2024-08-01',
            'endDate' => '2024-12-31',
        ]);

        $this->religionId = DB::table('religions')->insertGetId(['religionName' => 'Roman Catholic']);

        $unitId = DB::table('academicunits')->insertGetId([
            'unitName' => 'College of Computing',
            'unitType' => 'college',
        ]);

        $this->courseId = DB::table('courses')->insertGetId([
            'unitId' => $unitId,
            'courseName' => 'BS Computer Science',
            'courseCode' => 'BSCS',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);

        $this->majorId = DB::table('majors')->insertGetId([
            'courseId' => $this->courseId,
            'majorName' => 'General',
        ]);

        $this->curriculumId = DB::table('curriculums')->insertGetId([
            'courseId' => $this->courseId,
            'majorId' => $this->majorId,
            'effectiveYear' => '2024-01-01',
            'curriculumName' => 'Test Curriculum',
        ]);

        // Year 1 is three plain mandatory subjects: the mandatory-block case needs a set with
        // no prerequisite in it, or the load would be refused for a different reason.
        foreach (['A1', 'A2', 'A3'] as $code) {
            $this->subjects[$code] = $this->curriculumSubject($code, "Mandatory {$code}", 1);
        }

        // Year 2 carries the chain: D may not be proposed until P is passed or credited.
        $this->subjects['P'] = $this->curriculumSubject('P', 'Prerequisite P', 2);
        $this->subjects['D'] = $this->curriculumSubject('D', 'Dependent D', 2, $this->subjects['P']);

        // A null unitId is bounded by nothing (CollegeScope), so this account sees the one
        // course the fixture has — the decision act is about the right, not about a college.
        $this->evaluator = Staffusers::factory()->create([
            'officeId' => DB::table('offices')->insertGetId(['officeName' => 'Evaluation Office']),
            'role' => 'officeHead',
            'unitId' => null,
        ]);
        $this->evaluator->assignRole('DeptEvaluator');
    }

    private function curriculumSubject(string $code, string $name, int $yearLevel, ?int $prerequisiteId = null): int
    {
        $subjectId = $this->bareSubject($code, $name);

        DB::table('curriculumsubjects')->insert([
            'curriculumId' => $this->curriculumId,
            'subjectId' => $subjectId,
            'yearLevel' => $yearLevel,
            'semesterOffered' => '1st',
            'is_elective' => false,
            'prerequisiteSubjectId' => $prerequisiteId,
        ]);

        return $subjectId;
    }

    private function bareSubject(string $code, string $name): int
    {
        return (int) DB::table('subjects')->insertGetId([
            'subjectCode' => $code,
            'subjectName' => $name,
            'lectureUnits' => 3,
            'labUnits' => 0,
            'subjectType' => 'lecture',
        ]);
    }

    private function createStudent(): Students
    {
        return Students::create([
            'schoolIdNumber' => 'TEST-'.uniqid(),
            'lastName' => 'Test',
            'firstName' => 'Student',
            'middleName' => 'M',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => $this->religionId,
            'contactNumber' => '09171234567',
            'email' => 'test_'.uniqid().'@example.com',
            'username' => 'test_student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
        ]);
    }

    private function createEnrollment(Students $student, string $studentType = 'transferee', int $yearLevel = 2): Enrollments
    {
        return Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $this->courseId,
            'majorId' => $this->majorId,
            'termId' => $this->termId,
            'admissionId' => null,
            'yearLevel' => $yearLevel,
            'studentType' => $studentType,
            'enrollmentType' => $studentType === 'transferee' ? EnrollmentType::New : EnrollmentType::Old,
            'academicStanding' => 'regular',
            'evaluatedBy' => $this->evaluator->userId,
            'enrollmentStatus' => EnrollmentStatus::Pending,
            'curriculumId' => $this->curriculumId,
        ]);
    }

    /**
     * The desk act that files one equivalency. Grade 2.50 is inside the 3.00 fallback ceiling,
     * so the row is written — this is the only way a credit enters the system from a screen.
     */
    private function creditPayload(int $subjectId, array $overrides = []): array
    {
        return array_merge([
            'previousSubjectName' => 'Discrete Structures',
            'creditedToSubjectId' => $subjectId,
            'creditedUnits' => 3,
            'institutionName' => 'STI College',
            'institutionType' => 'college',
            'cityMunicipality' => 'Santa Cruz',
            'province' => 'Laguna',
            'grade' => '2.50',
            'remarks' => 'Covers the same learning outcomes.',
        ], $overrides);
    }

    /**
     * A row put on file without the screen — used only where the case says so, and where the
     * alternative would be a desk act the policy refuses.
     */
    private function seatCredit(Enrollments $enrollment, int $subjectId, string $decision = 'approved'): Creditedsubjects
    {
        return Creditedsubjects::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'previousSubjectName' => 'Discrete Structures',
            'creditedToSubjectId' => $subjectId,
            'creditedUnits' => 3,
            'remarks' => 'Filed by the department.',
            'creditDecision' => $decision,
            'decidedBy' => $this->evaluator->userId,
            'decidedDate' => SchoolTime::today(),
            'decisionReason' => $decision === 'rejected' ? 'Syllabus does not match the outcomes on file.' : null,
        ]);
    }

    private function propose(Enrollments $enrollment, array $subjectIds)
    {
        return $this->actingAs($this->evaluator)->post(route('evaluation.subjects.propose', $enrollment), [
            'subjects' => array_map(fn ($id) => ['subjectId' => $id], $subjectIds),
        ]);
    }

    private function decide(Creditedsubjects $credit, string $decision, ?string $reason = null)
    {
        return $this->actingAs($this->evaluator)->put(
            route('evaluation.credits.decide', $credit),
            $reason === null ? ['creditDecision' => $decision] : ['creditDecision' => $decision, 'decisionReason' => $reason]
        );
    }

    #[Test]
    public function a_credit_the_desk_records_arrives_as_its_own_signed_decision(): void
    {
        $enrollment = $this->createEnrollment($this->createStudent());

        $this->actingAs($this->evaluator)
            ->post(route('evaluation.credits.process', $enrollment), [
                'credits' => [$this->creditPayload($this->subjects['P'])],
            ])
            ->assertSessionHasNoErrors();

        $credit = Creditedsubjects::where('enrollmentId', $enrollment->enrollmentId)->firstOrFail();

        // The account that files the equivalency is the account that owns the judgement
        // (ruled 2026-10-26), so the row is approved on arrival and says who by, on what day.
        $this->assertSame('approved', $credit->creditDecision->value);
        $this->assertSame($this->evaluator->userId, (int) $credit->decidedBy);
        $this->assertSame(SchoolTime::today(), $credit->decidedDate->toDateString());
        $this->assertNull($credit->decisionReason);
    }

    #[Test]
    public function the_evidence_the_review_argues_from_is_stored_on_the_prior_record(): void
    {
        $enrollment = $this->createEnrollment($this->createStudent());

        $this->actingAs($this->evaluator)
            ->post(route('evaluation.credits.process', $enrollment), [
                'credits' => [
                    $this->creditPayload($this->subjects['P'], [
                        'previousSubjectCode' => 'DS-101',
                        'termTaken' => '2nd year, 1st semester',
                        'syllabusReference' => 'Annex B of the transcript of records',
                    ]),
                    $this->creditPayload($this->subjects['A1'], ['previousSubjectName' => 'Intro to Computing']),
                ],
            ])
            ->assertSessionHasNoErrors();

        $rows = DB::table('transferacademicrecords')->where('studentId', $enrollment->studentId)->get();
        $this->assertSame(2, $rows->count());

        $withEvidence = $rows->firstWhere('subjectNameAtOldSchool', 'Discrete Structures');
        $this->assertSame('DS-101', $withEvidence->previousSubjectCode);
        $this->assertSame('2nd year, 1st semester', $withEvidence->termTaken);
        $this->assertSame('Annex B of the transcript of records', $withEvidence->syllabusReference);

        // A field left blank stays blank: §7.3 asks for the evidence where required, and a desk
        // that has not been handed the paper is not made to invent one.
        $without = $rows->firstWhere('subjectNameAtOldSchool', 'Intro to Computing');
        $this->assertNull($without->previousSubjectCode);
        $this->assertNull($without->termTaken);
        $this->assertNull($without->syllabusReference);
    }

    #[Test]
    public function an_approved_equivalency_covers_the_prerequisite_it_was_mapped_to(): void
    {
        $enrollment = $this->createEnrollment($this->createStudent());
        $this->seatCredit($enrollment, $this->subjects['P']);

        // D is offered only to a student who has covered P, and P is not in this load: the
        // approved credit is the only thing that can satisfy it.
        $this->propose($enrollment, [$this->subjects['D']])->assertSessionHasNoErrors();
    }

    #[Test]
    public function a_declined_equivalency_stops_covering_the_prerequisite_it_was_mapped_to(): void
    {
        $enrollment = $this->createEnrollment($this->createStudent());
        $credit = $this->seatCredit($enrollment, $this->subjects['P']);

        $this->decide($credit, 'rejected', 'Only a title matched; the prior course is typing practice.')
            ->assertSessionHasNoErrors();

        $this->propose($enrollment, [$this->subjects['D']])->assertSessionHasErrors('subjects');

        $this->assertStringContainsString(
            'D requires P',
            session('errors')->get('subjects')[0],
            'The refusal after a decline must be the prerequisite gate naming both subjects.'
        );
    }

    #[Test]
    public function an_approved_equivalency_exempts_a_mandatory_subject_from_the_load(): void
    {
        // A regular first-year student — the only student the mandatory block is enforced on.
        $enrollment = $this->createEnrollment($this->createStudent(), 'firstYear', 1);
        $this->seatCredit($enrollment, $this->subjects['A3']);

        $this->propose($enrollment, [$this->subjects['A1'], $this->subjects['A2']])
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function a_declined_equivalency_puts_its_subject_back_inside_the_mandatory_block(): void
    {
        $enrollment = $this->createEnrollment($this->createStudent(), 'firstYear', 1);
        $credit = $this->seatCredit($enrollment, $this->subjects['A3']);

        // Flipped in place rather than through the panel: `processCredits` refuses a record
        // whose student type is neither transferee nor shifter, and no path in the build ever
        // re-types an enrollment to firstYear — so a regular student holding a credit is a row
        // a data correction produced, not a desk act. The rule it has to obey is still the
        // validator's, and this is the only honest way to reach it.
        $credit->update(['creditDecision' => 'rejected', 'decisionReason' => 'Not the same subject.']);

        $this->propose($enrollment, [$this->subjects['A1'], $this->subjects['A2']])
            ->assertSessionHasErrors('subjects');

        $this->assertStringContainsString(
            (string) $this->subjects['A3'],
            session('errors')->get('subjects')[0],
            'A declined credit has to put its own subject back where it was before the row.'
        );
    }

    #[Test]
    public function a_decline_without_a_reason_is_refused_and_names_the_field(): void
    {
        $enrollment = $this->createEnrollment($this->createStudent());
        $credit = $this->seatCredit($enrollment, $this->subjects['P']);

        $this->decide($credit, 'rejected')->assertSessionHasErrors('decisionReason');

        $untouched = $credit->fresh();
        $this->assertSame('approved', $untouched->creditDecision->value);
        $this->assertNull($untouched->decisionReason);
    }

    #[Test]
    public function crediting_a_declined_row_again_clears_the_reason_and_restamps_the_hand(): void
    {
        $enrollment = $this->createEnrollment($this->createStudent());
        $credit = $this->seatCredit($enrollment, $this->subjects['P']);

        $otherDesk = Staffusers::factory()->create(['role' => 'officeHead', 'unitId' => null]);
        $otherDesk->assignRole('DeptEvaluator');

        $this->decide($credit, 'rejected', 'Outcomes do not line up.')->assertSessionHasNoErrors();

        $declined = $credit->fresh();
        $this->assertSame('rejected', $declined->creditDecision->value);
        $this->assertSame('Outcomes do not line up.', $declined->decisionReason);

        $this->actingAs($otherDesk)
            ->put(route('evaluation.credits.decide', $credit), ['creditDecision' => 'approved'])
            ->assertSessionHasNoErrors();

        $reapproved = $credit->fresh();

        // One row states the decision it is in, never the history of both: a reason left beside
        // an approval would read as a refusal nobody withdrew.
        $this->assertSame('approved', $reapproved->creditDecision->value);
        $this->assertNull($reapproved->decisionReason);
        $this->assertSame($otherDesk->userId, (int) $reapproved->decidedBy);
        $this->assertSame(SchoolTime::today(), $reapproved->decidedDate->toDateString());
    }

    #[Test]
    public function a_desk_without_the_credit_right_cannot_flip_a_row(): void
    {
        $enrollment = $this->createEnrollment($this->createStudent());
        $credit = $this->seatCredit($enrollment, $this->subjects['P']);

        $viewer = Staffusers::factory()->create(['role' => 'staff', 'unitId' => null]);
        $viewer->assignRole('Staff');

        $this->assertFalse($viewer->hasPermissionTo('evaluation.credits.process'));

        $this->actingAs($viewer)
            ->put(route('evaluation.credits.decide', $credit), [
                'creditDecision' => 'rejected',
                'decisionReason' => 'Should never be written.',
            ])
            ->assertForbidden();

        $untouched = $credit->fresh();
        $this->assertSame('approved', $untouched->creditDecision->value);
        $this->assertNull($untouched->decisionReason);
    }

    #[Test]
    public function declining_a_row_leaves_the_prior_subject_and_its_grade_on_the_record(): void
    {
        $enrollment = $this->createEnrollment($this->createStudent());

        $this->actingAs($this->evaluator)
            ->post(route('evaluation.credits.process', $enrollment), [
                'credits' => [$this->creditPayload($this->subjects['P'])],
            ])
            ->assertSessionHasNoErrors();

        $credit = Creditedsubjects::where('enrollmentId', $enrollment->enrollmentId)->firstOrFail();

        $this->decide($credit, 'rejected', 'Only a title matched.')->assertSessionHasNoErrors();

        $record = DB::table('transferacademicrecords')
            ->where('transferRecordId', $credit->transferRecordId)
            ->first();

        // A refusal is a decision about the equivalency, not a deletion of the student's history.
        $this->assertNotNull($record);
        $this->assertSame('passed', $record->passResult);
        $this->assertEquals(2.5, (float) $record->gradeAtOldSchool);
        $this->assertDatabaseHas('creditedsubjects', ['creditedId' => $credit->creditedId]);
    }

    #[Test]
    public function the_panel_states_its_totals_from_the_server_and_offers_the_decision_to_the_desk_that_holds_the_right(): void
    {
        $enrollment = $this->createEnrollment($this->createStudent());
        $this->seatCredit($enrollment, $this->subjects['P']);
        $this->seatCredit($enrollment, $this->subjects['A1']);
        $this->seatCredit($enrollment, $this->subjects['A2'], 'rejected');

        $props = $this->actingAs($this->evaluator)
            ->get(route('evaluation.show', $enrollment))
            ->assertOk()
            ->viewData('page')['props'];

        $totals = $props['creditTotals'];

        // Three rows filed, two of them counting. The badge may not be a count of what the
        // screen renders: a declined row is rendered and is not credited.
        $this->assertSame(3, $totals['recorded']);
        $this->assertSame(2, $totals['approved']);
        $this->assertSame(1, $totals['rejected']);
        $this->assertEquals(6.0, $totals['approvedUnits']);
        $this->assertEquals(3.0, $totals['rejectedUnits']);
        $this->assertEquals(9.0, $totals['recordedUnits']);
        $this->assertTrue($props['can']['decideCredits']);

        $viewer = Staffusers::factory()->create(['role' => 'staff', 'unitId' => null]);
        $viewer->assignRole('Staff');
        $viewer->givePermissionTo('evaluation.view');

        $viewerProps = $this->actingAs($viewer)
            ->get(route('evaluation.show', $enrollment))
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertFalse($viewerProps['can']['decideCredits']);
        $this->assertSame(3, $viewerProps['creditTotals']['recorded']);
    }

    #[Test]
    public function a_row_backfilled_before_the_decision_column_existed_still_counts_and_says_it_was_never_signed(): void
    {
        $enrollment = $this->createEnrollment($this->createStudent());

        // Exactly what the migration leaves behind: a row whose decision comes from the column
        // default with no hand and no day. Nothing may silently stop covering a load that was
        // approved before the decision existed.
        $credit = Creditedsubjects::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'previousSubjectName' => 'Old Credit',
            'creditedToSubjectId' => $this->subjects['P'],
            'creditedUnits' => 3,
            'remarks' => 'Filed before this desk kept a decision per row.',
        ]);

        $backfilled = $credit->fresh();
        $this->assertSame('approved', $backfilled->creditDecision->value);
        $this->assertNull($backfilled->decidedBy);
        $this->assertNull($backfilled->decidedDate);

        $this->propose($enrollment, [$this->subjects['D']])->assertSessionHasNoErrors();

        $rows = collect($this->actingAs($this->evaluator)
            ->get(route('evaluation.show', $enrollment))
            ->viewData('page')['props']['enrollment']['creditedsubjects']);

        $shared = $rows->firstWhere('creditedId', $credit->creditedId);
        $this->assertSame('approved', $shared['creditDecision']);
        $this->assertNull($shared['decidedBy']);
        $this->assertNull($shared['decidedDate']);
    }

    #[Test]
    public function the_readiness_gate_the_registrar_reads_follows_the_same_decision(): void
    {
        // Third reader of the same rule: `EnrollmentReadiness::prerequisitesMet()` is what the
        // Registrar's panel shows and what RegistrarPolicy asks before an approval. It has its
        // own copy of the credited-subjects read, so it needs its own case — otherwise a change
        // to one reader leaves the other counting a declined equivalency.
        $enrollment = $this->createEnrollment($this->createStudent());
        $credit = $this->seatCredit($enrollment, $this->subjects['P']);
        $this->propose($enrollment, [$this->subjects['D']])->assertSessionHasNoErrors();

        $fresh = $enrollment->fresh(['enrolledSubjects']);
        $this->assertTrue(EnrollmentReadiness::prerequisitesMet($fresh));

        $this->decide($credit, 'rejected', 'Only the titles matched.')->assertSessionHasNoErrors();

        $this->assertFalse(
            EnrollmentReadiness::prerequisitesMet($enrollment->fresh(['enrolledSubjects'])),
            'A declined credit must not leave the Registrar reading the case as ready.'
        );
    }

    #[Test]
    public function a_subject_only_a_declined_credit_refers_to_still_cannot_be_deleted(): void
    {
        // The delete guard counts EVERY credited row, approved or not: a declined equivalency
        // still names the subject, and dropping it would leave the refusal pointing at nothing.
        // SysAdmin is used because Gate::before grants it every right — the case is about the
        // guard's predicate, not about who may reach it.
        $credited = $this->bareSubject('OLDX', 'Retired Subject With A Declined Credit');
        $loose = $this->bareSubject('OLDY', 'Retired Subject Nobody Refers To');

        $enrollment = $this->createEnrollment($this->createStudent());
        $this->seatCredit($enrollment, $credited, 'rejected');

        $admin = Staffusers::factory()->create(['role' => 'admin', 'unitId' => null]);
        $admin->assignRole('SysAdmin');

        $this->actingAs($admin)
            ->delete(route('admin.reference-data.subjects.destroy', $credited))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('subjects', ['subjectId' => $credited]);

        $this->actingAs($admin)
            ->delete(route('admin.reference-data.subjects.destroy', $loose))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('subjects', ['subjectId' => $loose]);
    }
}
