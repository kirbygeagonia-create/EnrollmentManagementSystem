<?php

// Mutation probe for tests/Feature/E2E/DemoDatasetRecordStatesTest.
//
// A checker that cannot fail has checked nothing, so each assertion in that class is fed the defect it is
// supposed to name, against a scratch schema this project owns, and the run must go red for the stated
// reason and green again once the mutant is taken back out.
//
// Two of the mutants are the interesting ones, and they disagree on purpose:
//   • a signed ID box with no request behind it lifts that rule's count from 0 to 1 — which the count
//     assertion ACCEPTS, because one record is ruled to sit in that state — so only the identity assertion
//     sees it. That is the whole reason the test asks which enrollment, not merely how many.
//   • the same shape on the Clinic rule is refused by both, because no ruling covers a Clinic box.
//
// Usage: DB_DATABASE=ems_lane38_ci php storage/app/scratch/mutate-lane38-recordstates-test.php
// The database comes from the environment and the first thing this file does is refuse any other: run without
// that prefix it reports `REFUSE: … not in 'ems'` and stops before touching a row, which is the guard doing
// its job — measured, not assumed, since the plain invocation is what a careless operator types.

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\IdRequestStatus;
use App\Enums\OfficeId;
use App\Enums\WorkflowStepStatus;
use App\Models\Enrollments;
use App\Models\Staffusers;
use App\Models\Students;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

const SCRATCH = 'ems_lane38_ci';
const TEST_FILE = 'tests/Feature/E2E/DemoDatasetRecordStatesTest.php';
const JUNIT = 'storage/app/scratch/lane38-mutant-junit.xml';

const NAMES = [
    'counted' => 'the record state audit passes on the seeded demo dataset',
    'ruled' => 'a signed box with no record behind it names only the enrollment the owner ruled on',
    'base' => 'the two base rows the pipeline creates are the ones its own reporters read',
    'unsigned' => 'a completed box without a signer fails the run it is supposed to gate',
];

/**
 * One spelling for a test name on both sides of a comparison.
 *
 * JUnit capitalises the first letter of a Pest test's display name ("The record state audit passes…") while
 * the console prints it lower, and every expectation in this file is written the way the console shows it.
 */
function normalize(string $name): string
{
    return strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? $name));
}

$php = 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$root = dirname(__DIR__, 3);
$pdoDatabase = DB::connection()->getDatabaseName();

if ($pdoDatabase !== SCRATCH) {
    fwrite(STDERR, 'REFUSE: this probe writes rows and only belongs in \''.SCRATCH.'\', not in \''.$pdoDatabase.'\'.'."\n");
    exit(1);
}

// Everything the mutants remove or rewrite is captured first, in full, keyed by the table's own primary key.
//
// Restore is an upsert by that key, never a delete: the first version of this file deleted the captured rows
// and re-inserted them, which worked on the child tables and died on `students` — a parent row `enrollments`
// points at cannot be deleted, so the mutant stayed planted and the shutdown guard reported it. A row that
// exists is written back column by column; a row that was deleted is inserted again.
//
// The key column is read from information_schema rather than kept here, because the hand-written list in the
// first version named `healthRecordId` and `idRequestID`, and the tables say `clinicRecordId` and
// `idRequestId` — a wrong name in a restore path is a mutant left in the database.
$captured = [];

$primaryKey = function (string $table): string {
    $column = DB::selectOne(
        'SELECT column_name AS name FROM information_schema.columns
           WHERE table_schema = ? AND table_name = ? AND column_key = ? LIMIT 1',
        [DB::connection()->getDatabaseName(), $table, 'PRI']
    );

    if ($column === null) {
        throw new RuntimeException("{$table} has no single primary key column this probe can restore by.");
    }

    return (string) $column->name;
};

$capture = function (string $table, array $where) use (&$captured, $primaryKey): array {
    $rows = DB::table($table)->where($where)->get()->map(fn ($row): array => (array) $row)->all();

    if ($rows === []) {
        throw new RuntimeException("Nothing to capture in {$table} — this schema does not hold the row the mutant needs.");
    }

    $captured[] = ['table' => $table, 'pk' => $primaryKey($table), 'rows' => $rows];

    return $rows;
};

