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
$controlClaims = preg_match('/(\d+) wired\/owed claim\(s\) extracted/', $out, $cm0) === 1 ? (int) $cm0[1] : -1;
$report('control', $code === 0 && substr_count($out, 'AGREES') === 2 && str_contains($out, 'DATED') && $controlClaims > 0,
    "exit {$code}, ".substr_count($out, 'AGREES').' agree(s), dated row '.(str_contains($out, 'DATED') ? 'named' : 'MISSING').", {$controlClaims} matrix claim(s)");

// 2 — a stale WIRED figure in the Status cell (the defect the instrument actually found).
// Every needle in this harness is re-cut onto the bytes the registers hold NOW, because the planted numbers are
// prose the batch just re-took: a leg anchored on the previous day's figure reports "plant matched nothing", which
// is the harness's age and never a defect in the checker (lane 44's rule for an anchor that has to be re-cut).
$copy = $plant($matrix, 'lane140-mx-wired.php.txt', 'twenty-eight of thirty-six tables use it',
    'fourteen of thirty-six tables use it', 1);
[$code, $out] = $run('--check-matrix --matrix='.$copy);
$report('stale-wired-cell', $code === 1 && str_contains($out, 'STALE') && str_contains($out, 'fourteen of thirty-six'),
    "exit {$code}");

// 3 — a stale OWED figure in the row's prose.
$copy = $plant($matrix, 'lane140-mx-owed.php.txt', '8 of the 36 pages using the shared table still offer no order',
    '19 of the 36 pages using the shared table still offer no order', 1);
[$code, $out] = $run('--check-matrix --matrix='.$copy);
$report('stale-owed-prose', $code === 1 && str_contains($out, 'STALE') && str_contains($out, 'OWED claims 19'),
    "exit {$code}");

// 3b — a COMPOUND spelled figure is read as a sum. The instrument's own defect, met when the twenty-first table
// made the register say "twenty-one": the reader took `twenty`, then matched `one of the 36 …` as its own capture
// and reported a true sentence as a claim of ONE. This plant is wrong in its SECOND half only, so it fires STALE
// exactly when 22 was added — and the extraction count must stay what the control read, because before the widening
// the whole form matched nothing and a claim went silently unjudged.
$copy = $plant($matrix, 'lane140-mx-compound.php.txt', 'twenty-eight of thirty-six tables use it',
    'twenty-two of thirty-six tables use it', 1);
[$code, $out] = $run('--check-matrix --matrix='.$copy);
$plantedClaims = preg_match('/(\d+) wired\/owed claim\(s\) extracted/', $out, $cm) === 1 ? (int) $cm[1] : -1;
$report('compound-figure-read-as-a-sum', $code === 1 && str_contains($out, 'WIRED claims 22')
    && $plantedClaims === $controlClaims,
    "exit {$code}, WIRED claims 22 ".(str_contains($out, 'WIRED claims 22') ? 'named' : 'MISSING').", {$plantedClaims} claim(s) extracted against the control's {$controlClaims}");

// 3c — a widening is not an off switch: `ten` joined the vocabulary before this batch and must still be readable
// after it, or the compound rewrite silently retired a number the prose has used.
$copy = $plant($matrix, 'lane140-mx-ten.php.txt', '8 of the 36 pages using the shared table still offer no order',
    'ten of the 36 pages using the shared table still offer no order', 1);
[$code, $out] = $run('--check-matrix --matrix='.$copy);
$report('single-word-ten-still-read', $code === 1 && str_contains($out, 'OWED claims 10'), "exit {$code}");

// 3d — a SINGLE-DIGIT arabic figure is a claim, not a letter. The reader used `/\d{2}/`, so the day the owed count
// reached 8 the run went from eight claims to SIX: the prose getting truer made the instrument judge less.
$copy = $plant($matrix, 'lane140-mx-digit1.php.txt', '8 of the 36 pages using the shared table still offer no order',
    '9 of the 36 pages using the shared table still offer no order', 1);
[$code, $out] = $run('--check-matrix --matrix='.$copy);
$report('single-digit-owed-is-judged', $code === 1 && str_contains($out, 'OWED claims 9'), "exit {$code}");

// 3e — a widening is not an off switch: the two-digit form the row wore all week must still be harvested.
$copy = $plant($matrix, 'lane140-mx-digit2.php.txt', '8 of the 36 pages using the shared table still offer no order',
    '18 of the 36 pages using the shared table still offer no order', 1);
[$code, $out] = $run('--check-matrix --matrix='.$copy);
$report('double-digit-owed-still-judged', $code === 1 && str_contains($out, 'OWED claims 18'), "exit {$code}");

// 3f — a claim OUTSIDE the gap row is judged. The §8 / §14 coverage rows state this same population in their own
// words, and while the checker was scoped to the `| G40 |` line it never opened them, so eighteen wired and eighteen
// owed sat in the matrix for weeks with nothing disagreeing.
$copy = $plant($matrix, 'lane140-mx-otherrow.php.txt', 'twenty-eight screens use it',
    'thirty screens use it', 1);
