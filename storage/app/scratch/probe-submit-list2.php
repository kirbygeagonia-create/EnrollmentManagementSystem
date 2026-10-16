<?php
$root = dirname(__DIR__, 3).'/resources/js';
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($it as $f) { if ($f->isFile() && str_ends_with($f->getFilename(), '.jsx')) { $files[] = $f->getPathname(); } }
sort($files);
$n = 0;
foreach ($files as $file) {
    $src = file_get_contents($file);
    if (! preg_match_all('/<button\b[^>]*?type="submit"[^>]*?>/s', $src, $bm, PREG_OFFSET_CAPTURE)) continue;
    foreach ($bm[0] as $w) {
        if (str_contains($w[0], 'form="')) continue;
        $n++;
        printf("%2d %-46s:%-4d %s\n", $n, basename($file), substr_count(substr($src,0,$w[1]),"\n")+1,
            trim(preg_replace('/\s+/',' ', substr($w[0],0,64))));
    }
}
printf("total no-form buttons seen by the audit's branch = %d\n", $n);
