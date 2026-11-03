<?php

/**
 * Read-only: what does the academic term list actually answer on this box, under each header, in the payload the
 * browser receives?
 *
 * Lane 47's rule, applied to a sort: ask how long the list is before claiming a desk demonstrated an order. This
 * screen's population is the school's own calendar (nine terms live), which is long enough to show a sequence change
 * rather than only the contract — so this probe prints both, and says which one it proved.
 *
 * The read goes through the HTTP kernel, not around it, and the payload is taken out of the page's `data-page`
 * attribute, because outside the test harness an Inertia response has no `viewData()` and a bare `X-Inertia` header
 * is answered 409. Nothing here writes: a term list is a GET.
 *
 * Usage: php storage/app/scratch/lane223-probe-term-order-live.php --allow-live
 */

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Academicterms;
use App\Models\Staffusers;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

$allowLive = false;
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if ($arg === '--allow-live') {
        $allowLive = true;
    } else {
        fwrite(STDERR, "REFUSED: this probe reads the live database and takes only --allow-live.\n");
        exit(1);
    }
}

$driver = DB::connection()->getDriverName();
$database = DB::connection()->getDatabaseName();
if ($database === 'ems' && ! $allowLive) {
    fwrite(STDERR, "REFUSED: pass --allow-live to read live `ems` deliberately.\n");
    exit(1);
}

printf("connection: %s / %s\n", $driver, $database);

$total = Academicterms::count();
if ($total === 0) {
    fwrite(STDERR, "REFUSED: the term table answers 0 rows on {$database}, so no sequence can be read here.\n");
    exit(1);
}
printf("the term list holds %d row(s) — %s\n\n", $total, $total > 1
    ? 'long enough that a header can visibly reorder the desk'
    : 'too short: only the contract could be shown');

$staff = Staffusers::query()->whereHas('roles', static fn ($q) => $q->where('name', 'Admin'))->first()
    ?? Staffusers::first();
if ($staff === null) {
    fwrite(STDERR, "REFUSED: no account on {$database} to ask the desk with.\n");
    exit(1);
}
printf("asking as %s (userId %s, roles %s)\n\n", $staff->username ?? '?', (string) $staff->userId,
    $staff->roles->pluck('name')->implode(', '));

/** @return array{0:int,1:array<string,mixed>} [status, props] */
$ask = static function (array $query) use ($app, $staff): array {
    Auth::login($staff);
    $request = Request::create(route('admin.reference-data.terms', $query), 'GET');
    $request->headers->set('X-Requested-With', 'XMLHttpRequest');
    $response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);
    $status = $response->getStatusCode();
    if ($status !== 200) {
        return [$status, []];
    }
    if (! preg_match('/data-page="([^"]*)"/', $response->getContent(), $m)) {
        fwrite(STDERR, "  REFUSED: no data-page attribute — the payload was never read.\n");
        exit(1);
    }
    $page = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
    if (! is_array($page) || ! isset($page['props']['terms']['data'])) {
        fwrite(STDERR, "REFUSED: props.terms.data absent — the probe names the wrong prop.\n");
        exit(1);
    }

    return [$status, $page['props']];
};

$label = static function (array $row): string {
    return ($row['academicYear']['yearLabel'] ?? '—').' / '.$row['semester']
        .' / '.Carbon::parse((string) $row['startDate'])->format('Y-m-d');
};

$cases = [
    ['no order asked at all (the screen opens here)', []],
    ['School Year ascending', ['sort' => 'academicYear', 'direction' => 'asc']],
    ['School Year descending', ['sort' => 'academicYear', 'direction' => 'desc']],
    ['Semester ascending', ['sort' => 'semester', 'direction' => 'asc']],
    ['Start Date descending', ['sort' => 'startDate', 'direction' => 'desc']],
    ['End Date ascending', ['sort' => 'endDate', 'direction' => 'asc']],
    ['a key the desk never lists, with the filters still set', ['search' => '2026', 'status' => 'active', 'sort' => 'nothing', 'direction' => 'desc']],
];

$sequences = [];
$offered = null;
foreach ($cases as [$name, $query]) {
    [$status, $props] = $ask($query);
    if ($status !== 200) {
        fwrite(STDERR, "  {$name}: the desk answered {$status} — nothing was measured\n");
        exit(1);
    }
    $rows = $props['terms']['data'];
    $seq = array_map($label, $rows);
    $sequences[$name] = $seq;
    $sort = $props['sort'] ?? [];
    if ($offered === null) {
        $offered = array_values($sort['available'] ?? []);
    }
    $filters = $props['filters'] ?? [];
    printf("%-56s rows %2d  applied %-12s %-4s  offered [%s]  echo sort=%s dir=%s search=%s status=%s\n",
        $name,
        count($rows),
        (string) ($sort['column'] ?? '?'),
        (string) ($sort['direction'] ?? '?'),
        implode(',', $sort['available'] ?? []),
        (string) ($filters['sort'] ?? 'ABSENT'),
        (string) ($filters['direction'] ?? 'ABSENT'),
        (string) ($filters['search'] ?? '—'),
        (string) ($filters['status'] ?? '—'));
    foreach (array_slice($seq, 0, 3) as $line) {
        echo '      '.$line."\n";
    }
}

$default = $sequences['no order asked at all (the screen opens here)'];
$distinct = [];
foreach ($sequences as $name => $seq) {
    $distinct[$name] = $seq === $default ? 'same as the opening order' : 'DIFFERENT';
}
$moved = count(array_filter($distinct, static fn (string $v): bool => $v === 'DIFFERENT'));
printf("\n%d of %d readings moved the list off its opening order; the reverse of School Year %s\n",
    $moved,
    count($sequences),
    $sequences['School Year descending'] === array_reverse($sequences['School Year ascending']) ? 'is the ascending list reversed' : 'is NOT the ascending list reversed');
printf("the desk was offered [%s]; status %s, which is the honest answer because that cell is computed from the row's dates\n",
    implode(',', $offered ?? []),
    in_array('status', $offered ?? [], true) ? 'IS OFFERED — that is the defect' : 'is not offered');
