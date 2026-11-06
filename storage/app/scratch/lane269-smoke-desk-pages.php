<?php

/**
 * Read-only smoke over the desk pages, through the HTTP stack, against the database the app uses.
 *
 * Today's batch changed a prop every authenticated page receives (`workflowPhases`, from an
 * office-keyed map to an ordered list), so the question worth asking is not "does the stepper unit
 * test pass" but "does every window still answer 200 with rows in it". This drives the kernel
 * itself — `Auth::setUser` plus a real Request — because `Request::create()` plus a manual
 * middleware call is not the stack, and a page that 500s behind a policy would read green to a
 * unit test. It writes nothing: every path here is a GET.
 *
 * Usage: php storage/app/scratch/lane269-smoke-desk-pages.php [--schema=ems]
 */

$root = dirname(__DIR__, 3);
chdir($root);
require $root.'/vendor/autoload.php';

$schema = 'ems';
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--schema=(\w+)$/', $arg, $m) === 1) {
        $schema = $m[1];
        continue;
    }

    fwrite(STDERR, "REFUSED: this probe reads only --schema=<database name>; got [{$arg}].\n");
    exit(1);
}

putenv('DB_CONNECTION=mysql');
putenv('DB_DATABASE='.$schema);
$_ENV['DB_CONNECTION'] = 'mysql';
$_ENV['DB_DATABASE'] = $schema;
$_SERVER['DB_CONNECTION'] = 'mysql';
$_SERVER['DB_DATABASE'] = $schema;

$app = require_once $root.'/bootstrap/app.php';
// The console kernel only bootstraps; the object that answers an HTTP request is the HTTP kernel,
// and calling handle() on the console one is a BadMethodCallException wearing a stack trace.
$console = $app->make(Illuminate\Contracts\Console\Kernel::class);
$console->bootstrap();
$http = $app->make(Illuminate\Contracts\Http\Kernel::class);

use App\Models\Staffusers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

$active = DB::connection()->getDatabaseName();
if ($active !== $schema) {
    fwrite(STDERR, "REFUSED: the connection answered [{$active}] while the probe was told [{$schema}].\n");
    exit(1);
}

// The administrator is the account the seeders create as `staff8` with the enum role `admin`;
// looking it up by role rather than by a remembered username is what keeps this probe honest on a
// schema whose accounts were re-seated.
$admin = Staffusers::where('status', 'active')
    ->where(fn ($q) => $q->where('role', 'admin')->orWhere('username', 'admin'))
    ->orderBy('userId')
    ->first();

if ($admin === null) {
    fwrite(STDERR, "REFUSED: [{$schema}] holds no active administrator account, so no page can be addressed as someone who may open all of them.\n");
    exit(1);
}

$paths = [
    '/dashboard',
    '/admission', '/admission/create',
    '/exam', '/exam/results', '/exam/create',
    '/clearance', '/clearance/periods',
    '/evaluation', '/evaluation/shift-requests',
    '/assessment',
    '/accounting', '/accounting/daily-report',
    '/registrar',
    '/blocking',
    '/clinic',
    '/id',
    '/students',
    '/admin/workflow-monitor',
    '/admin/reference-data', '/admin/reference-data/subjects',
    '/admin/users', '/admin/users/roles', '/admin/users/permissions', '/admin/users/settings', '/admin/users/audit-logs',
];

$bad = [];
$pages = 0;

foreach ($paths as $path) {
    $request = Request::create($path, 'GET');

    auth()->setUser($admin);

    try {
        $response = $http->handle($request);
    } catch (Throwable $e) {
        $bad[] = "{$path} → threw ".class_basename($e).': '.substr($e->getMessage(), 0, 120);
        continue;
    }

    $status = $response->getStatusCode();
    $body = $response->getContent();
    $pages++;

    if ($status !== 200) {
        $bad[] = "{$path} → HTTP {$status}";
        continue;
    }

    // An Inertia page delivers its props inside the `data-page` attribute; the browser receives that
    // same JSON. A page that answers 200 with no component is a render failure wearing a success code.
    if (! str_contains($body, 'data-page=')) {
        $bad[] = "{$path} → 200 but no Inertia page payload";
        continue;
    }

    if (preg_match('/Internal Server Error|Whoops, looks like something broke/i', $body) === 1) {
        $bad[] = "{$path} → 200 wrapping an error page";
    }
}

// One more claim worth weighing rather than assuming: the shared phase list reaches every page.
$probe = Request::create('/dashboard', 'GET');
auth()->setUser($admin);
$html = $http->handle($probe)->getContent();
preg_match('/data-page="([^"]*)"/', $html, $m);
$props = json_decode(html_entity_decode($m[1] ?? '', ENT_QUOTES), true)['props'] ?? [];
$list = $props['workflowPhases'] ?? null;

if (! is_array($list) || ! array_is_list($list)) {
    $bad[] = 'the shared workflowPhases is not a JSON list, so the browser will re-sort it again';
} else {
    printf("workflowPhases reaches the page as a list of %d boxes, first = %s\n", count($list), $list[0]['label'] ?? '?');
}

printf("%s: %d page(s) served, %d problem(s).\n", $schema, $pages, count($bad));

foreach ($bad as $line) {
    echo '  BAD  '.$line."\n";
}

exit($bad === [] ? 0 : 1);
