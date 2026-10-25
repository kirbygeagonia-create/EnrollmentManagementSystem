<?php

/**
 * Pass 200 — §1.1's living row records pass 199: the §25 identity matrix, whose bare numbers belong to the
 * column, not the cell.
 *
 *   php storage/app/scratch/refresh-docx-pass200.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass200.php --apply  # write
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

$from = 'because its precision has not been measured.';
$to = $from.' Pass 199 then drained the widest table in the document: the identity matrix has one row per required '
    .'context fact and one column per printed document, and its cells carry bare numbers because the file is a '
    .'column property stated nowhere in the cell — the row above the table only says that desk means the page '
    .'staff print and PDF means the template. So no instrument had ever read one of them, and they were sixteen '
    .'of the seventy pointers the line sweep prints as unattributable. Resolving each cell against its own '
    .'column showed that most of them were stale in the way only a template edit causes: the cell for student '
    .'type quoted a line that is a plain div, the certificate’s status cell quoted the enrollment-date row, the '
    .'slip’s “date to be signed” and “per-approval date” quoted the student name and the course row, and the '
    .'subject load’s signature range opened on a blank line. Every number was re-taken by reading the field the '
    .'cell claims, and the pass refuses unless each new line carries that field — twenty-nine such assertions '
    .'gated this write. The cells now name their files, which is the rule this repository learned from the data '
    .'dictionary: when a checker can only read the row, the document has to name the file rather than the tool '
    .'learning to guess it. The sweep’s count moved from 666 citations to 681 over 153 files with the blind '
    .'queue at 54.';

$n = substr_count($xml, $from);
$say($n === 1, 'the living revision row ends where this record goes (count '.$n.')');
foreach (['<', '>', '&'] as $ch) {
    $say(! str_contains($to, $ch), 'the replacement carries no raw '.$ch);
}
$say(preg_match('/[A-Za-z][A-Za-z0-9_.\-]*\.(?:php|jsx|js|md|json):\d/', $to) === 0,
    'the record writes no citation-shaped example that no file could answer');
$say(substr_count($xml, 'Pass 199 then') === 0, 'nothing in the document yet claims pass 199 ran');
$staged = str_replace($from, $to, $xml);
$say(substr_count($staged, 'Pass 199 then') === 1, 'the record is stated once');
$say(@(new DOMDocument)->loadXML($staged) !== false, 'the staged document.xml parses');
printf("\n  staged byte delta: %+d\n", strlen($staged) - strlen($xml));
printf("%d guard(s) failed.\n", $fails);
if ($fails > 0) {
    echo "NOTHING WRITTEN.\n";
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: §1.1’s living row would record pass 199.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass200.docx');
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
