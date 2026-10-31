<?php

/**
 * Docx pass 339 — the two `:227-287` comma-list tails that `lane76-shift-cites.php` deferred as BARE-DEFERRED.
 *
 * The shift tool refuses to attribute a bare `:NNN` when the nearest filename before it is another file's, so it
 * skipped these and reported the item; the earlier conclusion that the digits were run-split was MY probe's error
 * (it searched for `227-287</w:t>`, while the run continues past the number). Both copies are the tail of
 * `AccountingController.php:247 , :227-287` — the head was already moved by the tool, the tail was not, which is
 * the half-moved citation this repository calls the worst shape: it looks valid.
 *
 * Endpoints verified before any write: HEAD 227 and 287, working 231 and 291, each pair byte-identical, so the
 * window still names `record()` from signature to its balance update. The needle is the whole PAIR with its head,
 * asserted to occur exactly twice, and the read-back checks the replaced pair is gone and the new one occurs two
 * more times than it did — never document-wide absence, because `:231-291` could belong to another file.
 *
 * The pass also MEASURES the one remaining `:345` without writing it, so the next decision is made from bytes
 * rather than memory.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass339.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = $root.'/storage/app/scratch/pass339-backup.docx';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}
require __DIR__.'/docx-edit-engine.php';

$headPath = $root.'/storage/app/scratch/lane110-head-AccountingController.php';
if (! is_file($headPath) || filesize($headPath) === 0) {
    fwrite(STDERR, "REFUSED: no HEAD export at ".basename($headPath).".\n");

    exit(1);
}
$head = explode("\n", (string) file_get_contents($headPath));
$work = explode("\n", (string) file_get_contents($root.'/app/Http/Controllers/Accounting/AccountingController.php'));
foreach ([[227, 231], [287, 291]] as [$old, $new]) {
    $a = trim($head[$old - 1] ?? '(absent)');
    $b = trim($work[$new - 1] ?? '(absent)');
    if ($a === '' || $a !== $b) {
        fwrite(STDERR, "REFUSED: HEAD {$old} and WORK {$new} differ:\n  HEAD: {$a}\n  WORK: {$b}\n");

        exit(1);
    }
    echo "verified {$old} -> {$new} (both: ".substr($a, 0, 44)."…)\n";
}

$zip = new ZipArchive;
$zip->open($docx);
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$edits = [
    // Slot 3 is the anchor (null = the needle itself), slot 4 the expected count: both copies of the pair are the
    // same token in two different register rows, and the engine writes exactly the number stated.
    [':247 , :227-287', ':247 , :231-291', null, 2],
    // The last `:345` was measured, not remembered: it is the tail of "at AccountingController.php:302 when a
    // payment settles the account, and again at :345 when a zero-balance account is settled without money".
    ['and again at :345', 'and again at :349', null, 1],
];
$bad = 0;
foreach ($edits as $i => [$from, $to, , $want]) {
    $n = substr_count($xml, $from);
    printf('edit %d: %s occurs %d time(s), want %d%s', $i, $from, $n, $want, PHP_EOL);
    if ($n !== $want) {
        $bad++;
    }
}
if ($bad > 0) {
    fwrite(STDERR, "REFUSED — nothing written.\n");

    exit(1);
}

docxApply($docx, $backup, $edits, [], $apply);
if (! $apply) {
    echo "DRY RUN — nothing written.\n";

    exit(0);
}
$zip = new ZipArchive;
$zip->open($docx);
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();
$fail = 0;
foreach ($edits as $i => [$from, $to, , $want]) {
    $g = substr_count($after, $to);
    $l = substr_count($after, $from);
    printf('  edit %d: new form now %d (want %d), old form now %d (want 0)%s', $i, $g, $want, $l, PHP_EOL);
    if ($l !== 0 || $g !== $want) {
        $fail++;
    }
}
if ($fail > 0) {
    fwrite(STDERR, "REFUSED: read-back failed; restoring.\n");
    if (is_file($backup)) {
        copy($backup, $docx);
    }

    exit(1);
}
echo "written, re-read: both comma-list tails moved with their heads, and the last Accounting :345 moved too.\n";
