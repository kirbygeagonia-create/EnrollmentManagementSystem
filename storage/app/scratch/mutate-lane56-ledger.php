<?php

/**
 * Lane 56 — prove the new receipt-status rule and the generator line that mirrors it can both be moved.
 *
 * A report line that can only ever print 0 is decoration (the lesson lane 54 paid for), and a guard added in the
 * same breath as the fix has never been seen to refuse anything. So this harness plants exactly one defect —
 * Bea Alonzo's ₱8,000 installment marked `paid` on an account that still owes, which is §28.3 G-24's own shape —
 * and requires three things to answer it: `ems:audit:record-states` names it as an impossible state and exits 1,
 * the generator's report prints "1" on its own line, and the generator re-marks the row so the next run reads 0.
 *
 * It also refuses rather than reporting a pass: on the live schema, on a schema the demo dataset was never seeded
 * into, and on a mutation that did not actually change the row it aimed at (lane 53's sleeping mutant).
 *
 * Usage: DB_DATABASE=ems_l54_c php storage/app/scratch/mutate-lane56-ledger.php
 */

$root = dirname(__DIR__, 3);
$php = getenv('EMS_PHP') ?: 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$database = (string) getenv('DB_DATABASE');

if ($database === '' || $database === 'ems') {
    fwrite(STDERR, "REFUSED: DB_DATABASE must name a scratch schema; this harness writes rows. Live `ems` is not it.\n");
    exit(1);
}

$label = 'A receipt that says the account is settled while it still owes';
$line = 'Ledger — receipt this run had to re-mark partial';
$failures = 0;
$note = function (bool $ok, string $what) use (&$failures): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $failures++;
    }
};

/** Run an artisan command or a tool against the named schema and return its output with its own exit code. */
$run = function (string $script) use ($root, $php, $database): array {
    $out = [];
    $code = 0;
    exec('cd /d '.escapeshellarg($root).' && set "DB_DATABASE='.$database.'" && set "DB_CONNECTION=mysql"'
        .' && '.escapeshellarg($php).' '.$script.' 2>&1', $out, $code);

    return [$code, implode("\n", $out)];
};
$tool = fn (): array => $run('tools/seed_full_demo_pipeline.php');
$audit = fn (): array => $run('artisan ems:audit:record-states');

/** The row is read and planted through the booted application, never through a quoted one-liner. */
$row = function (string $mode = 'read') use ($run): array {
    [$code, $out] = $run('storage/app/scratch/lane56-receipt-state.php '.$mode);
    $parsed = [];
    foreach (explode("\n", $out) as $l) {
        if (preg_match('/^([a-z]+)=(.*)$/', trim($l), $kv) === 1) {
            $parsed[$kv[1]] = $kv[2];
        }
    }
    if ($code !== 0 || ! isset($parsed['status'])) {
        fwrite(STDERR, "REFUSED: the reader answered nothing usable (exit {$code}): ".preg_replace('/\s+/', ' ', substr($out, 0, 200))."\n");
        exit(1);
    }

    return $parsed;
};

echo "Schema this harness writes to: {$database}\n";

$state = $row();
$before = $state['status'];
if ($before === 'MISSING') {
    echo "REFUSED: DEMO-OR-0002 is not in {$database} — seed the demo pipeline into it first, or this run measures nothing.\n";
    exit(1);
}

$owing = (float) $state['balance'];
if ($owing <= 0) {
    printf("REFUSED: the account behind DEMO-OR-0002 holds no balance (%s) on %s, so the defect cannot be planted there.\n",
        $state['balance'], $database);
    exit(1);
}
printf("     DEMO-OR-0002 reads `%s` on an account owing ₱%s\n", $before, number_format($owing, 2));

// ------------------------------------------------------------------ 1. the clean control
[$controlCode, $out] = $tool();
$cleanReported = preg_match('/'.preg_quote($line, '/').'\s+(\d+)/', $out, $m) === 1 ? (int) $m[1] : -1;
$note($cleanReported === 0, "control: the generator reports 0 on this line (read {$cleanReported})");
$note($before === 'partial', "control: the seeded installment says `partial`, which is what the desk writes (was `{$before}`)");
if ($failures > 0) {
    echo "\nThe control did not hold — every later verdict here would be measuring the wrong state.\n";
    exit(1);
}

// ------------------------------------------------------------------ 2. plant G-24's shape
$planted = $row('plant');
$note((int) ($planted['changed'] ?? 0) === 1 && $planted['status'] === 'paid',
    'the mutation took: the row now says `'.$planted['status'].'` over a ₱'.number_format($owing, 2)
        .' balance (changed '.($planted['changed'] ?? '?').')');

// ------------------------------------------------------------------ 3. the audit must refuse it
[$auditCode, $auditOut] = $audit();
$note($auditCode === 1, 'ems:audit:record-states exits 1 on the planted row');
$note(str_contains($auditOut, 'FAIL') && str_contains($auditOut, $label), 'and names the rule by its own label');

// ------------------------------------------------------------------ 4. the generator must see and repair it
[$toolCode, $toolOut] = $tool();
$reported = preg_match('/'.preg_quote($line, '/').'\s+(\d+)/', $toolOut, $m2) === 1 ? (int) $m2[1] : -1;
$note($reported === 1, "the generator's own report prints 1 on the line (read {$reported})");
$note(str_contains($toolOut, 're-marked 1 receipt'), 'and says it re-marked one receipt');
$note($toolCode === 1, 'and refuses its own success banner (exit 1)');
$note(($row())['status'] === 'partial', 'the row is `partial` again after the run');

// ------------------------------------------------------------------ 5. and the next run must be clean
[$againCode, $againOut] = $tool();
$again = preg_match('/'.preg_quote($line, '/').'\s+(\d+)/', $againOut, $m3) === 1 ? (int) $m3[1] : -1;
$note($again === 0, 'a second run reports 0 on the line (read '.$again.')');
$note($againCode === $controlCode, 'and a second run exits as the control did ('.$controlCode.', read '.$againCode.')');

if ($failures > 0) {
    printf("\n%d case(s) did not behave — do not report this rule as proved.\n", $failures);
    exit(1);
}

echo "\nThe planted receipt moved the audit rule, the generator's line and the generator's exit code;\n"
    ."the repaired dataset answers 0 on all three.\n";
exit(0);
