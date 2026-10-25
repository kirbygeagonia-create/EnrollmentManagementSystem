<?php

/**
 * Lane 70, pass 242: two §? desk-display rows whose JSX pointers had slid off the fields they name.
 *
 * These are the 38th–42nd wrong targets, and they are the shape the bounds sweep cannot see: every number is a
 * live, non-blank line of the file the row itself names, and in Students/Show.jsx the neighbours are *about* the
 * same block — a badge prop, a section comment, a closing tag — so reading the row without reading the file reads
 * clean. The row labels a field, the pointer lands one to eight lines away from it.
 *
 * Students/Show.jsx (the Student 360 row): "Name, School ID ( :152,186,193 )" lands on `officeBadge=`, the
 * `{/* Identity fields *\/}` comment and a `</FormSection>`; the name is the PageHeader title at :147, the School
 * ID is its subtitle at :148 and the identity field's value at :189. "Student Type and Enrollment Type ( :68-69 )"
 * lands on a closing `</div>` and a blank line; the two types are printed at :60-61. "enrollment status ( :73-74 )"
 * lands on the money grid's wrapper divs; the badge is :65-66.
 *
 * Evaluation/Index.jsx (the Evaluation queue row): the claim is "Year Level and Academic Standing … are columns of
 * it now (:84, :90)", and :90 is the *Status* column while Year Level is :78. The row's other span,
 * `Evaluation/Index.jsx:84-91`, was offered for "Student Type as a badge column, with Year Level and Proposed
 * Standing beside it" — Student Type is :79-83 and Year Level :78, so the span starts six lines after its own
 * first claim. It becomes :78-91, which holds all three. The row's third citation, :184-187 (the Transferees /
 * Shifters tile), is checked and left alone: :186 really is `label="Transferees / Shifters"`.
 *
 * Every old number is asserted NOT to carry its claim and every new one asserted to, against the working files,
 * before the write. Post-conditions are scoped to the replaced tokens and their deltas, never to a bare number
 * document-wide (:60-61 and :65-66 already occur elsewhere in the document).
 */
$root = dirname(__DIR__, 3);
require_once $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};
$assertPlain = function (string $text, string $what) use (&$fails): void {
    foreach (['&', '<', '>'] as $c) {
        if (str_contains($text, $c)) {
            printf("  FAIL %s carries %s — it would be escaped twice or corrupt the part\n", $what, $c);
            $fails++;
        }
    }
};
$show = $root.'/resources/js/Pages/Students/Show.jsx';
$evalx = $root.'/resources/js/Pages/Evaluation/Index.jsx';
$A = (array) file($show);
$B = (array) file($evalx);
$at = static fn (array $f, int $n): string => rtrim((string) ($f[$n - 1] ?? ''));
$block = static function (array $f, int $from, int $to): string {
    $out = [];
    for ($i = $from; $i <= $to; $i++) {
        $out[] = rtrim((string) ($f[$i - 1] ?? ''));
    }

    return implode("\n", $out);
};

echo "=== stage 1a: Students/Show.jsx — what the three pointer groups land on, and what they should\n";
$guard(str_contains($at($A, 147), 'title={studentName}') && str_contains($at($A, 148), 'School ID: ${student.schoolIdNumber'),
    ':147-148 are the PageHeader title (the name) and its School ID subtitle');
$guard(str_contains($at($A, 188), 'label="School ID"') && str_contains($at($A, 189), '{student.schoolIdNumber'),
    ':188-189 are the identity card’s School ID field and its value');
$guard(! str_contains($at($A, 152), 'studentName') && str_contains($at($A, 152), 'officeBadge'),
    ':152 is `officeBadge=` — it renders no name');
$guard(str_contains($at($A, 186), 'Identity fields') && str_contains($at($A, 193), '</FormSection>'),
    ':186 is a section comment and :193 a closing tag — neither prints a field');
$guard(str_contains($at($A, 60), 'studentType') && str_contains($at($A, 61), 'enrollmentType'),
    ':60-61 print Student Type and Enrollment Type');
$guard(trim($at($A, 68)) === '</div>' && trim($at($A, 69)) === '',
    ':68-69 is a closing div and a blank line — the wrong target');
$guard(str_contains($block($A, 65, 66), 'enrollmentStatus') && str_contains($at($A, 66), 'formatStatusLabel'),
    ':65-66 is the enrollment-status badge');
$guard(str_contains($at($A, 73), 'grid grid-cols-2') && trim($at($A, 74)) === '<div>',
    ':73-74 is the money grid’s wrapper — the wrong target');

echo "\n=== stage 1b: Evaluation/Index.jsx — the queue’s columns\n";
$guard(str_contains($at($B, 78), "key: 'yearLevel'") && str_contains($at($B, 78), 'formatYearLevel'),
    ':78 is the Year Level column');
