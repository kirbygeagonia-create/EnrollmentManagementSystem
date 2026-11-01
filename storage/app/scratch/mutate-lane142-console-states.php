<?php

/**
 * Harness for lane142-measure-console-states.php — every bucket must be able to fire, and the coverage claim
 * must be able to fail.
 *
 * The instrument now answers EMPTY 0, LOADING 0, CONSOLE 0 over the real tree. A clean census is exactly the output
 * a blind reader produces, so the fixtures plant one shape per bucket and name the page that must be charged for it,
 * and the decisive leg is not a fixture at all: it points --control= at a copy of DataTable.jsx whose pending read is
 * removed, and requires the 28 pages that repaint rows from a router call to become chargeable again. If that number
 * does not move, the run that printed 0 was crediting a control it never proved.
 *
 * Usage: php storage/app/scratch/mutate-lane142-console-states.php
 */
$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$tool = 'storage/app/scratch/lane142-measure-console-states.php';
$fixtureDir = 'storage/app/scratch/lane142-fixture';
$emptyDir = 'storage/app/scratch/lane142-empty';
$cleanDir = 'storage/app/scratch/lane142-clean';
$controlCopy = 'storage/app/scratch/lane142-control-nosignal.jsx';
$bootCopy = 'storage/app/scratch/lane142-boot-nowatch.jsx';

$fail = 0;
$legs = 0;
$report = static function (string $leg, bool $ok, string $detail = '') use (&$fail, &$legs): void {
    $legs++;
    printf("  %s  %-40s %s\n", $ok ? 'ok  ' : 'BAD ', $leg, $detail);
    if (! $ok) {
        $fail++;
    }
};

$paths = [$fixtureDir, $emptyDir, $cleanDir, $controlCopy, $bootCopy];
$cleanup = static function () use (&$paths, $root): void {
    foreach ($paths as $p) {
        $full = $root.'/'.$p;
        if (is_dir($full)) {
            foreach (glob($full.'/*') ?: [] as $f) {
                unlink($f);
            }
            rmdir($full);
        } elseif (file_exists($full)) {
            unlink($full);
        }
    }
};
register_shutdown_function($cleanup);

foreach ([$fixtureDir, $emptyDir, $cleanDir] as $d) {
    if (is_dir($root.'/'.$d)) {
        array_map('unlink', glob($root.'/'.$d.'/*') ?: []);
        rmdir($root.'/'.$d);
    }
    if (! mkdir($root.'/'.$d, 0777, true) && ! is_dir($root.'/'.$d)) {
        fwrite(STDERR, "REFUSED: could not create {$d}.\n");

        exit(1);
    }
}

/** One fixture page, written and read back: a plant that never landed measures nothing. */
$page = static function (string $dir, string $name, string $body) use ($root): void {
    $path = $root.'/'.$dir.'/'.$name;
    if (@file_put_contents($path, $body) !== strlen($body) || (string) file_get_contents($path) !== $body) {
        fwrite(STDERR, "REFUSED: the fixture {$name} did not land.\n");

        exit(1);
    }
};

// EMPTY: a bare shared table whose page never names the emptiness.
$page($fixtureDir, 'A_bare_no_empty_state.jsx', <<<'JSX'
export default function A({ columns, rows }) {
    const submit = () => router.get(route('a.index'), { status });
    return <DataTable columns={columns} rows={rows} />;
}
JSX);

// EMPTY exemption: the same bare site, but the page short-circuits to its own EmptyState — the shape the first
// draft charged and was wrong to.
$page($fixtureDir, 'B_bare_but_guarded.jsx', <<<'JSX'
export default function B({ columns, rows }) {
    const submit = () => router.get(route('b.index'), { status });
    return rows.length === 0
        ? <EmptyState title="No records match these filters" description="Clear one." />
        : <DataTable columns={columns} rows={rows} />;
}
JSX);

