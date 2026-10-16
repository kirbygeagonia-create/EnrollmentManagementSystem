<?php
// Second anchor round: for the four rows whose question text is split across runs, find a
// contiguous fragment inside the SAME row to anchor on. Contiguity is what strpos needs;
// the cell prefix is matched against flattened cell text, so run splits do not matter there.
$src = 'Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$candidates = [
    // yearLevel row
    'the field is written at enrollment creation only',
    'defaults to 1 (26.6, G-2)',
    // zero-balance row
    'AccountingController.php:130-131',
    'which cannot run without a positive amount',
    'Adding a second edge',
    // office 8 row
    'has no case for 8',
    'honours whatever officeId the signer carries',
    'merged into Registrar (1), or removed',
    // installment row
    'the value should be dropped from the column and the enum',
    'a partially paid student simply stays assessed',
    'PaymentStatus::Partial needs a writer',
    // returned-payment row (cite repair only)
    'returnToEvaluation',
    'Whether the receipts survive the return',
    // statuses-replaced row
    'Which clearance statuses may be replaced as lost',
    // first-year row
    'Must a first-year collect all nine clearance signatures',
    // preamble paragraphs
    'The registers in §25, §26 and §27 each end with the questions',
    'Five decisions are asked in more than one register',
];
foreach ($candidates as $c) {
    printf("%2d  %s\n", substr_count($xml, $c), mb_substr($c, 0, 74));
}