$guard(str_contains($at($B, 84), "key: 'academicStanding'"), ':84 is the Proposed Standing column');
$guard(str_contains($at($B, 90), "key: 'enrollmentStatus'") === false
    && str_contains($at($B, 89), "key: 'enrollmentStatus'") && str_contains($at($B, 90), 'enrollmentStatusTone'),
    ':90 is the Status column’s badge line — not Year Level, so the pair (:84, :90) misses its own first claim');
$guard(str_contains($at($B, 79), "key: 'studentType'") && str_contains($block($B, 79, 83), 'studentTypeTone'),
    ':79-83 is the Student Type badge column, which :84-91 starts after');
$guard(str_contains($at($B, 186), 'Transferees / Shifters'),
    ':184-187 is cited for the merged tile and :186 really is its label — that pointer stays');
$guard($at($B, 91) !== '', ':91 is non-blank, so :78-91 is a legal endpoint for the sweep');
if ($fails > 0) {
    printf("\n%d guard(s) failed in stage 1 — NOTHING WRITTEN.\n", $fails);
    exit(1);
}

echo "\n=== stage 2: the needles on the staged bytes\n";
$z = new ZipArchive();
if ($z->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $z->getFromName('word/document.xml');
$parts = $z->numFiles;
$z->close();
$guard(str_contains($xml, '<w:body>'), 'the part read is document.xml ('.strlen($xml).' bytes)');

$edits = [
    [':152,186,193', ':147,148,189'],
    [':68-69', ':60-61'],
    [':73-74', ':65-66'],
    ['Evaluation/Index.jsx:84-91', 'Evaluation/Index.jsx:78-91'],
    ['(:84, :90)', '(:78, :84)'],
];
$before = [];
foreach ($edits as $i => [$from, $to]) {
    $c = substr_count($xml, $from);
    $before[$to] = substr_count($xml, $to);
    $guard($c === 1, 'needle '.($i + 1).' is unique in the staged bytes (seen '.$c.'): ['.$from.']');
    $assertPlain($to, 'needle '.($i + 1).' replacement');
    $wholeRun = substr_count($xml, '>'.$from.'</w:t>');
    $guard($wholeRun <= 1, 'needle '.($i + 1).' is its own code run or a unique substring of one (run-boundary hits: '.$wholeRun.'); the new number cannot be in the document yet, so only the old side is comparable');
}
$guard(substr_count($xml, '>'.$edits[0][0].'</w:t>') === 1 && substr_count($xml, '>'.$edits[1][0].'</w:t>') === 1
    && substr_count($xml, '>'.$edits[2][0].'</w:t>') === 1,
    'the three Students/Show.jsx tokens each fill exactly one code run, so no replacement can bleed into a neighbour');
if ($fails > 0) {
    printf("\n%d guard(s) failed before any write — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: five pointers re-taken in two desk-display rows —\n"
        ."  Students/Show.jsx  :152,186,193 -> :147,148,189   :68-69 -> :60-61   :73-74 -> :65-66\n"
        ."  Evaluation/Index.jsx  :84-91 -> :78-91   (:84, :90) -> (:78, :84)\n"
        ."Re-run with --apply.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass242.docx');
docxApply($src, $root.'/storage/app/scratch/pass242-backup.docx', $edits, [], $apply);

$z = new ZipArchive();
$z->open($src);
$round = (string) $z->getFromName('word/document.xml');
$roundParts = $z->numFiles;
$z->close();
echo "\n=== stage 3: re-read the written bytes\n";
$guard($roundParts === $parts, 'the archive still holds '.$parts.' members');
foreach ($edits as [$from, $to]) {
    $guard(substr_count($round, $from) === 0, 'no copy of the old pointer survives: ['.$from.']');
    $guard(substr_count($round, '>'.$to.'</w:t>') >= 1,
        'the new pointer is a whole code run, not spliced into a sentence: ['.$to.']');
}
$guard(substr_count($round, ':60-61') === $before[':60-61'] + 1, ':60-61 now occurs '.($before[':60-61'] + 1).'× (it was a live number elsewhere)');
$guard(substr_count($round, ':65-66') === $before[':65-66'] + 1, ':65-66 now occurs '.($before[':65-66'] + 1).'× (same reason)');
$guard(substr_count($round, 'Evaluation/Index.jsx:184-187') === substr_count($xml, 'Evaluation/Index.jsx:184-187'),
    'the tile pointer this pass did NOT touch is unchanged at 2 copies');
if ($fails > 0) {
    printf("\n%d guard(s) failed after the write — restore the docx from storage/app/scratch/ems-docx-pre-pass242.docx.\n", $fails);
    exit(1);
}
echo "pass 242 applied.\n";
