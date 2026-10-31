<?php

/**
 * Pass 356 — the Examination results screen becomes the twelfth wired table (§28.3 G-40).
 *
 * Two jobs from the same edit.
 *
 * (a) `results()` grew 25 lines, and the one citation that sits below the insert is the §28.3 G-15 row's second
 * number — the bare `:560` this document acquired yesterday when `lane76-shift-cites.php` moved `:524` and pass 351
 * recorded it. It now belongs to `:583`, and the claim it carries ("and echoes both keys") is no longer one line:
 * the echo is a four-line block since the sort keys joined `filters`. So the pointer becomes a range, `:583-586`,
 * and both endpoints were read before writing — 583 is the `'filters' => $request->only([…])` line and 586 closes
 * the array. Asserted, not arithmetic: `:585` already occurs once elsewhere in the document, so a single-line
 * replacement would have collided with a citation belonging to another sentence.
 *
 * (b) The register row named eleven screens and 25 of 36 without an order; the results screen makes it twelve and
 * 24, and the sentence's own count of wired pages moves with it. Leaving either figure standing is the membership
 * defect this document has refused in lanes 46, 64, 69 and 90.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass356.php [--apply]
 */
$root = dirname(__DIR__, 3);
require_once __DIR__.'/docx-edit-engine.php';
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$L = file($root.'/app/Http/Controllers/Exam/ExamController.php');
foreach ([
    [583, "'filters' => \$request->only(['result', 'search']) + [", 'the echo opens on the filters line'],
    [586, '],', 'and the block closes two lines later'],
] as [$no, $has, $claim]) {
    $text = trim((string) ($L[$no - 1] ?? ''));
    if (! str_contains($text, $has)) {
        fwrite(STDERR, sprintf("REFUSED: ExamController.php line %d is [%s], which does not carry %s.\n", $no, substr($text, 0, 70), $claim));

        exit(1);
    }
}
echo "ok   both endpoints of the new range read true\n";

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$edits = [
    [':560', ':583-586'],
    ['Blocking roster — 25 of the 36', 'Blocking roster, and on the Examination results screen — 24 of the 36'],
    ['the eleven wired pages call', 'the twelve wired pages call'],
];

foreach ($edits as $i => $edit) {
    $got = substr_count($xml, $edit[0]);
    if ($got !== 1) {
        fwrite(STDERR, sprintf("REFUSED: needle %d occurs %d time(s), wanted 1 [%s]\n", $i, $got, substr($edit[0], 0, 50)));

        exit(1);
    }
    echo sprintf("ok   needle %d occurs once\n", $i);
}

docxApply($docx, $root.'/storage/app/scratch/pass356-backup.docx', $edits, [], $apply);

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot reopen the docx.\n");

    exit(1);
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ([[':560', 0], [':583-586', 1], ['Examination results screen — 24 of the 36', 1], ['Blocking roster — 25 of the 36', 0], ['the twelve wired pages call', 1], ['the eleven wired pages call', 0]] as [$needle, $want]) {
    $got = substr_count($after, $needle);
    if ($got !== $want) {
        echo sprintf("BAD  [%s] occurs %d, wanted %d — restoring.\n", substr($needle, 0, 34), $got, $want);
        copy($root.'/storage/app/scratch/pass356-backup.docx', $docx);

        exit(1);
    }
}
echo "written and read back: G-15's echo pointer now names the four-line block, and G-40 names twelve wired tables.\n";
