<?php

/**
 * Which slot means what: prove that `docxApply`'s third slot is the ANCHOR and its fourth is the expected count.
 *
 * Why this exists. refresh-docx-pass247.php wrote four entries as `[from, to, 2]` believing slot 3 meant "expect two
 * copies". It does not: slot 3 is the positional anchor and slot 4 is the count. Passing the integer 2 there hands the
 * resolver the character "2" to search for — and `word/document.xml` is full of that character, so it lands on one
 * occurrence and reports `1 target(s) resolved uniquely` as though that were the whole job. A silently half-applied
 * pass is the worst failure a document tool can have: the other copy keeps saying what the ruling replaced, and every
 * checker is satisfied because both sentences are live prose.
 *
 * Measured on the current document, each of those four sentences really is present twice and its old wording zero
 * times, so pass 247 did land in both registers — but this probe is what proves the engine now refuses the shape
 * instead of resolving one target in silence.
 *
 * The engine ends a failed pass by exiting the process, which skips the caller's `finally`, so cleanup here is a
 * shutdown function rather than a try/finally — that is the second thing this file records.
 *
 *   php storage/app/scratch/lane74-probe-engine-slot3.php --case=3   # the mis-slot, now refused
 *   php storage/app/scratch/lane74-probe-engine-slot3.php --case=4   # the correct slot, both targets
 */
$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$copy = $root.'/storage/app/scratch/lane74-engine-slot3-probe.docx';
$backup = $root.'/storage/app/scratch/lane74-engine-slot3-backup.docx';

$case = 0;
foreach ($argv as $a) {
    if (str_starts_with($a, '--case=')) {
        $case = (int) substr($a, strlen('--case='));
    }
}
if (! in_array($case, [3, 4], true)) {
    fwrite(STDERR, "REFUSED: --case= must be 3 (the mis-slot) or 4 (the documented count). Known flags: --case=.\n");
    exit(1);
}

// The engine exits on a failed pass and a `finally` will not run, so unlink on shutdown instead.
register_shutdown_function(static function () use ($copy, $backup): void {
    foreach ([$copy, $backup] as $f) {
        if (is_file($f)) {
            unlink($f);
        }
    }
});

if (! copy($src, $copy)) {
    fwrite(STDERR, "REFUSED: the copy could not be made.\n");
    exit(1);
}
require_once $root.'/storage/app/scratch/docx-edit-engine.php';

$zip = new ZipArchive;
$zip->open($copy);
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$needle = 'Ruled 2026-10-26: as printed';
$found = substr_count($xml, $needle);
printf("case %d: needle held %d time(s) in the copy\n", $case, $found);
if ($found < 2) {
    fwrite(STDERR, "REFUSED: this probe needs a sentence the document holds at least twice; it has moved.\n");
    exit(1);
}

// DRY RUN only — apply=false, so the document on disk is never opened for writing.
$edit = $case === 3 ? [$needle, $needle.' [A]', 2] : [$needle, $needle.' [B]', null, 2];
docxApply($copy, $backup, [$edit], [], false);
