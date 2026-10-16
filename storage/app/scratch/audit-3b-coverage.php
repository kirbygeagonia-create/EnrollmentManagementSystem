<?php

/**
 * Audit lane 3b — which controller actions no test ever reaches, and which public methods no route
 * ever binds.
 *
 * The unchecked-checkbox defect (courses.requiresRetentionExam, found 2026-10-07) was not a schema
 * problem the database could announce: the column was NOT NULL with no default, which is legal, and
 * the insert only failed when a real payload omitted it. A static schema-vs-code scan can name the
 * shape but not prove the failure. Coverage can: a store/update/destroy method that no test posts to
 * has never been asked for a complete payload at all.
 *
 * So this pairs every route action with the test files: which named routes are never requested by
 * method+URI in tests/, and which public controller methods exist but no route binds (dead handlers
 * that a future reader will assume are reachable).
 *
 * Read-only.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Route;

$root = dirname(__DIR__, 3);

// ---- route actions --------------------------------------------------------------
$actions = [];
foreach (Route::getRoutes() as $r) {
    $controller = $r->getAction('controller');
    if (! is_string($controller) || ! str_contains($controller, '@')) {
        continue;
    }
    [$class, $method] = explode('@', $controller);
    foreach (array_diff($r->methods(), ['HEAD']) as $m) {
        $actions[] = ['route' => $r->getName() ?? $r->uri(), 'class' => $class, 'method' => $method,
            'http' => $m, 'uri' => $r->uri()];
    }
}

// ---- test files -----------------------------------------------------------------
$tests = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/tests')) as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
        $tests[str_replace($root.'\\', '', $f->getPathname())] = file_get_contents($f->getPathname());
    }
}
$all = implode("\n", $tests);

// A test reaches a route by name or by literal URI. Named first: PermissionMatrixTest declares its
// routes as data-provider strings — ['students.index', 'students.view', false] — and never writes
// route('students.index'), so a route\(\s*['"]...['"] regex alone reported nine tested routes as
// untested. Any complete quoted string in tests/ counts.
$byName = [];
if (preg_match_all('/[\'"]([\w\-]+\.[\w.\-]+)[\'"]/', $all, $rm)) {
    $byName = array_fill_keys($rm[1], true);
}
$literals = [];
if (preg_match_all('/(?:get|post|put|patch|delete|options)\(\s*[\'"](\/[^\'"]*)[\'"]/', $all, $um)) {
    foreach ($um[1] as $u) {
        $literals[] = '#^'.str_replace(['{.*}'], ['[^/]+'], preg_quote(preg_replace('/\{\w+\}/', '{.*}', $u), '#')).'$#';
    }
}

$untested = [];
foreach ($actions as $a) {
    $nameHit = isset($byName[$a['route']]);
    $uriHit = false;
    if (! $nameHit) {
        $shape = preg_replace('/\{[^}]+\}/', '[^/]+', $a['uri']);
        foreach ($tests as $src) {
            if (preg_match('#[\'"]/'.str_replace('\\', '\\\\', preg_replace('/\{[^}]+\}/', '(?:[^/]+|\\d+)', $shape)).'[\'"]#', $src) === 1) {
                $uriHit = true;

                break;
            }
        }
    }
    if (! $nameHit && ! $uriHit) {
        $untested[] = sprintf('%-7s %-46s %s', $a['http'], $a['route'], $a['class'].'::'.$a['method']);
    }
}

// ---- public controller methods with no route binding -----------------------------
$bound = [];
foreach ($actions as $a) {
    $bound[$a['class'].'::'.$a['method']] = true;
}
$unbound = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app/Http/Controllers')) as $f) {
    if (! $f->isFile() || ! str_ends_with($f->getFilename(), 'Controller.php')) {
        continue;
    }
    $relative = str_replace(['/', '\\'], ['\\', '\\'], substr($f->getPathname(), strlen($root.'/app/Http/Controllers')));
    $class = 'App\\Http\\Controllers'.substr($relative, 0, -4);
    if ($class === 'App\\Http\\Controllers\\Controller' || ! class_exists($class)) {
        continue;
    }
    // Read the file, don't reflect it: AuthorizesRequests contributes authorize()/authorizeResource()
    // and PHP reports them as declared on the controller, so reflection listed three inherited
    // framework methods per controller as "unbound public actions".
    foreach (explode("\n", file_get_contents($f->getPathname())) as $line) {
        if (! preg_match('/^\s{4}public function (\w+)\s*\(/', $line, $mm)) {
            continue;
        }
        $m = $mm[1];
        if (str_starts_with($m, '__')) {
            continue;
        }
        if (! isset($bound[$class.'::'.$m])) {
            $unbound[] = sprintf('%-58s::%s', str_replace('App\\Http\\Controllers\\', '', $class), $m);
        }
    }
}

printf("route actions: %d   test files: %d   actions no test names: %d\n",
    count($actions), count($tests), count($untested));

echo "\n== A. ROUTE ACTIONS NO TEST FILE NAMES (route('...') absent, literal URI absent) ==\n";
echo $untested === [] ? "  (none)\n" : '  '.implode("\n  ", array_values(array_unique($untested)))."\n";

echo "\n== B. PUBLIC CONTROLLER METHODS NO ROUTE BINDS ==\n";
echo $unbound === [] ? "  (none)\n" : '  '.implode("\n  ", array_values(array_unique($unbound)))."\n";
