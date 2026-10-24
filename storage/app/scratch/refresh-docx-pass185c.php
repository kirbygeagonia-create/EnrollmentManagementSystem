<?php

/**
 * Pass 185c — §1's overview still states §28.5's size as a present fact.
 *
 * Found by the open-items walk (lane69-285-count.php), not by a checker: §1 reads "§28.5 asks nineteen
 * questions, fourteen of them answered…", while pass 183 moved §29.4's family cell to twenty when P-19 was
 * registered on 2026-10-23. The sentence sits inside a dated §1.1 revision row, so lane 59 and lane 61 were
 * both right to leave it alone — a row whose first cell is a date is a record, and lane 61 found no open
 * verb leading any register row. But §1 is the page a reader trusts for the shape of the document, and
 * "asks" is present tense about a register that has since grown.
 *
 * Pass 179's rule applies verbatim: do not re-age a dated row to a newer figure — that would make the row
 * state something untrue on its own day. So the record keeps its numbers and gains its own date, and the
 * living count is pointed at the cell that carries it.
 *
 *   php storage/app/scratch/refresh-docx-pass185c.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass185c.php --apply  # write
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
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$from = '§28.5 asks nineteen questions, fourteen of them answered by rulings 3, 8, 9, 12, 13 and 15 or by the 2026-10-06 decisions';
$to = '§28.5 asked nineteen questions on the day that reconciliation was written, and fourteen were answered by rulings 3, 8, 9, 12, 13 and 15 or by the 2026-10-06 decisions; the register has grown since (P-19 was registered on 2026-10-23), so the count for today is §29.4’s family cell, not this record';

$say(substr_count($xml, $from) === 1, 'the §1 sentence is found exactly once (count '.substr_count($xml, $from).')');
foreach (['<', '>'] as $ch) {
    $say(! str_contains($to, $ch), 'the replacement carries no '.$ch);
}
$say(str_contains($to, 'nineteen'), 'the row keeps the figure that was true on its own day');
$say(str_contains($to, '§29.4'), 'the replacement points at the living count');

$staged = str_replace($from, $to, $xml);
$say(substr_count($staged, $to) === 1, 'the replacement landed once');
$say(substr_count($staged, 'asks nineteen questions') === 0, 'no present-tense count of the register survives in §1');
$say(@(new DOMDocument)->loadXML($staged) !== false, 'the staged document.xml parses');

printf("\n%d guard(s) failed.\n", $fails);
if ($fails > 0) {
    echo "NOTHING WRITTEN.\n";
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: §1's overview sentence would be dated and re-pointed.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass185c.docx');
$z = new ZipArchive;
if ($z->open($src) !== true) {
    exit("REFUSED: the docx cannot be reopened for the write.\n");
}
if ($z->getFromName('word/document.xml') !== $xml) {
    exit("REFUSED: document.xml changed under this pass since it was read — nothing written.\n");
}
$z->addFromString('word/document.xml', $staged);
$z->close();
echo "WROTE {$src}\n";
