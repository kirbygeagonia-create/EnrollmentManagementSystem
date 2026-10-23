<?php

/** Pass 180 target probe: where the register counts live, and what §24.17/§24.18 say today. */
$flat = explode("\n", (string) file_get_contents('storage/app/scratch/docx-flat.txt'));

$show = function (int $i, int $len = 400): void {
    global $flat;
    echo str_pad((string) ($i + 1), 5, ' ', STR_PAD_LEFT).': '.substr(trim($flat[$i]), 0, $len)."\n\n";
};

echo "=== last rows of §28.3 (to find the register's highest G-id) ===\n";
$g = [];
foreach ($flat as $i => $line) {
    if (preg_match('/^ROW \d+: (G-\d+) \|/', $line, $m) === 1) {
        $g[$m[1]] = $i;
    }
}
ksort($g, SORT_NATURAL);
$last = array_slice($g, -3, null, true);
foreach ($last as $id => $i) {
    echo $id.' at line '.($i + 1)."\n";
}

echo "\n=== every line naming a count of register rows / §28.3 ===\n";
foreach ($flat as $i => $line) {
    if (preg_match('/(register|§28\.3|§28 G-)[^\n]{0,120}?\b(\d{2})\b|\b(\d{2})\b[^\n]{0,60}(register rows|rows in §28|finding rows)/', $line, $m) === 1) {
        $snippet = preg_replace('/\s+/', ' ', substr(trim($line), 0, 300));
        echo ($i + 1).': '.$snippet."\n\n";
    }
}

echo "=== §24.17 intro + §24.18's population row, full ===\n";
foreach ([2160, 2204] as $n) {
    $show($n - 1, 1400);
}