$page($fixtureDir, 'C_clean.jsx', <<<'JSX'
export default function C({ columns, rows }) {
    const submit = () => router.get(route('c.index'), { status });
    return <DataTable columns={columns} rows={rows} emptyMessage="Nothing on this desk today" />;
}
JSX);

// CONSOLE without a refresh: a page whose only act is printing.
$page($fixtureDir, 'D_no_refresh_with_log.jsx', <<<'JSX'
export default function D({ columns, rows }) {
    console.log('a leftover trace');
    return <table className="x"><tbody><tr><td>{rows.length}</td></tr></tbody></table>;
}
JSX);

// LOADING on the surface the shared control cannot reach.
$page($fixtureDir, 'E_handbuilt_refresh.jsx', <<<'JSX'
export default function E({ rows }) {
    const apply = () => router.get(route('e.show'), { term: 1 }, { preserveState: true });
    return (
        <div>
            <button onClick={apply}>Apply</button>
            <table className="x"><tbody><tr><td>{rows.length}</td></tr></tbody></table>
        </div>
    );
}
JSX);

// The same page, credited because it reads the shared hook — the leg that proves the credit is read, not assumed.
$page($fixtureDir, 'F_handbuilt_with_hook.jsx', <<<'JSX'
export default function F({ rows }) {
    const waiting = useRouterPending();
    const apply = () => router.get(route('f.show'), { term: 1 }, { preserveState: true });
    return (
        <div>
            {waiting && <p role="status">Updating…</p>}
            <table className="x"><tbody><tr><td>{rows.length}</td></tr></tbody></table>
        </div>
    );
}
JSX);

