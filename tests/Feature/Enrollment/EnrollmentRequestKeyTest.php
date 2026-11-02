<?php

namespace Tests\Feature\Enrollment;

use App\Enums\AcademicStanding;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\OfficeId;
use App\Enums\Semester;
use App\Enums\StaffRole;
use App\Enums\StudentType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Auditlogs;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Enrollmentworkflow;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use App\Services\EnrollmentIssuer;
use App\Support\AuditAct;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADS guide problem 17: an enrollment POST that dies on the way leaves one record, and the desk can ask "did it
 * commit?" and get an answer.
 *
 * `enrollments.requestKey` names the ACT, and `EnrollmentIssuer::issue()` answers the existing record when it sees a
 * key it has already honoured. Unlike the active-seat key, which needs a generated column SQLite cannot compile,
 * this one is a plain nullable column with an
 * ordinary UNIQUE index, so it exists on the suite's SQLite driver too — which is the point of cases like
 * `the_index_refuses_a_second_row_carrying_one_key_on_this_driver`: the suite is a witness, not a bystander.
 *
 * Nullable means unkeyed writes are unaffected: a seeder, a migration backfill, and every caller that has not been
 * given a key keeps its behaviour, because MySQL and SQLite both treat NULLs as distinct in a unique index.
 */
class EnrollmentRequestKeyTest extends TestCase
{
    use RefreshDatabase;

    private Students $student;

    private Courses $course;

    private Academicterms $term;

    private Staffusers $desk;

    protected function setUp(): void
    {
        parent::setUp();

        // createWorkflow() writes one workflowsteps row per desk, and each names an office the schema points at.
        // Without these rows the FK refuses the act, and the failure looks like a defect in the key rather than in
        // this fixture.
        foreach ([1, 2, 3, 4, 5, 6, 11, 22] as $officeId) {
            Offices::firstOrCreate(['officeId' => $officeId], ['officeName' => 'Office '.$officeId]);
        }

        Religions::create(['religionId' => 1, 'religionName' => 'Roman Catholic']);
        $unit = Academicunits::create([
            'unitCode' => 'CAS', 'unitName' => 'College of Arts and Sciences', 'unitType' => 'college',
        ]);
        $this->course = Courses::create([
            'unitId' => $unit->unitId, 'courseCode' => 'AB', 'courseName' => 'Bachelor of Arts',
            'requiresEntranceExam' => false, 'requiresRetentionExam' => false,
        ]);
        $year = Academicyears::create([
            'yearStart' => 2026, 'yearEnd' => 2027, 'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01', 'endDate' => '2027-05-31',
        ]);
        $this->term = Academicterms::create([
            'academicYearId' => $year->academicYearId, 'semester' => Semester::First->value,
            'startDate' => '2026-06-01', 'endDate' => '2026-10-31',
        ]);
        $this->desk = Staffusers::factory()->create([
            'officeId' => OfficeId::Registrar->value,
            'role' => StaffRole::OfficeHead,
            'employeeNo' => 'P17-EMP-'.uniqid(),
            'username' => 'p17_desk_'.uniqid(),
            'email' => 'p17_desk_'.uniqid().'@example.com',
        ]);
        $this->student = Students::create([
            'schoolIdNumber' => 'P17-'.uniqid(), 'lastName' => 'Repeats', 'firstName' => 'Request',
            'middleName' => 'K', 'suffix' => 'N/A', 'gender' => 'male', 'birthdate' => '2006-01-01',
            'birthplace' => 'Test City', 'citizenship' => 'Filipino', 'civilStatus' => 'single',
            'religionId' => 1, 'contactNumber' => '09171234567', 'telephoneNumber' => null,
            'email' => 'p17_'.uniqid().'@example.com', 'username' => 'p17_'.uniqid(),
            'passwordHash' => bcrypt('password123'), 'status' => 'active',
        ]);
    }

    /** The desk's own act, replayed: one enrollment, one workflow form, and the same record both times. */
    #[Test]
    public function a_repeated_request_key_answers_the_enrollment_the_first_request_created(): void
    {
        $key = 'act-'.uniqid();

        $first = app(EnrollmentIssuer::class)->issue($this->attrs(), $this->desk, $key);
        $again = app(EnrollmentIssuer::class)->issue($this->attrs(), $this->desk, $key);

        $this->assertSame($first->enrollmentId, $again->enrollmentId,
            'a replay must answer the record that already exists, not mint a second one');
        $this->assertSame(1, Enrollments::where('requestKey', $key)->count(),
            'one act produced two enrollments — the interruption the guide asks about is still a duplicate.');
        $this->assertSame(1, Enrollmentworkflow::where('enrollmentId', $first->enrollmentId)->count(),
            'the replay opened a second workflow form on one enrollment.');
    }

