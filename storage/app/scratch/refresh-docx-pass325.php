<?php

/**
 * Pass 325: the ID desk's comma-list citation, re-taken again after the ENUM ruling aged it.
 *
 * Same shape as pass 322 and the same refusal of the naive needle: `lane76-shift-cites.php` resolved the head
 * (203 -> 207) and deferred the bare tail as BARE-DEFERRED, so writing the head alone would have left
 * `:207, :215` — a number that looks valid. The pair travels as one text edit.
 *
 * Both endpoints asserted byte-for-byte in HEAD (b64687d's file, exported by the shift tool's own run) and in the
 * working file before anything is written.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass325.php [--apply]
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

$live = (array) file($root.'/app/Http/Controllers/ID/IDController.php');
$head = (array) file($root.'/storage/app/scratch/lane104-head-IDController.php');

foreach ([[207, "'bloodType' => 'required|in:A+,A-,B+,B-,AB+,AB-,O+,O-',"], [219, "'bloodType' => \$validated['bloodType'],"]] as [$line, $claim]) {
    $bytes = trim((string) ($live[$line - 1] ?? ''));
    if ($bytes !== $claim) {
        fwrite(STDERR, "REFUSED: live line {$line} reads [{$bytes}], expected [{$claim}].\n");

        exit(1);
    }
}
foreach ([[203, "'bloodType' => 'required|in:A+,A-,B+,B-,AB+,AB-,O+,O-',"], [215, "'bloodType' => \$validated['bloodType'],"]] as [$line, $claim]) {
    $bytes = trim((string) ($head[$line - 1] ?? ''));
    if ($bytes !== $claim) {
        fwrite(STDERR, "REFUSED: HEAD line {$line} reads [{$bytes}], expected [{$claim}] — the export is not the version the pointer was keyed to.\n");

        exit(1);
    }
}

$from = '(IDController.php:203, :215)';
$to = '(IDController.php:207, :219)';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ([[$from, 1], [$to, 0]] as [$needle, $want]) {
    if (substr_count($xml, $needle) !== $want) {
        fwrite(STDERR, "REFUSED: [{$needle}] occurs ".substr_count($xml, $needle).", want {$want}.\n");

        exit(1);
    }
}

docxApply($docx, $root.'/storage/app/scratch/pass325-tmp-'.date('Ymd-His').'.docx', [[$from, $to, null, 1]], [], $apply);

if (! $apply) {
    echo "\nDRY RUN — pass 325 wrote nothing.\n";

    exit(0);
}

$again = new ZipArchive;
$again->open($docx);
$after = (string) $again->getFromName('word/document.xml');
$again->close();

if (substr_count($after, $to) !== 1 || substr_count($after, $from) !== 0) {
    fwrite(STDERR, "REFUSED after write: the pair did not land as one token.\n");

    exit(1);
}
if (substr_count($after, 'AuthServiceProvider.php:180-182') !== 1) {
    fwrite(STDERR, "REFUSED after write: AuthServiceProvider's own pointer moved.\n");

    exit(1);
}

echo "\npass 325 applied: the ID comma-list citation reads 207 and 219.\n";
