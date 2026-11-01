<?php

/**
 * Docx pass 344 — §28.3 G-52 closes, and the day's fourth half is recorded beside the other three.
 *
 * The two sites the narrowing rewriter could not splice — a load that runs THROUGH the student, where a column list on
 * the leaf would ask the addresses or the course table for a surname — were rewritten the same day inside closures, and
 * the census now answers no identity-only row at all. The row's verdict word therefore moves from Open to Closed, and
 * pass 341's own sentence about those two sites is re-taken rather than left promising work that is done.
 *
 * Edit 1 and 2 are rewrites (their retired needle must disappear). Edit 3 and 4 are appends: their replacement embeds
 * the needle, so the retired form is expected to survive exactly once, as the head of the new text — the per-edit
 * residual rule pass 335 and pass 341 each paid for once already.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass344.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = $root.'/storage/app/scratch/pass344-backup.docx';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}
require __DIR__.'/docx-edit-engine.php';

$verdict = ['Open 2026-10-31, found by reading the real payload rather than the code',
    'Closed 2026-10-31, found by reading the real payload rather than the code', 0];

$spliced = ['which is a per-site rewrite and not a byte splice, so the row stays open on them by name,',
    'which is a per-site rewrite rather than a byte splice. Both were written the same day: the load now runs through '
        .'a closure that selects the identity columns on the student and re-loads the relation inside it, the census '
        .'answers no identity-only row at all, and the register closes with the two screens that legitimately edit '
        .'the person record left whole by design and named as editors rather than as debt,', 0];

$row42 = ['editors rather than counted as debt.', 'editors rather than counted as debt. The day’s fourth half closed '
    .'§28.3 G-52: the two sites whose eager load runs THROUGH the student — the Registrar’s record page and the '
    .'clearance slip’s print page — now select inside a closure and re-load their relations there; the census answers '
    .'no identity-only row; and one shared predicate in `students-column-guard.php` decides “does this entry carry a '
    .'column list” for the census, the re-audit and the source-side test, refusing a closure with no select rather '
    .'than guessing. The shift tool moved 77 citation tokens for the two files that grew by two and three lines, and '
    .'its three deferred bare tails were re-taken by reading — one of them through its own run boundary, because the '
    .'plain needle for that number belongs to a different file’s comma list.', 1];

$instruments = ['each of which must make its named case print one failure.',
    'each of which must make its named case print one failure. The closure sites taught the harness a fifth shape: a '
        .'leg that dropped the closure’s own re-load stayed GREEN, because the same request reads the clearance’s '
        .'term enrollment and that lazy read fills the relation key with whole rows — so the case now asks for the '
        .'nested course, which only the eager load carries, and a relation key alone is not evidence. One predicate '
        .'answers “is this narrowed” for the census, the re-audit and the source-side test, and the re-audit prints '
        .'the closure-shaped sites it cannot judge rather than clearing them.', 1];

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    exit("REFUSED: the docx did not open as a zip.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$plan = [$verdict, $spliced, $row42, $instruments];
$textEdits = [];
$wantOld = [];
$bad = 0;
foreach ($plan as $i => [$from, $to, $residual]) {
    $n = substr_count($xml, $from);
    $wantNew = $to === '' ? 0 : 1;
    printf('edit %d: anchor occurs %d time(s), want 1 — %s%s', $i, $n, substr($from, 0, 44), PHP_EOL);
    if ($n !== 1) {
        // An append whose needle is already the head of a longer sentence is fine; an edit whose needle was never
        // written is the instrument's age and must stop the run.
        if ($to !== '' && substr_count($xml, $to) === $wantNew) {
            echo "  edit {$i}: the new form is already present once — treated as applied\n";
        } else {
            $bad++;
        }
    }
    if (str_contains($to, '<') || str_contains($to, '>')) {
        $bad++;
        echo "  edit {$i}'s replacement carries a raw tag character\n";
    }
    if (preg_match('/\.php:\d/u', $to) === 1) {
        $bad++;
        echo "  edit {$i}'s replacement introduces an unverified line citation\n";
    }
    $textEdits[] = [$from, $to, null, 1];
    $wantOld[] = [$residual, $wantNew];
}
if ($bad > 0) {
    $allApplied = true;
    foreach ($plan as [$from, $to]) {
        if ($to !== '' && substr_count($xml, $to) !== 1) {
            $allApplied = false;
        }
    }
    echo $allApplied ? "ALREADY APPLIED.\n" : "REFUSED — nothing written.\n";

    exit($allApplied ? 0 : 1);
}

docxApply($docx, $backup, $textEdits, [], $apply);
if (! $apply) {
    echo "DRY RUN — nothing written.\n";

    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    exit("REFUSED: the written docx did not open as a zip.\n");
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

$fail = 0;
foreach ($textEdits as $i => [$from, $to]) {
    [$residual, $wantNew] = $wantOld[$i];
    $newN = substr_count($after, $to);
    $oldN = substr_count($after, $from);
    // A deletion edit ($to === '') expects the needle gone; an append expects it to survive as its own head.
    $expectNew = $wantNew;
    $expectOld = $residual;
    printf('  edit %d: new form %d (want %d), retired form %d (want %d)%s', $i, $newN, $expectNew, $oldN, $expectOld, PHP_EOL);
    if ($newN !== $expectNew || $oldN !== $expectOld) {
        $fail++;
    }
}
if ($fail > 0) {
    fwrite(STDERR, "REFUSED: read-back failed; restoring the pass's own backup.\n");
    if (is_file($backup)) {
        copy($backup, $docx);
    }

    exit(1);
}

echo 'written, re-read: G-52 leads Closed, the pass-341 clause about the two splice-proof sites is re-taken, and the '
    ."day's record names the fourth half.\n";
