<?php

/**
 * Falsifier for the two guide readers written this batch:
 *   `lane235-check-guide-claims.php` — weighs every artefact claim the answered file makes against the tree.
 *   `lane228-read-analysis-boxes.php` — reads the paper back: ballots, answer windows, and the owner's word cap.
 *
 * Each instrument is proved in both directions. A checker that refuses everything would pass the "must complain"
 * legs and fail the "must stay quiet" ones, and a flag the parser accepts but never reads would pass both — so
 * `--over=N` is run twice, once at a number the paper cannot meet and once at a number it clears, which is the only
 * pair that shows the flag moves the verdict rather than the text.
 *
 * Everything planted goes into a copy under storage/app/scratch; the delivered documents are opened read-only.
 *
 * Usage: php storage/app/scratch/mutate-lane228-guide-readers.php
 */
$root = dirname(__DIR__, 3);
$php = PHP_BINARY;
$scratch = $root.'/storage/app/scratch';
$answers = $root.'/Documentation/GUIDE-PROBLEM-ANSWERS-ADS.md';
$checker = $scratch.'/lane235-check-guide-claims.php';
$reader = $scratch.'/lane228-read-analysis-boxes.php';
$made = [];
$bad = 0;
$legs = 0;

register_shutdown_function(function () use ($scratch, &$made) {
    foreach ($made as $f) {
        if (is_file($f)) {
            @unlink($f);
        }
    }
    $left = glob($scratch.'/lane228h-*');
    if ($left !== false && count($left) > 0) {
        echo "\nBAD   planted copies survive: ".implode(', ', array_map('basename', $left))."\n";
    }
});

$run = static function (string $cmd) use ($root): array {
    $out = [];
    $code = 0;
    exec('cd /d '.$root.' && '.$cmd.' 2>&1', $out, $code);

    return [$code, implode("\n", $out)];
};
$q = static fn (string $p): string => '"'.$p.'"';

$copy = static function (string $tag, ?string $body = null) use ($scratch, &$made, $answers): string {
    $path = $scratch.'/lane228h-'.$tag.'.md';
    file_put_contents($path, $body ?? (string) file_get_contents($answers));
    $made[] = $path;

    return $path;
};

$expect = static function (string $leg, array $want, string $cmd) use (&$bad, &$legs, $run): void {
    $legs++;
    [$code, $out] = $run($cmd);
    $fails = [];
    foreach ($want as $key => $rule) {
        if ($key === 'exit') {
            $ok = $rule === 'zero' ? $code === 0 : $code !== 0;
            if (! $ok) {
                $fails[] = 'exit '.($rule === 'zero' ? 'must be 0' : 'must be non-zero').', got '.$code;
            }

            continue;
        }
        if ($rule === 'absent' && str_contains($out, $key)) {
            $fails[] = 'the output must not carry ['.$key.']';
        } elseif ($rule !== 'absent' && ! str_contains($out, $rule)) {
            $fails[] = 'expected to read ['.$rule.']';
        }
    }
    if ($fails === []) {
        echo str_pad($leg, 34).'ok    '.($want['exit'] === 'zero' ? 'clean' : 'loud')."\n";

        return;
    }
    echo str_pad($leg, 34)."BAD   ".implode('; ', $fails)."\n".wordwrap(substr($out, 0, 420), 92, "\n      ", true)."\n";
    $bad++;
};

echo "=== lane235, the claim weigher ===\n";
$expect('control-resolves', ['ALL CLAIMS RESOLVE' => 'ALL CLAIMS RESOLVE', 'exit' => 'zero'],
    $q($php).' '.$q($checker).' --file='.$q($answers));

