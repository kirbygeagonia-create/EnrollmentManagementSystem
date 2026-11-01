<?php
// Probes the exact byte strings pass 390 intends to use, because a typographic apostrophe and a
// straight one are different needles and a wrong one is a refused write at best and a half-applied
// register at worst. Prints the count for each so the pass can assert expect= before it runs.
$root = dirname(__DIR__, 3);
$zip = new ZipArchive;
if ($zip->open($root.'/Documentation/EMS Complete Documentation.docx') !== true) {
    fwrite(STDERR, "REFUSED: the docx did not open.\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$needles = [
    'A1' => 'What is left is whether the schema should also reject a row written straight to the table, which today it does not.',
    'B1' => 'Its `down()` restores the key once those fixtures name a later term or drop the prior seat first.',
    'C1' => 'What the domain tables do hold now for enrollment integrity is',
    'C2' => 'which is the guide’s problem 17: a repeat of the same act is answered with the record the first send created rather than a second one.',
    'C3' => 'which is the guide\'s problem 17: a repeat of the same act is answered with the record the first send created rather than a second one.',
    'D1' => 'on the same schema moment that retired the VIRTUAL `seatKey`.',
    'E1' => 'The schema was tried once for this and retreated:',
    'F1' => 'because six test files each wrote two active seats for one student and one term and CI’s MySQL job was the only surface that could see them.',
    'G1' => 'withdrawn the same day by',
];
foreach ($needles as $name => $n) {
    printf("%-3s count %d — %s\n", $name, substr_count($xml, $n), $n);
}
