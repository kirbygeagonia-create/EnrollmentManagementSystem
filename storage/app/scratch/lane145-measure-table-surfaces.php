<?php

/**
 * Lane 145 — which desk lists come from the shared control, and which are hand-built tables beside it.
 *
 * The §14 loading fix goes into `DataTable` once rather than into 28 pages, because one mechanism is this
 * repository's rule (lanes 46, 69, 90, 96). But a shared control only protects the surfaces that use it: a page that
 * writes its own `<table>` keeps its own pending behaviour, and a census of the pages would report full coverage
 * while the hand-built tables stay outside it. So the population is measured before the mechanism is relied on:
 * every page under `resources/js/Pages`, split by whether it renders the shared control, renders a raw `<table`, or
 * renders neither.
 *
 * Usage: php storage/app/scratch/lane145-measure-table-surfaces.php [--json=<path>]
 */
$root = dirname(__DIR__, 3);
chdir($root);

$jsonTo = null;
foreach ($argv as $i => $a) {
    if ($i === 0) {
        continue;
    }
    if (str_starts_with($a, '--json=')) {
        $jsonTo = substr($a, 7);

        continue;
    }
    fwrite(STDERR, "REFUSED: this tool takes --json=<path> and received '{$a}'.\n");

    exit(1);
}

$pagesRoot = $root.'/resources/js/Pages';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pagesRoot, FilesystemIterator::SKIP_DOTS));
$files = [];
foreach ($it as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.jsx')) {
        $files[] = $f->getPathname();
    }
}
sort($files);
if ($files === []) {
    fwrite(STDERR, "REFUSED: no page file found — the walk read nothing.\n");

    exit(1);
}

$shared = [];
$rawOnly = [];
$noTable = [];
$both = [];
foreach ($files as $f) {
    $src = (string) file_get_contents($f);
    $rel = str_replace('\\', '/', substr($f, strlen($pagesRoot) + 1));
    $hasShared = preg_match('/<DataTable\b/', $src) === 1;
    // A raw <table> that is NOT inside the shared control's own file. Components under ui/ are excluded by the
    // directory walked, so anything found here is a page-level hand-built table.
    $hasRaw = preg_match('/<table\b/', $src) === 1;
    if ($hasShared && $hasRaw) {
        $both[] = $rel;
    } elseif ($hasShared) {
        $shared[] = $rel;
    } elseif ($hasRaw) {
        $rawOnly[] = $rel;
    } else {
        $noTable[] = $rel;
    }
}

printf("pages walked: %d\n", count($files));
printf("  shared control only : %d\n", count($shared));
printf("  raw <table> only    : %d  <- outside the shared control's reach\n", count($rawOnly));
printf("  both in one page    : %d\n", count($both));
printf("  no table at all     : %d\n", count($noTable));

if (count($shared) + count($rawOnly) + count($both) === 0) {
    fwrite(STDERR, "REFUSED: no page renders a table of any kind, which cannot be true — the reader is blind.\n");

    exit(1);
}

foreach ([['RAW ONLY', $rawOnly], ['BOTH', $both]] as [$label, $list]) {
    if ($list !== []) {
        echo "\n".$label.":\n";
        foreach ($list as $p) {
            echo '  '.$p."\n";
        }
    }
}

if ($jsonTo !== null) {
    $payload = json_encode([
        'pages' => count($files),
        'sharedOnly' => $shared,
        'rawOnly' => $rawOnly,
        'both' => $both,
        'noTable' => $noTable,
    ], JSON_PRETTY_PRINT);
    if (@file_put_contents($jsonTo, $payload) === false) {
        fwrite(STDERR, "REFUSED: could not write '{$jsonTo}'.\n");

        exit(1);
    }
    printf("\nwrote %s\n", $jsonTo);
}

exit(0);
