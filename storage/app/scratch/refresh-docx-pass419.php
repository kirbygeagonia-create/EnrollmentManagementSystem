<?php

/**
 * Pass 419 — `lane49`'s blind-spot figure moved again, in the same session, because the office page added lines of
 * the shape that tool counts.
 *
 * This is the class of defect the gate exists for and the reason a figure must be read from the tool at write time:
 * pass 417 re-took 363 to 366 this morning, and the twenty-second table's JSX made 366 wrong within the hour. The
 * value here is therefore taken from `lane49-zero-shown-as-absence.php`'s own print at run time and never carried
 * in by hand, and both surfaces (AGENTS.md and §28's row) are re-taken in one pass so one document cannot disagree
 * with the other. The pass re-runs `lane59` and `lane58` as its read-back; a write that leaves either refusing is
 * rolled back from the pass's own backup.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass419.php [--apply]
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

$out = [];
$code = 0;
exec('"'.PHP_BINARY.'" '.escapeshellarg($root.'/storage/app/scratch/lane49-zero-shown-as-absence.php').' 2>&1', $out, $code);
$body = preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $out));
if ($code !== 0) {
    $fail("lane49 exited {$code}, so its blind-spot count is not a reading:\n".mb_substr($body, -300));
}
if (preg_match('/Not a money\/percent figure the guard itself prints:\s*(\d+)/', $body, $m) !== 1) {
    $fail('lane49 printed no "Not a money/percent figure" tally this run — the reader is blind, not the tool silent.');
}
$linesNow = (int) $m[1];
printf("read from lane49: %d lines whose tested operand is not a money or percent figure\n", $linesNow);

$md = 'AGENTS.md';
$mdText = (string) file_get_contents($root.'/'.$md);
$mdFrom = '366 lines where';
$mdTo = $linesNow.' lines where';
if (substr_count($mdText, $mdFrom) !== 1) {
    $fail('the AGENTS needle occurs '.substr_count($mdText, $mdFrom).' time(s), wanted exactly 1.');
}
if ($mdFrom === $mdTo) {
    $fail('AGENTS already carries the reading — this pass has nothing to do.');
}

$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    $fail('the docx did not open (ZipArchive answers a truthy error code for a file it cannot read).');
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
if ($xml === '') {
    $fail('word/document.xml read empty.');
}
$dxFrom = '366 lines where the tested operand';
$dxTo = $linesNow.' lines where the tested operand';
if (substr_count($xml, $dxFrom) !== 1) {
    $fail('the docx phrase occurs '.substr_count($xml, $dxFrom).' time(s) in word/document.xml, wanted exactly 1 — '.
        'if it is split across runs this pass must go through the cell kind instead.');
}

if (! $apply) {
    printf("DRY RUN: AGENTS [%s] -> [%s]; docx [%s] -> [%s]\n", $mdFrom, $mdTo, $dxFrom, $dxTo);
    docxApply($docx, $root.'/storage/app/scratch/pass419-dryrun.docx', [[$dxFrom, $dxTo]], [], false);
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass419-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    $fail('the docx backup could not be written.');
}
$mdBackup = $root.'/storage/app/scratch/pass419-AGENTS-backup-'.date('Ymd-His').'.md';
if (! copy($root.'/'.$md, $mdBackup)) {
    $fail('the AGENTS backup could not be written.');
}

docxApply($docx, $root.'/storage/app/scratch/pass419-engine-backup.docx', [[$dxFrom, $dxTo]], [], true);
$w = @file_put_contents($root.'/'.$md, str_replace($mdFrom, $mdTo, $mdText));
if ($w !== strlen(str_replace($mdFrom, $mdTo, $mdText))) {
    copy($mdBackup, $root.'/'.$md);
    $fail('the AGENTS write returned '.var_export($w, true).' — restored.');
}

// Read back both surfaces, then let the two weighers decide whether the batch is true.
$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    $fail('the written docx did not reopen.');
}
$read = (string) $zip->getFromName('word/document.xml');
$zip->close();
$checks = [
    'docx phrase re-taken' => substr_count($read, $dxFrom) === 0 && substr_count($read, $dxTo) === 1,
    'AGENTS phrase re-taken' => substr_count((string) file_get_contents($root.'/'.$md), $mdFrom) === 0
        && substr_count((string) file_get_contents($root.'/'.$md), $mdTo) === 1,
];
$prev = libxml_use_internal_errors(true);
$doc = new DOMDocument;
$checks['document.xml parses'] = (bool) $doc->loadXML($read);
libxml_use_internal_errors($prev);
$failed = array_keys(array_filter($checks, static fn (bool $v): bool => ! $v));
if ($failed !== []) {
    copy($backup, $docx);
    copy($mdBackup, $root.'/'.$md);
    $fail('read-back refused ['.implode('; ', $failed).'] — both files restored.');
}

exec('"'.PHP_BINARY.'" '.escapeshellarg($root.'/storage/app/scratch/dump-docx-flat.php').' 2>&1');
foreach ([
    'lane59-instrument-counts.php' => ' --readings=storage/app/scratch/lane59-readings.json',
    'lane58-suite-claims.php' => ' --junit=storage/app/scratch/lane205-suite-junit.xml --local-suite=storage/app/scratch/lane205-suite.log',
] as $tool => $args) {
    $o = [];
    $c = 0;
    exec('"'.PHP_BINARY.'" '.escapeshellarg($root.'/storage/app/scratch/'.$tool).$args.' 2>&1', $o, $c);
    $txt = preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $o));
    printf("%s exit=%d :: %s\n", $tool, $c, str_replace("\n", ' / ', implode(' / ', array_slice(explode("\n", $txt), -2))));
    if ($c !== 0) {
        foreach (explode("\n", $txt) as $line) {
            if (str_contains($line, 'STALE')) {
                echo '   > '.mb_substr(trim($line), 0, 220)."\n";
            }
        }
        copy($backup, $docx);
        copy($mdBackup, $root.'/'.$md);
        $fail("{$tool} still refuses — both files restored from the pass's own backups.");
    }
}

printf("written; instrument figure re-taken in two surfaces and both weighers agree; backup %s\n", basename($backup));
