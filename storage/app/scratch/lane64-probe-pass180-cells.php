<?php

/** Pass 180 probe: the exact cell structures the two cloned rows must match. */
$flat = explode("\n", (string) file_get_contents('storage/app/scratch/docx-flat.txt'));
$after = function (string $heading) use ($flat): array {
    $out = [];
    $hit = false;
    foreach ($flat as $line) {
        if (! $hit) {
            if (preg_match('/^P \d+: '.preg_quote($heading, '/').' /', $line) === 1) {
                $hit = true;
            }

            continue;
        }
        if (preg_match('/^ROW \d+: (.*)$/', $line, $m) === 1) {
            $out[] = array_map('trim', explode(' | ', $m[1]));

            continue;
        }
        if (preg_match('/^P \d+: \d+\.\d+ /', $line) === 1) {
            break;
        }
    }

    return $out;
};
foreach (['28.3', '24.17'] as $h) {
    $rows = $after($h);
    echo "=== §{$h}: ".count($rows).' rows ==='."\n";
    foreach ($rows as $i => $cells) {
        echo ($i === 0 ? 'HEADER' : 'row  ').count($cells).' cells | first='.($cells[0] ?? '').
            ($i === 0 ? ' | '.implode(' ;; ', $cells) : '')."\n";
    }
    echo "\n";
}
