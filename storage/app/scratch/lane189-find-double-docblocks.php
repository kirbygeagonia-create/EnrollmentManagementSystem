<?php
// Finds two consecutive docblocks in the files this batch repaired: the patch that added a docblock above a
// method that already had one leaves the OLD sentence attached to whatever declaration follows it, which reads
// as documentation of the wrong thing. Prints file, line, and the first line of each half.
$files = array_slice($argv, 1);
if ($files === []) {
    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('tests'));
    foreach ($it as $f) {
        if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
            $files[] = $f->getPathname();
        }
    }
}
$hits = 0;
foreach ($files as $path) {
    $lines = file($path) ?: [];
    for ($i = 0; $i + 1 < count($lines); $i++) {
        if (preg_match('/^\s*\*\/\s*$/', $lines[$i]) === 1 && preg_match('/^\s*\/\*\*/', $lines[$i + 1]) === 1) {
            $first = trim(preg_replace('/\s+/', ' ', (string) ($lines[$i - 1] ?? '')));
            echo '  '.$path.':'.($i + 1)."  …ends a docblock, next line opens another (previous text: ".substr($first, 0, 70).")\n";
            $hits++;
        }
    }
}
printf("%d back-to-back docblock pair(s) across %d file(s).\n", $hits, count($files));
