<?php
/**
 * Lane 107 probe: read the Accounting and Assessment queues THROUGH the HTTP stack on live `ems`, and ask what the
 * browser actually receives.
 *
 * The Pest cases prove the same thing on the suite's SQLite schema. This asks the question of the school's own
 * database and the real router, because G-51 was a wire defect: `ListSort` joined `students` to order a surname and
 * the builder's bare `select *` answered that table's columns on the fee-sheet row, so `passwordHash` reached the
 * desk's payload. `Students::$hidden` cannot stop that, so the only proof that matters is the serialized page.
 *
 * It reads the payload out of the page's `data-page` attribute, not `viewData()` — outside the test harness
 * `Illuminate\Http\Response` has no such method, and a request carrying only `X-Inertia` is answered 409 with no
 * props because it lacks the server's `X-Inertia-Version`. Both writes are absent: this probe only GETs.
 *
 * Usage: php storage/app/scratch/lane107-probe-live-queue-payload.php
 */
require __DIR__.'/../../../vendor/autoload.php';
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Staffusers;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

$targets = [
    ['accounting.index', 'office2_head', 'assessments', ['name', 'studentType', 'academicStanding']],
    ['assessment.index', 'office3_head', 'assessments', ['name', 'studentType', 'academicStanding']],
    ['registrar.index', 'office1_head', 'enrollments', ['name', 'studentType', 'academicStanding']],
];

$leaks = 0;
foreach ($targets as [$route, $username, $prop, $sorts]) {
    $staff = Staffusers::where('username', $username)->first();
    if ($staff === null) {
        fwrite(STDERR, "REFUSED: no account named {$username} on this database — the desk cannot be asked.\n");
        exit(1);
    }

    echo "== {$route} as {$username} ==\n";
    $rowsSeen = null;
    foreach ($sorts as $sort) {
        Auth::login($staff);
        $request = Request::create(route($route, ['sort' => $sort, 'direction' => 'asc']), 'GET');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);
        $status = $response->getStatusCode();
        if ($status !== 200) {
            fwrite(STDERR, "  the desk answered {$status} for sort={$sort} — nothing was measured\n");
            $leaks++;
            continue;
        }
        $html = $response->getContent();
        if (! preg_match('/data-page="([^"]*)"/', $html, $m)) {
            fwrite(STDERR, "  REFUSED: no data-page attribute in the response, so the payload was never read\n");
            $leaks++;
            continue;
        }
        $page = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
        if (! is_array($page) || ! isset($page['props'][$prop]['data'])) {
            fwrite(STDERR, '  REFUSED: props.'.$prop.'.data absent — the probe is naming the wrong prop\n');
            $leaks++;
            continue;
        }
        $rows = $page['props'][$prop]['data'];
        $count = is_array($rows) ? count($rows) : 0;
        $echo = $page['props']['sort'] ?? [];
        $keys = $count > 0 ? array_keys($rows[0]) : [];
        sort($keys);
        printf(
            "  rows %2d  applied %s/%s  offered %d  row columns %d\n",
            $count,
            (string) ($echo['column'] ?? '?'),
            (string) ($echo['direction'] ?? '?'),
            is_array($echo['available'] ?? null) ? count($echo['available']) : 0,
            count($keys)
        );

        // Where the field sits is the whole question. `json_encode` of the row finds a name ANYWHERE, which reads
        // exactly like the G-51 defect while describing the queue's own eager load — the screen shows the student's
        // name, so it joins the student, and a birthdate inside that relation is not the same claim as a birthdate on
        // the fee-sheet row. Report the path, and refuse to call the nested one a leak of the pinned kind.
        $findPaths = static function ($node, string $field, string $trail = '') use (&$findPaths): array {
            $hits = [];
            if (! is_array($node)) {
                return $hits;
            }
            foreach ($node as $k => $v) {
                $here = $trail === '' ? (string) $k : $trail.'.'.((string) $k);
                if ((string) $k === $field) {
                    $hits[] = $here;
                }
                if (is_array($v)) {
                    $hits = array_merge($hits, $findPaths($v, $field, $here));
                }
            }

            return $hits;
        };

        foreach (['passwordHash', 'contactNumber', 'birthdate'] as $secret) {
            $paths = [];
            foreach ($rows as $r) {
                foreach ($findPaths($r, $secret, $prop) as $p) {
                    $paths[$p] = true;
                }
            }
            if ($paths === []) {
                printf("  %-14s absent from the payload entirely\n", $secret);
                continue;
            }
            $atTop = false;
            foreach (array_keys($paths) as $p) {
                if (! str_contains($p, '.')) {
                    $atTop = true;
                }
            }
            printf("  %-14s %s — paths: %s\n", $secret, $atTop ? 'ON THE ROW (G-51 shape)' : 'nested only', implode(', ', array_slice(array_keys($paths), 0, 3)));
            if ($atTop) {
                $leaks++;
            }
        }
        if ($rowsSeen === null) {
            $rowsSeen = $keys;
        } elseif ($keys !== $rowsSeen) {
            printf("  SHAPE DRIFT: sort=%s gives %d columns where the first order gave %d\n", $sort, count($keys), count($rowsSeen));
            $leaks++;
        }
    }
    echo '  row columns: '.implode(', ', $rowsSeen ?? [])."\n";
}

echo $leaks === 0
    ? "VERDICT: no credential column reached any desk payload, and every order answered the same column set.\n"
    : sprintf("%d finding(s) above.\n", $leaks);
exit($leaks === 0 ? 0 : 1);
