<?php
// Two probes for the lane 56f correction, kept out of a shell string so no backtick or quote can bite.
$root = dirname(__DIR__, 3);

$flat = (string) file_get_contents($root.'/storage/app/scratch/docx-flat.txt');
echo "== docx claims that a fresh dataset seeds N partial rows ==\n";
$hits = preg_match_all('/[^|]{0,160}partial[^|]{0,160}/i', $flat, $m) ? $m[0] : [];
$shown = 0;
foreach ($hits as $h) {
    if (preg_match('/(one|two|three|four|five|six|no)\s+(?:`?partial`?\s+rows?|partial)/i', $h) === 1) {
        echo '  '.preg_replace('/\s+/', ' ', substr($h, 0, 240))."\n";
        $shown++;
    }
}
echo "  (matched {$shown} of ".count($hits)." partial-bearing cells)\n";

echo "\n== HEAD's dataset: the receipt that lies ==\n";
$p = new PDO('mysql:host=127.0.0.1;dbname=ems_l56_b', 'root', '');
$q = $p->query('SELECT p.orNumber, p.amount, p.paymentStatus, p.enrollmentId, a.totalAssessedAmount, a.remainingBalance'
    .' FROM payments p JOIN studentassessments a ON a.enrollmentId = p.enrollmentId'
    ." WHERE p.paymentStatus = 'paid' AND a.totalAssessedAmount > 0 AND a.remainingBalance > 0");
foreach ($q as $r) {
    printf("  %s  PHP %s  marked `%s`  enrollment %s  sheet %s  owes %s\n",
        $r['orNumber'], $r['amount'], $r['paymentStatus'], $r['enrollmentId'],
        $r['totalAssessedAmount'], $r['remainingBalance']);
}
echo '  report lines HEAD prints: ';
echo substr_count((string) file_get_contents($root.'/storage/app/scratch/lane56-head-seed_full_demo_pipeline.php'), "'Ledger —");
echo "\n";
