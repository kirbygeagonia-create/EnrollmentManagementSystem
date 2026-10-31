<?php

/**
 * Docx pass 341 — the code moved, so the register follows in the same batch (rule 2).
 *
 * §28.3 G-52's row was written when the census counted 13 whole-student load sites and its narrowing had reached only
 * the desk queues. Nine more sites came off that number today, so four of the row's clauses are now understatements or
 * plain under-tenses, and one claims the rewriter REFUSES a site whose page paints a name suffix — which is what it did
 * before it learned to widen the list from that same reading. A stale claim about a tool's behaviour is worse than a
 * stale count, because the next reader trusts the tool rather than re-running it.
 *
 * §1.1's living row records the day's first two halves; the third goes beside them.
 *
 * Nothing is re-aged: the 13-row reading keeps its own date and states what came after it. Every needle was measured to
 * occur exactly once in `word/document.xml` first (`lane70-probe-needles.php`), because the flat dump joins runs with a
 * space and a phrase copied out of it is not always contiguous in the file.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass341.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = $root.'/storage/app/scratch/pass341-backup.docx';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}
require __DIR__.'/docx-edit-engine.php';

// 1. the census reading, kept as that day's record with today's result stated beside it.
$census = 'The same census read afterwards answers 13 rows — 7 detail screens, 4 print paths and 2 legitimate editors '
    .'— which is the whole remaining debt,';
$censusTo = 'The same census read afterwards answered 13 rows — 7 detail screens, 4 print paths and 2 legitimate '
    .'editors. Nine of those sites were narrowed the same day, and the four that remain are a different shape: two are '
    .'the legitimate editors, and two carry a literal that names a relation ON the student, where a column list '
    .'appended to the leaf would ask the addresses table or the course table for a surname — the Registrar’s own record '
    .'screen loads addresses and guardians through it, and the slip’s print page loads the course through it. Those two '
    .'need a closure that selects on the student and re-loads the relation inside it, which is a per-site rewrite and '
    .'not a byte splice, so the row stays open on them by name,';

// 2. the suffix flag: re-dated, and the rule it states is now the rewriter's own reading.
$suffix = '5 of the detail screens and 3 of the print paths also paint the name SUFFIX, which a five-column list does '
    .'not carry: the narrowing rewriter refuses any site whose page paints a suffix rather than widening the set on its '
    .'own,';
$suffixTo = 'that same read flagged that 5 of those 7 detail screens and 3 of the 4 print paths paint the name SUFFIX, '
    .'which a five-column list does not carry — so the rewriter derives each site’s list from the page beneath it '
    .'instead of from a constant shared by every screen,';

// 3. what that derivation actually wrote.
$got = 'and the six-column name set is what those screens will get.';
$gotTo = 'and that is the set it wrote: four detail sites and all three Registrar print pages carry the suffix now, '
    .'while the fee sheet and the assessment sheet, whose import graphs paint none, took the five-column list.';

// 4. the tool's behaviour, which the row still described in its first shape.
$refuses = 'and the tool refuses any site whose page reads one rather than writing it anyway.';
$refusesTo = 'and the rewriter reads that flag rather than writing a constant, so no screen is narrowed to a list it '
    .'then has to widen.';

// 5. how the new half is pinned.
$pinned = 'kills the leak and the bad column by putting each shape back.';
$pinnedTo = 'kills the leak and the bad column by putting each shape back. The record screens and print pages added the '
    .'same day are pinned on the same surface the queue cases use: each desk’s own test drives its real request and '
    .'compares the nested student to the exact identity set — equality, not absence, because an absence assertion also '
    .'passes when the name line itself has gone missing — and `mutate-g52-detail-print-wire-tests.php` plants the whole '
    .'load, a dropped suffix and an extra key on the hand-built assign pick-list, each of which must make its named '
    .'case print one failure.';

// 6. the day's record.
$row = 'and recorded why the other two are left alone.';
$rowTo = 'and recorded why the other two are left alone. The day’s third half narrowed the nine desk record and print '
    .'sites whose eager load ends at the student, so §28.3 G-52 now owes only the two that load a relation through it '
    .'and cannot take a column list at the leaf; the two screens that legitimately edit the person record are named as '
    .'editors rather than counted as debt.';

$zip = new ZipArchive;
// `!== true`, not `! $zip->open(...)`: ZipArchive::open answers an error CODE for a file that is not an archive, and
// an error code is truthy, so a negated guard never fires and the run dies later on "uninitialized Zip object".
if ($zip->open($docx) !== true) {
    exit("REFUSED: the docx did not open as a zip.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$textEdits = [
    [$census, $censusTo, null, 1],
    [$suffix, $suffixTo, null, 1],
    [$got, $gotTo, null, 1],
    [$refuses, $refusesTo, null, 1],
    [$pinned, $pinnedTo, null, 1],
    [$row, $rowTo, null, 1],
];

$bad = 0;
foreach ($textEdits as $i => [$from, $to, , $want]) {
    $n = substr_count($xml, $from);
    printf('edit %d: anchor occurs %d time(s), want %d — %s%s', $i, $n, $want, substr($from, 0, 46), PHP_EOL);
    if ($n !== $want) {
        $bad++;
    }
    if (preg_match('/\.php:\d/u', $to) === 1) {
        $bad++;
        echo "  edit {$i}'s replacement introduces an unverified line citation\n";
    }
    if (str_contains($to, '<') || str_contains($to, '>')) {
        $bad++;
        echo "  edit {$i}'s replacement carries a raw tag character the engine would refuse\n";
    }
}
if ($bad > 0) {
    $already = true;
    foreach ($textEdits as [, $to]) {
        if (substr_count($xml, $to) !== 1) {
            $already = false;
        }
    }
    echo $already ? "ALREADY APPLIED.\n" : "REFUSED — nothing written.\n";

    exit($already ? 0 : 1);
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
// Edits 4 and 5 APPEND: their replacement begins with the very sentence it keeps, so the retired form is still on the
// page — as the head of the new one. Expecting 0 there refused a correct write (pass 335's lesson, met again), and
// expecting the new form's count alone would not have noticed. The residual is therefore stated per edit: an append
// must leave exactly ONE copy (its own head), a rewrite must leave none.
$append = [false, false, false, false, true, true];
foreach ($textEdits as $i => [$from, $to]) {
    $newN = substr_count($after, $to);
    $oldN = substr_count($after, $from);
    $wantOld = $append[$i] ? 1 : 0;
    printf('  edit %d: new form %d (want 1), retired form %d (want %d)%s', $i, $newN, $oldN, $wantOld, PHP_EOL);
    if ($newN !== 1 || $oldN !== $wantOld) {
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

echo "written, re-read: G-52 states which sites are narrowed, which two remain and why, and the day's record names the"
    ." third half.\n";
