<?php

/**
 * Lane 116: the column keys each unwired screen shows, so a wiring batch starts from the page rather than from
 * memory. Read-only, and it refuses a page whose list it cannot parse instead of reporting zero columns.
 *
 * Usage: php storage/app/scratch/lane116-sort-candidates.php Page/Name [...]
 */
$root = dirname(__DIR__, 3);

if (count(array_slice($argv, 1)) === 0) {
    fwrite(STDERR, "REFUSED: pass one or more page paths, e.g. resources/js/Pages/Blocking/Index.jsx\n");

    exit(1);
}

$bad = 0;
foreach (array_slice($argv, 1) as $rel) {
    $path = str_starts_with($rel, 'resources/') ? $root.'/'.$rel : $root.'/resources/js/Pages/'.$rel.'.jsx';
    if (! is_file($path)) {
        fwrite(STDERR, "REFUSED: no page at {$rel}\n");
        $bad++;

        continue;
    }
    $src = (string) file_get_contents($path);
    $keys = [];
    if (preg_match('/const columns = useMemo\(\(\) => \[(.*?)\n\s{4}\],/su', $src, $m) === 1) {
        $block = $m[1];
    } elseif (preg_match('/const columns = \[(.*?)\n\s{4}\];/su', $src, $m) === 1) {
        $block = $m[1];
    } else {
        fwrite(STDERR, 'REFUSED: '.basename($path)." carries no column list this reader can parse — open it, do not guess.\n");
        $bad++;

        continue;
    }
    preg_match_all("/\n\s*\{\s*key:\s*'([^']+)',\s*label:\s*'([^']*)'/u", $block, $k, PREG_SET_ORDER);
    printf("%-22s %d column(s)\n", basename($path), count($k));
    foreach ($k as $one) {
        printf("    %-26s %s\n", $one[1], $one[2]);
    }
    if (str_contains($block, 'sortKey:')) {
        echo "    (this page already carries sortKey on some column)\n";
    }
}

exit($bad === 0 ? 0 : 1);
