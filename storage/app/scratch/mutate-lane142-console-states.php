<?php

/**
 * Harness for lane142-measure-console-states.php — each bucket must be able to fire on a planted positive.
 *
 * The instrument reports five findings over the real tree, and a report of five is not evidence that the reader
 * works: the first draft counted a page whose only act is `window.print()` as "missing a loading state", which is
 * a finding the tool invented. So the fixtures below are a small tree of their own, each page asserting exactly one
 * behaviour — a missing empty message, a router refresh with no pending state, a clean page, a page with no refresh
 * at all (which must NOT be flagged), and a table tag the scanner cannot bound (which must be named, not skipped).
 * A bucket that cannot fire on its own fixture is decoration.
 *
 * Usage: php storage/app/scratch/mutate-lane142-console-states.php
 */
$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$tool = 'storage/app/scratch/lane142-measure-console-states.php';
$fixtureDir = 'storage/app/scratch/lane142-fixture';
$emptyDir = 'storage/app/scratch/lane142-empty';

$fail = 0;
$legs = 0;
$report = static function (string $leg, bool $ok, string $detail = '') use (&$fail, &$legs): void {
    $legs++;
    printf("  %s  %-32s %s\n", $ok ? 'ok  ' : 'BAD ', $leg, $detail);
    if (! $ok) {
        $fail++;
    }
};

// Built fresh every run, removed at the end: a fixture that outlives its harness becomes a page the next reader walks.
$dirs = [$fixtureDir, $emptyDir];
$cleanup = static function () use (&$dirs, $root): void {
    foreach ($dirs as $d) {
        $p = $root.'/'.$d;
        if (! is_dir($p)) {
            continue;
        }
        foreach (glob($p.'/*') ?: [] as $f) {
            unlink($f);
        }
        rmdir($p);
    }
};
register_shutdown_function($cleanup);

foreach ($dirs as $d) {
    $p = $root.'/'.$d;
    if (is_dir($p)) {
        array_map('unlink', glob($p.'/*.jsx') ?: []);
        rmdir($p);
    }
    if (! mkdir($p, 0777, true) && ! is_dir($p)) {
        fwrite(STDERR, "REFUSED: could not create the fixture directory {$d}.\n");

        exit(1);
    }
}

/** One file, written and read back — a fixture that never landed measures nothing. */
$page = static function (string $name, string $body) use ($root, $fixtureDir): void {
    $path = $root.'/'.$fixtureDir.'/'.$name;
    $written = @file_put_contents($path, $body);
    if ($written !== strlen($body) || (string) file_get_contents($path) !== $body) {
        fwrite(STDERR, "REFUSED: the fixture {$name} did not land on disk.\n");

        exit(1);
    }
};

$page('A_missing_both.jsx', <<<'JSX'
export default function A({ columns, rows }) {
    const submit = () => router.get(route('a.index'), { status });
    return <DataTable columns={columns} rows={rows} />;
}
JSX);

$page('B_clean.jsx', <<<'JSX'
export default function B({ columns, rows }) {
    const { processing, post } = useForm();
    return <DataTable columns={columns} rows={rows} emptyMessage="Nothing owed today" busy={processing} />;
}
JSX);

$page('C_no_refresh_with_log.jsx', <<<'JSX'
export default function C({ columns, rows }) {
    console.log('a leftover trace');
    return <DataTable columns={columns} rows={rows} emptyMessage="No receipts today" onClick={() => window.print()} />;
}
JSX);

