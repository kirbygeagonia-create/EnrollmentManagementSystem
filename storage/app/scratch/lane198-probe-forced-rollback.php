<?php

/**
 * The guide's §V.2 asks for "a failure scenario and the observed result", and §V.3 for atomicity.
 *
 * T02 and T17 have been carried as "not executed, and not reachable as posed" because the only injection thought
 * of was an INPUT that fails mid-loop — and the assign act validates `enrollmentIds.*` with `exists:` before the
 * transaction opens, so a bad id never reaches it. That reasoning stopped one layer too early: a real mid-
 * transaction fault is a DATABASE event (a deadlock, a lost connection, a refused write), and Laravel notifies a
 * query listener after each statement inside the same transaction. Throwing there is not a seam added for a test —
 * it is exactly the shape of the failure the guide poses, arriving at the boundary the act does not control.
 *
 * So this drives the ONE writer that puts an enrollment and its workflow form in a single transaction
 * (`App\Services\EnrollmentIssuer::issue()`) twice against a scratch schema, and prints both observations:
 *
 *   commit   — a fresh act with its own request key leaves one enrollment, one workflow form, and the box rows
 *              the same transaction wrote;
 *   failure  — the same act with a listener that throws the first time it sees the workflow-form insert. The
 *              enrollment row WAS already written when the fault fired, and after the rollback NOTHING is there:
 *              no enrollment for that key, no form, no box rows, and the count of each is re-read from the server.
 *
 * Refusals (exit 1, cause named, so a green line can never be a non-event): a `--schema=` that is not a scratch
 * name or is the live `ems`; a schema whose `enrollments` table is empty (the act would fail for the wrong
 * reason); the injected fault never firing (a rollback of nothing proves nothing); any row surviving the failure;
 * or the commit case not writing what the transaction promises.
 *
 * Usage: DB_CONNECTION=mysql php storage/app/scratch/lane198-probe-forced-rollback.php --schema=ems_l197_a
 */
$root = dirname(__DIR__, 3);
chdir($root);

$argvMap = [];
foreach (array_slice($GLOBALS['argv'], 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) {
        $argvMap[$m[1]] = $m[2] ?? true;
    } else {
        fwrite(STDERR, 'REFUSED: this tool does not understand '.escapeshellarg($a).".\n");
        exit(1);
    }
}
$unknown = array_values(array_diff(array_keys($argvMap), ['schema']));
if ($unknown !== []) {
    fwrite(STDERR, 'REFUSED: flag(s) not read by this tool: --'.implode(', --', $unknown).".\n");
    exit(1);
}
$schema = (string) ($argvMap['schema'] ?? '');
if ($schema === '' || ! preg_match('/^ems_[a-z0-9]+_[a-z]$/', $schema) || $schema === 'ems') {
    fwrite(STDERR, "REFUSED: --schema must be a scratch schema name like ems_lNNN_a; got '{$schema}'. The live database is never a rehearsal target.\n");
    exit(1);
}
if ((string) getenv('DB_CONNECTION') !== 'mysql') {
    fwrite(STDERR, "REFUSED: DB_CONNECTION is not pinned to mysql, so this run would write to the suite's SQLite file while claiming a server reading.\n");
    exit(1);
}
putenv('DB_DATABASE='.$schema);
$_ENV['DB_DATABASE'] = $schema;
$_SERVER['DB_DATABASE'] = $schema;

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Enrollments;
use App\Models\Staffusers;
use App\Models\Students;
use App\Services\EnrollmentIssuer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

// The first run of this probe died on `Unknown column 'isActive'` — the framework rendered the fatal and the
// process still exited 0, so a wrapper that reads the status would have recorded a verdict about a run that
// observed nothing. Anything that escapes the named guards below is therefore a refusal, by name, with exit 1.
set_exception_handler(static function (Throwable $e) use ($fail): never {
    $fail('the probe threw outside its own guards: '.get_class($e).' — '.substr($e->getMessage(), 0, 200));
});

$live = (int) DB::table('enrollments')->count();
if ($live === 0) {
    $fail("schema {$schema} holds no enrollments, so a refusal could not be told from an unseeded database.");
}
// `staffusers.status` is the enum('active','inactive') the desk accounts carry; there is no `isActive` column,
// and asking for one dies on 1054 before either case runs.
$staff = Staffusers::where('status', 'active')->first();
if (! $staff instanceof Staffusers) {
    $fail("schema {$schema} holds no active staff account to sign the act.");
}

$counts = static function (): array {
    return [
        'enrollments' => (int) DB::table('enrollments')->count(),
        'forms' => (int) DB::table('enrollmentworkflow')->count(),
        'boxes' => (int) DB::table('workflowsteps')->count(),
    ];
};

$stage = static function (string $tag) use ($staff): Students {
    $src = Students::where('status', 'active')->firstOrFail();
    // Raw columns, not toArray(): an appended accessor is not a fillable key and would throw on create, which
    // is a probe defect wearing the costume of a database finding.
    $row = $src->getAttributes();
    unset($row['studentId'], $row['created_at'], $row['updated_at']);
    $row['schoolIdNumber'] = $tag.'-'.strtoupper(substr(uniqid(), -8));
    $row['username'] = strtolower($tag.'_'.substr(uniqid(), -8));
    $row['email'] = strtolower($tag.'_'.substr(uniqid(), -8).'@example.test');
    $row['passwordHash'] = bcrypt('probe-not-a-credential');

    return Students::create($row);
};

