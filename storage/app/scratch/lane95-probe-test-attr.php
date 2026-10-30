<?php
// Read-only: every test file that uses #[Test] but never imports the attribute class.
// An unimported attribute resolves to a class in the file's own namespace, PHP does not
// complain at parse time, and the file silently contributes zero tests.
$root = dirname(__DIR__, 3);
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/tests', FilesystemIterator::SKIP_DOTS));
$flagged = 0; $checked = 0;
foreach ($it as $f) {
    if (! $f->isFile() || ! preg_match('/Test\.php$/', $f->getFilename())) { continue; }
    $src = (string) file_get_contents($f->getPathname());
    $checked++;
    $uses = preg_match('/#\[Test\b/', $src) === 1;
    $imports = str_contains($src, 'use PHPUnit\Framework\Attributes\Test;')
        || str_contains($src, 'PHPUnit\Framework\Attributes\Test as ');
    if ($uses && ! $imports) {
        printf("  MISSING-IMPORT %s (%d #[Test] uses)\n", str_replace($root.'/', '', $f->getPathname()), preg_match_all('/#\[Test\b/', $src));
        $flagged++;
    }
}
printf("checked %d test file(s); %d use #[Test] without importing it\n", $checked, $flagged);
exit($flagged > 0 ? 1 : 0);
