<?php

/**
 * Prove `lane73b-probe-import-order.php` can fail, and can stay quiet about the two shapes it once mistook for defects.
 *
 * The class it guards is the one this repository has now paid for three times: Pint's `fully_qualified_strict_types`
 * shortens a bootstrap `\Illuminate\Contracts\Console\Kernel::class` and files its own `use` block *below* the call.
 * The file parses, `pint --test` calls it clean, and PHP resolves the unqualified name at compile time, so it dies at
 * runtime with "Class Kernel does not exist". The third victim was `lane72-probe-school-day.php` on 2026-10-26 —
 * formatted by a pass over a batch that only meant to add a sentence, and it fataled the first time it was run after
 * that. So this harness plants the defect rather than trusting the sweep's silence, which is the rule for every
 * checker here: a checker that cannot fail has checked nothing.
 *
 * Legs 3 and 4 are the sweep's own two first drafts. Its first read raw lines and named 16 files, fifteen of them
 * prose inside a docblock; its second tokenized but treated a closure's `use ($a, $b)` as an import. Both must now
 * stay quiet, and a leg that only tests "found the bad thing" would not have caught either.
 *
 * Leg 5 is the exit-code trap this file names over and over: the sweep used to `exit("REFUSED: …")`, which returns
 * status 0, so CI would have read a refusal as a pass. The refusal now has to carry its cause AND a non-zero status.
 *
 *   php storage/app/scratch/mutate-lane73b-import-order.php
 */
$root = dirname(__DIR__, 3);
$tool = $root.'/storage/app/scratch/lane73b-probe-import-order.php';
$php = escapeshellarg(PHP_BINARY);
$scratch = $root.'/storage/app/scratch';

if (! is_readable($tool)) {
    fwrite(STDERR, "REFUSED: the sweep is not readable at $tool.\n");
    exit(1);
}

$copies = [];
$make = static function (string $name, string $body) use (&$copies, $scratch): string {
    $path = $scratch.'/'.$name;
    file_put_contents($path, $body);
    $copies[] = $path;

    return $path;
};
$run = static function (string $args) use ($php, $root, $tool): array {
    exec('cd '.escapeshellarg($root).' && '.$php.' '.escapeshellarg($tool).' '.$args.' 2>&1', $lines, $code);

    return ['out' => implode("\n", $lines), 'code' => $code];
};

$fails = 0;
$report = static function (string $leg, bool $ok, string $detail = '') use (&$fails): void {
    if (! $ok) {
        $fails++;
    }
    printf("%-8s %-26s %s\n", $ok ? 'PASS' : 'BAD', $leg, $detail);
};

$lateBody = <<<'PHP'
<?php

/** A copy that reproduces exactly what Pint does to a bootstrap probe. */
require __DIR__.'/../../../vendor/autoload.php';
$app = require_once __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use Illuminate\Contracts\Console\Kernel;

echo "never reached\n";
PHP;

$proseBody = <<<'PHP'
<?php

/**
 * The class the sweep must NOT name: prose in a docblock that reads like a call.
 *
 * `Kernel::class` appears here only as a sentence, and `Str::headline` is English plus an example, so no code in
 * this file resolves a short name before its import.
 */
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

echo Str::headline('ok'), PHP_EOL;
PHP;

$closureBody = <<<'PHP'
<?php

/** The other shape that fooled the second draft: a closure binding list is not an import. */
use Illuminate\Support\Str;

$add = function (int $a, int $b) use ($c) {
    return $a + $b;
};
$c = 1;

echo Str::headline('ok'), PHP_EOL;
PHP;

