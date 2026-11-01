<?php
// Probes candidate needles and anchors for pass 390, printing counts so the pass can assert expect=
// before it writes. Run with --selftest to confirm the probe is read rather than accepted: a needle
// known to be absent must answer 0, and the tool must say so rather than stay silent.
$root = dirname(__DIR__, 3);
$zip = new ZipArchive;
if ($zip->open($root.'/Documentation/EMS Complete Documentation.docx') !== true) {
    fwrite(STDERR, "REFUSED: the docx did not open.\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
if ($xml === '') {
    fwrite(STDERR, "REFUSED: document.xml read empty.\n");
    exit(1);
}

$cases = [
    // anchors
    'A:students-head' => 'students — 23 columns',
    'A:admissions-head' => 'admissions — 13 columns',
    'A:notifications-head' => 'notifications — 9 columns',
    'A:requestkey-cell' => 'the issuance guard: one key, one enrollment',
    // needles
    'N:students-admissions-shared' => 'Change since the previous edition — documented for the first time here: created_at, updated_at.',
    'N:notifications' => 'documented for the first time here: created_at, data, id, notifiable_id, notifiable_type, read_at, type, updated_at;',
    'N:enrollments' => 'documented for the first time here: created_at, curriculumId, returnReason, updated_at;',
    'N:count-sentence' => 'Twelve keys in ten tables.',
    'N:columns-481' => '481 columns',
    'N:indexes-138' => '138 indexes',
    'N:constraints-184' => '184 table constraints',
    'N:documented-439' => '439 columns documented',
    'N:enrollments-head-24' => 'enrollments — 24 columns',
    'N:A1' => 'What is left is whether the schema should also reject a row written straight to the table, which today it does not.',
    'N:B1' => 'Its `down()` restores the key once those fixtures name a later term or drop the prior seat first.',
    'N:C1' => 'What the domain tables do hold now for enrollment integrity is `uq_enrollments_request_key` over `requestKey`, which is the guide’s problem 17: a repeat of the same act is answered with the record the first send created rather than a second one.',
    'N:D1' => 'on the same schema moment that retired the VIRTUAL `seatKey`.',
    'N:selftest-absent' => 'this phrase cannot exist in any document 4f3b1c',
];
foreach ($cases as $name => $needle) {
    printf("%-28s count %d — %s\n", $name, substr_count($xml, $needle), substr($needle, 0, 70));
}
