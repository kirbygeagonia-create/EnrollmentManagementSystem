<?php
// Third round: the exact spans pass 42 intends to replace, plus the §28.5 column header.
$src = 'Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$spans = [
    'one meeting can work through the list',
    'Five decisions are asked in more than one register and should be answered once',
    'Answering the version in this table settles all of its copies',
    'Why the repository cannot settle it',
    'The decision',
    'Stated in',
    '28.5 Consolidated list of decisions to put to the school',
    'is what the code prints today (P-13)',
    '“New Student” is what the code prints today',
    'the “Document No.” line is blank today',
    'If yes, PaymentStatus::Partial needs a writer',
    'The value exists in clearanceperiods.periodStatus',
    'The machine has one edge into paid',
    'the policy and the controller differ on pending',
    'App\Enums\OfficeId has no case',
    'Two paths exist and only one is reachable (§25.1); the choice is institutional',
    'the system warns and proceeds (§26.4)',
    'Nothing enforces it (§26.4). If it is policy',
];
foreach ($spans as $s) {
    printf("%2d  %s\n", substr_count($xml, $s), mb_substr($s, 0, 74));
}
