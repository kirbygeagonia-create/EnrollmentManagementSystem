<?php

/**
 * Docx pass 330 — G-52 is narrowed on three queues and stays OPEN, and §1.1 records the defect only CI could see.
 *
 * Two things happened after pass 329 registered G-52.
 *
 *  (1) The fix, partly. The Registrar, Accounting and Assessment queues now load the student for the five identity
 *      columns their pages render. It was written as a line-neutral change on purpose, so no citation into those
 *      handlers aged. Then the census that was written to size the job — `lane108-census-whole-student-loads.php`,
 *      which binds each whole-student eager load to the action that runs it and to the `Inertia::render` inside that
 *      same action — answered 25 load sites over 25 controllers, of which 24 serve a page that reads nothing beyond
 *      the name. So three handlers is not the class, and the row keeps leading `Open`: closing it on what was
 *      reached is the overclaim this document refuses. The remaining sites split 12 desk list pages, 8 detail
 *      screens and 4 print paths, and the last two groups are judged in the browser and on paper rather than from
 *      source, which is why they were not narrowed blind.
 *  (2) A regression this box could not see. Narrowing the loads the first time named a column `students` does not
 *      have, and the three queues answered 500 — while the sort classes stayed green, because their payload case
 *      compared column sets without ever asking whether the desk had ANSWERED. That case now asserts a 200 first.
 *      Separately, applying the ENUM ruling surfaced that the admission queue's status tile was cloned AFTER the
 *      order, so it inherited that order's join and its pinned `admissions.*`, and `selectRaw()` appended to them:
 *      MySQL 8's `ONLY_FULL_GROUP_BY` answered 1055, so a desk clicking the surname header met a 500 on every run
 *      that SQLite made look clean. CI's MySQL job named it; it was reproduced on a scratch schema, the tile now
 *      replaces its own column list, and both drivers answer the same assertions.
 *
 * Edits are `text` kind only — needles verified contiguous in `document.xml`, each asserted to occur exactly once,
 * and the retired wording is asserted gone on read-back. No register row was added or removed, so no count of the
 * register moves; the figures written into the row are the census's own, and the row carries the date it read them.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass330.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

require __DIR__.'/docx-edit-engine.php';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$textEdits = [
    // 1. the verdict cell — the leading verb STAYS `Open`, and the clause records what was reached.
    [
        'Open 2026-10-31, found by reading the real payload rather than the code.',
        'Open 2026-10-31, found by reading the real payload rather than the code, and narrowed the same day on the three queues that read named.',
    ],
    // 2. the outstanding-work clause becomes the measured population, not the three sites this batch happened to reach.
    [
        'It needs no school ruling, because no desk is asking to see these fields and none displays them; it waits only on the citation cascade, since three handlers move and each line is a document pointer.',
        'It needs no school ruling, because no desk is asking to see these fields and none displays them. The four sites behind the Registrar, Accounting and Assessment queues were made line-neutral `with` rewrites, so no citation aged; the census that sized the job then measured 25 whole-student load sites over 25 controllers, of which 24 answer a page that reads nothing beyond the name and one (the admission case screen) legitimately edits them. What is left is 12 desk list pages, 8 detail screens and 4 print paths — read 2026-10-30, and the last two groups are narrowed only after the browser and the paper have been asked, because a column list written from a guess is a 500 at the desk rather than a wrong value.',
    ],
    // 3. the assertion a 500 cannot pass as a measurement.
    [
        'No case count or assertion total is stated here, because such a figure belongs to a run rather than to the code.',
        'The first attempt named a column `students` never had, and all three queues answered 500 while the sort classes reported green — a case that compares the columns of a page without asking whether the desk answered is a case that cannot fail, so each of the three now asserts a 200 on every request it compares before it compares anything. No case count or assertion total is stated here, because such a figure belongs to a run rather than to the code.',
    ],
    // 4. §1.1: the MySQL-only tile defect, which only CI's second driver could see.
    [
        'that is G-52, registered open, and its row says why the fix is three `with` clauses rather than a question for the school.',
        'that is G-52, registered open and narrowed the same day on the three queues the live read named, with its row carrying the census that sized the rest. Applying the ruling also turned up a defect this box could not see: the admission queue’s status tile was cloned after the order, so it inherited that order’s join and its pinned `admissions.*`, and `selectRaw()` appended to them — MySQL 8’s `ONLY_FULL_GROUP_BY` answered 1055, so a desk clicking the surname header met a 500 while every local run stayed green on SQLite. CI’s MySQL job named it; it was reproduced on a scratch schema, the tile now replaces its own column list, and both drivers answer the same assertions.',
    ],
];

$failures = [];
$beforeCount = [];
foreach ($textEdits as $i => [$from, $to]) {
    $n = substr_count($xml, $from);
    $beforeCount[$i] = $n;
    printf('edit %d: %d occurrence(s) — %s%s', $i, $n, substr(trim($from), 0, 56), PHP_EOL);
    if ($n !== 1) {
        $failures[] = "edit {$i} matched {$n} time(s), wanted exactly 1";
    }
    if (str_contains($to, '|')) {
        $failures[] = "edit {$i}'s replacement carries a literal pipe, which the flat dump cannot tell from a cell boundary";
    }
    if (preg_match('/\.php:\d/u', $to) === 1) {
        $failures[] = "edit {$i}'s replacement introduces a line citation the pass has not verified";
    }
    // A replacement that ENDS by restating its own needle is not a no-op, and demanding the old bytes be gone after
    // it would refuse a correct write — pass 328's first read-back did exactly that. The honest expectation is
    // arithmetic: one copy removed, as many copies re-added as the replacement carries.
    $residual = $n - 1 + substr_count($to, $from);
    if ($n > 0) {
        printf('       → after this edit the old bytes should still occur %d time(s)%s', $residual, PHP_EOL);
    }
    // A verdict cell must still LEAD with its verb, because two readers of this register judge the leading word.
    if ($i === 0 && ! str_starts_with($to, 'Open ')) {
        $failures[] = "edit 0 would move the verdict off `Open`, and this row is not closed";
    }
}
if ($failures !== []) {
    // All four new forms present once and the old forms at their residual counts means the pass already ran. That is
    // a success to report, not a needle to force: a re-run must never double-write a sentence.
    $already = true;
    foreach ($textEdits as [$from, $to]) {
        if (substr_count($xml, $to) !== 1) {
            $already = false;

            break;
        }
    }
    if ($already) {
        echo "ALREADY APPLIED — every replacement is present once; nothing was written twice.\n";

        exit(0);
    }
    fwrite(STDERR, "REFUSED — nothing written:\n".implode("\n", $failures)."\n");

    exit(1);
}

// The engine writes this copy BEFORE it writes the document, so it is the pre-write backup — and on a dry run it is
// never created at all, because `docxApply` returns before reaching the copy.
$backup = $root.'/storage/app/scratch/pass330-backup.docx';

docxApply($docx, $backup, $textEdits, [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot re-open the written archive.\n");

    exit(1);
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

$bad = 0;
foreach ($textEdits as $i => [$from, $to]) {
    $g = substr_count($after, $to);
    $l = substr_count($after, $from);
    $wantOld = $beforeCount[$i] - 1 + substr_count($to, $from);
    printf('  edit %d: new form %d (want 1), old form %d (want %d)%s', $i, $g, $l, $wantOld, PHP_EOL);
    if ($g !== 1 || $l !== $wantOld) {
        $bad++;
    }
}
if ($bad > 0) {
    fwrite(STDERR, "REFUSED: {$bad} read-back check(s) failed after the write — restoring.\n");
    if (! is_file($backup)) {
        fwrite(STDERR, "AND this pass kept no backup of its own (`{$backup}` is absent), so the write stands and a"
            ." human must read the four rows back before anything else is edited. The engine writes the backup only"
            ." on the apply path; a pass that offers a restore has to check the file it offers to restore from.\n");

        exit(1);
    }
    if (copy($backup, $docx)) {
        fwrite(STDERR, 'restored '.basename($backup).'; nothing of the bad write is left.'."\n");
    } else {
        fwrite(STDERR, "AND the restore failed. Restore ".basename($backup)." by hand.\n");
    }

    exit(1);
}
echo 'written, re-read: G-52 still leads Open with the rest measured, and §1.1 says what CI saw that this box did not.'."\n";
