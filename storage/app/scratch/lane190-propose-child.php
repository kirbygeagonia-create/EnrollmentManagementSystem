<?php

/**
 * One propose-subject act, driven twice at once by lane190.
 *
 * Calls `EvaluationController::proposeSubjects()` — the real act, through the container, with the policy and the
 * validation running — for one enrollment and one payload, and prints a single JSON line the parent reads. It does
 * not reconstruct the delete-then-insert: a fixture that writes the table bypasses the controller's guard, which is
 * this repository's standing rule for a race probe.
 *
 * Auth::setUser, not loginUsingId (a CLI boot has no started session, so Auth::id() answers null and every case
 * dies on the policy with a misleading 403 — measured in lane168).
 */

use App\Http\Controllers\Evaluation\EvaluationController;
use App\Models\Enrollments;
use App\Models\Staffusers;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$root = dirname(__DIR__, 3);

$opt = static function (string $name) {
    $prefix = '--'.$name.'=';
    foreach ($GLOBALS['argv'] as $a) {
        if (str_starts_with($a, $prefix)) {
            return substr($a, strlen($prefix));
        }
    }

    return null;
};

$schema = $opt('schema');
$enrollmentId = (int) $opt('enrollment');
$deskId = (int) $opt('desk');
$rawSubjects = $opt('subjects');
$payload = is_string($rawSubjects) ? json_decode((string) base64_decode($rawSubjects, true), true) : null;
$attemptTag = (string) $opt('tag');

if (! is_string($schema) || preg_match('/^ems_l190_[a-z]$/', $schema) !== 1) {
    fwrite(STDERR, 'REFUSED: --schema must name ems_l190_[a-z] (got '.var_export($schema, true).").\n");
    exit(1);
}
if ($enrollmentId <= 0 || $deskId <= 0) {
    fwrite(STDERR, "REFUSED: --enrollment and --desk must be positive ids.\n");
    exit(1);
}
if (! is_array($payload) || ! isset($payload['subjects']) || $payload['subjects'] === []) {
    fwrite(STDERR, "REFUSED: --subjects must carry a non-empty {subjects:[…]} payload.\n");
    exit(1);
}

putenv('DB_CONNECTION=mysql');
putenv('DB_DATABASE='.$schema);
$_ENV['DB_DATABASE'] = $schema;
$_SERVER['DB_DATABASE'] = $schema;

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$app['config']->set('database.default', 'mysql');
$app['config']->set('database.connections.mysql.database', $schema);
DB::purge('mysql');

$actual = DB::connection()->getDatabaseName();
if ($actual !== $schema) {
    fwrite(STDERR, "REFUSED: the child is talking to `{$actual}`, not the scratch schema `{$schema}` it was given.\n");
    exit(1);
}

// Both children wait for one shared instant so the two acts overlap instead of queueing behind each other's
// process start; without this a race that never happened reads as a race that is closed.
$started = microtime(true);
$startAt = $opt('start-at');
if ($startAt !== null) {
    while (microtime(true) < (float) $startAt) {
        usleep(200);
    }
    $started = microtime(true);
}

$status = 'ok';
$detail = null;

try {
    Auth::setUser(Staffusers::findOrFail($deskId));
    $enrollment = Enrollments::findOrFail($enrollmentId);

    $request = Request::create('/evaluation/'.$enrollmentId.'/subjects', 'POST', $payload);
    $request->setLaravelSession($app['session']->driver());

    $controller = $app->make(EvaluationController::class);
    $controller->proposeSubjects($request, $enrollment);

    $status = 'proposed';
} catch (ValidationException $e) {
    $status = 'refused';
    $flat = [];
    foreach ($e->errors() as $key => $messages) {
        $flat[] = $key.': '.implode(' ', (array) $messages);
    }
    $detail = implode(' | ', $flat);
} catch (Illuminate\Auth\Access\AuthorizationException $e) {
    $status = 'forbidden';
    $detail = $e->getMessage() ?: 'policy refused';
} catch (Illuminate\Database\QueryException $e) {
    $status = 'error';
    $detail = 'driver '.($e->errorInfo[1] ?? '?').' '.$e->getMessage();
} catch (Throwable $e) {
    $status = 'error';
    $detail = get_class($e).': '.substr($e->getMessage(), 0, 220);
}

echo json_encode([
    'tag' => $attemptTag,
    'enrollmentId' => $enrollmentId,
    'status' => $status,
    'detail' => $detail,
    'ms' => round((microtime(true) - $started) * 1000, 1),
    'proposedAfter' => DB::table('enrolledsubjects')
        ->where('enrollmentId', $enrollmentId)->where('status', 'proposed')->count(),
    'statusAfter' => DB::table('enrollments')->where('enrollmentId', $enrollmentId)->value('enrollmentStatus'),
    'readAt' => date('H:i:s'),
    'pid' => getmypid(),
]), "\n";