// (1) a file the repository does not hold. The plant ADDS a claim, so the count it reports is the control's plus one —
// asserting it unchanged would refuse a correct plant, and asserting a remembered number would go stale the day the
// answers file gains a sentence. The relation is the measurement, and it also proves the plant was read at all.
[$c0, $o0] = $run($q($php).' '.$q($checker).' --file='.$q($answers));
preg_match('/(\d+) file claims/', $o0, $base);
$wantCount = (string) ((int) ($base[1] ?? -1) + 1).' file claims';
$planted = $copy('bogusfile', (string) file_get_contents($answers)."\n\nSee `Lane228BogusFile.php` for the reader.\n");
$expect('bogus-file-named', ['UNRESOLVED' => 'UNRESOLVED', 'Lane228BogusFile.php' => 'Lane228BogusFile.php',
    $wantCount => $wantCount, 'FILE' => 'FILE', 'exit' => 'nonzero'],
    $q($php).' '.$q($checker).' --file='.$q($planted));

// (2) a method no class answers.
$plantedM = $copy('bogusmethod', (string) file_get_contents($answers)."\n\nPinned by `Lane228BogusClass::bogusMethod()`.\n");
$expect('bogus-method-named', ['UNRESOLVED' => 'UNRESOLVED', 'METHOD' => 'METHOD',
    'Lane228BogusClass::bogusMethod()' => 'Lane228BogusClass::bogusMethod()', 'exit' => 'nonzero'],
    $q($php).' '.$q($checker).' --file='.$q($plantedM));

// (3) a document with nothing to judge must refuse, not print a clean verdict.
$empty = $copy('empty', "# answers\n\nNothing here names a file, a class or a method.\n");
$expect('no-claims-refuses', ['REFUSED: no claims were read' => 'REFUSED: no claims were read', 'exit' => 'nonzero'],
    $q($php).' '.$q($checker).' --file='.$q($empty));

$expect('missing-file-refuses', ['REFUSED:' => 'REFUSED:', 'is not on disk' => 'is not on disk', 'exit' => 'nonzero'],
    $q($php).' '.$q($checker).' --file='.$q($scratch.'/lane228h-no-such-file.md'));
$expect('unknown-flag-refused', ['REFUSED: this tool does not read the flag' => 'REFUSED: this tool does not read the flag',
    '--no-such-flag-at-all' => '--no-such-flag-at-all', 'exit' => 'nonzero'],
    $q($php).' '.$q($checker).' --file='.$q($answers).' --no-such-flag-at-all');

echo "\n=== lane228, the paper reader ===\n";
// (4) the word cap moves the verdict: a cap of one cannot be met by an answer file, a cap of nine thousand can.
$expect('over-cap-refuses', ['answers over 1 words' => 'answers over 1 words', 'REFUSED' => 'REFUSED', 'exit' => 'nonzero'],
    $q($php).' '.$q($reader).' --longest=2 --over=1');
$expect('over-cap-clears', ['answers over 9999 words: 0' => 'answers over 9999 words: 0', 'exit' => 'zero'],
    $q($php).' '.$q($reader).' --longest=2 --over=9999');

$expect('ballots-counted', ['☑' => '☑', 'exit' => 'zero'], $q($php).' '.$q($reader).' --ballots');
$expect('missing-copy-refuses', ['REFUSED' => 'REFUSED', 'exit' => 'nonzero'],
    $q($php).' '.$q($reader).' --copy='.$q($scratch.'/lane228h-no-such-copy.docx'));
// The refusal wording is read from the tool, not remembered: this leg first expected "this reader does not read" and
// the tool answers "this tool does not read", which is the harness's age wearing the costume of a surviving defect.
$expect('lane228-unknown-flag-refused', ['REFUSED: this tool does not read the flag' => 'REFUSED: this tool does not read the flag',
    '--no-such-flag-at-all' => '--no-such-flag-at-all', 'exit' => 'nonzero'], $q($php).' '.$q($reader).' --no-such-flag-at-all');

echo "\n";
echo $bad === 0
    ? "ALL KILLED: {$legs} leg(s), 0 bad\n"
    : "{$bad} BAD of {$legs} leg(s)\n";
exit($bad === 0 ? 0 : 1);
