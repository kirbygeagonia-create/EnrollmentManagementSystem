<?php

/**
 * Probe which candidate needles for pass 124 actually live inside one <w:t> run.
 *
 * The engine's text edit replaces bytes inside a single run, so a needle that the document happens to split
 * across two runs simply is not there — and a pass that reports "1 occurrence" for a substring of one run
 * while the intended sentence spans two is how an edit silently rewrites half a clause. This prints the raw
 * byte count per needle so a pass can be written against what the file holds, not against the flat dump.
 */

$root = dirname(__DIR__, 3);
$zip = new ZipArchive();
$zip->open($root.'/Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$needles = [
    'two box rules at :263-280',
    'printed papers attributable to nobody (§28 G-9), the past-term records the demo pipeline had walked through the Clinic and ID boxes before it wrote the acts that sign them, and enrollment 53, whose contradictory request row was deleted on 2026-10-11 with its signature left standing.',
    'the past-term records the demo pipeline had walked through the Clinic and ID boxes before it wrote the acts that sign them',
    'A report tool counts those and leaves them.',
    'the 21 legacy rows with no key at all remain outside it, because MySQL treats their NULLs as distinct, which is why they are reserved rather than repaired (ruling 6).',
    'the 21 legacy rows with no key at all remain outside it',
    'which is why they are reserved rather than repaired (ruling 6)',
    'The 21 legacy rows that carry no key at all are kept untouched by ruling 6, so §20.5 counts prints and does not reconstruct history from them.',
    'The 21 legacy rows that carry no key at all are kept untouched by ruling 6',
    'for the 21 rows that carry no key at all, MySQL treats NULLs as distinct, so the unique index cannot reject a repeat of those. New prints are safe by the sequencing rule.',
    'for the 21 rows that carry no key at all, MySQL treats NULLs as distinct',
    'What the schema cannot reject is a repeated documentNo among the 21 legacy rows whose key is NULL, because MySQL treats NULLs as distinct (§25 P-14).',
    'among the 21 legacy rows whose key is NULL',
    'stepping over the numbers the key-less legacy rows hold. What the schema still cannot reject is a repeat among those 21 NULL-keyed rows, because MySQL treats NULLs as distinct.',
    'a repeat among those 21 NULL-keyed rows',
    'Nothing for the log.',
    'One limit stays, and it is the schema',
    'Append-only still means',
    'Closed for new prints.',
];

foreach ($needles as $n) {
    printf("%2d  %s\n", substr_count($xml, $n), substr($n, 0, 110));
}
