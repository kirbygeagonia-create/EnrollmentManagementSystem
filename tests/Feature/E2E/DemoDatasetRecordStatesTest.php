<?php

namespace Tests\Feature\E2E;

use App\Enums\IdRequestStatus;
use App\Enums\OfficeId;
use App\Enums\WorkflowStepStatus;
use App\Models\Enrollments;
use App\Models\Payments;
use App\Models\Staffusers;
use App\Models\Students;
use App\Models\Workflowsteps;
use App\Support\SchoolTime;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The demo dataset, measured by the command that exists to measure it.
 *
 * `ems:audit:record-states` counts the record states no desk action can produce. Until 2026-10-16 nothing
 * ran it against a database built from empty: the demo pipeline refused on a schema that did not already
 * hold its two named base students, so the only databases that ever had a demo dataset were the developer's
 * live `ems` and copies of `Documentation/ems-demo-backup.sql` — and a defect in the seeder that writes
 * them showed up only when someone opened a screen. That refusal is gone (the pipeline now creates both
 * rows), so this file can ask the question in CI: seed a fresh schema with the CI recipe, run the pipeline,
 * and read the audit.
 *
 * It reads the register rather than only its exit code, because the exit code lumps the eleven impossible
 * states together. A note that climbs from 0 to 3 here says which rule and how many; the same climb in a
 * `php artisan` step that only checks the status says nothing until somebody asks.
 *
 * The ruled exception is asserted by identity, not by count: one signed ID box with no request behind it is
 * a record the owner ruled on 2026-10-11 (the contradictory request row deleted, the signature left
 * standing), and `AuditRecordStates` says so in its own `why`. A count of 1 therefore proves nothing on its
 * own — a NEW row silently wearing the same number would pass. The two relation queries below restate what
 * that command's private methods ask, deliberately and in one place, so the exception can be named rather
 * than tolerated; if a rule in `AuditRecordStates` changes shape, these two must change with it.
 */
class DemoDatasetRecordStatesTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * The demo term and the term before it.
     *
     * Both are literals because DevReferenceDataSeeder pins them (`['termId' => 10]`, `['termId' => 11]`),
     * which is the case AGENTS.md distinguishes from a name-keyed seeder whose ids follow the counter. The
     * pipeline's own `$termId`/`$priorTermId` at tools/seed_full_demo_pipeline.php:79-81 are the same two.
     */
    private const DEMO_TERM = 11;

    private const PRIOR_TERM = 10;

    /** Enrollment 53 — the one signed ID box the owner ruled stays signed with no request behind it. */
    private const RULED_ID_BOX_ENROLLMENTS = [53];

    private string $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = (string) (getenv('E2E_DB_DATABASE') ?: 'ems');

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->database,
        ]);
        DB::purge('mysql');

        $mysql = config('database.connections.mysql');

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                "MySQL at {$mysql['host']}:{$mysql['port']} as {$mysql['username']} cannot open '{$this->database}': "
                .trim($e->getMessage())
            );
        }

        // A migrated-but-empty schema is this suite's own doing (RefreshDatabase rebuilds it), and a seeded
        // reference schema is CI's seed step; neither has a demo dataset. Naming the missing table and the
        // command that fills it is what separates "this environment cannot host the check" from "the check
        // ran and the dataset is broken".
        try {
            $students = DB::connection('mysql')->table('students')->count();
        } catch (QueryException $e) {
            $this->markTestSkipped(
                "Database '{$this->database}' has no students table — it was never migrated."
                .' Run: php artisan migrate --force'
            );

            return;
        }

        if ($students === 0) {
            $this->markTestSkipped(
                "Database '{$this->database}' holds no demo dataset; this check measures what the pipeline"
                .' writes. Run: php artisan db:seed && php artisan db:seed --class=DevReferenceDataSeeder'
                .' && php tools/seed_full_demo_pipeline.php'
            );
        }

        $this->beginDatabaseTransaction();
    }

    #[Test]
    public function the_record_state_audit_passes_on_the_seeded_demo_dataset(): void
    {
        $exit = Artisan::call('ems:audit:record-states');
        $report = Artisan::output();

        // Every impossible state is one failing record, so the command's own status is the contract.
        $this->assertSame(0, $exit, $report);
        $this->assertStringNotContainsString('FAIL', $report, $report);

        $counts = $this->registerCounts($report);

        // Twelve rules print, each with its own number: a run that silently reports fewer is a run that
        // measured less than the operator was told.
        $this->assertCount(12, $counts, $report);

        foreach ($counts as $label => $count) {
            if ($label === 'An ID box signed with no validated request behind it') {
                // Asserted by identity in the next test, since a count of 1 is only the ruling if it is the
                // ruled enrollment wearing it.
                $this->assertLessThanOrEqual(
                    count(self::RULED_ID_BOX_ENROLLMENTS),
                    $count,
                    "Rule '{$label}' reads {$count}; at most ".count(self::RULED_ID_BOX_ENROLLMENTS)
                        .' record(s) are ruled to sit in this state, and the next test names them.'
                );

                continue;
            }

            $this->assertSame(0, $count, "Rule '{$label}' reads {$count}; only the ruled ID box may be non-zero.");
        }
    }

    #[Test]
    public function a_signed_box_with_no_record_behind_it_names_only_the_enrollment_the_owner_ruled_on(): void
    {
        foreach ($this->idBoxesWithNoValidatedRequest() as $enrollmentId) {
            $this->assertContains(
                $enrollmentId,
                self::RULED_ID_BOX_ENROLLMENTS,
                "Enrollment {$enrollmentId} has its ID box signed with no validated request behind it, which is not"
                .' the record ruled on 2026-10-11: either a write path signed the box without validating a card,'
                .' or a new exception needs a ruling of its own.'
            );
        }

        // No ruling covers a signed Clinic box with no health assessment: the act that signs it is the
        // assessment itself, and the seven past-term rows that once sat here were retired on 2026-10-15.
        $this->assertSame(
            [],
            $this->clinicBoxesWithNoAssessment(),
            'A Clinic box is signed with no health assessment behind it. §14R.2 of the acceptance script makes'
            .' recording the assessment the signature, so this is a write path, not history.'
        );
    }

    #[Test]
    public function the_two_base_rows_the_pipeline_creates_are_the_ones_its_own_reporters_read(): void
    {
        $juan = Students::where('schoolIdNumber', 'DEMO-2026-001')->first();
        $this->assertNotNull(
            $juan,
            'DEMO-2026-001 is missing: tools/seed_full_demo_pipeline.php creates it, so its absence means the'
            .' pipeline never ran against this database.'
        );

        // The Daily Collections Report reads today's cash receipt, and the receipt is written against an
        // enrollment — a student alone is not enough to put a row on that report.
        $juanEnrollment = Enrollments::where('studentId', $juan->studentId)->where('termId', self::DEMO_TERM)->first();
        $this->assertNotNull($juanEnrollment, 'DEMO-2026-001 holds no enrollment on the demo term, so today\'s receipt has nothing to sit on.');

        $today = Payments::where('enrollmentId', $juanEnrollment->enrollmentId)
            ->where('orNumber', 'DEMO-OR-TODAY-01')
            ->whereDate('paymentDate', SchoolTime::today())
            ->first();
        $this->assertNotNull(
            $today,
            "DEMO-OR-TODAY-01 is not dated today on enrollment {$juanEnrollment->enrollmentId}: the Daily"
            .' Collections Report would show nothing. Re-run php tools/seed_full_demo_pipeline.php — it re-dates'
            .' the slip rather than minting a second one for a number it controls.'
        );

        $cashier = Staffusers::find($today->processedBy);
        $this->assertSame(
            OfficeId::Accounting->value,
            (int) ($cashier?->officeId ?? -1),
            "Receipt {$today->orNumber} is processedBy user ".var_export($today->processedBy, true)
            .', which is no Accounting officer: the slip would name an officer from another desk.'
        );

        $liza = Students::where('schoolIdNumber', 'DEMO-2026-004')->first();
        $this->assertNotNull($liza, 'DEMO-2026-004 is missing: the pipeline creates her, and her prior-term record is the standing evidence the Evaluation desk derives from.');

        $prior = Enrollments::where('studentId', $liza->studentId)
            ->where('termId', self::PRIOR_TERM)
            ->where('enrollmentStatus', 'enrolled')
            ->first();
        $this->assertNotNull(
            $prior,
            'DEMO-2026-004 holds no completed record on term '.self::PRIOR_TERM
            .'; section 4b of the pipeline grades that record and the create beside it copies its program and year level.'
        );

        $failed = DB::table('enrolledsubjects')
            ->where('enrollmentId', $prior->enrollmentId)
            ->whereNotNull('grade')
            ->where('grade', '>', 3.00)
            ->count();
        $this->assertGreaterThan(
            0,
            $failed,
            'Her prior load carries no grade worse than 3.00, so nothing derives irregular and the repeat the demo'
            .' shows is a year level the record does not contradict.'
        );

        $repeat = Enrollments::where('studentId', $liza->studentId)->where('termId', self::DEMO_TERM)->first();
        $this->assertNotNull($repeat, 'DEMO-2026-004 holds no demo-term record — the desk has no repeating student to decide on.');
        $this->assertSame(
            (int) $prior->yearLevel,
            (int) $repeat->yearLevel,
            "Her demo-term record sits year {$repeat->yearLevel} beside a prior record at year {$prior->yearLevel}"
            .': the point of the row is a student repeating the level.'
        );
    }

    /**
     * Parse the printed register into label => count.
     *
     * Read from the command's own output rather than by calling its private methods, so the check exercises
     * what an operator reads. A label that no longer parses as a number is a report whose format moved, and
     * this test says so instead of quietly comparing fewer than twelve rows.
     *
     * @return array<string,int>
     */
    private function registerCounts(string $report): array
    {
        $counts = [];

        foreach (explode("\n", $report) as $line) {
            if (preg_match('/^\s*(ok|FAIL|note)\s+(.+?)\s{2,}(\d+)\s*$/u', $line, $m) !== 1) {
                continue;
            }

            $counts[$m[2]] = (int) $m[3];
        }

        return $counts;
    }

    /**
     * Enrollments whose ID box is signed while no validated request sits behind it.
     *
     * A retired enrollment is excluded because a record no desk will pick up again is not a state waiting to
     * be decided — the same exclusion `AuditRecordStates` makes in both box rules.
     *
     * @return list<int>
     */
    private function idBoxesWithNoValidatedRequest(): array
    {
        return Enrollments::whereHas('enrollmentworkflow.workflowsteps', fn ($q) => $q
            ->where('officeId', OfficeId::IdOffice->value)
            ->where('stepStatus', WorkflowStepStatus::Completed->value))
            ->where('enrollmentStatus', '!=', 'dropped')
            ->whereDoesntHave('idrequests', fn ($q) => $q->where('status', IdRequestStatus::Validated->value))
            ->pluck('enrollmentId')
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function clinicBoxesWithNoAssessment(): array
    {
        return Enrollments::whereHas('enrollmentworkflow.workflowsteps', fn ($q) => $q
            ->where('officeId', OfficeId::Clinic->value)
            ->where('stepStatus', WorkflowStepStatus::Completed->value))
            ->where('enrollmentStatus', '!=', 'dropped')
            ->whereDoesntHave('clinicrecords')
            ->pluck('enrollmentId')
            ->values()
            ->all();
    }

    #[Test]
    public function a_completed_box_without_a_signer_fails_the_run_it_is_supposed_to_gate(): void
    {
        // The state the register is named for, planted here rather than read: a box carrying a status and no
        // name on it. Wrapped in the trait's transaction, so the row this test writes is gone when it ends —
        // nothing about this check may leave a mark on the database it is measuring.
        $box = Workflowsteps::where('stepStatus', WorkflowStepStatus::Completed->value)
            ->whereNotNull('signedBy')
            ->first();

        if ($box === null) {
            $this->markTestSkipped('This dataset holds no completed, signed workflow box to unsigned.');
        }

        DB::table('workflowsteps')->where('workflowStepId', $box->workflowStepId)->update(['signedBy' => null]);

        $exit = Artisan::call('ems:audit:record-states');
        $report = Artisan::output();

        $this->assertSame(1, $exit, $report);
        $this->assertStringContainsString('A workflow box completed with no signer', $report, $report);
    }
}
