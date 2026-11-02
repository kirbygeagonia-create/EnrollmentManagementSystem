<?php

/**
 * Pass 401 — §28.3's living G-40 row states the wired count as EIGHT in one sentence and as 18 in the sentence
 * two lines above it. The tree answers eighteen (`lane140-measure-wired-screens.php` reads the pages that render
 * the shared table and reach `listSort.js`, and the distinct actions in `app/` that call `ListSort::apply`).
 *
 * `lane140` had been agreeing with this row, because its extractor spelled the denominator as
 * `(N) of the 36 pages …` while the register writes `(N) of the thirty-six tables that render the shared component
 * sort on the server today`. A claim the tool cannot read is not a clean claim, so the extractor learned this shape
 * (and the article, which the matrix drops and the register keeps) BEFORE the prose was touched, and this pass
 * refuses unless the widened instrument reads the new sentence back.
 *
 * The stale queues-versus-directories split is dropped rather than re-typed: no instrument weighs that division,
 * so restating it in a new number would keep a claim the repository cannot check.
 *
 * The dated §1.1 row (dump line 40, "The eighth sortable table…") carries the same words and is RIGHT: eight was
 * the tree's answer on 2026-10-30, and lane 60/72's rule says a dated row is never re-aged. `lane140` names it
 * DATED, and this pass asserts that it still does.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass401.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$apply = false;
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
    } else {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/refresh-docx-pass401-backup.docx';

$from = 'eight of the thirty-six tables that render the shared component sort on the server today, seven of them queues and one a directory';
$to = 'eighteen of the thirty-six tables that render the shared component sort on the server today';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    $fail('the docx did not open.');
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
if ($xml === '') {
    $fail('word/document.xml read empty.');
}

// A needle is probed in the archive, not in the flattened dump: the dump joins runs with a space and can invent a
// phrase the file does not hold contiguously (pass 212's lesson).
$hits = substr_count($xml, $from);
if ($hits !== 1) {
    $fail("the needle occurs {$hits} time(s) in document.xml; this edit addresses exactly one clause, and a count of 0 "
        .'means the prose moved since the register was read.');
}

printf("stage 1 ok: the stale clause occurs %d time(s)\n", $hits);
if (! $apply) {
    echo "DRY RUN — nothing written.\n";
    exit(0);
}

if (! copy($docx, $backup)) {
    $fail('the backup copy could not be written, so the pass has no way to undo its own edit.');
}

$restore = static function () use ($backup, $docx): void {
    copy($backup, $docx);
    echo "restored the docx from its own backup\n";
};

docxApply($docx, $backup, [[$from, $to]], [], true);

$z2 = new ZipArchive;
if ($z2->open($docx) !== true) {
    $fail('the docx did not reopen after the write.');
}
$after = (string) $z2->getFromName('word/document.xml');
$z2->close();

if (substr_count($after, $to) !== 1) {
    $restore();
    $fail('the new clause did not land exactly once ('.substr_count($after, $to).' copies).');
}
if (substr_count($after, $from) !== 0) {
    $restore();
    $fail('the stale clause still occurs in the document after the write.');
}
echo "stage 2 ok: the clause now reads the tree's number, and the retired wording is gone\n";

// The instrument that owns this figure reads the FLAT dump, so re-dump before believing any verdict about it.
exec(escapeshellarg((string) PHP_BINARY).' '.escapeshellarg($root.'/storage/app/scratch/dump-docx-flat.php').' 2>&1', $dumpOut, $dumpCode);
if ($dumpCode !== 0) {
    $restore();
    $fail('the flat re-dump exited '.$dumpCode.': '.implode(' / ', array_slice($dumpOut, -3)).'; the docx was rolled back.');
}

exec(escapeshellarg((string) PHP_BINARY).' '.escapeshellarg($root.'/storage/app/scratch/lane140-measure-wired-screens.php').' --check-matrix --check-docx 2>&1', $judge, $judgeCode);
$verdict = implode("\n", $judge);
if ($judgeCode !== 0 || ! str_contains($verdict, 'AGREES')) {
    $restore();
    fwrite(STDERR, $verdict."\n");
    $fail('lane140 does not agree with the document after the write, so the docx was rolled back to its own backup.');
}
if (! str_contains($verdict, 'DATED  dump line 40')) {
    $restore();
    $fail('the dated §1.1 row is no longer exempted, which means the exemption aged — the 2026-10-30 figure was true '
        .'on its own day and must stay as written.');
}
echo "stage 3 ok: lane140 reads the living claim and still carries the dated row as HISTORY\n";
printf("document.xml %d -> %d bytes\n", strlen($xml), strlen($after));
unlink($backup);
echo "PASS 401 WRITTEN\n";
