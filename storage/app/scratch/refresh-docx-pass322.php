<?php

/**
 * Pass 322: the comma-list citation into IDController, re-taken as ONE token.
 *
 * `lane76-shift-cites.php` resolved `IDController.php:170` but deferred its bare partner `:182`, because a bare
 * continuation is attributed by the nearest preceding name and the sentence's two numbers live in one run. Writing
 * only the head would have left `:203, :182` — a number that now looks valid, which is the defect AGENTS.md records
 * for a mapped token that is half a comma list. So the pair travelled as one text edit.
 *
 * Both endpoints were read in BOTH versions before the write, not shifted by arithmetic:
 *   HEAD 170 = `'bloodType' => 'required|in:A+,A-,B+,B-,AB+,AB-,O+,O-',`   -> live 203, same bytes
 *   HEAD 182 = `'bloodType' => $validated['bloodType'],`                    -> live 215, same bytes
 * and the naive needle `182</w:t>` was rejected on purpose: it belongs to `AuthServiceProvider.php:180-182`, a true
 * pointer in another file — lane 69's "a needle can be another citation's tail", met again.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass322.php [--apply]
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

$live = file('app/Http/Controllers/ID/IDController.php');
$head = file('storage/app/scratch/lane103-head-IDController.php');

foreach ([
    [203, "'bloodType' => 'required|in:A+,A-,B+,B-,AB+,AB-,O+,O-',"],
    [215, "'bloodType' => \$validated['bloodType'],"],
] as [$line, $claim]) {
    $bytes = trim((string) ($live[$line - 1] ?? ''));
    if ($bytes !== $claim) {
        fwrite(STDERR, "REFUSED: live line {$line} reads [{$bytes}], expected [{$claim}].\n");

        exit(1);
    }
}
foreach ([
    [170, "'bloodType' => 'required|in:A+,A-,B+,B-,AB+,AB-,O+,O-',"],
    [182, "'bloodType' => \$validated['bloodType'],"],
] as [$line, $claim]) {
    $bytes = trim((string) ($head[$line - 1] ?? ''));
    if ($bytes !== $claim) {
        fwrite(STDERR, "REFUSED: HEAD line {$line} reads [{$bytes}], expected [{$claim}] — the export is not the version the pointer was keyed to.\n");

        exit(1);
    }
}

$from = '(IDController.php:170, :182)';
$to = '(IDController.php:203, :215)';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ([[$from, 1], [$to, 0]] as [$needle, $want]) {
    $h = substr_count($xml, $needle);
    if ($h !== $want) {
        fwrite(STDERR, "REFUSED: [{$needle}] occurs {$h}, want {$want}.\n");

        exit(1);
    }
}

docxApply($docx, $root.'/storage/app/scratch/pass322-tmp-'.date('Ymd-His').'.docx', [[$from, $to, null, 1]], [], $apply);

if (! $apply) {
    echo "\nDRY RUN — pass 322 wrote nothing.\n";

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
// The neighbour the naive needle would have hit must be untouched.
if (substr_count($after, 'AuthServiceProvider.php:180-182') !== 1) {
    fwrite(STDERR, "REFUSED after write: AuthServiceProvider's own pointer moved.\n");

    exit(1);
}

echo "\npass 322 applied: the ID desk's comma-list citation reads 203 and 215.\n";
