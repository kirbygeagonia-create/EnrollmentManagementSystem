<?php

/**
 * Does every route the walkthrough tells a desk to call exist?
 *
 * A row whose screen is gone strands an office exactly like a row whose record is gone, and the
 * §15.1 finding was found by eye. This compares each path the script names against the live route
 * table, with `{parameter}` names normalised away — the script writes {payment} where the route
 * may bind {paymentId}, and that difference is not a defect.
 */

$root = dirname(__DIR__, 3);
// An optional argument lets a mutant copy of the script be measured, so a clean sweep can be proved
// to fire on a planted route rather than trusted because it was quiet.
$md = file($argv[1] ?? $root.'/Documentation/Per-Office-UAT-Walkthrough.md');
$routes = array_filter(explode("\n", file_get_contents(__DIR__.'/routes.txt')));

$norm = function (string $p): string {
    $p = preg_replace('/\{[^}]+\}/', '{}', $p);
    $p = preg_replace('/\?.*$/', '', $p);          // query strings are not part of a route
    // The script writes a concrete id where the route binds a parameter
    // (/evaluation/596/clearance/confirm, /accounting/286/settle), so digits are a placeholder too.
    $p = preg_replace('/(?<=\/)\d+(?=\/|$)/', '{}', $p);
    if ($p !== '' && $p[0] !== '/') {
        $p = '/'.$p;                                // route:list prints URIs with no leading slash
    }

    return rtrim($p, '/') === '' ? '/' : rtrim($p, '/');
};

$live = [];
foreach ($routes as $line) {
    $parts = explode(' ', $line, 2);
    $uri = $parts[1] ?? '';
    $live[strtoupper(trim($parts[0])).' '.$norm($uri)] = $line;
}

$claims = [];
foreach ($md as $i => $line) {
    // Some rows exist to prove a route is GONE (§16.5: "POST /register does not exist"). Treating
    // that as a missing route would turn a passing check into a false finding, so carry the intent.
    $absent = (bool) preg_match('/does not exist|no public self-registration|returns 404|404/i', $line);
    // "POST /a/b/{x}/c" style, method first.
    if (preg_match_all('/\b(GET|POST|PATCH|PUT|DELETE) (\/[A-Za-z0-9_{}\/.-]+)/', $line, $m, PREG_SET_ORDER)) {
        foreach ($m as $set) {
            $claims[] = [strtoupper($set[1]), $set[2], $i + 1, 'explicit'.($absent ? ', expected absent' : '')];
        }
    }
    // A "Path:" line lists screens the desk walks, with no method: read as GET.
    if (preg_match('/^Path:/', $line) && preg_match_all('/`(\/[A-Za-z0-9_{}\/.-]*)`/', $line, $m2)) {
        foreach ($m2[1] as $p) {
            $claims[] = ['GET', $p, $i + 1, 'Path: line'];
        }
    }
}

$missing = 0;
$seen = [];

foreach (array_unique(array_map(fn ($c) => implode('|', $c), $claims)) as $packed) {
    [$method, $path, $lineNo, $kind] = explode('|', $packed);
    if (str_contains($path, '...')) {
        printf("  SKIP  %-6s %-52s line %-4d (%s) — shorthand, not a route\n", $method, $path, $lineNo, $kind);

        continue;
    }
    $key = $method.' '.$norm($path);
    if (isset($seen[$key])) {
        continue;
    }
    $seen[$key] = true;

    if (isset($live[$key])) {
        if (str_contains($kind, 'expected absent')) {
            $missing++;
            printf("  WRONG %-6s %-52s line %-4d — the row says it does not exist, but the route IS registered\n", $method, $path, $lineNo);
        }

        continue;
    }

    if (str_contains($kind, 'expected absent')) {
        printf("  PASS  %-6s %-52s line %-4d — correctly absent: no such route on the install\n", $method, $path, $lineNo);

        continue;
    }

    // A path may exist under another verb (the script sometimes drops it) — say which, because
    // "missing" and "wrong verb" are different findings and only one of them is a dead route.
    $other = array_values(array_filter(array_keys($live), fn ($k) => str_ends_with($k, ' '.$norm($path))));
    $missing++;
    printf("  MISS  %-6s %-52s line %-4d (%s)%s\n", $method, $path, $lineNo, $kind,
        $other === [] ? '' : '  exists as: '.implode(', ', $other));
}

printf("\n%d distinct routes claimed; %d not found on the install\n", count($seen), $missing);
