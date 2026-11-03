<?php

/**
 * Pass 435 — one instrument figure this batch moved without being asked to.
 *
 * The timetable cell grew two lines of JSX, and `lane49-zero-shown-as-absence.php` counts lines whose tested
 * operand is a name, code, date or error message. It printed 384 when both sentences were written and prints 386
 * today; the sentence in the authority document and the one in AGENTS.md are the same claim read from the same
 * footer, so both move together or the document contradicts itself across two surfaces.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass435.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$apply = in_array('--apply', array_slice($GLOBALS['argv'], 1), true);
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

$docx = $root.'/Documentation/EMS Complete Documentation.docx';

// The figure is read from the instrument's own footer, never typed from memory.
$out = (string) shell_exec('"'.PHP_BINARY.'" storage/app/scratch/lane49-zero-shown-as-absence.php 2>&1');
if (preg_match('/^Not a money\/percent figure the guard itself prints: (\d+)$/m', $out, $m) !== 1) {
    $fail('lane49 printed no "Not a money/percent figure" line this run, so there is no measurement to write.');
}
$today = (int) $m[1];

$needle = '384 lines where the tested operand is a name, code, date or error message';
$to = $today.' lines where the tested operand is a name, code, date or error message';
if ($needle === $to) {
    $fail('the instrument still answers 384, so this pass has nothing to correct — re-read before writing.');
}

$readPart = static function (string $docx, string $part) use ($fail): string {
    $zip = new ZipArchive;
    if ($zip->open($docx) !== true) {
        $fail("could not open {$part} inside the docx.");
    }
    $body = $zip->getFromName($part);
    $zip->close();
    if (! is_string($body) || $body === '') {
        $fail("{$part} read empty.");
    }

    return $body;
};

$xml = $readPart($docx, 'word/document.xml');
if (substr_count($xml, $needle) !== 1) {
    $fail('the sentence carries its needle '.substr_count($xml, $needle).' times, expected exactly 1.');
}
if (substr_count($xml, $to) !== 0) {
    $fail('the new figure already occurs in the document — read where it came from before writing it again.');
}

printf("lane49 answers %d today; the document says 384. %s\n", $today, $apply ? 'applying' : 'DRY RUN');
if (! $apply) {
    echo "DRY RUN — nothing written.\n";
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass435-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    $fail('the backup copy could not be written.');
}
docxApply($docx, $backup, [[$needle, $to]], [], true);

$read = $readPart($docx, 'word/document.xml');
if (substr_count($read, $needle) !== 0 || substr_count($read, $to) !== 1) {
    unlink($backup);
    copy($backup, $docx);
    $fail('the figure did not read back as one corrected sentence — the backup was restored.');
}
printf("WROTE one §-figure correction to %d; backup at %s\n", $today, basename($backup));
