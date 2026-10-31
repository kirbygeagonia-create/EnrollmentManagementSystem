<?php

/**
 * Docx pass 338 — the last Accounting pointer the +4 shift left behind: `AccountingController.php:298`.
 *
 * Pass 337 fixed the two word-anchored copies (`box at :298`, `signature at :345`); this one is the file-prefixed
 * citation, whose needle `php:298` is unique in `document.xml` (asserted below, as is the endpoint identity:
 * HEAD line 298 and working line 302 are the same `signStepByOffice(... OfficeId::Accounting ...)` statement,
 * byte for byte). `:302` occurs elsewhere in the document for other files, which is why the read-back checks the
 * disappearance of the ACCOUNTING-prefixed token rather than the bare number — the pass-229 rule that an
 * after-check must be scoped to the replaced pair.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass338.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = $root.'/storage/app/scratch/pass338-backup.docx';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}
require __DIR__.'/docx-edit-engine.php';

$head = (string) file_get_contents($root.'/storage/app/scratch/lane110-head-AccountingController.php');
$work = (string) file_get_contents($root.'/app/Http/Controllers/Accounting/AccountingController.php');
$line = static fn (string $s, int $n): string => trim(explode("\n", $s)[$n - 1] ?? '(absent)');
$a = $line($head, 298);
$b = $line($work, 302);
if ($a === '' || $a !== $b) {
    fwrite(STDERR, "REFUSED: HEAD 298 and WORK 302 differ:\n  HEAD: {$a}\n  WORK: {$b}\n");

    exit(1);
}
echo "verified :298 -> :302 (both lines: ".substr($a, 0, 46)."…)\n";

$zip = new ZipArchive;
$zip->open($docx);
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$textEdits = [['AccountingController.php:298', 'AccountingController.php:302']];
$n = substr_count($xml, $textEdits[0][0]);
printf('needle %s occurs %d time(s)%s', $textEdits[0][0], $n, PHP_EOL);
if ($n !== 1) {
    if (substr_count($xml, $textEdits[0][1]) === 1 && $n === 0) {
        echo "ALREADY APPLIED.\n";

        exit(0);
    }
    fwrite(STDERR, "REFUSED — nothing written.\n");

    exit(1);
}

docxApply($docx, $backup, $textEdits, [], $apply);
if (! $apply) {
    echo "DRY RUN — nothing written.\n";

    exit(0);
}
$zip = new ZipArchive;
$zip->open($docx);
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();
$g = substr_count($after, 'AccountingController.php:302');
$l = substr_count($after, 'AccountingController.php:298');
printf('read-back: AccountingController.php:302 now %d, :298 now %d%s', $g, $l, PHP_EOL);
if ($l !== 0) {
    fwrite(STDERR, "REFUSED: the stale token survives; restoring.\n");
    if (is_file($backup)) {
        copy($backup, $docx);
    }

    exit(1);
}
echo "written, re-read: no AccountingController pointer still names the pre-shift window.\n";