$restore = function () use (&$captured): void {
    foreach (array_reverse($captured) as $bite) {
        foreach ($bite['rows'] as $row) {
            $key = $row[$bite['pk']];
            $columns = $row;
            unset($columns[$bite['pk']]);

            if (DB::table($bite['table'])->where($bite['pk'], $key)->exists()) {
                DB::table($bite['table'])->where($bite['pk'], $key)->update($columns);
            } else {
                DB::table($bite['table'])->insert($row);
            }
        }
    }

    $captured = [];
};

$restored = false;

register_shutdown_function(function () use ($restore, &$restored, &$captured): void {
    if (! $restored && $captured !== []) {
        fwrite(STDERR, "\nshutdown: a planted mutant was still in the schema — restoring now\n");

        try {
            $restore();
            $restored = true;
        } catch (Throwable $e) {
            fwrite(STDERR, '  restore failed: '.$e->getMessage()."\n");
        }
    }
});

$run = function () use ($php, $root): array {
    $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open(
        [$php, 'artisan', 'test', TEST_FILE, '--log-junit', JUNIT],
        $desc,
        $pipes,
        $root,
        array_merge((array) getenv(), ['E2E_DB_DATABASE' => SCRATCH])
    );
    // ANSI, not newlines, is what the first version of this parser tripped on: the runner decorates the test
    // names even when nothing is watching a terminal, so the captured strings carried escape sequences that
    // printed as nothing and matched nothing. The JUnit log is read instead; the text is kept for the dump.
    $out = preg_replace('/\e\[[0-9;]*m/', '', stream_get_contents($pipes[1]).stream_get_contents($pipes[2]));

    foreach ($pipes as $p) {
        fclose($p);
    }

    $status = proc_close($proc);
    $failed = [];

    if (is_file(JUNIT)) {
        $xml = simplexml_load_file(JUNIT);

        if ($xml !== false) {
            // `//testcase[failure|error|skipped]`, the same predicate CI's own report step uses. The first
            // version tested `$case->failure !== []`, which is true for every test case — a SimpleXML
            // child-list is never an empty array — so the control run reported all four tests red while the
            // runner printed four ticks beside it.
            foreach ($xml->xpath('//testcase[failure|error|skipped]') ?: [] as $case) {
                $failed[] = normalize((string) $case['name']);
            }
        }
    } else {
        $failed[] = '(no junit log written — the run did not reach the report)';
    }

    return ['status' => $status, 'failed' => $failed, 'out' => $out];
};

/**
 * One mutant: plant, run, name what went red, take it back out.
 *
 * @param  callable():void  $plant
 * @param  list<string>  $expect  NAMES keys that must fail
 * @param  list<string>  $expectPass  NAMES keys that must still pass
 */
$trial = function (string $what, callable $plant, array $expect, array $expectPass) use ($run, $restore, &$restored): bool {
    $plant();
    $restored = false;
    $result = $run();
    $restore();
    $restored = true;

    $missing = array_values(array_filter(
        $expect,
        fn (string $key): bool => ! in_array(normalize(NAMES[$key]), $result['failed'], true)
    ));
    $expectedNames = array_map(fn (string $k): string => normalize(NAMES[$k]), $expect);
    $extra = array_values(array_filter(
        $result['failed'],
        fn (string $name): bool => ! in_array($name, $expectedNames, true)
    ));
    $leaked = array_values(array_filter(
        $expectPass,
        fn (string $key): bool => in_array(normalize(NAMES[$key]), $result['failed'], true)
    ));

    $ok = $result['status'] !== 0 && $missing === [] && $extra === [] && $leaked === [];

    printf(
        "%s  %-58s red: %-32s green: %s\n",
        $ok ? 'PASS' : 'FAIL',
        $what,
        implode(', ', $result['failed']) === '' ? 'nothing' : implode(', ', $result['failed']),
        implode(', ', $expectPass) === '' ? '—' : implode(', ', $expectPass)
    );

    if (! $ok) {
        echo "     expected red: ".implode(', ', array_map(fn (string $k) => NAMES[$k], $expect))."\n";
        echo '     exit '.$result['status'].'; not red: '.implode(', ', $missing)
            .'; unexpected red: '.implode(', ', $extra)
            .'; should have stayed green: '.implode(', ', $leaked)."\n";
        // The run's own words, because a verdict that disagrees with the runner is usually this file reading
        // the runner wrong rather than the class failing to notice a defect.
        echo "     ---- runner output ----\n".$result['out']."     -----------------------\n";
    }

    return $ok;
};

