<?php

/**
 * Docx pass 346 — the day's own §1.1 row still introduced G-52 as an OPEN item after its register row was closed.
 *
 * Found by reading every mention of the id after pass 344 wrote the verdict, not by a checker: `lane61-open-claims.php`
 * indexes a register row's leading verb and then judges sentences that ASK a question, and this sentence does not ask
 * — it presents. That is the mirror of the defect pass 181 and 176b had to fix ("a sentence presenting an answered id
 * as settled" and its opposite), and it lands in the one row a reader takes to the window as today's summary.
 *
 * Not a re-aging: the row is dated 2026-10-31 and the finding opened, was narrowed and was closed on that day, so the
 * sentence now tells the whole arc in one pass instead of stopping at the first third of it.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass346.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = $root.'/storage/app/scratch/pass346-backup.docx';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}
require __DIR__.'/docx-edit-engine.php';

$open = ['that is G-52, registered open, narrowed first on the three queues the live read named',
    'that is G-52, opened by that read and closed the same day: narrowed first on the three queues the live read named,',
    null, 1];

$debt = ['and then on all thirteen desk lists the census counted, with its row carrying the remaining sites as a '
    .'measured debt.',
    'then on every desk list the census counted, and finally on the two sites its byte splice could not reach — a '
        .'record page and a print page that arrive at the student through a relation, where the column list had to '
        .'travel inside a closure that re-loads that relation. The census now answers no whole-student load except the '
        .'two screens that edit the person record.', null, 1];

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    exit("REFUSED: the docx did not open as a zip.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$textEdits = [$open, $debt];
$bad = 0;
foreach ($textEdits as $i => [$from, $to, , $want]) {
    $n = substr_count($xml, $from);
    printf('edit %d: anchor occurs %d time(s), want %d — %s%s', $i, $n, $want, substr($from, 0, 44), PHP_EOL);
    if ($n !== $want) {
        $bad++;
    }
    if (str_contains($to, '<') || str_contains($to, '>') || preg_match('/\.php:\d/u', $to) === 1) {
        $bad++;
        echo "  edit {$i} carries a tag character or an unverified citation\n";
    }
}
if ($bad > 0) {
    $applied = true;
    foreach ($textEdits as [, $to]) {
        if (substr_count($xml, $to) !== 1) {
            $applied = false;
        }
    }
    echo $applied ? "ALREADY APPLIED.\n" : "REFUSED — nothing written.\n";

    exit($applied ? 0 : 1);
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
    $newN = substr_count($after, $to);
    $oldN = substr_count($after, $from);
    printf('  edit %d: new form %d (want 1), retired form %d (want 0)%s', $i, $newN, $oldN, PHP_EOL);
    if ($newN !== 1 || $oldN !== 0) {
        $fail++;
    }
}
// The word that made the sentence a second answer about the finding's state must be gone from that clause.
printf('  “registered open” now occurs %d time(s) document-wide (want 0)%s', substr_count($after, 'registered open'), PHP_EOL);
if (substr_count($after, 'registered open') !== 0) {
    $fail++;
}
if ($fail > 0) {
    fwrite(STDERR, "REFUSED: read-back failed; restoring the pass's own backup.\n");
    if (is_file($backup)) {
        copy($backup, $docx);
    }

    exit(1);
}
echo "written, re-read: the day's row tells the whole arc of G-52 instead of stopping at its opening.\n";
