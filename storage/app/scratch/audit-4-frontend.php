<?php

/**
 * Audit lane 4 — the Inertia contract between a controller and the page that renders it.
 *
 * The catalog defect (33 inert reset(object) calls, fixed 2026-10-08) was one instance of a wider
 * shape: a page reads a name the server never sends, and the UI answers with an empty state instead
 * of an error, because `undefined ?? {}` is legal and silent. The same shape in the other direction
 * — a controller shares a key no page reads — is how a feature ends up half-built and looks finished.
 *
 * This checks four things per page:
 *  A. a JSX file no controller renders (dead screen) / a render target with no file (a 500 screen);
 *  B. a prop the page destructures that no render of that page supplies (silent empty state);
 *  C. a key some render supplies that the page never reads (a feature the desk cannot see);
 *  D. the leftovers a browser console tells you about: window.confirm/alert, console.*, and the
 *     hard-coded office integers that are supposed to come from the shared officeId.
 *
 * Read-only.
 */

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'ems',
    'database.connections.mysql.username' => 'root', 'database.connections.mysql.password' => '']);
DB::purge('mysql');
$liveOffices = DB::table('offices')->pluck('officeName', 'officeId')->all();

// ---- controller render sites ----------------------------------------------------
$renders = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app')) as $f) {
    if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.php')) {
        continue;
    }
    $src = file_get_contents($f->getPathname());
    if (! preg_match_all('/Inertia::render\(\s*[\'"]([^\'"]+)[\'"]\s*(,)?/', $src, $m, PREG_OFFSET_CAPTURE)) {
        continue;
    }
    $rel = str_replace($root.'\\', '', $f->getPathname());
    foreach ($m[1] as $i => $page) {
        $comma = $m[2][$i][1];
        $keys = [];
        if ($comma !== '' && $comma !== null) {
            // Take the top-level string keys of the props array literal.
            $tail = substr($src, $comma);
            $depth = 0;
            $len = strlen($tail);
            $body = '';
            for ($p = 0; $p < $len; $p++) {
                $ch = $tail[$p];
                if ($ch === '[') {
                    $depth++;
                } elseif ($ch === ']') {
                    $depth--;
                    if ($depth === 0) {
                        $body = substr($tail, 0, $p + 1);

                        break;
                    }
                }
            }
            if (preg_match_all('/^\s*[\'"](\w+)[\'"]\s*=>/m', $body, $km)) {
                $keys = $km[1];
            }
        }
        $renders[$page[0]][] = ['at' => $rel, 'keys' => $keys];
    }
}

// ---- global props every page receives ------------------------------------------
$globals = ['auth', 'flash', 'errors', 'can', 'workflowPhases', 'workflowReturnOfficeId',
    'offices', 'currentTerm', 'queueCounts', 'sidebar', 'appName', 'appLogo', 'recent',
    'notifications', 'unreadCount', 'roleNames', 'officeId', 'unitId', 'desks'];
// Over-inclusive on purpose: every quoted key in the middleware counts as globally shared, so a page
// reading one of them is never reported as reading a prop nobody supplies.
foreach (glob($root.'/app/Http/Middleware/HandleInertiaRequests.php') as $mf) {
    $msrc = file_get_contents($mf);
    if (preg_match_all('/[\'"](\w+)[\'"]\s*=>/', $msrc, $gm)) {
        $globals = array_values(array_unique(array_merge($globals, $gm[1])));
    }
}

// ---- pages on disk --------------------------------------------------------------
$diskPages = [];
$pagesRoot = $root.'/resources/js/Pages/';
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/js/Pages')) as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.jsx')) {
        $key = str_replace('\\', '/', substr(str_replace('\\', '/', $f->getPathname()), strlen($pagesRoot)));
        $diskPages[$key] = $f->getPathname();
    }
}

$missing = [];
$dead = [];
foreach ($diskPages as $key => $path) {
    $bare = preg_replace('/\.jsx$/', '', $key);
    if (! isset($renders[$bare])) {
        $dead[] = $key;
    }
}
foreach (array_keys($renders) as $page) {
    if (! isset($diskPages[$page.'.jsx'])) {
        $missing[] = sprintf('%-42s rendered by %s', $page, implode(', ', array_column($renders[$page], 'at')));
    }
}