    /**
     * Prompt §9 asks that an act be answerable, not merely the row change behind it. The issuer is the act's only
     * writer, so the audit row for the enrollment it creates must read `enrollment.issue` — and the replay must not
     * add a second one, because a replay is the same act rather than a new one.
     */
    #[Test]
    public function the_issuing_act_names_itself_in_the_audit_trail_and_a_replay_adds_no_second_entry(): void
    {
        $key = 'audit-'.uniqid();

        $first = app(EnrollmentIssuer::class)->issue($this->attrs(), $this->desk, $key);
        app(EnrollmentIssuer::class)->issue($this->attrs(), $this->desk, $key);

        $actions = Auditlogs::where('entityTable', 'enrollments')
            ->where('entityId', (string) $first->enrollmentId)
            ->pluck('action')
            ->values()
            ->all();

        $this->assertContains(AuditAct::ISSUE, $actions,
            'the audit trail still says only `created`, so §9 cannot be answered as a query: '.json_encode($actions));
        $this->assertNotContains('created', $actions,
            'a named act must replace the event word, not sit beside it as a second spelling.');
        $this->assertCount(1, $actions,
            'a replay of one act wrote a second audit row; the trail would read two issues from one desk action.');
    }

    /**
     * The read at the top of `issue()` is the fast path; the index is the guard. This asks the server the question
     * the two-request race would ask it, and it must be refused on the driver the suite runs on — the claim the
     * generated-column seat key can never make locally.
     */
    #[Test]
    public function the_index_refuses_a_second_row_carrying_one_key_on_this_driver(): void
    {
        $key = 'race-'.uniqid();
        app(EnrollmentIssuer::class)->issue($this->attrs(), $this->desk, $key);

        try {
            DB::table('enrollments')->insert($this->rawRow($key));
            $this->fail('the schema accepted a second enrollment for one request key; the guard is not on this driver.');
        } catch (UniqueConstraintViolationException|QueryException $e) {
            $this->assertMatchesRegularExpression(
                '/uq_enrollments_request_key|1062|UNIQUE constraint failed/',
                $e->getMessage(),
                'refused, but not by the key — another constraint would let a real replay through.'
            );
        }

        $this->assertSame(1, Enrollments::where('requestKey', $key)->count());
    }

    /** A null key is not "the same key twice": unkeyed writes stay ordinary writes. */
    #[Test]
    public function two_unkeyed_acts_are_both_allowed_because_null_is_outside_the_rule(): void
    {
        $one = app(EnrollmentIssuer::class)->issue($this->attrs(), $this->desk, null);
        $two = app(EnrollmentIssuer::class)->issue(
            ['termId' => $this->makeSecondTerm()->termId] + $this->attrs(), $this->desk, null
        );

        $this->assertNotSame($one->enrollmentId, $two->enrollmentId,
            'two unkeyed acts must not be collapsed into one record — that would make the key mandatory by accident.');
        $this->assertNull($one->requestKey);
        $this->assertNull($two->requestKey);
    }

    /**
     * The column and the index must both exist, and the index must be UNIQUE. Asserting the shape rather than the
     * name alone is what catches a migration that added the column and forgot the key — a nullable column with no
     * index is a comment about idempotency, not idempotency.
     */
    #[Test]
    public function the_schema_carries_a_unique_index_over_the_key_not_merely_a_column(): void
    {
        $this->assertTrue($this->uniqueIndexExists('enrollments', 'uq_enrollments_request_key'),
            DB::getDriverName().' reports no UNIQUE index named uq_enrollments_request_key on enrollments.');
    }

