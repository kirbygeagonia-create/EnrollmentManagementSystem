<?php

/**
 * One assign act, driven twice at once by lane168.
 *
 * Takes the block, the schedule, the enrollment and the desk account, boots the application against a scratch
 * schema, and calls `BlockingController::assignStudents()` for exactly one student. Prints one JSON line the parent
 * reads. It calls the action rather than the HTTP stack on purpose and says so in the verdict: PHP's built-in
 * server handles one request at a time, so an "HTTP race" leg would serialise and read as no race — the false
 * green this file has met from the other side several times.
 */

use App\Http\Controllers\Blocking\BlockingController;
use App\Models\Blocks;
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
            // The offset is strlen($prefix): this file's own lane recorded a `strlen` used as a *character* index
            // and returning the letter `s`, so the flag was accepted and ignored.
            return substr($a, strlen($prefix));
        }
    }

    return null;
};

$schema = $opt('schema');
$blockId = (int) $opt('block');
$scheduleId = (int) $opt('schedule');
$enrollmentId = (int) $opt('enrollment');
$deskId = (int) $opt('desk');

// Refused and announced before the framework boots: a provider that reads a table (SettingsServiceProvider reads
// `settings`) would otherwise have queried live `ems` on its way to the schema this child was told to use.
if (! is_string($schema) || preg_match('/^ems_l168_[a-z]$/', $schema) !== 1) {
    fwrite(STDERR, 'REFUSED: --schema must name ems_l168_[a-z] (got '.var_export($schema, true).").\n");
    exit(1);
}
if ($blockId <= 0 || $scheduleId <= 0 || $enrollmentId <= 0 || $deskId <= 0) {
    fwrite(STDERR, "REFUSED: --block, --schedule, --enrollment and --desk must all be positive ids.\n");
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

$started = microtime(true);
$outcome = null;
$status = 'ok';

// Both children are told the same instant to begin at, so the two acts overlap rather than queue behind each
// other's process start. Without the gate the second child often begins after the first has already committed,
// and a race that did not happen reads as a race that is fixed.
$startAt = $opt('start-at');
if ($startAt !== null) {
    while (microtime(true) < (float) $startAt) {
        usleep(200);
    }
    $started = microtime(true);
}

try {
    // Auth::setUser, not loginUsingId: a CLI boot has no started session, so the session guard's
    // loginUsingId writes an id nothing can read back and Auth::id() answers null — measured, then fixed. The
    // policy then sees the same Staffusers row the desk would.
    Auth::setUser(Staffusers::findOrFail($deskId));
    $block = Blocks::findOrFail($blockId);

    $request = Request::create('/blocking/'.$blockId.'/assign', 'POST', [
        'enrollmentIds' => [$enrollmentId],
        'scheduleId' => $scheduleId,
    ]);
    $request->setLaravelSession($app['session']->driver());

    $controller = $app->make(BlockingController::class);
    $controller->assignStudents($request, $block);

    // The act either stamped its row or threw a ValidationException; reaching here means it stamped.
    $status = 'assigned';
} catch (ValidationException $e) {
    $status = 'refused';
    $outcome = array_key_first($e->errors());
} catch (Throwable $e) {
    $status = 'error';
    $outcome = get_class($e).': '.$e->getMessage();
}

echo json_encode([
    'enrollmentId' => $enrollmentId,
    'status' => $status,
    'detail' => $outcome,
    'ms' => round((microtime(true) - $started) * 1000, 1),
    'seatCountAfter' => DB::table('enrolledsubjects')
        ->where('blockId', $blockId)->where('status', '!=', 'dropped')
        ->distinct()->count('enrollmentId'),
    'readAt' => date('H:i:s'),
    'pid' => getmypid(),
]), "\n";