// ---- per-page prop contract -----------------------------------------------------
$unsupplied = [];
$unconsumed = [];
$ambiguous = [];
foreach ($renders as $page => $sites) {
    if (! isset($diskPages[$page.'.jsx'])) {
        continue;
    }
    $path = $diskPages[$page.'.jsx'];
    if (! is_file($path)) {
        continue;
    }
    $src = file_get_contents($path);

    $alias = [];
    // Names the page reads off its props object. The parameter list is brace-matched, not
    // [^}]*: `export default function Index({ assessments, summary = {}, queueCounts = {} })`
    // closes the first inner brace and the naive regex silently dropped every key after it —
    // which the "shares a key the page never reads" section then reported as missing features.
    $read = [];
    if (preg_match('/export default function \w+\s*\(\s*\{/', $src, $dm, PREG_OFFSET_CAPTURE) === 1) {
        $open = $dm[0][1] + strlen($dm[0][0]) - 1;
        $depth = 0;
        $list = '';
        for ($p = $open, $n = strlen($src); $p < $n; $p++) {
            $ch = $src[$p];
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    $list = substr($src, $open + 1, $p - $open - 1);

                    break;
                }
            }
        }
        foreach (preg_split('/,(?![^<>=]*>)/', $list) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            // `{ courses: localName }` binds localName; a bare `courses` binds courses.
            // The contract is against the ORIGINAL key, the usage against the local name —
            // Clearance/Index destructures `stats: serverStats`, and testing the alias against
            // the shared keys reported a supplied prop as a silent gap.
            if (preg_match('/^(\w+)\s*:\s*(\w+)/', $entry, $nm) === 1) {
                $read[$nm[2]] = true;
                $alias[$nm[2]] = $nm[1];
            } elseif (preg_match('/^(\w+)/', $entry, $nm) === 1) {
                $read[$nm[1]] = true;
                $contract[$nm[1]] = true;
            }
        }
    }
    if (preg_match_all('/usePage\(\)\.props\.(\w+)/', $src, $pm)) {
        foreach ($pm[1] as $p) {
            $read[$p] = true;
        }
    }
    if (preg_match_all('/props\??\.(\w+)/', $src, $pm2)) {
        foreach ($pm2[1] as $p) {
            $read[$p] = true;
        }
    }

    $supplied = [];
    foreach ($sites as $s) {
        foreach ($s['keys'] as $k) {
            $supplied[$k] = ($supplied[$k] ?? 0) + 1;
        }
    }
    $sharedEverywhere = array_keys($supplied);
    if (count($sites) > 1) {
        $sharedEverywhere = array_values(array_filter($sharedEverywhere, fn ($k) => $supplied[$k] === count($sites)));
    }

    foreach (array_keys($read) as $name) {
        // An alias binds a local name to the shared key it renames.
        if (in_array($name, $globals, true) || isset($supplied[$name])
            || (isset($alias[$name]) && (isset($supplied[$alias[$name]]) || in_array($alias[$name], $globals, true)))) {
            continue;
        }
        $unsupplied[] = sprintf('%-40s reads %s — no render supplies it  (%s)',
            $page, $name, implode(', ', array_column($sites, 'at')));
    }
    foreach ($sharedEverywhere as $k) {
        // A key is consumed if the page names it anywhere in its own text — the destructure, a
        // nested component's prop, a map over it. Requiring it in the parameter list alone flagged
        // pages that hand the whole props object down.
        $uses = preg_match_all('/\b'.preg_quote($k, '/').'\b/', $src);
        if ($uses === 0) {
            $unconsumed[] = sprintf('%-40s shares %-24s 0 mentions in the page  %s', $page, $k,
                count($sites) > 1 ? '('.count($sites).' render sites)' : '');
        } elseif ($uses === 1 && isset($read[$k])) {
            $unconsumed[] = sprintf('%-40s shares %-24s named only in the parameter list  %s', $page, $k,
                count($sites) > 1 ? '('.count($sites).' render sites)' : '');
        }
    }
    if (count($sites) > 1) {
        $partial = array_values(array_filter(array_keys($supplied), fn ($k) => $supplied[$k] < count($sites)));
        if ($partial !== []) {
            $ambiguous[] = sprintf('%-40s %d render sites, keys present on only some: %s', $page, count($sites), implode('/', $partial));
        }
    }
}

