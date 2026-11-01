<?php

/**
 * Harness for lane140-measure-wired-screens.php — prove the wired/owed checker can refuse.
 *
 * The instrument was written this batch and immediately found a real defect (the matrix's Status cell had fallen four
 * tables behind its own sentence), which is exactly the moment a checker deserves suspicion rather than gratitude: a
 * reader that reports one stale claim may also be silently reading none. Each leg therefore plants one shape and
 * requires the named complaint, and two legs require silence on the shapes that must NOT fire — the dated §1.1 row,
 * and a byte-identical copy.
 *
 * Usage: php storage/app/scratch/mutate-lane140-wired-counts.php
 */
$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$tool = 'storage/app/scratch/lane140-measure-wired-screens.php';
$matrix = 'Documentation/Prompt-Requirement-Matrix-2026-10-26.md';
$dump = 'storage/app/scratch/docx-flat.txt';

$copies = [];
$cleanup = static function () use (&$copies, $root): void {
    foreach ($copies as $c) {
        if (file_exists($root.'/'.$c)) {
            unlink($root.'/'.$c);
        }
    }
};
register_shutdown_function($cleanup);

/** A throwaway copy under a lane-specific name, removed by the shutdown handler above. */
$makeCopy = static function (string $src, string $name) use ($root, &$copies): string {
    if (! file_exists($root.'/'.$src)) {
        fwrite(STDERR, "REFUSED: no source file '{$src}' to copy.\n");

        exit(1);
    }
    $dst = 'storage/app/scratch/'.$name;
    if (! copy($root.'/'.$src, $root.'/'.$dst)) {
        fwrite(STDERR, "REFUSED: could not write the copy '{$dst}'.\n");

        exit(1);
    }
    $copies[] = $dst;

    return $dst;
};

/** Replace, verify the plant landed, and return the copy's path. */
$plant = static function (string $src, string $name, string $from, string $to, int $expect): string {
    global $root, $makeCopy;
    $dst = $makeCopy($src, $name);
    $text = (string) file_get_contents($root.'/'.$dst);
    $before = substr_count($text, $from);
    if ($before !== $expect) {
        fwrite(STDERR, sprintf("BAD: the plant needle occurs %d time(s) in %s, wanted %d — nothing was planted.\n",
            $before, $name, $expect));

        exit(1);
    }
    $planted = str_replace($from, $to, $text);
    $written = @file_put_contents($root.'/'.$dst, $planted);
    if ($written !== strlen($planted)) {
        fwrite(STDERR, "BAD: the plant of {$name} returned ".var_export($written, true).".\n");

        exit(1);
    }
    if (substr_count((string) file_get_contents($root.'/'.$dst), $to) < 1) {
        fwrite(STDERR, "BAD: {$name} does not carry the planted bytes — the plant is unread by the tool.\n");

        exit(1);
    }

    return $dst;
};

$run = static function (string $args) use ($root, $php, $tool): array {
    $out = [];
    $code = 0;
    exec($php.' '.$root.'/'.$tool.' '.$args.' 2>&1', $out, $code);

    return [$code, implode("\n", $out)];
};

$fail = 0;
$legs = 0;
$report = static function (string $leg, bool $ok, string $detail = '') use (&$fail, &$legs): void {
    $legs++;
    if (! $ok) {
        $fail++;
        printf("  BAD  %-40s %s\n", $leg, $detail);

        return;
    }
    printf("  ok   %-40s %s\n", $leg, $detail);
};

// 1 — control: both surfaces agree, and the dated row is named rather than judged.
[$code, $out] = $run('--check-matrix --check-docx');
$report('control', $code === 0 && substr_count($out, 'AGREES') === 2 && str_contains($out, 'DATED'),
    "exit {$code}, ".substr_count($out, 'AGREES')." agree(s), dated row ".(str_contains($out, 'DATED') ? 'named' : 'MISSING'));

// 2 — a stale WIRED figure in the Status cell (the defect the instrument actually found).
$copy = $plant($matrix, 'lane140-mx-wired.php.txt', 'eighteen of thirty-six tables use it',
    'fourteen of thirty-six tables use it', 1);
[$code, $out] = $run('--check-matrix --matrix='.$copy);
$report('stale-wired-cell', $code === 1 && str_contains($out, 'STALE') && str_contains($out, 'fourteen of thirty-six'),
    "exit {$code}");

// 3 — a stale OWED figure in the row's prose.
$copy = $plant($matrix, 'lane140-mx-owed.php.txt', '18 of the 36 pages using the shared table still offer no order',
    '19 of the 36 pages using the shared table still offer no order', 1);
[$code, $out] = $run('--check-matrix --matrix='.$copy);
$report('stale-owed-prose', $code === 1 && str_contains($out, 'STALE') && str_contains($out, 'OWED claims 19'),
    "exit {$code}");

// 4 — a stale figure in a LIVING document row must fire through --dump=.
$copy = $plant($dump, 'lane140-dp-living.php.txt', 'the eighteen wired pages call',
    'the sixteen wired pages call', 1);