// ------------------------------------------------------------------ targets, read from the schema
$clinicSeat = Enrollments::whereHas('enrollmentworkflow.workflowsteps', fn ($q) => $q
    ->where('officeId', OfficeId::Clinic->value)
    ->where('stepStatus', WorkflowStepStatus::Completed->value))
    ->whereHas('clinicrecords')
    ->orderBy('enrollmentId')
    ->first();

$idSeat = Enrollments::whereHas('enrollmentworkflow.workflowsteps', fn ($q) => $q
    ->where('officeId', OfficeId::IdOffice->value)
    ->where('stepStatus', WorkflowStepStatus::Completed->value))
    ->whereHas('idrequests', fn ($q) => $q->where('status', IdRequestStatus::Validated->value))
    ->orderBy('enrollmentId')
    ->first();

$juan = Students::where('schoolIdNumber', 'DEMO-2026-001')->firstOrFail();
$liza = Students::where('schoolIdNumber', 'DEMO-2026-004')->firstOrFail();
$todaySlip = DB::table('payments')->where('orNumber', 'DEMO-OR-TODAY-01')->first();
$registrarHead = Staffusers::where('username', 'office1_head')->firstOrFail();

$lizaRepeat = Enrollments::where('studentId', $liza->studentId)->where('termId', 11)->firstOrFail();

if ($clinicSeat === null || $idSeat === null || $todaySlip === null) {
    fwrite(STDERR, "REFUSE: this schema has no signed Clinic box with a record behind it, no signed ID box with a"
        ." validated request behind it, or no DEMO-OR-TODAY-01 — the demo pipeline never ran here.\n");
    exit(1);
}

echo "Probe target: ".DB::connection()->getDatabaseName()." (clinic seat {$clinicSeat->enrollmentId},"
    ." id seat {$idSeat->enrollmentId}, Juan {$juan->studentId}, Liza {$liza->studentId})\n";
echo "Reference: the class runs green against this schema before any mutant is planted.\n\n";

$control = $run();
$controlGreen = $control['status'] === 0 && $control['failed'] === [];
printf("%s  %-58s red: %s\n\n", $controlGreen ? 'PASS' : 'FAIL', 'control — no mutant planted',
    $controlGreen ? 'nothing' : implode(', ', $control['failed']));

if (! $controlGreen) {
    fwrite(STDERR, "REFUSE: the unmutated class is not green, so nothing below this line can prove anything.\n");
    fwrite(STDERR, '  status '.$control['status'].' from the run; its own output follows.'."\n".$control['out']."\n");
    exit(1);
}

$results = [];

// 1. A signed Clinic box stripped of its health assessment: both the count and the identity assertion.
$results[] = $trial(
    'clinic box signed, assessment deleted',
    function () use ($clinicSeat, $capture): void {
        $capture('clinicrecords', ['enrollmentId' => $clinicSeat->enrollmentId]);
        DB::table('clinicrecords')->where('enrollmentId', $clinicSeat->enrollmentId)->delete();
    },
    ['counted', 'ruled'],
    ['base', 'unsigned']
);

// 2. A signed ID box stripped of its validated request: only the identity assertion, by design — the count
//    still reads 1, which is how many records are ruled to sit in this state.
$results[] = $trial(
    'ID box signed, validated request deleted',
    function () use ($idSeat, $capture): void {
        $capture('idrequests', ['enrollmentId' => $idSeat->enrollmentId]);
        DB::table('idrequests')->where('enrollmentId', $idSeat->enrollmentId)->delete();
    },
    ['ruled'],
    ['counted', 'base', 'unsigned']
);

// 3. Yesterday's receipt: the demo promise the Daily Collections Report reads.
$results[] = $trial(
    "today's receipt re-dated to yesterday",
    function () use ($todaySlip, $capture): void {
        $capture('payments', ['paymentId' => $todaySlip->paymentId]);
        DB::table('payments')->where('paymentId', $todaySlip->paymentId)
            ->update(['paymentDate' => DB::raw('paymentDate - INTERVAL 1 DAY')]);
    },
    ['base'],
    ['counted', 'ruled', 'unsigned']
);

