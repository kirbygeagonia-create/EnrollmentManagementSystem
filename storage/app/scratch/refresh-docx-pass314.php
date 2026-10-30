<?php

/**
 * Pass 314: the clearance slip's PDF action, which lane 76 could not claim.
 *
 * §25's print-route row cites `ClearanceController.php:384 and :412` — the desk page and the sibling that serves
 * the PDF. The shift tool rewrote 32 tokens in that file and refused this one, because `:412` also stands four
 * times elsewhere in the document under `RegistrarController.php`, and a tool that guessed which one a batch meant
 * would move a citation belonging to another file. So the number is re-taken by reading: the working controller
 * holds `downloadSlip()` at line 435, and this pass refuses unless the line it names carries that signature.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass314.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$live = $root.'/app/Http/Controllers/Clearance/ClearanceController.php';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

require __DIR__.'/docx-edit-engine.php';

$lines = explode("\n", (string) file_get_contents($live));
$target = null;
foreach ($lines as $i => $text) {
    if (str_contains($text, 'public function downloadSlip(')) {
        if ($target !== null) {
            fwrite(STDERR, "REFUSED: downloadSlip() appears twice in the controller.\n");

            exit(1);
        }
        $target = $i + 1;
    }
}
if ($target === null) {
    fwrite(STDERR, "REFUSED: no downloadSlip() in the live controller.\n");

    exit(1);
}
printf("downloadSlip() is line %d: %s\n", $target, trim($lines[$target - 1]));

$from = 'and :412';
$to = 'and :'.$target;

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$hits = substr_count($xml, $from);
if ($hits !== 1) {
    fwrite(STDERR, "REFUSED: [{$from}] occurs {$hits} time(s), want 1.\n");

    exit(1);
}

printf("%s: the slip's PDF pointer re-taken\n", $apply ? 'APPLY' : 'DRY RUN');

docxApply($docx, $apply ? $root.'/storage/app/scratch/pass314-backup-'.date('Ymd-His').'.docx'
    : $root.'/storage/app/scratch/pass314-dry.docx', [[$from, $to]], [], $apply);

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

foreach ([[$from, 0], [$to, 1]] as [$needle, $want]) {
    if (($h = substr_count($after, $needle)) !== $want) {
        fwrite(STDERR, "REFUSED after the write: [{$needle}] occurs {$h}, want {$want}.\n");

        exit(1);
    }
}

echo "APPLIED.\n";
