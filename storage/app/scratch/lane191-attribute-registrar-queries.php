<?php

/**
 * lane191 — which two queries does one Registrar queue row actually issue, and from which line?
 *
 * ADS problem 9 is priced in the documents as "40 queries for a 20-row page against 2 batched"
 * (`lane164-out.txt`), but no artefact says WHICH queries those are or which method sends them. That matters
 * because the fix cannot be a list-only shortcut: the same three readiness gates are read by the record page,
 * and a second definition of "may I approve this" is this repository's oldest defect. So the first step is a
 * measurement that names the call sites.
 *
 * Read-only by the nature of the act: it dispatches the same GET the desk dispatches against live `ems`, and
 * `RegistrarController::index()` is a paginated read with no write on its path (the guard that makes that claim
 * checkable is the row count and the `students` count printed below — if the source moved, the probe says so).
 * It prints every statement in order, attributed to the first application frame above it, and the count per
 * row so the "40 queries" figure can be re-taken rather than recalled.
 *
 * Usage: php storage/app/scratch/lane191-attribute-registrar-queries.php [--rows=20]
 */
$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';

$opt = static function (string $name, $default = null) {
    $prefix = '--'.$name.'=';
    foreach (array_slice($GLOBALS['argv'], 1) as $a) {
        if (str_starts_with($a, $prefix)) {
            return substr($a, strlen($prefix));
        }
    }

    return $default;
};
foreach (array_slice($GLOBALS['argv'], 1) as $a) {
    if (str_starts_with($a, '--') && ! preg_match('/^--(rows|verbose)=/', $a)) {
        fwrite(STDERR, 'REFUSED: this tool does not read '.escapeshellarg($a).".\n");
        exit(1);
    }
}
$rows = (int) $opt('rows', 20);
if ($rows < 1 || $rows > 50) {
    fwrite(STDERR, "REFUSED: --rows must be 1 to 50 (got {$rows}) — this is an attribution read, not a load test.\n");
    exit(1);
}

$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$db = $app['db'];
$schema = $db->connection()->getDatabaseName();
if ($schema !== 'ems') {
    fwrite(STDERR, "REFUSED: this probe reads `{$schema}` but was written to read live `ems` read-only.\n");
    exit(1);
}

$statements = [];
\DB::listen(function ($q) use (&$statements) {
    // Attribute by walking the live stack for the first frame inside app/ that is not the connection itself.
    $who = null;
    foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30) as $f) {
        if (! isset($f['file'])) {
            continue;
        }
        if (str_contains($f['file'], '/vendor/') || str_contains($f['file'], '\\vendor\\')) {
            continue;
        }
        if (str_contains($f['file'].'/'.$f['function'], 'lane191')) {
            continue;
        }
        $who = basename($f['file']).':'.$f['line'].' '.$f['function'].'()';
        break;
    }
    $statements[] = ['sql' => preg_replace('/\s+/', ' ', $q->sql), 'from' => $who];
});

$desk = App\Models\Staffusers::where('username', 'staff8')->first();
if (! $desk) {
    fwrite(STDERR, "REFUSED: live `ems` answers no staff8, so the queue cannot be opened as a desk.\n");
    exit(1);
}

$request = Illuminate\Http\Request::create('/registrar?per_page='.$rows, 'GET');
$request->setLaravelSession($app['session']->driver());
Illuminate\Support\Facades\Auth::setUser($desk);

$before = count($statements);
$studentsBefore = (int) $db->connection()->table('students')->count();
$response = $httpKernel->handle($request);
$status = $response->getStatusCode();
$studentsAfter = (int) $db->connection()->table('students')->count();
if ($studentsBefore !== $studentsAfter) {
    fwrite(STDERR, "REFUSED: live `ems` went from {$studentsBefore} to {$studentsAfter} students across the dispatch."
        ." The GET was assumed read-only; that assumption is now falsified, so stop and read the action.\n");
    exit(1);
}

$queries = array_slice($statements, $before);
if ($status !== 200) {
    fwrite(STDERR, "REFUSED: the queue answered {$status}, so the statements below are an error page's reads, not the desk's.\n");
    exit(1);
}

// Pull the row count back out of the payload the browser receives, so the divisor is measured and not assumed.
$html = $response->getContent();
if (! preg_match('/data-page="([^"]*)"/', $html, $m)) {
    fwrite(STDERR, "REFUSED: the response carries no data-page attribute, so the page size cannot be read.\n");
    exit(1);
}
$page = json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5), true);
$listed = count($page['props']['enrollments']['data'] ?? []);
if ($listed === 0) {
    fwrite(STDERR, "REFUSED: the queue handed over 0 rows, so a per-row cost cannot be divided out.\n");
    exit(1);
}

$counts = [];
foreach ($queries as $i => $q) {
    $key = $q['from'] ?? '(unattributed)';
    $counts[$key] = ($counts[$key] ?? 0) + 1;
}

printf("live `ems`, Registrar queue: %d row(s) listed, %d statement(s) after the page's own eager loads\n", $listed, count($queries));
echo "\nstatements, in order:\n";
foreach ($queries as $i => $q) {
    printf("  %2d  %-62s  %s\n", $i + 1, substr((string) $q['from'], 0, 62), substr($q['sql'], 0, 88));
}
echo "\nattributed totals:\n";
arsort($counts);
foreach ($counts as $k => $c) {
    printf("  %3d  %s\n", $c, $k);
}
printf("\nper-row cost: %.2f statement(s) per listed row counting the page's own shared reads\n", count($queries) / $listed);

// The figure the fix has to move is not that one: it is the statements whose COUNT equals the rows listed,
// because those are the reads that repeat per row. Naming them by call site is the point of this probe.
$perRow = array_filter($counts, fn ($c) => $c === $listed && $listed > 1, ARRAY_FILTER_USE_BOTH);
$perRowTotal = array_sum($perRow);
printf("repeating per-row statements: %d over %d row(s) = %.1f per row, from %d call site(s)\n",
    $perRowTotal, $listed, $perRowTotal / max(1, $listed), count($perRow));
foreach ($perRow as $k => $c) {
    echo '  - '.$k."\n";
}
if ($perRow === []) {
    fwrite(STDERR, "REFUSED: no call site repeated once per row, so the queue's page is smaller than the repeating\n"
        ."reads it would take to answer this question — re-run against a page with more rows than one.\n");
    exit(1);
}
echo "The documents price P09 at 40 queries for a 20-row page against 2 batched; the totals above name which\n"
    ."methods send them, which is the step the fix cannot skip — the same gates are read by the record page, so\n"
    ."a batched answer has to live in the class both call, not beside the list that wants it.\n";
