<?php

/**
 * Pass 185d — §25.4 P-2's evidence cell still quotes the paper as printing "Year 1".
 *
 * Found by enumerating every `Year <digit>` literal in the regenerated flat dump, not by a checker:
 * two tokens survive in the document, and only one is a present-tense claim. The other ("five
 * pick-lists had been writing 'Year 2'") is a past-tense defect record and is left alone.
 *
 * This row is the register's own record of how P-2 was closed — "Measured on the paper rather than
 * on the blade" — and the ruling of 2026-10-24 ("the proper format is 2nd year") moved the paper it
 * measured. `enrollment-form.blade.php:83` now prints YearLevel::format($enrollment->yearLevel), so
 * the Year Level field of DEMO-2026-017's form reads `1st Year`, and an evidence cell quoting `Year 1`
 * is now a reading of paper that no longer exists. The lane 62 family is exactly this: a claim about
 * an artefact.
 *
 * Unlike pass 185c, this is NOT a dated row being re-aged — it is a present-tense "now carries" about
 * a document the build still renders, so the figure follows the code within the same batch.
 *
 *   php storage/app/scratch/refresh-docx-pass185d.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass185d.php --apply  # write
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

$from = 'read the text out of the PDF, which now carries DEMO-2026-017, the course, Year 1, 2nd Semester and 2025-2026 where the 2026-10-21 reading found no identity token of any kind.';
$to = 'read the text out of the PDF, which now carries DEMO-2026-017, the course, 1st Year, 2nd Semester and 2025-2026 where the 2026-10-21 reading found no identity token of any kind. The level reads as an ordinal because of the ruling of 2026-10-24, taken after this row was written; the five fields themselves are unchanged.';

$say(substr_count($xml, $from) === 1, 'the P-2 evidence sentence is found exactly once (count '.substr_count($xml, $from).')');
foreach (['<', '>'] as $ch) {
    $say(! str_contains($to, $ch), 'the replacement carries no '.$ch);
}
$say(str_contains($to, 'DEMO-2026-017'), 'the identity the row was built to prove is still named');
$say(str_contains($to, '2026-10-21 reading'), 'the comparison against the older reading survives');
$say(str_contains($to, '1st Year'), 'the cell quotes the level the paper carries today');

$staged = str_replace($from, $to, $xml);
$say(substr_count($staged, $to) === 1, 'the replacement landed once');
$say(substr_count($staged, ', Year 1, 2nd Semester') === 0, 'no bare "Year 1" survives between the course and the semester');
$say(preg_match('/Year 1(?![0-9])/u', $staged, $x) === 0, 'no Year-1 literal of any kind is left in the document: '.($x[0] ?? 'none'));
$say(@(new DOMDocument)->loadXML($staged) !== false, 'the staged document.xml parses');

printf("\n%d guard(s) failed.\n", $fails);
if ($fails > 0) {
    echo "NOTHING WRITTEN.\n";
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: P-2's evidence cell would quote 1st Year and carry its ruling date.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass185d.docx');
$z = new ZipArchive;
if ($z->open($src) !== true) {
    exit("REFUSED: the docx cannot be reopened for the write — nothing written.\n");
}
if ($z->getFromName('word/document.xml') !== $xml) {
    exit("REFUSED: document.xml changed under this pass since it was read — nothing written.\n");
}
$z->addFromString('word/document.xml', $staged);
$z->close();
echo "WROTE {$src}\n";
