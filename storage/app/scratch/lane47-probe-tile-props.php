<?php

/**
 * Lane 47 probe — what the desk screens' tiles actually answer on live ems, after the fix.
 *
 * Reads each handler's own Inertia props by calling the action with an authenticated request. This is the
 * handler, not the browser: it proves the number that reaches the screen, and it deliberately does not claim
 * anything about what a desk paints.
 *
 * Read-only. Nothing is written; the only request is a GET.
 *
 * Usage: php storage/app/scratch/lane47-probe-tile-props.php
 */

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admission\AdmissionController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Assessment\AssessmentController;
use App\Http\Controllers\Blocking\BlockingController;
use App\Http\Controllers\Clinic\ClinicController;
use App\Http\Controllers\Clearance\ClearanceController;
use App\Http\Controllers\Evaluation\EvaluationController;
use App\Http\Controllers\Exam\ExamController;
use App\Models\Staffusers;
use Illuminate\Http\Request;

set_exception_handler(function (\Throwable $e): void {
    fwrite(STDERR, 'FATAL: '.$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n");
    exit(1);
});

// An unbounded desk. Which account is decided by measurement, not by assumption: Gate reads the guard, not
// the request's user resolver, so `$this->authorize()` inside an action refuses unless the guard itself is
// logged in — set both, or every screen answers "unauthorized" and the probe reports a refusal as a number.
$candidates = Staffusers::orderBy('userId')->get();
$staff = null;
foreach ($candidates as $c) {
    \Illuminate\Support\Facades\Auth::setUser($c);
    if (\Illuminate\Support\Facades\Gate::forUser($c)->allows('viewAny', \App\Models\Enrollments::class)) {
        $staff = $c;

        break;
    }
}
\Illuminate\Support\Facades\Auth::setUser($staff);

if ($staff === null) {
    fwrite(STDERR, "FATAL: no account on this box is allowed to open a desk queue\n");
    exit(1);
}

printf("asking as %s (unitId=%s, role=%s, permissions=%d)\n\n",
    $staff->username, $staff->unitId ?? 'null', $staff->role->value ?? '?', $staff->getAllPermissions()->count());

$ask = function (string $label, string $class, string $method, array $query = []) use ($staff): void {
    $request = Request::create('/'.$label, 'GET', $query);
    $request->setUserResolver(fn () => $staff);
    \Illuminate\Support\Facades\Auth::setUser($staff);
    // Paginator::resolveCurrentPage() reads the container's request, not the one handed to the action, so a
    // synthetic ?page=2 that is never bound answers page 1 and the probe would report a page it never asked
    // for. Bind it, then ask.
    $app->instance('request', $request);
    \Illuminate\Pagination\Paginator::currentPageResolver(fn ($pageName) => $request->input($pageName, 1));

    try {
        $response = app($class)->{$method}($request);
    } catch (\Throwable $e) {
        printf("  %-24s REFUSED: %s\n", $label, $e->getMessage());

        return;
    }

    // Inertia\Response keeps its props in a protected field, and the payload is only assembled by the
    // middleware in a real request — so read the field, which is what the handler actually handed over.
    $props = null;
    $reflection = new ReflectionObject($response);
    if ($reflection->hasProperty('props')) {
        $prop = $reflection->getProperty('props');
        $prop->setAccessible(true);
        $props = $prop->getValue($response);
    }
    if (is_array($props) && isset($props['page']['props']) && is_array($props['page']['props'])) {
        $props = $props['page']['props'];
    }
    // a Closure prop is resolved by Inertia, not by us; unwrap the ones that are plain data
    if (is_array($props)) {
        foreach ($props as $k => $v) {
            if ($v instanceof \Closure) {
                $props[$k] = $v();
            }
        }
    }
    if (! is_array($props)) {
        printf("  %-24s UNBOUND: the response carries no readable props (%s)\n", $label, get_class($response));

        return;
    }

    // The desks name their number-bag three ways: stats (most desks), summary+queueCounts (Accounting),
    // summary (Assessment). Print whichever the handler actually sent.
    $stats = [];
    foreach (['stats', 'summary', 'queueCounts'] as $key) {
        if (isset($props[$key]) && is_array($props[$key])) {
            $stats[$key] = $props[$key];
        }
    }
    $counts = $stats === [] ? 'no stats prop' : json_encode($stats);
    $list = null;
    foreach (['enrollments', 'blocks', 'logs', 'exams', 'admissions', 'clearances', 'assessments'] as $k) {
        $value = $props[$k] ?? null;
        if ($value instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $list = $k.': '.$value->total().' in the filtered set, '.$value->count().' rows on page '.$value->currentPage();
            if (count($value->items()) === 0 && $value->total() > 0) {
                $list .= ' — this page holds nothing, so the tiles are the only number on the screen';
            }

            break;
        }
    }
    printf("  %-24s %s\n", $label, $counts);
    if ($list !== null) {
        printf("  %-24s   list: %s\n", '', $list);
    }
    if (isset($props['entityTables'])) {
        printf("  %-24s   entityTables: %d offered\n", '', count($props['entityTables']));
    }
};

$ask('evaluation', EvaluationController::class, 'index');
$ask('evaluation-page2', EvaluationController::class, 'index', ['page' => 2]);
$ask('blocking', BlockingController::class, 'index');
$ask('blocking-page2', BlockingController::class, 'index', ['page' => 2]);
$ask('clinic', ClinicController::class, 'index');
$ask('exam-index', ExamController::class, 'index');
$ask('exam-results', ExamController::class, 'results');
$ask('admission', AdmissionController::class, 'index');
$ask('admission-page2', AdmissionController::class, 'index', ['page' => 2]);
$ask('clearance', ClearanceController::class, 'index');
$ask('clearance-page2', ClearanceController::class, 'index', ['page' => 2]);
$ask('assessment', AssessmentController::class, 'index');
$ask('assessment-page2', AssessmentController::class, 'index', ['page' => 2]);
$ask('audit-logs', UserManagementController::class, 'auditLogs');
