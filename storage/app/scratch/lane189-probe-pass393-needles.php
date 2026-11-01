<?php
// Probes every needle pass 393 intends to use, printing counts so a refused write is a reading rather than a
// mystery. Cells whose text carries a styled identifier are split across runs in document.xml even when the flat
// dump reads them continuously, so each candidate is tested twice: whole sentence, then its shortest head.
$root = dirname(__DIR__, 3);
$zip = new ZipArchive;
if ($zip->open($root.'/Documentation/EMS Complete Documentation.docx') !== true) {
    fwrite(STDERR, "REFUSED: the docx did not open.\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$needles = [
    'intro-count' => 'and in three cases the answer is nothing.',
    'intro-tail' => 'The fourth column says what, in practice, keeps the data clean',
    '239-enforce' => 'The application enforces it; the database does not.',
    '239-idx-full' => 'idx_enrollments_term_status is still a plain index, and no unique key over (studentId, termId) exists.',
    '239-idx-head' => 'is still a plain index, and no unique key over',
    '239-stays' => 'The rule stays in this register because the storyboard states a database invariant and the database does not hold it',
    '239-stays-tail' => 'a row written straight into the table still gives one student two active enrollments in a term',
    '2362-seat-cell1' => 'One active enrollment per student per term',
    '2362-fee-cell1' => 'One assessment per student per term',
    'census-nine' => 'nine across the domain tables, listed individually in',
    'census-nine-b' => 'These are the only duplication rules the server defends.',
    '2362-6row-note' => 'Enforced by the server: uq_student_clearanceperiod',
];
foreach ($needles as $name => $n) {
    printf("%-16s count %d — %s\n", $name, substr_count($xml, $n), substr($n, 0, 78));
}
