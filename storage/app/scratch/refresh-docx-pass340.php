<?php

/**
 * Docx pass 340 — task #41 moved the code, so the register follows in the same batch (rule 2).
 *
 * Two sentences are now understatements rather than falsehoods:
 *   §28.3 G-46 says the Clinic and ID lists "are one call rather than two copies". Three more lists joined that call
 *     today — the assessment queue, the registrar queue and the cashier's queue base — and the row does not say so,
 *     while two cards deliberately remain NARROWER than their pages because a badge names a subset the desk's list
 *     does not. A reader of that row would still conclude four desks copy the predicate.
 *   §1.1's 2026-10-31 row records the day's first half only.
 *
 * Both are appended to, never re-aged: the G-46 row leads `Closed 2026-10-30` and its own sentences are that day's
 * record, so each new clause carries its date and states what changed after it (lane 60's and lane 72's rule).
 * Each anchor is a sentence tail measured to occur exactly once before any write; the needles are short and
 * run-local on purpose, because the flat dump joins runs with a space and a phrase copied out of it is not always
 * contiguous in `document.xml`.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass340.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = $root.'/storage/app/scratch/pass340-backup.docx';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}
require __DIR__.'/docx-edit-engine.php';

$g46 = 'two pairs are one call rather than two copies.';
$g46To = $g46.' Read again on 2026-10-31, because three more lists joined that call: the assessment queue, the '
    .'registrar queue and the cashier’s queue base each take their rows from the same class their card counts, and '
    .'`DashboardQueueCountMatchesDeskTest` now reads the body of each action rather than the whole file, since a '
    .'file-wide check cannot tell a second legitimate list from a duplicate and trips over a comment that only names '
    .'the method. Two cards stay deliberately narrower than the page beneath them — the cashier’s counts the owing '
    .'sheets and the clearance card the pending rows — so those two are not to be unified: a badge names a queue, '
    .'the list names what a desk may still act on, and hiding rows to make two numbers agree is the §8 contradiction '
    .'with the sign flipped.';
$row42 = 'to be on disk before it measures anything.';
$row42To = $row42.' The same day’s second half also made three desk lists ask `App\Support\DeskQueues` for their '
    .'rows instead of restating the predicate the dashboard card already owns, and recorded why the other two are '
    .'left alone.';

$zip = new ZipArchive;
$zip->open($docx);
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$textEdits = [
    [$g46, $g46To, null, 1],
    [$row42, $row42To, null, 1],
];
$bad = 0;
foreach ($textEdits as $i => [$from, $to, , $want]) {
    $n = substr_count($xml, $from);
    printf('edit %d: anchor occurs %d time(s), want %d — %s%s', $i, $n, $want, substr($from, 0, 44), PHP_EOL);
    if ($n !== $want) {
        $bad++;
    }
    if (preg_match('/\.php:\d/u', $to) === 1) {
        $bad++;
        echo "  edit {$i}'s replacement introduces an unverified line citation\n";
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
$zip->open($docx);
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();
$fail = 0;
foreach ($textEdits as $i => [$from, $to]) {
    printf('  edit %d: new form %d (want 1)%s', $i, substr_count($after, $to), PHP_EOL);
    if (substr_count($after, $to) !== 1) {
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
echo "written, re-read: the G-46 row and the day's record now say which lists are one call and which stay a subset.\n";