[$code, $out] = $run('--check-matrix --matrix='.$copy);
$report('claim-outside-the-gap-row-is-judged', $code === 1 && str_contains($out, 'WIRED claims 30')
    && str_contains($out, 'the matrix:'),
    "exit {$code}, WIRED claims 30 ".(str_contains($out, 'WIRED claims 30') ? 'named' : 'MISSING'));

// 3g — the owed PLAN sentence is a membership claim about the same count, and it joined the vocabulary when the
// matrix said nine on a day eight were owed.
$copy = $plant($matrix, 'lane140-mx-plan.php.txt', 'The plan for the eight screens still owed',
    'The plan for the twelve screens still owed', 1);
[$code, $out] = $run('--check-matrix --matrix='.$copy);
$report('owed-plan-sentence-is-judged', $code === 1 && str_contains($out, 'OWED claims 12'), "exit {$code}");

// 4 — a stale figure in a LIVING document row must fire through --dump=.
$copy = $plant($dump, 'lane140-dp-living.php.txt', 'the twenty-eight wired pages call',
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
// The dated count is MEASURED in this run rather than remembered. Pass 401 added a living claim and the extractor
// learned the register's spelled shape, so a leg asserting "1 carried by a dated §1.1 row" would have reported the
// document getting truer as the tool going bad — verifier expectations age in exactly this way (lane 72's rule).
$cleanCopy = $makeCopy($dump, 'lane140-dp-clean.php.txt');
[$cleanCode, $cleanOut] = $run('--check-docx --dump='.$cleanCopy);
$cleanDated = preg_match('/(\d+) carried by a dated/', $cleanOut, $dm) === 1 ? (int) $dm[1] : -1;
// The dated row's OWN figure is read off the clean run rather than remembered: pass 401 proved that a leg asserting
// a frozen number reports the document getting truer as the tool going bad.
if (preg_match('/DATED\s+dump line \d+: \[(\d+) of the 36 pages that render the shared table/', $cleanOut, $fm) !== 1) {
    fwrite(STDERR, "BAD: the clean run names no dated `N of the 36 pages that render the shared table` claim, so this "
        ."leg has nothing to falsify — its population moved.\n");

    exit(1);
}
$datedFigure = (int) $fm[1];
$report('dated-rule-holds-on-clean', $cleanCode === 0 && $cleanDated >= 1 && str_contains($cleanOut, 'AGREES'),
    "exit {$cleanCode}, {$cleanDated} dated claim(s) (row figure {$datedFigure}), clean verdict "
    .($cleanCode === 0 ? 'agrees' : 'refused'));

$copy = $plant($dump, 'lane140-dp-dated.php.txt', $dateCell.' One sort mechanism',
    '(this issue) One sort mechanism', 1);
[$code, $out] = $run('--check-docx --dump='.$copy);
$nowDated = preg_match('/(\d+) carried by a dated/', $out, $dm2) === 1 ? (int) $dm2[1] : -1;
$report('dated-rule-proved-by-falsification', $code === 1 && str_contains($out, 'claims '.$datedFigure)
    && $nowDated === $cleanDated - 1,
    "exit {$code}; dated went {$cleanDated} -> {$nowDated}, wanted exactly one fewer; the same figure ({$datedFigure}) "
    .(str_contains($out, 'claims '.$datedFigure) ? 'fired as a living claim' : 'did not fire'));

// 7 — a dump with nothing left to judge must REFUSE, not certify. Every living claim is stripped (three of them
// since pass 401); the dated rows' figures stay in the file and must not be counted as judged claims, which is what
// makes this leg mean "read nothing" rather than "read something stale".
$copyA = $plant($dump, 'lane140-dp-empty-a.php.txt', 'the twenty-eight wired pages call', 'the wired pages call', 1);
$text = (string) file_get_contents($root.'/'.$copyA);
$needle = '8 of the 36 pages that use the shared table';
if (substr_count($text, $needle) !== 1) {
    fwrite(STDERR, 'BAD: the owed needle occurs '.substr_count($text, $needle).' time(s) in the dump copy, wanted 1'
        ." — the leg would silently leave a claim standing.\n");

    exit(1);
}
$text = str_replace($needle, 'the pages that use the shared table', $text);
// Pass 401 left the register a THIRD living claim in the spelled shape the extractor has now learned, so the leg
// has to mute it too — otherwise the run judges something, agrees, and the "no JUDGED" refusal stays unreachable.
$needle3 = 'twenty-eight of the thirty-six tables that render the shared component sort on the server';
if (substr_count($text, $needle3) !== 1) {
    fwrite(STDERR, 'BAD: the spelled wired needle occurs '.substr_count($text, $needle3).' time(s) in the dump copy, wanted 1'
        ." — the leg would silently leave a claim standing.\n");

    exit(1);
}
$text = str_replace($needle3, 'the tables that render the shared component sort on the server', $text);
$w = @file_put_contents($root.'/'.$copyA, $text);
if ($w !== strlen($text)) {
    fwrite(STDERR, 'BAD: the second plant returned '.var_export($w, true).".\n");

    exit(1);
}
[$code, $out] = $run('--check-docx --dump='.$copyA);
$report('judged-nothing-refuses', $code === 1 && str_contains($out, 'no JUDGED'), "exit {$code}");

// 8 — blinding the handler walk must refuse rather than print 0 over the tree's call sites. The copy IS the tool,
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