    /**
     * A key names one act, so the same key presenting a different student or term is refused rather than answered
     * with somebody else's record. This is the server's own guard, not the screen's key hygiene: a desk page that
     * reused one key for two students would otherwise be handed the first student's enrollment as a success.
     */
    #[Test]
    public function one_key_may_not_name_two_different_acts(): void
    {
        $key = 'collide-'.uniqid();
        $first = app(EnrollmentIssuer::class)->issue($this->attrs(), $this->desk, $key);

        $other = Students::create([
            'schoolIdNumber' => 'P17B-'.uniqid(), 'lastName' => 'Other', 'firstName' => 'Student',
            'middleName' => 'N', 'suffix' => 'N/A', 'gender' => 'female', 'birthdate' => '2006-02-02',
            'birthplace' => 'Test City', 'citizenship' => 'Filipino', 'civilStatus' => 'single',
            'religionId' => 1, 'contactNumber' => '09171234568', 'telephoneNumber' => null,
            'email' => 'p17b_'.uniqid().'@example.com', 'username' => 'p17b_'.uniqid(),
            'passwordHash' => bcrypt('password123'), 'status' => 'active',
        ]);

        try {
            app(EnrollmentIssuer::class)->issue(
                ['studentId' => $other->studentId] + $this->attrs(), $this->desk, $key
            );
            $this->fail('one request key answered two different acts; the second desk got the first student\'s record.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('requestKey', $e->errors());
            $this->assertStringContainsString('different act', $e->errors()['requestKey'][0]);
        }

        $this->assertSame(1, Enrollments::where('requestKey', $key)->count());
        $this->assertSame($first->studentId, Enrollments::where('requestKey', $key)->value('studentId'),
            'the refusal must leave the original act exactly as it was.');
    }

    /**
     * The desk half of the mechanism: a screen that never sends a key leaves the guard unreachable, and nothing in
     * the PHP suite could see that — so this reads the page's own source, the way the year-level and tone-map
     * guards do in a repository with no JavaScript test suite.
     */
    #[Test]
    public function the_desk_page_sends_a_request_key_with_the_issue_act(): void
    {
        $path = base_path('resources/js/Pages/Evaluation/Index.jsx');
        $page = @file_get_contents($path);
        $this->assertIsString($page, "the issuing screen cannot be read at {$path}; a case that reads nothing passes on an empty string.");

        $this->assertStringContainsString('newRequestKey', $page,
            'the issuing screen no longer mints an act key, so a desk that retries a dropped send makes a second enrollment.');
        // `[^)]*` cannot cross the `(` and `)` of `transform((data) => …)`, so the bound is the call's own span.
        // This is the character-class trap lane 46 and lane 50 both paid for, met here in my own new assertion.
        $this->assertMatchesRegularExpression('/transform\(.{0,220}?requestKey:\s*issueKey/s', $page,
            'the key is not put on the wire: the payload the department posts carries no act identity.');
        $this->assertStringContainsString("setIssueKey(newRequestKey('issue'))", $page,
            'the key is not replaced after a success, so one key would name every act the desk performs in a visit.');
    }

    // ---- helpers ----------------------------------------------------------------------------------------

    /**
     * Read from the server on both drivers, because this class runs on CI's MySQL job as well as here and a test
     * that hard-codes which index exists where is a second definition of the schema.
     */
    private function uniqueIndexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() === 'mysql') {
            return DB::select(
                'select 1 from information_schema.STATISTICS where TABLE_SCHEMA = DATABASE()
                    and TABLE_NAME = ? and INDEX_NAME = ? and NON_UNIQUE = 0 limit 1',
                [$table, $index]
            ) !== [];
        }

        foreach (DB::select("pragma index_list(`{$table}`)") as $row) {
            if (($row->name ?? '') === $index && (int) ($row->unique ?? 0) === 1) {
                $cols = array_map(fn ($c) => (string) ($c->name ?? ''), DB::select("pragma index_info(`{$index}`)"));

                return $cols === ['requestKey'];
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function attrs(?int $termId = null): array
    {
        return [
            'studentId' => $this->student->studentId,
            'courseId' => $this->course->courseId,
            'termId' => $termId ?? $this->term->termId,
            'yearLevel' => 1,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => EnrollmentType::New,
            'academicStanding' => AcademicStanding::Regular,
        ];
    }

    /**
     * The same row the issuer would write, for the insert that must be refused by the server rather than by php.
     *
     * @return array<string, mixed>
     */
    private function rawRow(string $key): array
    {
        return [
            'studentId' => $this->student->studentId,
            'courseId' => $this->course->courseId,
            'termId' => $this->term->termId,
            'yearLevel' => 1,
            'studentType' => StudentType::FirstYear->value,
            'enrollmentType' => EnrollmentType::New->value,
            'academicStanding' => AcademicStanding::Regular->value,
            'enrollmentStatus' => EnrollmentStatus::Pending->value,
            'evaluatedBy' => $this->desk->userId,
            'formIssuedDate' => '2026-06-01',
            'requestKey' => $key,
            'created_at' => '2026-06-01 00:00:00',
            'updated_at' => '2026-06-01 00:00:00',
        ];
    }

    private function makeSecondTerm(): Academicterms
    {
        return Academicterms::create([
            'academicYearId' => $this->term->academicYearId, 'semester' => Semester::Second->value,
            'startDate' => '2026-11-01', 'endDate' => '2027-03-31',
        ]);
    }
}
