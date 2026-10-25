<?php

/**
 * Read the queue: for each bare pointer the anchor checker cannot bind, print the construct the cited line
 * actually sits inside, and the document's own claim beside it.
 *
 * The anchor tool needs an identifier in the sentence to pair against, and these sentences name none — so the
 * check is the other direction: ask the file which method owns the line, and ask the row what it says the action
 * is. Written for the RegistrarController.php tranche of the 110-row UNBOUND queue on 2026-10-25.
 */
$root = dirname(__DIR__, 3);
$lines = (array) file($root.'/storage/app/scratch/docx-flat.txt');
$rowsToRead = [371, 713, 1566, 2213, 2214, 2215, 2435];
foreach ($rowsToRead as $n) {
    $s = trim((string) ($lines[$n - 1] ?? ''));
    printf("--- dump row %d\n%s\n\n", $n, mb_substr(preg_replace('/\s+/u', ' ', $s), 0, 700));
}

$targets = [
    'RegistrarController.php' => [156, 244, 249, 266, 360, 382, 405, 413, 428, 437, 452, 464, 490],
    'ClearanceController.php' => [380, 404, 408],
];
foreach ($targets as $base => $nums) {
    $hits = glob($root.'/app/Http/Controllers/**/'.$base) ?: [];
    $hits = array_merge($hits, glob($root.'/app/Http/Controllers/*/'.$base) ?: []);
    $path = $hits[0] ?? null;
    if ($path === null) {
        printf("%s: NOT FOUND by glob — refusing to guess\n", $base);

        continue;
    }
    $fl = (array) file($path);
    printf("=== %s (%d lines)\n", $path, count($fl));
    foreach ($nums as $n) {
        $enc = '';
        for ($i = $n; $i >= 1; $i--) {
            if (preg_match('/^\s{4}public function (\w+)/u', (string) ($fl[$i - 1] ?? ''), $m) === 1) {
                $enc = $m[1];

                break;
            }
        }
        printf("  :%-4d  inside %-22s  line reads: %s\n", $n, $enc === '' ? '(no public method above)' : $enc.'()',
            trim(mb_substr(rtrim((string) ($fl[$n - 1] ?? '')), 0, 78)));
    }
    echo "\n";
}
