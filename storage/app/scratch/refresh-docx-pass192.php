<?php

/**
 * Pass 192 — name three more pointers, two of which were also quoting the wrong lines.
 *
 * Read before written, and the read changed the number in two of the three:
 *   :93-124   WorkflowService.php  — line 93 is `foreach ($this->stepsFor($enrollment) as $order =>
 *             [$officeId, $label])` and 102 returns the workflow, so the span really is where the office and
 *             the order are paired, as the row says. Only the file name was missing.
 *   :128-132  WorkflowService.php:208 — `public function signStep()` is at 208. 128-132 is inside the create
 *     path, so the row cited the wrong lines for its own method; the declaration is cited instead, which is
 *     the one thing the read proved.
 *   :403-421  UserManagementController.php:494 — `public function auditLogs()` is at 494, ninety-one lines
 *     below what the row quotes. Declaring the method is the verified target; the filter behaviour the row
 *     describes lives inside it, and a range that names both would have to be read to the closing brace first.
 *
 * A needle that is not unique document-wide is skipped, not guessed, and no offset arithmetic is used: the
 * first attempt at this class applied splices in reverse insertion order rather than reverse offset order and
 * the parse guard caught it, so this file has exactly one mechanism — replace a needle that occurs once.
 *
 *   php storage/app/scratch/refresh-docx-pass192.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass192.php --apply  # write
 */
$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$fails = 0;
$say = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD ', $what);
    if (! $ok) {
        $fails++;
    }
};

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

// Verified against the files named above.
$edits = [
    [':93-124', 'WorkflowService.php:93-124'],
    [':128-132', 'WorkflowService.php:208'],
    [':403-421', 'UserManagementController.php:494'],
];

$accepted = [];
foreach ($edits as [$old, $new]) {
    $n = substr_count($xml, $old);
    if ($n === 1) {
        $accepted[] = [$old, $new];
        printf("  ok   %-12s unique, -> %s\n", $old, $new);
    } else {
        printf("  skip %-12s occurs %d times document-wide\n", $old, $n);
    }
}
$say(count($accepted) === count($edits), 'every needle this pass claims is addressable ('.count($accepted).'/'.count($edits).')');

$staged = $xml;
foreach ($accepted as [$old, $new]) {
    $staged = str_replace($old, $new, $staged);
}
foreach ($accepted as [$old, $new]) {
    $say(str_contains($staged, $new), 'the named citation is present: '.$new);
    foreach (['<', '>', '&'] as $ch) {
        $say(! str_contains($new, $ch), $new.' carries no raw '.$ch);
    }
}
$say(substr_count($xml, '<w:tbl>') === substr_count($staged, '<w:tbl>'), 'no table moved');
$say(@(new DOMDocument)->loadXML($staged) !== false, 'the staged document.xml parses');
printf("\n  %d edit(s); staged byte delta: %+d\n", count($accepted), strlen($staged) - strlen($xml));
printf("%d guard(s) failed.\n", $fails);
if ($fails > 0) {
    echo "NOTHING WRITTEN.\n";
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: three pointers would name their file.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass192.docx');
$z = new ZipArchive;
if ($z->open($src) !== true) {
    echo "REFUSED: the docx cannot be reopened for the write — nothing written.\n";
    exit(1);
}
if ($z->getFromName('word/document.xml') !== $xml) {
    echo "REFUSED: document.xml changed under this pass since it was read — nothing written.\n";
    exit(1);
}
$z->addFromString('word/document.xml', $staged);
$z->close();
echo "WROTE {$src}\n";
