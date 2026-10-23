<?php

/**
 * Probe for pass 179 — how many times does each intended needle actually occur, and does the
 * cell I mean to edit hold exactly one paragraph? A text edit resolves only a needle that lives
 * inside ONE <w:t> run, so a heading split into a code-styled run plus a plain one reports 0 and
 * the edit has to be addressed another way. Nothing here writes.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    exit("cannot open\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$needles = [
    'census 452 (whole clause)',
    'Counts: 63 tables (55 EMS domain + 8 framework), 452 columns',
    'census anchor',
    'Counts: 63 tables',
    '452 columns',
    '24 heading whole',
    'studentclearances — 9 columns',
    '24 heading tail only',
    '— 9 columns',
    '9 columns',
    '24 role clause',
    'with an overall status and an optional individually-extended deadline for a student’s own situation, separate from the period’s general window.',
    '24 role clause (straight apostrophes)',
    "with an overall status and an optional individually-extended deadline for a student's own situation, separate from the period's general window.",
    '24 created-by',
    'Created by 2026_08_02_1000000021_create_studentclearances_table.php.',
    '24 rows-in-demo',
    'Rows in the demo database: 4.',
    'shared extended-deadline sentence',
    'What is still not institutional is the per-student deadline: studentclearances.extendedDeadline is written by nobody and read by nothing, so an extension moves the window rather than the individual.',
    'half-answered status word',
    'Half answered and built 2026-10-03 (ruling 15)',
    'E-8 row anchor',
    '>E-8<',
    'E-3 row anchor',
    '>E-3<',
    '30.4 row anchor',
    '>studentclearances.extendedDeadline<',
    'dictionary row cell anchor',
    '>extendedDeadline<',
    '2589 row anchor',
    'storyboard list or the code comments?',
    '2589 answer prefix',
    'The two disagree on BR5',
    '2593 row anchor',
    'Is a returned enrollment’s payment kept',
    '2593 answer prefix',
    'the state machine allows paid',
    '2596 answer prefix',
    'returnToEvaluation() stores a reason',
    'E-3 consequence prefix',
    'Status moves to returnedToEvaluation',
    '1.1 living row anchor',
    '>2026-10-21 (this issue)<',
    '28.8 open-item sentence',
    'names the eight rows that still owe an answer',
    '30.6 owed list',
    'the program and term the enrollment form cannot print (P-2)',
];

for ($i = 0; $i < count($needles); $i += 2) {
    printf("%-34s %d\t[%s]\n", $needles[$i], substr_count($xml, $needles[$i + 1]), mb_substr($needles[$i + 1], 0, 46));
}

/**
 * Cell layout of the four rows this pass rewrites: the engine addresses a cell by the first 60
 * characters of its flattened text, and refuses when the cell holds more than one paragraph.
 */
foreach (['>E-3<', '>E-8<', '>studentclearances.extendedDeadline<', 'storyboard list or the code comments?',
    'Is a returned enrollment’s payment kept', 'returnToEvaluation() stores a reason and moves the status'] as $anchor) {
    $at = strpos($xml, $anchor);
    if ($at === false) {
        printf("\nrow %s : ANCHOR NOT FOUND\n", $anchor);

        continue;
    }
    $start = strrpos(substr($xml, 0, $at), '<w:tr');
    $end = (int) strpos($xml, '</w:tr>', $at) + 7;
    $row = substr($xml, $start, $end - $start);
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
    printf("\nrow %s : %d cells, %d <w:p> in the row\n", $anchor, count($cm[0]), substr_count($row, '<w:p>'));
    foreach ($cm[0] as $j => $cell) {
        preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $tm);
        $flat = html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1);
        printf("   cell %d  paras=%d runs=%d  head60=%s\n", $j + 1, substr_count($cell, '<w:p>'),
            count($tm[1]), mb_substr(preg_replace('/\s+/', ' ', $flat), 0, 60));
    }
}