// A table tag the scanner cannot bound: silently skipping this would print the same clean totals.
$page($fixtureDir, 'G_unbound_tag.jsx', <<<'JSX'
export default function G({ rows }) {
    return <DataTable rows={{ a: 1 >
}
JSX);

// The clean directory holds only the guarded page, so --ci must be able to pass a tree.
$page($cleanDir, 'Only_clean.jsx', <<<'JSX'
export default function Z({ columns, rows }) {
    const submit = () => router.get(route('z.index'), { status });
    return <DataTable columns={columns} rows={rows} emptyMessage="Nothing here" />;
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

// 1 — the fixture tree, one charge per bucket.
[$code, $out] = $run('--pages='.$fixtureDir);
$report('fixture control exits 0', $code === 0, "exit {$code}");
$report('EMPTY charges the bare page only', $bucket($out, 'EMPTY') === 1 && str_contains($out, 'A_bare_no_empty_state.jsx'),
    'EMPTY='.$bucket($out, 'EMPTY'));
$report('EmptyState exempts the guarded page', ! str_contains($out, 'B_bare_but_guarded.jsx'), 'B stayed out of the queue');
$report('LOADING charges the hand-built refresh', $bucket($out, 'LOADING') === 1
    && str_contains($out, 'E_handbuilt_refresh.jsx'), 'LOADING='.$bucket($out, 'LOADING'));
$report('the shared hook credits F', ! str_contains($out, 'F_handbuilt_with_hook.jsx'), 'F credited by the hook it reads');
$report('CONSOLE charges the print-only page', $bucket($out, 'CONSOLE') === 1
    && str_contains($out, 'D_no_refresh_with_log.jsx'), 'CONSOLE='.$bucket($out, 'CONSOLE'));
$report('a page with no refresh is not LOADING', ! str_contains($out, 'LOADING  D_no_refresh_with_log.jsx'),
    'D named only for its trace');
$report('PARSE names the unbound tag', $bucket($out, 'PARSE') === 1 && str_contains($out, 'G_unbound_tag.jsx'),
    'PARSE='.$bucket($out, 'PARSE'));
$report('C is not in the queue', ! str_contains($out, 'C_clean.jsx'), 'clean fixture stayed silent');

// 2 — the real tree, with the coverage premise printed.
[$code, $out] = $run('');
$report('tree control line says yes', str_contains($out, 'shared control carries the refresh signal: yes'), '');
$report('tree buckets are all zero', $bucket($out, 'EMPTY') === 0 && $bucket($out, 'LOADING') === 0
    && $bucket($out, 'CONSOLE') === 0 && $bucket($out, 'PARSE') === 0,
    sprintf('EMPTY=%d LOADING=%d CONSOLE=%d PARSE=%d', $bucket($out, 'EMPTY'), $bucket($out, 'LOADING'),
        $bucket($out, 'CONSOLE'), $bucket($out, 'PARSE')));
$report('table sites are 41', str_contains($out, 'table sites rendered: 41'), 'the lane 90 population');
$report('28 pages repaint from a router call', str_contains($out, '28 repaint the rows from a'), '');

// 3 — THE decisive leg: strip the pending read from a copy of the control and the census must charge again.
$controlSrc = (string) file_get_contents($root.'/resources/js/Components/ui/DataTable.jsx');
$stripped = str_replace('useRouterPending', 'nothingOfTheSort', $controlSrc);
if ($stripped === $controlSrc) {
    fwrite(STDERR, "REFUSED: the strip plant matched nothing in DataTable.jsx — the leg cannot fail as written.\n");

    exit(1);
}
if (@file_put_contents($root.'/'.$controlCopy, $stripped) !== strlen($stripped)) {
    fwrite(STDERR, "REFUSED: the control copy did not land.\n");

    exit(1);
}
[$code, $out] = $run('--control='.$controlCopy);
$report('unproved control re-charges the tree', $bucket($out, 'LOADING') === 28
    && str_contains($out, 'shared control carries the refresh signal: NO'),
    sprintf('LOADING=%d', $bucket($out, 'LOADING')));

// 4 — and the same through the boot file: a control that is never armed covers nothing.
$bootSrc = (string) file_get_contents($root.'/resources/js/app.jsx');
$unarmed = str_replace('watchRouterPending();', '/* plant: not armed */', $bootSrc);
if ($unarmed === $bootSrc) {
    fwrite(STDERR, "REFUSED: the boot plant matched nothing in app.jsx.\n");

    exit(1);
}
if (@file_put_contents($root.'/'.$bootCopy, $unarmed) !== strlen($unarmed)) {
    fwrite(STDERR, "REFUSED: the boot copy did not land.\n");

    exit(1);
}
[$code, $out] = $run('--boot='.$bootCopy);
$report('unarmed boot re-charges the tree', $bucket($out, 'LOADING') === 28
    && str_contains($out, 'boot arms the watcher: no'), sprintf('LOADING=%d', $bucket($out, 'LOADING')));

// 5 — --ci is a different claim from the hand run: it refuses while a bucket is open, passes on a closed tree.
[$code, $out] = $run('--pages='.$fixtureDir.' --ci');
$report('ci refuses an open queue', $code === 1 && str_contains($out, 'REFUSED (--ci)'), "exit {$code}");
[$code, $out] = $run('--pages='.$cleanDir.' --ci');
$report('ci passes a closed tree', $code === 0 && str_contains($out, 'NOT JUDGED'), "exit {$code}");

// 6 — the refusals, told apart by their own messages.
[$code, $out] = $run('--pages='.$emptyDir);
$report('empty population refuses', $code === 1 && str_contains($out, 'REFUSED'), "exit {$code}");
[$code, $out] = $run('--pages=storage/app/scratch/lane142-does-not-exist');
$report('missing pages dir refuses by name', $code === 1 && str_contains($out, 'lane142-does-not-exist'), "exit {$code}");
[$code, $out] = $run('--control=storage/app/scratch/lane142-no-such-control.jsx');
$report('missing control copy refuses by name', $code === 1 && str_contains($out, 'lane142-no-such-control'),
    "exit {$code}");
[$code, $out] = $run('--all');
$report('unknown flag refused by name', $code === 1 && str_contains($out, "'--all'"), "exit {$code}");

printf("\n%s: %d leg(s), %d bad\n", $fail === 0 ? 'ALL PROVABLE' : 'FAILED', $legs, $fail);

exit($fail === 0 ? 0 : 1);
