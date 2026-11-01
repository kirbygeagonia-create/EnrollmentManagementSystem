<?php

/**
 * One enrollment act, run by lane172's worker pool.
 *
 * Boots the application against the scratch schema, becomes the desk account, and calls
 * `EnrollmentIssuer::issue()` — the one writer that puts an enrollment and its workflow form in a single
 * transaction. In `volume` mode it first stages a synthetic student for each issue (copied from a real row in the
 * clone so the NOT NULL columns are the ones the schema actually demands, and marked `LOAD-` so the parent can
 * scope every integrity question to this run's own rows). In `contention` mode it issues for one student that
 * already exists, so the seat rule is what answers.
 *
 * It calls the service rather than the HTTP stack on purpose and says so in the line it prints: PHP's built-in
 * server handles one request at a time, so an HTTP race leg would serialise and read as no race.
 *
 * Prints exactly one JSON line. A child that prints nothing is a REFUSED run for the parent, never a clean pass.
 */

use App\Enums\EnrollmentStatus;
use App\Models\Enrollments;
use App\Models\Staffusers;
use App\Services\EnrollmentIssuer;
use App\Services\EnrollmentStateMachine;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$opt = static function (string $name) {
    $prefix = '--'.$name.'=';
    foreach ($GLOBALS['argv'] as $a) {
        if (str_starts_with($a, $prefix)) {
            return substr($a, strlen($prefix));
        }
    }

    return null;
};

$known = ['schema', 'mode', 'offset', 'limit', 'term', 'course', 'desk', 'student', 'enrollment', 'start-at'];
foreach ($GLOBALS['argv'] as $a) {
    if (preg_match('/^--([a-z0-9-]+)=/', $a, $m) === 1 && ! in_array($m[1], $known, true)) {
        fwrite(STDERR, 'REFUSED: this child does not understand --'.$m[1].".\n");

        exit(1);
    }
}

$schema = $opt('schema');
$mode = (string) $opt('mode');
$offset = (int) $opt('offset');
$limit = (int) ($opt('limit') ?? 1);
$term = (int) $opt('term');
$course = (int) $opt('course');
$deskId = (int) $opt('desk');
$student = (int) $opt('student');
$enrollmentId = (int) $opt('enrollment');

// Refused and announced before the framework boots: a service provider that reads a table on boot
// (SettingsServiceProvider reads `settings`) would otherwise have queried live `ems` on the way to this schema.
if (! is_string($schema) || preg_match('/^ems_l172_[a-z]$/', $schema) !== 1) {
    fwrite(STDERR, 'REFUSED: --schema must name ems_l172_[a-z] (got '.var_export($schema, true).").\n");

    exit(1);
}
if (! in_array($mode, ['volume', 'contention', 'cancel'], true)) {
    fwrite(STDERR, "REFUSED: --mode must be volume, contention or cancel (got {$mode}).\n");

    exit(1);
}
if ($term <= 0 || $course <= 0 || $deskId <= 0 || $limit <= 0) {
    fwrite(STDERR, "REFUSED: --term, --course, --desk and --limit must all be positive (mode {$mode}).\n");

    exit(1);
}
if ($mode === 'contention' && $student <= 0) {
    fwrite(STDERR, "REFUSED: contention mode names no --student to contend over.\n");

    exit(1);
}
if ($mode === 'cancel' && $enrollmentId <= 0) {
    fwrite(STDERR, "REFUSED: cancel mode names no --enrollment to drop.\n");

    exit(1);
}

putenv('DB_CONNECTION=mysql');
putenv('DB_DATABASE='.$schema);
$_ENV['DB_DATABASE'] = $schema;
$_SERVER['DB_DATABASE'] = $schema;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$app['config']->set('database.default', 'mysql');
$app['config']->set('database.connections.mysql.database', $schema);
DB::purge('mysql');

$actual = DB::connection()->getDatabaseName();
if ($actual !== $schema) {
    fwrite(STDERR, "REFUSED: this child is talking to `{$actual}`, not the scratch schema `{$schema}` it was given.\n");

    exit(1);
}

// Every worker is handed the same instant, so the acts overlap rather than queue behind each other's boot. Without
// the gate the second worker often begins after the first has committed, and a race that did not happen reads as a
// race that is fixed.
$startAt = $opt('start-at');
if ($startAt !== null) {
    $waitUntil = (float) $startAt;
    if ($waitUntil - microtime(true) > 20.0) {
        fwrite(STDERR, 'REFUSED: --start-at is more than 20 seconds in the future, which is a stuck parent, not a gate.');

        exit(1);
    }
    while (microtime(true) < $waitUntil) {
        usleep(200);
    }
}

$started = microtime(true);
$created = 0;
$cancelled = 0;
$refused = 0;
$errored = 0;
$codes = [];
$detail = null;

