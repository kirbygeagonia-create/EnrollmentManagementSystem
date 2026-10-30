<?php

/**
 * Pass 314b: the OTHER bare `:412` for the same action, which the first pass did not reach.
 *
 * §25.1's print-route table states the clearance slip twice: once as a row cell (`downloadSlip() serves the PDF
 * ( :412 )`) and once as a continuation of the pair `ClearanceController.php:384 and :412`. Pass 314 re-took the
 * second; the citation sweep then flagged the first, because a needle spelled `and :NNN` cannot reach one spelled
 * `( :NNN )` — and the sweep only refuses a line that is blank or past the end, so this one surfaced as an EMPTY
 * LINE rather than as two passes' worth of missed work.
 *
 * Same assertion as its sibling: line 435 must be `downloadSlip()`'s signature.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass314b.php [--apply]
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
        $target = $target === null ? $i + 1 : 'twice';
    }
}
if (! is_int($target)) {
    fwrite(STDERR, "REFUSED: downloadSlip() is not found exactly once in the live controller.\n");

    exit(1);
}
printf("downloadSlip() is line %d\n", $target);

$from = 'serves the PDF ( :412 )';
$to = 'serves the PDF ( :'.$target.' )';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

if (($hits = substr_count($xml, $from)) !== 1) {
    fwrite(STDERR, "REFUSED: [{$from}] occurs {$hits} time(s), want 1.\n");

    exit(1);
}

printf("%s: the slip's PDF cell re-taken\n", $apply ? 'APPLY' : 'DRY RUN');

docxApply($docx, $apply ? $root.'/storage/app/scratch/pass314b-backup-'.date('Ymd-His').'.docx'
    : $root.'/storage/app/scratch/pass314b-dry.docx', [[$from, $to]], [], $apply);

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

foreach ([[$from, 0], [$to, 1], ['( :412 )', 0]] as [$needle, $want]) {
    if (($h = substr_count($after, $needle)) !== $want) {
        fwrite(STDERR, "REFUSED after the write: [{$needle}] occurs {$h}, want {$want}.\n");

        exit(1);
    }
}

echo "APPLIED: both copies of the clearance PDF pointer now name line {$target}.\n";
