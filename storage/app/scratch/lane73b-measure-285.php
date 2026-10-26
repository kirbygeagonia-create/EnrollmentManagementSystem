<?php

/**
 * Read-only, for docx pass 247: how many §28.5 rows are still open, and how many copies of each needle exists.
 *
 * §29.4's "Open decisions 20" cell counts the §28.5 rows that are still questions, so it may only be rewritten
 * against a count read out of the document — the defect this repository has named repeatedly is a tally that stays
 * standing while the rows under it move.
 */
$root = 'C:/Users/ADMIN/OneDrive/Documents/3rd_Year/EnrollmentManagementSystem';
$zip = new ZipArchive;
if ($zip->open($root.'/Documentation/EMS Complete Documentation.docx') !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$needles = [
    'which-offices-open',
    'slip-content-open',
    'pdf-open',
    'fee-open',
    'section-285-intro',
    'p19-tail-stays-open',
];
$samples = [
    'which-offices-open' => 'Which offices SEAIT requires to sign, and what each one clears, is still the school’s to state',
    'slip-content-open' => 'So the answer SEAIT gives is the wording of the lines and the order of the signatures, not a column.',
    'pdf-open' => 'Which of the two SEAIT accepts at a window, and whether the generated PDF is the file of record, is institutional',
    'fee-open' => 'and the ₱100.00 in the seeders is the developer’s placeholder.',
    'p19-tail-stays-open' => 'items 1, 2, 4 and 5 of the sheet stay open on their own questions.',
];
foreach ($samples as $k => $s) {
    printf("  count %d — %s\n", substr_count($xml, $s), $k);
}

$intro = 'The sixteen that waited on an office or on whoever provisions the server on the day this table was reconciled';
printf("  count %d — section-285-intro\n", substr_count($xml, $intro));

// --- §28.5 row census, read from the flat dump -------------------------------------------------------------
$dump = file($root.'/storage/app/scratch/docx-flat.txt') ?: [];
$in = false;
$rows = [];
foreach ($dump as $i => $l) {
    $t = trim(preg_replace('/\s+/', ' ', $l));
    if (str_starts_with($t, 'P ') && str_contains($t, '28.5 Consolidated list')) {
        $in = true;

        continue;
    }
    if ($in && preg_match('/^ROW \d+: (\d+)\./', $t)) {
        break;  // next section's first row
    }
    if ($in && str_starts_with($t, 'P ') && preg_match('/^P \d+: \d+\./', $t)) {
        break;
    }
    if ($in && str_starts_with($t, 'ROW ')) {
        $rows[] = [$i + 1, $t];
    }
}

$answering = '/\b(Answered|Closed|Ruled|Settled|Contained|Enforced|Adopted|Built|Done|No longer)\b/';
$open = [];
$answered = [];
foreach ($rows as [$n, $t]) {
    if (str_contains($t, 'The decision |')) {
        continue;  // header
    }
    $cells = array_slice(array_map('trim', explode('|', $t)), 1);
    $verdict = $cells[1] ?? '';
    if (preg_match($answering, (string) $verdict)) {
        $answered[] = $n;
    } else {
        $open[] = [$n, mb_substr((string) ($cells[0] ?? ''), 0, 76)];
    }
}

printf("\n§28.5 rows read: %d — answering-verb verdicts %d, still questions %d\n", count($rows), count($answered), count($open));
foreach ($open as [$n, $q]) {
    printf("  OPEN line %-6d %s\n", $n, $q);
}
