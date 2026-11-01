<?php

/**
 * Proof that the daily report's consistent-read case can fail.
 *
 * `PartPaymentAndRefundTest::the_daily_report_takes_its_two_reads_inside_one_transaction` asserts that both
 * `payments` reads of the sheet run one transaction level deeper than the request started at. A case like that
 * passes on a report that never had two reads, on a listener whose needle matches the other engine's quoting, and
 * on a controller that stopped querying the table — three ways this repository has already been caught, all of them
 * "green over an unread thing". So the retired shape is planted: the transaction wrapper comes off, and the named
 * case must go red. Then the bytes are restored by hash and the case must go green again.
 *
 * Every needle is asserted to occur exactly once before it is written, the restore runs from a shutdown hook
 * whether or not the script finishes, and a leg whose filter selects no test is BAD rather than a survivor —
 * "No tests found" has been read as a pass here before.
 *
 * Usage: php storage/app/scratch/mutate-p20-consistent-read-tests.php
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$controller = $root.'/app/Http/Controllers/Accounting/AccountingController.php';

if (! is_file($controller)) {
    fwrite(STDERR, "REFUSED: {$controller} is not on disk.\n");

    exit(1);
}

$original = (string) file_get_contents($controller);
$hash = sha1($original);
$restored = false;

register_shutdown_function(static function () use ($controller, $original, $hash, &$restored): void {
    if ($restored || ! is_file($controller)) {
        return;
    }
    file_put_contents($controller, $original);
    $restored = sha1((string) file_get_contents($controller)) === $hash;
    fwrite(STDERR, ($restored ? '' : 'REFUSED: the controller could not be restored to its original bytes.')."\n");
});

$legs = [];
$bad = 0;
$say = static function (string $name, bool $ok, string $detail = '') use (&$legs, &$bad): void {
    $legs[] = ($ok ? 'ok   ' : 'BAD  ').$name.($detail !== '' ? ' — '.$detail : '');
    if (! $ok) {
        $bad++;
    }
};

$case = 'the_daily_report_takes_its_two_reads_inside_one_transaction';
$runCase = static function () use ($php, $root, $case): array {
    chdir($root);
    exec(escapeshellarg($php).' artisan test tests/Feature/Accounting/PartPaymentAndRefundTest.php --filter='
        .escapeshellarg($case).' 2>&1', $out, $code);
    $text = implode("\n", $out);

    return [$code, $text];
};

// ── 1. the control, before anything is touched ────────────────────────────────────────────────────────────────
[$rc, $out] = $runCase();
$green = str_contains($out, '1 passed') && $rc === 0;
$say('control-green', $green, 'rc='.$rc.' '.trim(str_replace("\n", ' | ', (string) strrchr($out, 'T') ?: $out)));
if (! $green) {
    echo "\n".implode("\n", $legs)."\n";
    echo "BAD  the case is not green before any plant, so a later red proves nothing.\n";
    $restored = true;

    exit(1);
}

// ── 2. the retired shape: take the transaction wrapper off the two reads ───────────────────────────────────────
$from = "        \$reads = [];\n        DB::transaction(function () use (\$date, &\$reads): void {";
$to = "        \$reads = [];\n        (static function () use (\$date, &\$reads): void {";
if (substr_count($original, $from) !== 1) {
    $say('wrapper-needle-found', false, 'the transaction opener occurs '.substr_count($original, $from)
        .' time(s); re-cut this leg onto the current shape rather than skipping the plant');
} else {
    $closeFrom = "            \$reads = ['payments' => \$payments, 'refunds' => \$refunds];\n        });";
    $closeTo = "            \$reads = ['payments' => \$payments, 'refunds' => \$refunds];\n        })();";
    if (substr_count($original, $closeFrom) !== 1) {
        $say('closer-needle-found', false, 'the closing `});` occurs '.substr_count($original, $closeFrom).' time(s)');
    } else {
        file_put_contents($controller, str_replace([$from, $closeFrom], [$to, $closeTo], $original));
        [$rc2, $out2] = $runCase();
        $red = str_contains($out2, '1 failed') && $rc2 !== 0;
        $say('wrapper-removed-the-case-goes-red', $red, 'rc='.$rc2.' '
            .trim(str_replace("\n", ' | ', (string) strrchr($out2, 'T') ?: $out2)));
        file_put_contents($controller, $original);
        $restored = sha1((string) file_get_contents($controller)) === $hash;
    }
}

// ── 3. a filter that selects nothing is this harness's failure, not a surviving defect ────────────────────────
chdir($root);
exec(escapeshellarg($php).' artisan test tests/Feature/Accounting/PartPaymentAndRefundTest.php --filter='
    .escapeshellarg('the_daily_report_takes_its_two_reads_inside_a_thing')
    .' 2>&1', $o3, $rc3);
$empty = implode("\n", $o3);
$say('bogus-filter-is-not-green', ! str_contains($empty, '1 passed'), 'rc='.$rc3);

// ── 4. the tree is byte-identical to what this run found ─────────────────────────────────────────────────────
$say('tree-restored-by-hash', sha1((string) file_get_contents($controller)) === $hash,
    'the controller must be exactly the bytes this run opened');

echo "\n".implode("\n", $legs)."\n";
printf("\n%d leg(s), %d bad\n", count($legs), $bad);

exit($bad === 0 ? 0 : 1);