$page('D_unbound_tag.jsx', <<<'JSX'
export default function D({ rows }) {
    return <DataTable rows={{ a: 1 >
}
JSX);

// E is the leg that proves the scope tightening is READ rather than accepted: a page that refreshes a table and has
// a SAVE button's `form.processing` must still be charged, because that word was what made 24 of 28 pages look
// covered under the first rule.
$page('E_save_pending_only.jsx', <<<'JSX'
export default function E({ columns, rows }) {
    const { processing, post } = useForm();
    const applyFilter = () => router.get(route('e.index'), { status });
    return (
        <>
            <button disabled={processing} onClick={post}>Save</button>
            <DataTable columns={columns} rows={rows} emptyMessage="Nothing here" />
        </>
    );
}
JSX);

$run = static function (string $args) use ($root, $php, $tool): array {
    $out = [];
    $code = 0;
    exec($php.' '.$root.'/'.$tool.' '.$args.' 2>&1', $out, $code);

    return [$code, implode("\n", $out)];
};

$bucket = static function (string $out, string $label): int {
    return preg_match('/^'.preg_quote($label, '/').'\s+(\d+)$/m', $out, $m) === 1 ? (int) $m[1] : -1;
};

// 1 — the four fixtures, each with its own expected bucket count.
[$code, $out] = $run('--pages='.$fixtureDir);
$report('fixture control exits 0', $code === 0, "exit {$code}");
$report('EMPTY fires on A only', $bucket($out, 'EMPTY') === 1, 'EMPTY='.($bucket($out, 'EMPTY')));
$report('LOADING fires on A and E', $bucket($out, 'LOADING') === 2, 'LOADING='.($bucket($out, 'LOADING')));
$report('CONSOLE fires on C only', $bucket($out, 'CONSOLE') === 1, 'CONSOLE='.($bucket($out, 'CONSOLE')));
$report('PARSE names the unbound tag', $bucket($out, 'PARSE') === 1 && str_contains($out, 'D_unbound_tag.jsx'),
    'PARSE='.($bucket($out, 'PARSE')));
// A save button's pending state does not cover a table refresh — the leg the first rule would have passed.
$report('a save-only pending still charges E', str_contains($out, 'LOADING') && str_contains($out, 'E_save_pending_only.jsx'),
    'E named for its refresh');
// The scope leg: C has a console.log and no router call, so it must NOT be charged a loading state.
$report('a page with no refresh is not LOADING', str_contains($out, 'C_no_refresh_with_log.jsx')
    && ! str_contains($out, 'LOADING  C_no_refresh_with_log.jsx'), 'C named only for its console trace');
$report('B is not in the queue', str_contains($out, 'B_clean.jsx') === false, 'clean fixture stayed silent');

// 2 — the real tree still answers the findings it was written against.
[$code, $out] = $run('');
$report('tree EMPTY is one', $bucket($out, 'EMPTY') === 1, 'EMPTY='.($bucket($out, 'EMPTY')));
$report('tree LOADING is 28 of 28 refreshing pages', $bucket($out, 'LOADING') === 28
    && str_contains($out, '28 repaint the rows from a'), 'LOADING='.($bucket($out, 'LOADING')));
$report('tree CONSOLE is zero', $bucket($out, 'CONSOLE') === 0, 'CONSOLE='.($bucket($out, 'CONSOLE')));
$report('table sites are 41', str_contains($out, 'table sites rendered: 41'),
    'the same population lane 90 counts for the pagination census');

// 3 — --ci must refuse while any bucket is open, and must not certify a run that read nothing.
[$code, $out] = $run('--pages='.$fixtureDir.' --ci');
$report('ci refuses an open queue', $code === 1 && str_contains($out, 'REFUSED (--ci)'), "exit {$code}");

// 4 — a clean fixture directory answers 0 and prints the NOT JUDGED sentence rather than a blank pass.
$cleanDir = 'storage/app/scratch/lane142-clean';
$dirs[] = $cleanDir;
if (! is_dir($root.'/'.$cleanDir)) {
    mkdir($root.'/'.$cleanDir, 0777, true);
}
copy($root.'/'.$fixtureDir.'/B_clean.jsx', $root.'/'.$cleanDir.'/B_clean.jsx');
[$code, $out] = $run('--pages='.$cleanDir.' --ci');
$report('ci passes a closed tree', $code === 0 && str_contains($out, 'NOT JUDGED'), "exit {$code}");

// 5 — an empty population refuses instead of reporting every bucket at zero.
[$code, $out] = $run('--pages='.$emptyDir);
$report('empty population refuses', $code === 1
    && (str_contains($out, 'REFUSED') || str_contains($out, 'no page renders')), "exit {$code}");

// 6 — a --pages= that names no directory is refused by name, not walked as nothing.
[$code, $out] = $run('--pages=storage/app/scratch/lane142-does-not-exist');
$report('missing pages dir refuses by name', $code === 1 && str_contains($out, 'lane142-does-not-exist'), "exit {$code}");

// 7 — an argument the tool does not read is refused rather than accepted inertly.
[$code, $out] = $run('--all');
$report('unknown flag refused by name', $code === 1 && str_contains($out, "'--all'"), "exit {$code}");

printf("\n%s: %d leg(s), %d bad\n", $fail === 0 ? 'ALL PROVABLE' : 'FAILED', $legs, $fail);

exit($fail === 0 ? 0 : 1);
