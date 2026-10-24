<?php
// Read-only: does any §25.4 print finding still stand open, and does the decision
// sheet's own sentence about that agree?

$s = file_get_contents('storage/app/scratch/docx-flat.txt');
$lines = explode("\n", $s);

$open = [];
$rows = 0;
foreach ($lines as $l) {
    if (!preg_match('/^ROW \d+: (P-\d+) \| ([^\n]{0,90})/u', $l, $m)) {
        continue;
    }
    // §25.4 rows are "P-n | <leading verdict cell>"; other tables use P-n inside prose.
    if (!preg_match('/Closed|Open|Provisional|Ruled|Deferred|Rendering|Implementation|Not yet/u', $m[2])) {
        continue;
    }
    $rows++;
    if (!preg_match('/^Closed/u', $m[2])) {
        $open[] = $m[1].' → '.trim($m[2]);
    }
}

echo "§25.4 P-rows with a verdict-leading cell: {$rows}\n";
echo $open === []
    ? "OPEN: none — every printed-document finding is closed.\n"
    : "OPEN:\n  ".implode("\n  ", $open)."\n";

$sheet = file_get_contents('Documentation/School-Decision-Sheet-2026-10-23.md');
echo "\nthe sheet's own sentence about §25.4:\n";
foreach (explode("\n", $sheet) as $n => $l) {
    if (str_contains($l, 'three of which') || str_contains($l, 'of which is not')) {
        echo '  line '.($n + 1).': '.trim($l)."\n";
    }
}
