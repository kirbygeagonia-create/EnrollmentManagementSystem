<?php
/**
 * Lane 69 probe 2: the 15 bare `:NNN` continuations the document writes near an EvaluationController.php
 * citation. One added `use` line moved that file by one, so each bare number must be attributed by
 * reading — a bare number cannot be checked by any file-aware sweep, and the lane 51 pass already found
 * one such number that belonged to a different file than the citation it sat beside.
 *
 * Prints ±150 bytes of the sentence and, beside it, what the candidate file holds at that line today.
 * Read-only.
 */

$root = dirname(__DIR__, 3);
$text = (string) file_get_contents($root.'/storage/app/scratch/docx-flat.txt');
$controller = explode("\n", (string) file_get_contents($root.'/app/Http/Controllers/Evaluation/EvaluationController.php'));

// Every bare token, plus the file the sentence named last before it (that is the only attribution text offers).
preg_match_all('/(?<![\\w.\\-]):\s*([0-9]+)(?:-([0-9]+))?/', $text, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
$rows = [];
foreach ($all as $m) {
    $pos = $m[0][1];
    $before = substr($text, max(0, $pos - 400), min(400, $pos));
    if (! preg_match('/EvaluationController\.php/u', $before)) {
        continue;
    }
    // Whichever file name sits closest on the left is what the sentence was naming.
    preg_match_all('/([A-Za-z0-9_\/\\\\.-]+\.(?:php|blade\.php|jsx|js))(?!)/u', $before, $files);
    $nearest = end($files[1]);
    $rows[] = [
        'token' => $m[0][0],
        'a' => (int) $m[1][0],
        'b' => isset($m[2][0]) && $m[2][0] !== '' ? (int) $m[2][0] : (int) $m[1][0],
        'nearest' => $nearest,
        'ctx' => preg_replace('/\s+/u', ' ', substr($text, max(0, $pos - 110), 240)),
    ];
}

// The token that IS the file's own citation (file name immediately before the colon) is not a bare one.
$rows = array_values(array_filter(
    $rows,
    fn ($r) => ! preg_match('/EvaluationController\.php:\s*'.$r['a'].'$/u', preg_replace('/\s+/u', ' ', substr($text, max(0, (int) (strpos($text, $r['ctx'])) - 60), 60)))
));

echo count($rows)." bare numbers sit within 400 bytes of an EvaluationController.php mention:\n\n";
foreach ($rows as $r) {
    $at = $r['a'] - 1;
    $line = $controller[$at] ?? '(past the end)';
    printf(
        "%s   nearest named file: %s\n    sentence: …%s…\n    controller.php:%d reads  %s\n\n",
        $r['token'],
        $r['nearest'],
        $r['ctx'],
        $r['a'],
        trim($line) === '' ? '(blank)' : mb_substr(trim($line), 0, 110)
    );
}