[$code, $out] = $run('--check-docx --dump='.$copy);
$report('docx-living-row-stale', $code === 1 && str_contains($out, 'WIRED claims 16'), "exit {$code}");

// 5 — the dated exemption is READ, not accepted: re-spell the row's date as living and the same 34 must fire.
// The date cell is located by the row's own title, never hardcoded: pass 372 widened that row to a span
// (`2026-10-29 – 11-01`) and a leg anchored on the remembered single date would have reported "plant matched
// nothing" — the harness's age, not the tool's (lane 44's rule for a mutant anchored on a generated line).
$dumpText = (string) file_get_contents($root.'/'.$dump);
if (preg_match('/^ROW \d+: (20\d\d-\d\d-\d\d(?: – \d\d-\d\d)?) One sort mechanism/mu', $dumpText, $m) !== 1) {
    fwrite(STDERR, "BAD: no §1.1 row leads with a date and the title 'One sort mechanism' — this leg's population "
        ."is gone, so it cannot test the exemption.\n");

    exit(1);
}
$dateCell = $m[1];
$copy = $plant($dump, 'lane140-dp-dated.php.txt', $dateCell.' One sort mechanism',
    '(this issue) One sort mechanism', 1);
[$code, $out] = $run('--check-docx --dump='.$copy);
$report('dated-rule-proved-by-falsification', $code === 1 && str_contains($out, 'OWED claims 34')
    && str_contains($out, '0 carried by a dated'), "exit {$code} (cell was [{$dateCell}])");

// 6 — and on the untouched dump the same claim stays exempt.
$copy = $makeCopy($dump, 'lane140-dp-clean.php.txt');
[$code, $out] = $run('--check-docx --dump='.$copy);
$report('dated-rule-holds-on-clean', $code === 0 && str_contains($out, '1 carried by a dated'), "exit {$code}");

// 7 — a dump with nothing left to judge must REFUSE, not certify. Both living claims are stripped; the dated row's
// "34 of the 36" stays in the file and must not be counted as a judged claim, which is what makes this leg mean
// "read nothing" rather than "read something stale".
$copyA = $plant($dump, 'lane140-dp-empty-a.php.txt', 'the eighteen wired pages call', 'the wired pages call', 1);
$text = (string) file_get_contents($root.'/'.$copyA);
$needle = '18 of the 36 pages that use the shared table';
if (substr_count($text, $needle) !== 1) {
    fwrite(STDERR, "BAD: the owed needle occurs ".substr_count($text, $needle)." time(s) in the dump copy, wanted 1"
        ." — the leg would silently leave a claim standing.\n");

    exit(1);
}
$text = str_replace($needle, 'the pages that use the shared table', $text);
$w = @file_put_contents($root.'/'.$copyA, $text);
if ($w !== strlen($text)) {
    fwrite(STDERR, "BAD: the second plant returned ".var_export($w, true).".\n");

    exit(1);
}
[$code, $out] = $run('--check-docx --dump='.$copyA);
$report('judged-nothing-refuses', $code === 1 && str_contains($out, 'no JUDGED'), "exit {$code}");

// 8 — blinding the handler walk must refuse rather than print 0 over a tree of 18 call sites. The copy IS the tool,
// so the copy is what gets run: a leg that runs the original would measure the original and call it the mutant.
$copy = $plant($tool, 'lane140-tool-blind.php.txt', "\$appFiles = \$walk(\$root.'/app', 'php');",
    "\$appFiles = \$walk(\$root.'/app', 'jsx');", 1);
$out = [];
$code = 0;
exec($php.' '.$root.'/'.$copy.' 2>&1', $out, $code);
$body = implode("\n", $out);
$report('blind-walk-refuses', $code === 1 && str_contains($body, 'walk of app/ found no .php file'),
    "exit {$code}");

// 9 / 10 / 11 — every refusal told apart by its own message.
[$code, $out] = $run('--dump='.$dump);
$report('dump-without-checkdocx', $code === 1 && str_contains($out, '--dump= only means something beside --check-docx'),
    "exit {$code}");
[$code, $out] = $run('--matrix='.$matrix);
$report('matrix-without-checkmatrix', $code === 1 && str_contains($out, '--matrix= only means something beside --check-matrix'),
    "exit {$code}");
[$code, $out] = $run('--verbose');
$report('unknown-flag-refused-by-name', $code === 1 && str_contains($out, '--verbose'), "exit {$code}");

// 12 — a missing dump must refuse rather than answer about rows it never read.
[$code, $out] = $run('--check-docx --dump=storage/app/scratch/lane140-no-such-dump.txt');
$report('missing-dump-refuses', $code === 1 && str_contains($out, "no flat dump at 'storage/app/scratch/lane140-no-such-dump.txt'"),
    "exit {$code}");

// 13 — control again: every plant is in a throwaway copy, so the tree and both registers are untouched.
[$code, $out] = $run('--check-matrix --check-docx');
$report('trailing control', $code === 0 && substr_count($out, 'AGREES') === 2, "exit {$code}");

printf("\n%s: %d leg(s), %d bad\n", $fail === 0 ? 'ALL PROVABLE' : 'FAILED', $legs, $fail);

exit($fail === 0 ? 0 : 1);
