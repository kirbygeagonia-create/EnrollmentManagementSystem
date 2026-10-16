<?php

/**
 * Audit lane 2 — the routing surface: who is allowed to reach each route, and which names the
 * frontend actually calls.
 *
 * Three failures this looks for, in order of how much they cost:
 *
 *  1. A route whose handler never asks a question about permission. routes/web.php carries no
 *     permission:/role: middleware at all (measured: 0 occurrences), so EVERY check lives inside a
 *     controller method. If the method that answers a POST never calls authorize()/Gate/can(),
 *     any logged-in staff account can perform that action — the UI button being hidden is not a
 *     control, because the URL is right there in the page source.
 *
 *  2. A route('name') the frontend calls that does not exist. route() is a global from the bundled
 *     Ziggy-style helper; an unknown name throws inside render, and because most of these calls sit
 *     in a submit handler the failure appears only when the desk presses Save.
 *
 *  3. A route nobody links to. Not a vulnerability — a dead door, and the docx registers cite
 *     endpoints by name, so a stale name there is a claim about code that is no longer true.
 *
 * Read-only. Nothing is written.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Route;

$root = dirname(__DIR__, 3);

/** Guard tokens that mean "this code asked who is allowed". */
$guardTokens = [
    '$this->authorize(', 'validateCan(', 'Gate::allows(', 'Gate::forUser(', 'Gate::denies(',
    '->can(', '->cannot(', 'hasPermissionTo(', 'checkPermissionTo(', 'hasAnyPermission(',
    'authorizeResource(', 'abort_unless(', 'ensureIsNotMissing(', 'isGrantedToRole(',
    'hasRole(', '->allows(',
];

function methodBody(string $src, string $method): ?string
{
    // Find `function <method>(` then brace-match its body. Nested braces are counted, so a
    // closure inside the method does not end the extract early.
    $pos = 0;
    $best = null;
    while (preg_match('/function\s+'.$method.'\s*\(/', $src, $m, PREG_OFFSET_CAPTURE, $pos) === 1) {
        $open = strpos($src, '{', $m[0][1] + strlen($m[0][0]));
        if ($open === false) {
            break;
        }
        $depth = 0;
        for ($i = $open, $n = strlen($src); $i < $n; $i++) {
            if ($src[$i] === '{') {
                $depth++;
            } elseif ($src[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    $best = substr($src, $open, $i - $open + 1);
                    break 2;
                }
            }
        }
        $pos = $open + 1;
    }

    return $best;
}

/** The first line of a method body that contains one of the found guard tokens. */
function firstGuardLine(string $body, array $tokens): string
{
    foreach (preg_split('/\R/', $body) as $line) {
        foreach ($tokens as $t) {
            if (str_contains($line, $t)) {
                return trim($line);
            }
        }
    }

    return '(token appears only in a multi-line expression)';
}

$files = [];
foreach ([$root.'/resources/js', $root.'/resources/views'] as $dir) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $f) {
        if ($f->isFile() && preg_match('/\.(jsx|js|blade\.php|php)$/', $f->getFilename())) {
            $files[] = $f->getPathname();
        }
    }
}
$frontend = '';
foreach ($files as $f) {
    $frontend .= "\n".file_get_contents($f);
}
$frontendNames = [];
if (preg_match_all('/route\(\s*[\'"]([A-Za-z0-9_.:-]+)[\'"]/', $frontend, $rm)) {
    foreach ($rm[1] as $n) {
        $frontendNames[$n] = ($frontendNames[$n] ?? 0) + 1;
    }
}
// DeskSubNav.jsx carries its links as descriptors — { name: 'Clearance Periods', route: 'clearance.periods' }
// — and calls route(tab.route) at render. A literal-route() regex alone reported five live, linked
// routes as dead doors, so any complete quoted string in resources/ counts as a call site too.
$descriptors = [];
if (preg_match_all('/[\'"]([A-Za-z][\w.:-]*\.[\w.:-]+)[\'"]/', $frontend, $qm)) {
    foreach ($qm[1] as $n) {
        $descriptors[$n] = ($descriptors[$n] ?? 0) + 1;
    }
}

$appRefs = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app')) as $f) {
    if ($f->isFile() && preg_match('/\.php$/', $f->getFilename())) {
        if (preg_match_all('/[\'"]([A-Za-z][\w.:-]*\.[\w.:-]+)[\'"]/', file_get_contents($f->getPathname()), $am)) {
            foreach ($am[1] as $n) {
                $appRefs[$n] = ($appRefs[$n] ?? 0) + 1;
            }
        }
    }
}

$known = [];
$routes = [];
foreach (Route::getRoutes() as $r) {
    $name = $r->getName();
    if ($name === null) {
        continue;
    }
    $known[$name] = true;
    $routes[] = $r;
}

$srcCache = [];
$readSrc = function (string $path) use (&$srcCache, $root): string {
    $abs = $root.'/'.$path;
    if (! isset($srcCache[$abs])) {
        $srcCache[$abs] = is_file($abs) ? file_get_contents($abs) : '';
    }

    return $srcCache[$abs];
};

$noAuth = [];
$unguarded = [];
$methodBlind = [];
$guardLines = [];
$showGuards = in_array('--show-guards', $argv ?? [], true);
$guarded = 0;

