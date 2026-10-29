<?php

/**
 * Pass 285: §28.3's G-39 row said the tone map was still re-declared. It is not.
 *
 * The certificate screen now reads the shared enrollment-tone map through the barrel and its local six-key
 * helper is deleted rather than left beside it as a second answer. Pinned by EnrollmentToneVocabularyTest,
 * which reads the sources because there is no JavaScript test suite — the same method lane 69 used for the
 * year-level pairing.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass285.php [--apply]
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

$from = 'Open 2026-10-29 (phase 5). Measured by reading rather than recalled: only ONE surface re-declares the map, not all of them — the claim this sweep was handed said more, and the files disproved it. The fix is to read the desk’s own tone function rather than keep a second answer to one word.';
$to = 'Closed 2026-10-29. The certificate screen reads the shared `enrollmentStatusTone` map through the barrel and its local six-key helper is deleted, not left beside the shared one as a second answer; a returned record now paints as a hold on paper the way it does on every desk. Pinned by `EnrollmentToneVocabularyTest`, which reads the sources because the repository has no JavaScript test suite: one case fails if ANY page re-declares the enrollment vocabulary, one that the certificate page reads the shared map, one that the shared map carries both return states. The claim this sweep was handed said every print page did this; the scan that found the one also cleared the others, which is what makes the first case worth keeping.';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

if (substr_count($xml, $from) !== 1) {
    fwrite(STDERR, 'REFUSED: the G-39 cell matches '.substr_count($xml, $from)
        ." places; pass 281 wrote it as one run, so a mismatch means the row moved.\n");

    exit(1);
}

$backup = $root.'/storage/app/scratch/pass285-backup-'.date('Ymd-His').'.docx';
if ($apply && ! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: no backup at {$backup}.\n");

    exit(1);
}

printf("%s: G-39's row takes its closure\n", $apply ? 'APPLY' : 'DRY RUN');
docxApply($docx, $apply ? $backup : $root.'/storage/app/scratch/pass285-dry.docx', [[$from, $to]], [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();

if (substr_count($read, $from) !== 0 || substr_count($read, $to) !== 1) {
    fwrite(STDERR, "REFUSED READ-BACK: old ".substr_count($read, $from).', new '.substr_count($read, $to).".\n");

    exit(1);
}

echo "\npass 285 applied: the register says what the build now does.\n";
