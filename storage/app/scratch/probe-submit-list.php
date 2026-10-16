<?php
$root = dirname(__DIR__, 3).'/resources/js';
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($it as $f) { if ($f->isFile() && str_ends_with($f->getFilename(), '.jsx')) { $files[] = $f->getPathname(); } }
sort($files);
$a = 0; $b = 0;
foreach ($files as $file) {
    $src = file_get_contents($file);
    if (!preg_match_all('/<[a-z]+\b[^>]*?type="submit"[^>]*?>/s', $src, $all, PREG_OFFSET_CAPTURE)) continue;
    foreach ($all[0] as $m) {
        if (str_contains($m[0], 'form="')) continue;
        $a++;
        $line = substr_count(substr($src, 0, $m[1]), "\n") + 1;
        $auditWouldMatch = (bool) preg_match('/<button\b[^>]*?type="submit"[^>]*?>/', substr($src, $m[1]), $x)
            && str_contains(substr($x[0], 0, strlen($m[0])), 'form="') === false
            && preg_match('/<button\b[^>]*?type="submit"[^>]*?>/', substr($src, $m[1]), $y)
            && strlen($y[0]) === strlen($m[0]);
        if (!$auditWouldMatch) {
            $b++;
            printf("MISSED BY AUDIT  %s:%d\n   %s\n", basename($file), $line, trim(preg_replace('/\s+/', ' ', substr($m[0], 0, 160))));
        }
    }
}
printf("\nno-form submit elements=%d  missed by the audit's regex=%d\n", $a, $b);