try {
    // Auth::setUser, not loginUsingId: a CLI boot has no started session, so the session guard writes an id nothing
    // can read back and Auth::id() answers null.
    Auth::setUser(Staffusers::findOrFail($deskId));

    if ($mode === 'cancel') {
        // The desk act, through the machine that is the only writer of status history — not a raw UPDATE, because a
        // load test that bypasses the transition would prove nothing about the concurrency the workflow actually has.
        $machine = $app->make(EnrollmentStateMachine::class);
        try {
            $machine->transition(
                Enrollments::findOrFail($enrollmentId),
                EnrollmentStatus::Dropped,
                Auth::user(),
                'Load run: a cancellation competing with an enrollment for the same seat'
            );
            $cancelled++;
        } catch (QueryException $e) {
            $refused++;
            $codes[(int) ($e->errorInfo[1] ?? 0)] = ($codes[(int) ($e->errorInfo[1] ?? 0)] ?? 0) + 1;
        } catch (Throwable $e) {
            $errored++;
            $detail = $detail ?? (get_class($e).': '.substr($e->getMessage(), 0, 220));
        }

        echo json_encode([
            'mode' => $mode,
            'limit' => $limit,
            'student' => null,
            'enrollment' => $enrollmentId,
            'created' => $created,
            'cancelled' => $cancelled,
            'refused' => $refused,
            'errored' => $errored,
            'codes' => $codes,
            'detail' => $detail,
            'ms' => round((microtime(true) - $started) * 1000, 1),
            'pid' => getmypid(),
        ]), "\n";

        exit($errored > 0 ? 1 : 0);
    }

    $issuer = $app->make(EnrollmentIssuer::class);
    $template = null;
    if ($mode === 'volume') {
        $template = DB::table('students')->where('schoolIdNumber', 'not like', 'LOAD-%')
            ->orderBy('studentId')->limit(1)->first();
        if ($template === null) {
            fwrite(STDERR, "REFUSED: the clone holds no student to copy the required columns from.\n");

            exit(1);
        }
        $template = (array) $template;
        unset($template['studentId'], $template['created_at'], $template['updated_at']);
    }

    for ($i = 0; $i < $limit; $i++) {
        $studentId = $student;

        if ($mode === 'volume') {
            $tag = 'LOAD-'.str_pad((string) ($offset + $i), 7, '0', STR_PAD_LEFT);
            $studentId = (int) DB::table('students')->insertGetId([
                ...$template,
                'schoolIdNumber' => $tag,
                'email' => strtolower($tag).'@load.invalid',
                'username' => strtolower($tag),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        try {
            // The seat question is answered the way every desk answers it today — `EnrollmentIssuer::seatHolder()`,
            // which the controllers call before they issue. The database key that used to back this refusal was
            // withdrawn on 2026-11-01 pending a fixture repair (see the withdrawal migration), so a run that claimed
            // "the server refused" after that date would be quoting a constraint the schema no longer holds.
            $held = EnrollmentIssuer::seatHolder($studentId, $term);
            if ($held !== null) {
                $refused++;
                $codes['seat-held'] = ($codes['seat-held'] ?? 0) + 1;

                continue;
            }

            $issuer->issue([
                'studentId' => $studentId,
                'courseId' => $course,
                'termId' => $term,
                'yearLevel' => 1,
                'studentType' => 'firstYear',
                'enrollmentType' => 'new',
            ], Auth::user());
            $created++;
        } catch (QueryException $e) {
            // The driver's own number, not the SQLSTATE: 23000 covers every integrity refusal, and the difference
            // between "the seat key refused" and "some other column refused" is in errorInfo[1].
            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            if (in_array($driverCode, [1062, 1213, 1205], true)) {
                $refused++;
                $codes[$driverCode] = ($codes[$driverCode] ?? 0) + 1;

                continue;
            }
            $errored++;
            $detail = $detail ?? ('unexpected driver '.$driverCode.': '.substr($e->getMessage(), 0, 220));
        } catch (Throwable $e) {
            $errored++;
            $detail = $detail ?? (get_class($e).': '.substr($e->getMessage(), 0, 220));
        }
    }
} catch (Throwable $e) {
    $errored++;
    $detail = $detail ?? (get_class($e).': '.substr($e->getMessage(), 0, 220));
}

echo json_encode([
    'mode' => $mode,
    'limit' => $limit,
    'student' => $mode === 'contention' ? $student : null,
    'enrollment' => null,
    'created' => $created,
    'cancelled' => $cancelled,
    'refused' => $refused,
    'errored' => $errored,
    'codes' => $codes,
    'detail' => $detail,
    'ms' => round((microtime(true) - $started) * 1000, 1),
    'pid' => getmypid(),
]), "\n";

if ($errored > 0) {
    exit(1);
}
