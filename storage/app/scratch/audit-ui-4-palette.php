<?php
/**
 * Lane A3 pre-flight — classify every raw-palette usage so the conversion is driven by counts,
 * not by eyeballing 13 files. Each hit is put in exactly one bucket by the element it sits on.
 */
$root = str_replace('\\', '/', dirname(__DIR__, 3).'/resources/js/Pages');
$palette = 'emerald|blue|indigo|red|amber|violet|rose|sky|teal|green|orange|purple|cyan|lime|fuchsia|yellow|pink';

$buckets = ['button' => [], 'pill' => [], 'panel' => [], 'text' => [], 'other' => []];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$files = [];
foreach ($it as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.jsx')) {
        $files[] = str_replace('\\', '/', $f->getPathname());
    }
}
sort($files);

foreach ($files as $file) {
    $short = str_replace($root.'/', '', $file);
    foreach (preg_split('/\R/', (string) file_get_contents($file)) as $i => $line) {
        if (! preg_match("/\b(?:bg|text|border|from|to|ring)-(?:{$palette})-\d{2,3}/", $line)) {
            continue;
        }
        $t = trim($line);
        // Only the desk folders are in this pass; Admin catalogues ride along because they share
        // the components, but they are counted separately below.
        $desk = ! str_starts_with($short, 'Admin/');

        if (str_contains($t, '<button') || preg_match('/className="[^"]*(?:px-3|px-4|py-1|py-2)[^"]*"/', $t) && preg_match('/rounded-lg|rounded-xl/', $t) && str_contains($t, 'text-white')) {
            $k = 'button';
        } elseif (preg_match('/rounded-full/', $t) && preg_match('/bg-(?:'.$palette.')-\d/', $t)) {
            $k = 'pill';
        } elseif (preg_match('/^\s*<(div|section|aside)/', $t) && preg_match('/bg-(?:'.$palette.')-(?:50|100)/', $t)) {
            $k = 'panel';
        } elseif (preg_match('/^\s*<(p|span|small|h\d)/', $t) && preg_match('/text-(?:'.$palette.')-\d/', $t) && ! preg_match('/bg-/', $t)) {
            $k = 'text';
        } else {
            $k = 'other';
        }

        $buckets[$k][] = ($desk ? '' : '[admin] ').$short.':'.($i + 1).'  '.mb_substr(preg_replace('/\s+/', ' ', $t), 0, 96);
    }
}

foreach ($buckets as $k => $hits) {
    printf("\n== %-6s %d hit(s)\n", $k, count($hits));
    foreach (array_slice($hits, 0, 6) as $h) {
        echo '   '.$h."\n";
    }
    if (count($hits) > 6) {
        echo '   …'.(count($hits) - 6)." more\n";
    }
}

$total = array_sum(array_map('count', $buckets));
printf("\ntotal raw-palette usages across Pages/: %d\n", $total);
