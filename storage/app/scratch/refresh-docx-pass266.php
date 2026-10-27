<?php

/**
 * The invented pointer in the record row: §1.1 now cites §7.9, and the document has no §7.9.
 *
 * `lane37-section-reference-sweep.php` caught it — 925 references, one NO-SECTION — because the §-numbering
 * in the authority document stops at §7.4, and the §7.9 this batch built against is a section OF THE
 * DEVELOPMENT PROMPT, not of this document. That is the same defect class as a record row quoting a
 * `File.php:NN` shape and thereby inventing a citation: prose about a numbering system written in the
 * numbering system's own form reads as a pointer, and here it became one.
 *
 * Fixed by naming the source rather than renumbering the document: `prompt §7.9`, which is the convention the
 * document already uses for its own references into the prompt.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass266.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass266-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$dump = (string) file_get_contents($root.'/storage/app/scratch/docx-flat.txt');

// Only the record row's mention is being corrected; if any other sentence cites §7.9, this pass has the
// wrong idea of how many places need it.
$hits = preg_match_all('/§7\.9/', $dump);

if ($hits !== 1) {
    fwrite(STDERR, "REFUSED: the dump holds {$hits} mentions of §7.9, not the one this row wrote.\n");

    exit(1);
}

if (str_contains($dump, 'prompt §7.9')) {
    fwrite(STDERR, "REFUSED: the record row already names the prompt; this pass has nothing to repair.\n");

    exit(1);
}

printf("read §7.9 occurs %d time(s) in the document, inside pass 263's record row\n", $hits);

$zip = new ZipArchive;

if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the document for probing.\n");

    exit(1);
}

$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$found = substr_count($xml, '(§7.9)');

if ($found !== 1) {
    fwrite(STDERR, "REFUSED: the needle (§7.9) occurs {$found} time(s) in document.xml; the run split decides whether this is a text edit at all.\n");

    exit(1);
}

docxApply($docx, $backup, [['(§7.9)', '(prompt §7.9)', null, 1]], [], $apply);

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$again = new ZipArchive;
$again->open($docx);
$after = (string) $again->getFromName('word/document.xml');
$again->close();

foreach (['(§7.9)' => 0, '(prompt §7.9)' => 1] as $needle => $expected) {
    $seen = substr_count($after, $needle);

    if ($seen !== $expected) {
        fwrite(STDERR, "REFUSED (post-write): '{$needle}' occurs {$seen}, expected {$expected}.\n");

        exit(1);
    }

    printf("after %-20s %d\n", $needle, $seen);
}

echo "\npass 266 applied: the record row points at the prompt's §7.9 instead of a section this document does not have.\n";