try {
    // 1 — control, run BEFORE anything is planted: the real tree must be clean, the by-design exception must be
    //     named in its own bucket, and CI mode must exit 0. Order matters — a copy left in scratch is inside the
    //     directory every later leg walks, so a planted defect would poison the next measurement and this harness's
    //     own control would be the thing that failed.
    $r = $run('--ci');
    $report('control-clean', $r['code'] === 0 && str_contains($r['out'], 'no other import sits below')
        && str_contains($r['out'], 'BY-DESIGN') && str_contains($r['out'], 'probe-use-ordering.php'),
        sprintf('exit %d; walked-line %s; by-design bucket %s', $r['code'],
            preg_match('/walked (\d+) file/', $r['out'], $m) ? $m[1].' files' : 'ABSENT',
            str_contains($r['out'], 'BY-DESIGN') ? 'named' : 'ABSENT'));

    // 2 — the planted defect must be NAMED, and CI mode must fail on it.
    $p = $make('lane73b-import-order-LATE.php', $lateBody);
    $r = $run('--ci --file=storage/app/scratch/'.basename($p));
    $report('plants-are-named', str_contains($r['out'], 'LATE-IMPORT') && str_contains($r['out'], 'Kernel')
        && $r['code'] === 1, sprintf('exit %d (hand mode is 0 by design, CI mode must be 1)', $r['code']));

    // 3 — hand mode on the same file must exit 0: the modes are different claims, and a leg that accepted either
    //     would pass on a broken instrument.
    $r = $run('--file=storage/app/scratch/'.basename($p));
    $report('hand-mode-exits-0', str_contains($r['out'], 'LATE-IMPORT') && $r['code'] === 0,
        sprintf('exit %d', $r['code']));

    // 4 — prose is not code: the first draft answered 16 files, fifteen of them docblocks.
    $p2 = $make('lane73b-import-order-PROSE.php', $proseBody);
    $r = $run('--ci --file=storage/app/scratch/'.basename($p2));
    $report('docblock-prose-ignored', ! str_contains($r['out'], 'LATE-IMPORT') && $r['code'] === 0,
        sprintf('exit %d%s', $r['code'], str_contains($r['out'], 'LATE-IMPORT') ? ', and it named prose' : ''));

    // 5 — a closure's binding list is not an import: the second draft's 41 false hits.
    $p3 = $make('lane73b-import-order-CLOSURE.php', $closureBody);
    $r = $run('--ci --file=storage/app/scratch/'.basename($p3));
    $report('closure-use-not-import', ! str_contains($r['out'], 'LATE-IMPORT') && $r['code'] === 0,
        sprintf('exit %d%s', $r['code'], str_contains($r['out'], 'LATE-IMPORT') ? ', and it read `use ($c)` as an import' : ''));

    // 6 — a refusal must carry its cause AND a non-zero status (exit("text") used to answer 0 here).
    $r = $run('--ci --file=storage/app/scratch/lane73b-no-such-file.php');
    $report('refusal-has-status', str_contains($r['out'], 'REFUSED') && $r['code'] === 1,
        sprintf('exit %d — 0 would mean CI reads a refusal as a pass', $r['code']));

    // 7 — a scope that reads nothing must refuse rather than report a clean verdict over zero files.
    $r = $run('--ci --dir=storage/app/scratch/lane73b-no-such-dir');
    $report('empty-scope-refuses', str_contains($r['out'], 'REFUSED') && $r['code'] === 1,
        sprintf('exit %d; the tool %s a --dir= flag', $r['code'],
            str_contains($r['out'], 'not a flag') ? 'refuses' : (str_contains($r['out'], 'REFUSED') ? 'handles' : 'ACCEPTS INERTLY')));
} finally {
    foreach ($copies as $c) {
        if (is_file($c)) {
            unlink($c);
        }
    }
    $left = array_values(array_filter(glob($scratch.'/lane73b-import-order-*.php') ?: [],
        'is_file'));
    printf("\n%s copy(ies) left in scratch%s\n", $left === [] ? 'no' : 'ORPHAN ',
        $left === [] ? '' : ': '.implode(', ', array_map('basename', $left)));
    if ($left !== []) {
        $fails++;
    }
}

printf("%d leg(s) failed.\n", $fails);
echo $fails === 0
    ? 'VERDICT: the sweep names the planted defect, stays quiet on prose and closure bindings, and its refusals '
        ."carry a status.\n"
    : "VERDICT: the sweep is not proven — do not cite it as a gate until this harness is green.\n";
exit($fails === 0 ? 0 : 1);
