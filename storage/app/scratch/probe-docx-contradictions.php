<?php
/**
 * Find every place the document still asserts a state that a closed register row denies.
 * Each pattern is a claim about the build; the register rows (§28.2/§28.3 and §27.2) say the
 * claim was settled, so any other row carrying it is a self-contradiction waiting for the panel.
 * Reads docx-flat.txt only. Nothing here writes.
 */
$flat = file_get_contents(__DIR__.'/docx-flat.txt');
$lines = explode("\n", $flat);

$patterns = [
    'seat guard exists' => [
        'Nothing enforces it',
        'No controller checks it either',
        'nothing stops a second',
        'no guard for it exists',
        'There is no unique key on',
    ],
    'year level is derived' => [
        'yearLevel does not follow',
        'defaults to 1',
        'nothing updates it',
        'written at enrollment creation only',
        'show every returning student as first year',
    ],
    'zero balance can be settled' => [
        'cannot be moved to paid',
        'one edge into paid',
        'the deadlock of E-1',
        'writes paid for every receipt',
    ],
    'office 8 is retired' => [
        'office 8',
        'office row 8',
        'ten seeded offices',
        'honours whatever officeId',
    ],
    'replacement fee is Reference Data' => [
        'no fee type is seeded',
        'config default',
        'a figure fixed here',
    ],
    'documents are numbered' => [
        'Document No.',
        'line is blank',
    ],
    'the PDF path' => [
        'only one is reachable',
        'Two paths exist',
    ],
    'replacement statuses' => [
        'differ on pending',
        'also accepts pending',
    ],
    'extended period is read' => [
        'nothing reads it',
        'extendedDeadline',
    ],
    'first-year clearance' => [
        'no type branch',
    ],
    'print rights' => [
        'three print rights',
        'the four Registrar print rights',
        'print.enrollmentForm',
    ],
    'counts' => [
        '87',
        'the permission set',
    ],
];

foreach ($lines as $line) {
    // "ROW 2558: text" / "P 2547: text" — match against the text only, never the locator,
    // or a needle like "87" hits every row number that contains it.
    if (! preg_match('/^(ROW|P) (\d+): (.*)$/s', $line, $m)) {
        continue;
    }
    [$whole, $kind, $at, $text] = $m;
    foreach ($patterns as $group => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                printf("%-6s %-6s %-34s | %s\n", $kind, $at, $group.' :: '.$needle, mb_substr(trim($text), 0, 230));
                break 2;
            }
        }
    }
}