// 4. The base student missing altogether — the state the pipeline used to refuse instead of create.
$results[] = $trial(
    'DEMO-2026-001 renamed away',
    function () use ($juan, $capture): void {
        $capture('students', ['studentId' => $juan->studentId]);
        DB::table('students')->where('studentId', $juan->studentId)
            ->update(['schoolIdNumber' => 'MUTANT-0001']);
    },
    ['base'],
    ['counted', 'ruled', 'unsigned']
);

// 5. The repeat stops being a repeat: her demo-term record climbs a year off the prior one.
$results[] = $trial(
    "Liza's demo-term record no longer repeats her prior level",
    function () use ($lizaRepeat, $capture): void {
        $capture('enrollments', ['enrollmentId' => $lizaRepeat->enrollmentId]);
        DB::table('enrollments')->where('enrollmentId', $lizaRepeat->enrollmentId)
            ->update(['yearLevel' => (int) $lizaRepeat->yearLevel + 1]);
    },
    ['base'],
    ['counted', 'ruled', 'unsigned']
);

// 6. A receipt a desk that never cashed money signed.
$results[] = $trial(
    "today's receipt credited to the Registrar's officer",
    function () use ($todaySlip, $registrarHead, $capture): void {
        $capture('payments', ['paymentId' => $todaySlip->paymentId]);
        DB::table('payments')->where('paymentId', $todaySlip->paymentId)
            ->update(['processedBy' => $registrarHead->userId]);
    },
    ['base'],
    ['counted', 'ruled', 'unsigned']
);

echo "\n";

// The restore is asserted rather than assumed: every captured row count is re-read after the last mutant.
foreach ([
    'clinicrecords' => ['enrollmentId', $clinicSeat->enrollmentId],
    'idrequests' => ['enrollmentId', $idSeat->enrollmentId],
    'payments' => ['orNumber', 'DEMO-OR-TODAY-01'],
    'students' => ['studentId', $juan->studentId],
] as $table => [$column, $value]) {
    $left = DB::table($table)->where($column, $value)->count();
    printf("%s  %-58s %d row(s)\n", $left > 0 ? 'PASS' : 'FAIL', "restore leaves {$table} rows for {$value}", $left);
    $results[] = $left > 0;
}

$schoolId = DB::table('students')->where('studentId', $juan->studentId)->value('schoolIdNumber');
$results[] = $schoolId === 'DEMO-2026-001';
printf("%s  %-58s %s\n", $results[count($results) - 1] ? 'PASS' : 'FAIL', 'restore puts the school number back', (string) $schoolId);

$level = DB::table('enrollments')->where('enrollmentId', $lizaRepeat->enrollmentId)->value('yearLevel');
$results[] = (int) $level === (int) $lizaRepeat->yearLevel;
printf("%s  %-58s year %s\n", $results[count($results) - 1] ? 'PASS' : 'FAIL', 'restore puts Liza back at her level', (int) $level);

$date = DB::table('payments')->where('orNumber', 'DEMO-OR-TODAY-01')->value('paymentDate');
$results[] = $date === $todaySlip->paymentDate;
printf("%s  %-58s %s\n", $results[count($results) - 1] ? 'PASS' : 'FAIL', 'restore re-dates the receipt to the day it was', (string) $date);

$cashier = DB::table('payments')->where('orNumber', 'DEMO-OR-TODAY-01')->value('processedBy');
$results[] = (int) $cashier === (int) $todaySlip->processedBy;
printf("%s  %-58s user %s\n", $results[count($results) - 1] ? 'PASS' : 'FAIL', 'restore returns the receipt to its cashier', (string) $cashier);

$final = $run();
$results[] = $final['status'] === 0;
printf("\n%s  %-58s %s\n", $final['status'] === 0 ? 'PASS' : 'FAIL', 'class green again after the last mutant',
    $final['status'] === 0 ? 'all four tests' : 'red: '.implode(', ', $final['failed']));

$failed = count(array_filter($results, fn (bool $ok): bool => ! $ok));

echo "\n".($failed === 0
    ? "Every assertion in DemoDatasetRecordStatesTest was fed its own defect and named it.\n"
    : "{$failed} verdict(s) failed — the class or this probe needs another look before either is trusted.\n");

exit($failed === 0 ? 0 : 1);
