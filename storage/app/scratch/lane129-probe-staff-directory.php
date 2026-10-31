<?php

/**
 * Ask the running staff directory, through the HTTP stack, what its order and its two filters actually do.
 *
 * The suite writes its own fixtures; this reads the desk a person would open. Three questions, in the shape the
 * register claims: (1) does the screen hand back `sort` and `direction` it obeyed, and which columns may carry a
 * header; (2) does an office filter survive a search that matches someone in a DIFFERENT office — the defect this
 * batch fixed, asked of the live rows rather than of a fixture; (3) how many accounts does the desk hold, and can a
 * LEFT hop be demonstrated here at all or only in a fixture that writes one (lane 102's rule: ask how long the queue
 * is before claiming a desk demonstrated an order).
 *
 * Read-only GETs, logged in as an account that already exists on this database.
 *
 * usage: php storage/app/scratch/lane129-probe-staff-directory.php
 */
use App\Models\Staffusers;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (array_slice($argv ?? [], 1) !== []) {
    fwrite(STDERR, "REFUSED: this probe reads no flags.\n");

    exit(1);
}

$admin = Staffusers::query()->get()->first(fn (Staffusers $s) => $s->hasRole('SysAdmin'));
if ($admin === null) {
    fwrite(STDERR, "REFUSED: no SysAdmin account on this database, so the desk cannot be asked.\n");

    exit(1);
}

$ask = function (string $route, array $params) use ($app, $admin): array {
    Auth::login($admin);
    $request = Request::create(route($route, $params), 'GET');
    $request->headers->set('X-Requested-With', 'XMLHttpRequest');
    $response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);
    $status = $response->getStatusCode();
    if ($status !== 200) {
        return [$status, null, null];
    }
    if (! preg_match('/data-page="([^"]*)"/', $response->getContent(), $m)) {
        fwrite(STDERR, "  REFUSED: no data-page attribute, so the payload was never read\n");

        exit(1);
    }

    return [$status, json_decode(html_entity_decode($m[1]), true), null];
};

$problems = 0;

// (1) The order contract, and the live queue length.
[$status, $page] = $ask('admin.users.index', []);
if ($status !== 200) {
    fwrite(STDERR, "REFUSED: the directory answered {$status} with no parameters.\n");

    exit(1);
}
$props = $page['props'];
$sort = $props['sort'] ?? null;
if ($sort === null) {
    fwrite(STDERR, "REFUSED: no `sort` prop, so the screen was handed no order to report.\n");

    exit(1);
}
$total = $props['users']['total'] ?? null;
printf("live accounts in the directory : %s\n", var_export($total, true));
printf("default order the handler says  : %s %s\n", $sort['column'], $sort['direction']);
printf("columns that may carry a header : %s\n", implode(', ', $sort['available']));
printf("first three surnames today      : %s\n", implode(', ', array_slice(collect($props['users']['data'])->pluck('lastName')->all(), 0, 3)));
if ($sort['direction'] !== 'asc') {
    echo "  FINDING: a directory that opens descending is the Z-first defect this family exists to refuse\n";
    $problems++;
}

// (2) The office filter against a search belonging to another office.
$officeA = Staffusers::whereNotNull('officeId')->value('officeId');
$other = Staffusers::where('officeId', '!=', $officeA)->whereNotNull('officeId')->first();
if ($other === null) {
    echo "  the directory holds one office only, so the filter/search pair cannot be discriminated here\n";
} else {
    $term = substr($other->lastName, 0, 3);
    [$s2, $p2] = $ask('admin.users.index', ['officeId' => $officeA, 'search' => $term]);
    if ($s2 !== 200) {
        fwrite(STDERR, "  REFUSED: the filtered request answered {$s2}\n");

        exit(1);
    }
    $seen = collect($p2['props']['users']['data'])->pluck('username')->all();
    printf("\nfilter office %d, search %s (%s is in office %d)\n", $officeA, $term, $other->lastName, $other->officeId);
    printf("  rows answered : %d  [%s]\n", $p2['props']['users']['total'], implode(', ', $seen));
    $foreign = array_filter($seen, fn ($u) => $u === $other->username);
    if ($foreign !== []) {
        echo "  FINDING: the search widened the office filter — the defect this batch fixed is still reachable\n";
        $problems++;
    } else {
        echo "  ok: the search did not answer a row from another office\n";
    }
    printf("  echoed filters: sort=%s direction=%s officeId=%s search=%s\n",
        $p2['props']['filters']['sort'] ?? '-', $p2['props']['filters']['direction'] ?? '-',
        $p2['props']['filters']['officeId'] ?? '-', $p2['props']['filters']['search'] ?? '-');
}

// (3) Can the LEFT hop be demonstrated on this data at all?
$nulls = Staffusers::whereNull('officeId')->count();
printf("\naccounts with no office on this database : %d\n", $nulls);
echo $nulls === 0
    ? "  so the LEFT hop is NOT demonstrated by the live desk — it is pinned by the fixture that writes one, and this line is the honest bound on that claim\n"
    : sprintf("  the live desk holds %d office-less account(s); ordering by Office must keep them listed\n", $nulls);

printf("\n%d finding(s)\n", $problems);

exit($problems === 0 ? 0 : 1);
