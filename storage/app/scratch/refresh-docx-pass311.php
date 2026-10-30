<?php

/**
 * Pass 311: §28.3's G-40 row follows the third wired screen.
 *
 * The register said two queues sort and 34 of the 36 shared-table pages offer no order. The student directory is
 * the third, so both halves of that sentence moved — and the row also gains the one promise the directory taught:
 * a list's default direction is its own decision, because the helper's answer when nothing is sent is descending.
 *
 * Both needles were probed unique in `word/document.xml` before writing; a number that occurs twice document-wide
 * would be a different citation someone else owns.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass311.php [--apply]
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

$edits = [
    [
        'It is wired on the Registrar approval queue and on the Admission queue',
        'It is wired on the Registrar approval queue, on the Admission queue and on the student directory',
    ],
    [
        '34 of the 36 pages that use the shared table still offer no order',
        '33 of the 36 pages that use the shared table still offer no order. The screen half of the control has one'
        .' definition now: the three wired pages call resources/js/Components/ui/listSort.js rather than each'
        .' spelling the toggle, and a directory asks for its own ascending default, because the answer the'
        .' helper gives when a request carries no direction is descending — which suits a queue of newest rows and is'
        .' the last thing a list of surnames wants',
    ],
];

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ($edits as $i => [$from, $to]) {
    $hits = substr_count($xml, $from);
    if ($hits !== 1) {
        fwrite(STDERR, "REFUSED: edit {$i} needle occurs {$hits} time(s), expected 1: [{$from}]\n");

        exit(1);
    }
    if (str_contains($xml, $to)) {
        fwrite(STDERR, "REFUSED: edit {$i} has already been applied — [{$to}] is in the document.\n");

        exit(1);
    }
}

printf("%s: two sentences in §28.3's G-40 row\n", $apply ? 'APPLY' : 'DRY RUN');

docxApply($docx, $apply ? $root.'/storage/app/scratch/pass311-backup-'.date('Ymd-His').'.docx'
    : $root.'/storage/app/scratch/pass311-dry.docx', $edits, [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$again = new ZipArchive;
if ($again->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot re-open the written archive.\n");

    exit(1);
}
$after = (string) $again->getFromName('word/document.xml');
$again->close();

// The §1.1 row titled "2026-10-29 One sort mechanism, one wired queue" records what the register said on the day it
// was written, and a dated row is never re-aged to a newer figure (lane 60's rule, met from the other side in pass
// 244). So the old tally surviving THERE is correct; what must not survive is the living sentence in §28.3.
foreach ([['34 of the 36 pages', 1, 'the dated §1.1 reading stays and the register reading moved'],
    ['33 of the 36 pages', 1, 'the new tally must sit once'],
    ['on the student directory', 1, 'the third screen must be named'], ['listSort.js', 1, 'the shared module must be named']] as [$needle, $want, $why]) {
    if (($h = substr_count($after, $needle)) !== $want) {
        fwrite(STDERR, "REFUSED ({$why}): [{$needle}] occurs {$h}, want {$want}.\n");

        exit(1);
    }
}

echo "APPLIED: the register row states today's wiring and the directory's own default.\n";