// ---- leftovers ------------------------------------------------------------------
$leftovers = [];
$magic = [];
$iconOnly = [];
$usedOffices = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/js')) as $f) {
    if (! $f->isFile() || ! preg_match('/\.(jsx|js)$/', $f->getFilename())) {
        continue;
    }
    $rel = str_replace($root.'\\', '', $f->getPathname());
    foreach (file($f->getPathname()) as $no => $line) {
        $t = trim($line);
        if ($t === '' || str_starts_with($t, '//') || str_starts_with($t, '*')) {
            continue;
        }
        if (preg_match('/\b(window\.)?(alert|confirm)\s*\(/', $t) || preg_match('/\bconsole\.(log|warn|error)\s*\(/', $t)) {
            $leftovers[] = sprintf('%-58s:%-4d %s', $rel, $no + 1, substr($t, 0, 96));
        }
        // Office numbers are an enum on the server and cannot be imported into JSX, so the desk
        // either reads the shared officeId or spells a number out. A bare comparison against a
        // literal is where an office renumbering (office 8 folded into Registrar, ruling 12) breaks.
        if (preg_match('/officeId\s*[!=]==?\s*(\d+)/', $t, $mm) === 1) {
            $magic[] = sprintf('%-58s:%-4d %s', $rel, $no + 1, substr($t, 0, 96));
        }
        // The descriptors that build the launcher carry the number as a FIELD, not a comparison:
        // `officeId: 8`. Comparing only against === missed every one of them, including the dead
        // legacy office 8 that ruling 12 folded into Registrar.
        if (preg_match('/officeId\s*:\s*\[([^\]]*)\]/', $t, $lm) === 1) {
            foreach (preg_split('/[,\s]+/', $lm[1], -1, PREG_SPLIT_NO_EMPTY) as $n) {
                if (ctype_digit($n)) {
                    $usedOffices[(int) $n][] = $rel.':'.($no + 1);
                }
            }
        } elseif (preg_match('/officeId\s*:\s*(\d+)/', $t, $fm) === 1) {
            $usedOffices[(int) $fm[1]][] = $rel.':'.($no + 1);
        }
    }
    // Icon-only controls: a <button> with no accessible name. Peeking at what follows the tag is not
    // enough — the catalog screens open with <svg> then spell the label out ("Add Course"), and that
    // first pass reported 27 well-labelled buttons as unnamed. The interior of every <svg> is
    // removed first (paths and viewBoxes are not labels), and what remains must hold no word.
    $body = file_get_contents($f->getPathname());
    if (preg_match_all('/<button\b[^>]*>/s', $body, $tm, PREG_OFFSET_CAPTURE) > 0) {
        foreach ($tm[0] as $tag) {
            $tail = substr($body, $tag[1], 2500);
            $close = strpos($tail, '</button>');
            if ($close === false) {
                continue;
            }
            $inner = substr($tail, strlen($tag[0]), $close - strlen($tag[0]));
            $stripped = preg_replace('/<svg\b.*?<\/svg>/s', '', $inner);
            $stripped = preg_replace('/<path\b[^>]*\/>/', '', $stripped);
            $hasText = preg_match('/[A-Za-z][A-Za-z ]{2,}/', strip_tags($stripped)) === 1
                || preg_match('/\{\s*[\'"][^\'"]{3,}[\'"]/', $stripped) === 1;
            $named = preg_match('/aria-label|aria-labelledby|title=|\bsrOnly\b|\bsr-only\b|\.srOnly/', $tag[0].$inner) === 1;
            if (! $hasText && ! $named) {
                $no = substr_count(substr($body, 0, $tag[1]), "\n") + 1;
                $iconOnly[] = sprintf('%-58s:%-4d %s', $rel, $no, trim(preg_replace('/\s+/', ' ', $tag[0])));
            }
        }
    }
}

printf("pages rendered: %d   JSX files under Pages: %d   global shared keys: %d\n",
    count($renders), count($diskPages), count($globals));
echo "\n== A1. JSX PAGE NO CONTROLLER RENDERS (dead screen) ==\n";
echo $dead === [] ? "  (none)\n" : '  '.implode("\n  ", $dead)."\n";

echo "\n== A2. RENDER TARGET WITH NO JSX FILE (a 500 or a blank mount) ==\n";
echo $missing === [] ? "  (none)\n" : '  '.implode("\n  ", $missing)."\n";

echo "\n== B. PAGE READS A PROP NO RENDER SUPPLIES (silent empty state) ==\n";
echo $unsupplied === [] ? "  (none)\n" : '  '.implode("\n  ", array_values(array_unique($unsupplied)))."\n";

echo "\n== C. CONTROLLER SHARES A KEY THE PAGE NEVER READS ==\n";
echo $unconsumed === [] ? "  (none)\n" : '  '.implode("\n  ", array_values(array_unique($unconsumed)))."\n";

echo "\n== D. SAME PAGE RENDERED FROM SEVERAL SITES WITH DIFFERENT KEY SETS ==\n";
echo $ambiguous === [] ? "  (none)\n" : '  '.implode("\n  ", array_values(array_unique($ambiguous)))."\n";

echo "\n== E. alert/confirm/console REMNANTS IN THE FRONTEND ==\n";
echo $leftovers === [] ? "  (none)\n" : '  '.implode("\n  ", $leftovers)."\n";

echo "\n== F. officeId COMPARED TO A BARE INTEGER IN JSX ==\n";
echo $magic === [] ? "  (none)\n" : '  '.implode("\n  ", $magic)."\n";

echo "\n== F2. OFFICE NUMBERS SPOKEN IN JSX, CHECKED AGAINST THE LIVE offices TABLE ==\n";
foreach ($usedOffices as $n => $where) {
    printf("  officeId %-4s %-26s used at %s\n", $n, $liveOffices[$n] ?? '*** NOT A LIVE OFFICE ***',
        implode(' ', array_slice(array_unique($where), 0, 4)));
}
$unusedLive = array_diff(array_keys($liveOffices), array_keys($usedOffices));
echo '  live offices no JSX descriptor names: '.($unusedLive === [] ? '(none)' : implode(', ', $unusedLive))."\n";

echo "\n== G. ICON-ONLY <button> WITH NO ACCESSIBLE NAME ==\n";
echo $iconOnly === [] ? "  (none)\n" : '  '.implode("\n  ", $iconOnly)."\n";