$attrs = static function (int $studentId) use ($schema): array {
    // `academicterms` holds no status column (measured: termId, academicYearId, semester, startDate, endDate),
    // so "a term the act accepts" is answered from the data rather than from a flag: take a term an existing
    // enrollment on this very schema already sits on, and print which one was chosen.
    $term = (int) (DB::table('enrollments')->whereNotNull('termId')->value('termId')
        ?? DB::table('academicterms')->value('termId'));
    if ($term === 0) {
        throw new RuntimeException("schema {$schema} names no term to seat an enrollment on.");
    }

    return [
        'studentId' => $studentId,
        'courseId' => (int) DB::table('courses')->value('courseId'),
        'termId' => $term,
        'yearLevel' => 1,
        'studentType' => 'firstYear',
        'enrollmentType' => 'new',
    ];
};

Auth::setUser($staff);
$issuer = app(EnrollmentIssuer::class);
$out = [];
$bad = 0;

// ---------- 1. the commit half: one act, everything it names, together ----------
$keyA = 'RBK-A-'.strtoupper(substr(uniqid(), -10));
$before = $counts();
$enrollment = $issuer->issue($attrs((int) $stage('RBKA')->studentId), $staff, $keyA);
$after = $counts();
$written = (int) Enrollments::where('requestKey', $keyA)->count();
// The form and its boxes are re-read from the server by id, not off the returned model: a relation the issuer
// never eager-loaded would be a lazy read on a stale in-memory row, and the observation must be the server's.
$formWorkflow = DB::table('enrollmentworkflow')->where('enrollmentId', $enrollment->enrollmentId)->value('workflowId');
$form = (int) ($formWorkflow === null ? 0 : 1);
$boxes = (int) DB::table('workflowsteps')->where('workflowId', (int) $formWorkflow)->count();
$ok = $written === 1 && $form === 1 && $boxes > 0
    && $after['enrollments'] === $before['enrollments'] + 1
    && $after['forms'] === $before['forms'] + 1
    && $after['boxes'] === $before['boxes'] + $boxes;
if (! $ok) {
    $bad++;
}
$out[] = sprintf('COMMIT   key %s: enrollment %d, form %d, boxes %d (tables +%d/+%d/+%d) %s',
    $keyA, $written, $form, $boxes,
    $after['enrollments'] - $before['enrollments'],
    $after['forms'] - $before['forms'],
    $after['boxes'] - $before['boxes'],
    $ok ? 'ok' : 'BAD');

// ---------- 2. the failure half: the fault arrives AFTER the first row is written ----------
$keyB = 'RBK-B-'.strtoupper(substr(uniqid(), -10));
$studentB = (int) $stage('RBKB')->studentId;
$injected = 0;
$seenAtFault = 0;
$before = $counts();

DB::listen(static function ($query) use (&$injected, &$seenAtFault, $keyB): void {
    $sql = strtolower(str_replace(['`', '"'], '', $query->sql));
    if ($injected === 0 && str_contains($sql, 'insert into enrollmentworkflow')) {
        $injected++;
        // The visibility read is itself a statement on this connection, so the fire-once flag is set BEFORE it.
        // The falsifier's third leg moves the needle to `select `, and with the counter still at zero that read
        // re-entered this listener, fired it again, and the process died on a stack overflow wearing the costume
        // of a verdict — exit 255, no refusal, no cause.
        $seenAtFault = DB::table('enrollments')->where('requestKey', $keyB)->exists() ? 1 : 0;
        throw new RuntimeException('forced fault after the enrollment row was written');
    }
});

$thrown = '';
try {
    $issuer->issue($attrs($studentB), $staff, $keyB);
} catch (Throwable $e) {
    $thrown = get_class($e).': '.substr($e->getMessage(), 0, 80);
}
DB::listen(static function () : void {});
$after = $counts();

$orphans = (int) Enrollments::where('requestKey', $keyB)->count();
$studentRows = (int) Enrollments::where('studentId', $studentB)->count();
$ok = $injected === 1
    && $seenAtFault === 1
    && $thrown !== ''
    && $orphans === 0
    && $studentRows === 0
    && $after === $before;
if (! $ok) {
    $bad++;
}
$out[] = sprintf('FAULT    injected %d time(s), threw %s', $injected, $thrown === '' ? 'NOTHING (BAD)' : $thrown);
$out[] = sprintf('ORDER    at the instant of the fault the enrollment row for that key was visible on the same connection: %s',
    $seenAtFault === 1 ? 'YES (so the rollback had something to undo)' : 'NO (BAD — the fault fired before any write)');
$out[] = sprintf('ROLLBACK key %s: enrollments %d, that student\'s enrollments %d; tables +%d/+%d/+%d %s',
    $keyB, $orphans, $studentRows,
    $after['enrollments'] - $before['enrollments'],
    $after['forms'] - $before['forms'],
    $after['boxes'] - $before['boxes'],
    $ok ? 'nothing survived' : 'BAD — rows moved');
$out[] = sprintf('schema %s held %d enrollments before the pair and %d after (the commit adds exactly one)',
    $schema, $live, (int) DB::table('enrollments')->count());

foreach ($out as $line) {
    echo $line."\n";
}
if ($bad > 0) {
    fwrite(STDERR, "{$bad} observation(s) did not match what the transaction promises — read that as the probe, not as the engine.\n");
    exit(1);
}
echo "VERDICT: the same act is observed committing every row it names and rolling back every row it wrote when the fault arrives between two of them.\n";
exit(0);
