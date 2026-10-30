<?php

/**
 * Pass 298: two bare pointers re-taken by reading, after the G-40 block moved RegistrarController by 23 lines.
 *
 * The shift tool re-pointed 43 number tokens in that file and REFUSED three it could not attribute — a bare `:412`
 * twice and a bare `:157` once whose nearest name on the dump row is a foreign file. The two `:412` turned out to be
 * ClearanceController's own (the slip's print page and its download), so leaving them was right. The `:157` is not:
 * the sentence it sits in claims "the seven-gate checklist shows registrarApprovalPending false", and the key is
 * written on live line 210 of RegistrarController while the checklist array opens at 205 — so both halves of that
 * citation are stale, and the window beside it (`:185-196`, which the shift faithfully moved from `:162-173`) lands
 * inside the method's payment lines rather than on the array.
 *
 * The same number belongs to a second file in a different sentence: "at compute time :157 sets coverage to zero on
 * purpose" is AssessmentController's, and that file gained a line above it in the same batch, so the assignment is
 * now at 158 while 157 is the comment that explains it. Both go through run-anchored needles, never a global replace:
 * `:157` occurs twice in document.xml and only one copy is Registrar's.
 *
 * Two further copies of `RegistrarController.php:185-196` exist in citation cells whose sentences do not name the
 * checklist ("RegistrarPolicy.php:36-91 , RegistrarController.php:185-196" and "The enrollments columns; …"). One
 * range cannot carry two claims, so they are left standing and printed as a reading queue rather than re-pointed to
 * a number measured for a different sentence.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass298.php [--apply]
 */

$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', array_slice($argv, 1), true);

$registrar = $root.'/app/Http/Controllers/Registrar/RegistrarController.php';
$assessment = $root.'/app/Http/Controllers/Assessment/AssessmentController.php';

$line = function (string $file, int $n): string {
    $lines = explode("\n", str_replace("\r\n", "\n", (string) file_get_contents($file)));

    return $lines[$n - 1] ?? '(past the end of the file)';
};

/**
 * @var array<int, array{0: string, 1: string}>
 */
$assertions = [
    [$registrar, 205, 'return ['],
    [$registrar, 210, 'registrarApprovalPending'],
    [$assessment, 158, '$totalScholarshipCoverage = 0'],
    [$assessment, 157, '//'],
];

$failed = [];
foreach ($assertions as [$f, $n, $needle]) {
    $text = $line($f, $n);
    if (! str_contains($text, $needle)) {
        $failed[] = basename($f).":{$n} does not contain '{$needle}' — it reads: ".trim($text);
    }
}
if ($failed !== []) {
    fwrite(STDERR, "REFUSED: the lines the new numbers name do not carry the claims.\n  ".implode("\n  ", $failed)."\n");

    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");

    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

// The pair is split across runs, so no contiguous needle spans it (the probe above is kept in this header as the
// reason). Take each token by run instead, both anchored on the one prose phrase that precedes them: the engine
// resolves the first occurrence of the needle AFTER a unique anchor, which is how a `:157` that occurs twice in the
// document is moved in only one of its two sentences.
$anchor = 'the next pending box is Accounting, not Registrar';
$nAnchor = substr_count($xml, $anchor);
printf("anchor %s — occurs %d time(s) (want 1)\n", var_export($anchor, true), $nAnchor);
if ($nAnchor !== 1) {
    fwrite(STDERR, "REFUSED: the anchor phrase is not unique, so neither token could be attributed to this sentence.\n");

    exit(1);
}
foreach (['185-196' => 3, ':157' => 2] as $tok => $want) {
    $n = substr_count($xml, $tok);
    printf("token %s — occurs %d time(s) (this file expects %d, of which exactly one sits after the anchor)\n", $tok, $n, $want);
    if ($n !== $want) {
        fwrite(STDERR, "REFUSED: the token census moved, so the run-split assumption no longer holds.\n");

        exit(1);
    }
}

$assessFrom = 'at compute time :157';
$nAssess = substr_count($xml, $assessFrom);
printf("needle %s — occurs %d time(s) (want 1)\n", var_export($assessFrom, true), $nAssess);
if ($nAssess !== 1) {
    fwrite(STDERR, "REFUSED: the Assessment needle is not unique.\n");

    exit(1);
}

$edits = [
    ['185-196', '205-210', $anchor, 1],
    [':157', ':210', $anchor, 1],
    [$assessFrom, 'at compute time :158', null, 1],
];

docxApply($docx, $root.'/storage/app/scratch/pass298-backup-'.date('Ymd-His').'.docx', $edits, [], $apply);

printf("\nTwo copies of RegistrarController.php:185-196 were deliberately NOT moved; their sentences name the\n"
    ."policy range and the enrollments columns, so this pass' number is measured for the checklist claim only.\n");
