<?php
/**
 * Lane 69 probe: one added `use` line at the top of EvaluationController.php moved every line below it
 * by one, so every citation the authority docx makes into that file has to move too — and the dangerous
 * half is the bare continuations (`… , :272`), which name no file and therefore no file-aware checker
 * can see them.
 *
 * This prints both lists from the flat dump: every explicit EvaluationController.php range, and every
 * bare `:NNN` with the sentence before it, so each one can be attributed by reading rather than by
 * arithmetic. Read-only: it writes nothing.
 */

$dump = dirname(__DIR__, 3).'/storage/app/scratch/docx-flat.txt';
$text = (string) file_get_contents($dump);

echo "=== EXPLICIT citations into EvaluationController.php ===\n";
$explicit = [];
$at = 0;
while (($pos = strpos($text, 'EvaluationController.php', $at)) !== false) {
    $tail = substr($text, $pos, 70);
    if (preg_match('/^EvaluationController\.php:\s*([0-9]+)(?:-([0-9]+))?/', $tail, $m)) {
        $explicit[] = [$pos, $m[1], $m[2] ?? null];
    }
    $at = $pos + 24;
}
sort($explicit);
foreach ($explicit as [$pos, $a, $b]) {
    printf("  byte %-8d :%s-%s   …%s\n", $pos, $a, $b ?? $a, preg_replace('/\s+/u', ' ', substr($text, max(0, $pos - 60), 60)));
}
echo '  count: '.count($explicit)."  (all of these need +1 when the cited line is >= 46)\n";

echo "\n=== BARE :NNN tokens, with what names a file before them ===\n";
preg_match_all('/(?<![\\w.\\-]):\s*([0-9]+)(?:-([0-9]+))?/', $text, $bare, PREG_SET_ORDER);
$rows = [];
foreach ($bare as $m) {
    $pos = strpos($text, $m[0]);
    $before = preg_replace('/\s+/u', ' ', substr($text, max(0, $pos - 260), 260));
    // The nearest file name in the sentence decides whose line this is.
    preg_match_all('/([\\w\\/\\.-]+\\.(?:php|blade\\.php|jsx|js))/', $before, $files);
    $nearest = $files[1] ?? [];
    $rows[] = [$m[1], $m[2] ?? $m[1], $nearest === [] ? '(no file in the last 260 bytes)' : end($nearest), substr($before, -90)];
}
usort($rows, fn ($x, $y) => strcmp($x[2], $y[2]));
$controller = array_filter($rows, fn ($r) => str_contains($r[2], 'EvaluationController'));
foreach ($controller as $r) {
    printf("  :%s-%s  nearest file: %s\n      …%s\n", $r[0], $r[1], $r[2], $r[3]);
}
echo '  bare tokens attributed to EvaluationController.php: '.count($controller)."\n";
echo '  bare tokens in total: '.count($rows)."\n";
