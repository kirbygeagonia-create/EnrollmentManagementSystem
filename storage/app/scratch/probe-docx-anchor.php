<?php
/**
 * Which candidate strings actually exist, contiguously, in the raw document.xml?
 * The flat dump shows text with entities decoded or not depending on the run, and Word
 * splits a sentence across <w:t> elements — so an anchor that reads fine in the dump can
 * match nothing in the XML. Every edit in pass 42 is anchored through this probe first.
 */
$src = 'Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    exit("cannot open\n");
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$questions = [
    'Which offices must sign a clearance slip, and what each one clears?',
    'Does the school recognize a separate Clearance Office?',
    'What must a printed slip contain to be accepted at the Registrar’s window?',
    'What is the lost-slip replacement fee, and who sets it?',
    'Should a certificate state student type, enrollment type, or both — and in what words?',
    'Is an official PDF required for any of these documents, or is browser printing the accepted practice?',
    'Must printed documents carry a serial number that matches the print log?',
    'Is one active enrollment per student per term school policy?',
    'May a block be assigned to a room that cannot hold it?',
    'Which clearance statuses may be replaced as lost, and must the fee be charged first every time?',
    'Should yearLevel be promoted automatically each term?',
    'Should an enrollment whose balance is zero be allowed to reach paid without a payment row, and if so which desk signs it out?',
    'Is an “extended” clearance period a real institutional state, and does it accept slips?',
    'Must a first-year collect all nine clearance signatures, or are some obligations not applicable to them?',
    'Should the seeded offices row 8 “Clearance” and its clearancerequirements row be kept, merged into Registrar (1), or removed?',
    'Does the school want installment tracking?',
    'When the Registrar returns a paid enrollment to Department Evaluation, must the payments be voided or refunded first?',
];

$why = [
    'The software can only list the ten seeded offices',
    '§12.1 says the function belongs to the Registrar',
    'The field list in §15 of the revision brief',
    'the template prints a config default',
    'is what the code prints today (P-13)',
    'Two paths exist and only one is reachable',
    'line is blank today (P-6)',
    'Nothing enforces it (§26.4)',
    'the system warns and proceeds (§26.4)',
    'the policy and the controller differ on pending',
    'the field is written at enrollment creation only',
    'The machine has one edge into paid',
    'The value exists in clearanceperiods.periodStatus',
    'The previous revision claimed first-years are auto-approved',
    'App\\Enums\\OfficeId has no case for 8',
    'If yes, PaymentStatus::Partial needs a writer',
    'returnToEvaluation() stores a reason',
    'RegistrarController.php:205-229',
    'The registers in §25, §26 and §27 each end',
    'Five decisions are asked in more than one register',
];

echo "--- questions (the row anchor) ---\n";
foreach ($questions as $q) {
    printf("%2d  %s\n", substr_count($xml, $q), mb_substr($q, 0, 78));
}
echo "\n--- why-cell prefixes ---\n";
foreach ($why as $w) {
    printf("%2d  %s\n", substr_count($xml, $w), mb_substr($w, 0, 78));
}
