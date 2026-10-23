<?php

/**
 * Lane 65 candidate probe — how many peso figures does the authority docx state, and which of them are
 * present-tense claims about the demo dataset rather than history?
 *
 * Lane 41 binds peso figures in the two demo-day guides and the walkthrough; lane 60 binds schema figures
 * and ratios over rows; lane 59 binds counts to the tool named beside them. Nothing yet asks whether a
 * "₱17,500" written in §17 or §28 is what the fee catalogue and the demo accounts answer today.
 */
$flat = explode("\n", (string) file_get_contents('storage/app/scratch/docx-flat.txt'));
$HIST = '/\b(20\d\d-\d\d-\d\d|was |were |had |used to|once|earlier|before|since|closed|ruled|measured|stale|previous)\b/iu';
$hits = [];
foreach ($flat as $i => $line) {
    if (preg_match_all('/₱\s?([0-9][0-9,]*(?:\.\d\d)?)/u', $line, $m) !== false) {
        if (! isset($m[1]) || $m[1] === []) {
            continue;
        }
        foreach ($m[1] as $k => $amount) {
            $start = (int) strpos($line, '₱'.$amount);
            $ctx = mb_substr($line, max(0, $start - 90), 200, 'UTF-8');
            $hist = preg_match($HIST, $ctx) === 1;
            $hits[] = ['line' => $i + 1, 'amount' => $amount, 'hist' => $hist, 'ctx' => preg_replace('/\s+/u', ' ', $ctx)];
        }
    }
}
$present = array_values(array_filter($hits, fn (array $h): bool => ! $h['hist']));
printf("peso tokens in the document: %d over %d lines\n", count($hits), count(array_unique(array_column($hits, 'line'))));
printf("  carried in a sentence with a history marker: %d\n", count($hits) - count($present));
printf("  read as present-tense claims:                %d\n\n", count($present));
$distinct = [];
foreach ($present as $h) {
    $distinct[$h['amount']] = ($distinct[$h['amount']] ?? 0) + 1;
}
arsort($distinct);
echo "distinct amounts among the present-tense claims:\n";
foreach (array_slice($distinct, 0, 18, true) as $amt => $n) {
    printf("  ₱%-12s x%d\n", $amt, $n);
}
echo "\nfirst 14 present-tense claims, with their sentence context:\n";
foreach (array_slice($present, 0, 14) as $h) {
    printf("  :%-5s ₱%-10s %s\n", $h['line'], $h['amount'], mb_substr($h['ctx'], 0, 150));
}