foreach ($routes as $r) {
    $mw = $r->gatherMiddleware();
    $hasAuth = false;
    $permissionMw = [];
    foreach ($mw as $m) {
        if ($m === 'auth' || str_starts_with((string) $m, 'auth:')) {
            $hasAuth = true;
        }
        if (is_string($m) && (str_starts_with($m, 'permission') || str_starts_with($m, 'role:'))) {
            $permissionMw[] = $m;
        }
    }
    $action = $r->getAction('controller');
    $actionName = is_string($action) ? $action : '';
    $isClosure = $actionName === '' && $r->getAction('uses') instanceof Closure;
    if ($actionName !== '' && ! str_contains($actionName, '@')) {
        $isClosure = true;
    }

    if (! $hasAuth && ! in_array('guest', $mw, true)) {
        $noAuth[] = sprintf('%-8s %-52s %s', implode('|', $r->methods()), $r->uri(), $r->getName());
    }
    if (! $hasAuth || $isClosure) {
        continue;
    }

    if (! preg_match('/^(App\\\\[A-Za-z0-9_\\\\]+)@([\w]+)$/', $actionName, $mm)) {
        continue;
    }
    [$all, $class, $method] = $mm;
    $classRel = 'app/'.str_replace('\\', '/', substr($class, 4)).'.php';
    $src = $readSrc($classRel);
    if ($src === '') {
        $methodBlind[] = sprintf('%-8s %-40s %s  (controller file not found: %s)',
            implode('|', $r->methods()), $r->uri(), $r->getName(), $classRel);

        continue;
    }

    $body = methodBody($src, $method);
    $inBody = $body !== null ? array_values(array_filter($guardTokens, fn ($t) => str_contains($body, $t))) : [];
    $inFile = array_values(array_filter($guardTokens, fn ($t) => str_contains($src, $t)));

    if ($body === null) {
        $methodBlind[] = sprintf('%-8s %-40s %s  (method %s::%s not found)',
            implode('|', $r->methods()), $r->uri(), $r->getName(), $class, $method);

        continue;
    }
    if ($inBody !== []) {
        $guarded++;
        if ($showGuards && ! in_array('GET', $r->methods(), true)) {
            $line = firstGuardLine($body, $inBody);
            $guardLines[] = sprintf('%-7s %-42s %-30s %s',
                implode('|', array_diff($r->methods(), ['HEAD'])), $r->getName(),
                implode(' ', $inBody), $line);
        }

        continue;
    }
    $label = sprintf('%-8s %-40s %-34s %s',
        implode('|', array_diff($r->methods(), ['HEAD'])), $r->uri(), $r->getName(),
        $inFile === [] ? 'NO GUARD ANYWHERE IN FILE' : 'method body is blind; file guards elsewhere: '.implode(' ', $inFile));
    if ($inFile === []) {
        $unguarded[] = $label;
    } else {
        $methodBlind[] = $label;
    }
}

$unknown = [];
foreach (array_keys($frontendNames) as $n) {
    if (! isset($known[$n])) {
        $unknown[] = $n;
    }
}

$neverLinked = [];
$serverSideOnly = [];
foreach (array_keys($known) as $n) {
    if (isset($frontendNames[$n]) || isset($descriptors[$n])) {
        continue;
    }
    if (isset($appRefs[$n])) {
        $serverSideOnly[] = sprintf('%-34s %d reference(s) in app/ (redirect), none in the UI', $n, $appRefs[$n]);

        continue;
    }
    $neverLinked[] = $n;
}

printf("routes with a name: %d   guarded by an in-method check: %d   route names called from the frontend: %d\n",
    count($routes), $guarded, count($frontendNames));

echo "\n== A. ROUTES WITH NEITHER auth NOR guest MIDDLEWARE (public surface) ==\n";
echo $noAuth === [] ? "  (none)\n" : '  '.implode("\n  ", array_unique($noAuth))."\n";

echo "\n== B. AUTHENTICATED ROUTES WHOSE HANDLER BODY ASKS NOTHING (no guard token at all in the file) ==\n";
echo $unguarded === [] ? "  (none)\n" : '  '.implode("\n  ", $unguarded)."\n";

echo "\n== C. AUTHENTICATED ROUTES WHOSE BODY IS BLIND BUT WHOSE FILE GUARDS SOMEWHERE ==\n";
echo $methodBlind === [] ? "  (none)\n" : '  '.implode("\n  ", $methodBlind)."\n";

if ($showGuards) {
    echo "\n== C2. THE GUARD LINE EACH WRITE ROUTE ACTUALLY RUNS (--show-guards) ==\n";
    echo $guardLines === [] ? "  (none)\n" : '  '.implode("\n  ", $guardLines)."\n";
}

echo "\n== D. ROUTE NAMES THE FRONTEND CALLS THAT DO NOT EXIST ==\n";echo $unknown === [] ? "  (none)\n" : '  '.implode("\n  ", $unknown)."\n";

echo "\n== E. ROUTE NAMES ONLY A SERVER-SIDE REDIRECT REACHES (no UI link) ==\n";
echo $serverSideOnly === [] ? "  (none)\n" : '  '.implode("\n  ", $serverSideOnly)."\n";

echo "\n== F. ROUTE NAMES NEITHER THE UI NOR app/ REFERENCES (dead doors; tests may still call them) ==\n";
echo $neverLinked === [] ? "  (none)\n" : '  '.implode("\n  ", $neverLinked)."\n";
