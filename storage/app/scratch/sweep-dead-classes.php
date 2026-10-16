<?php
/**
 * Every class under app/ that no other tracked file names. A class nothing references is
 * either dead code or loaded by convention (providers, middleware aliases, artisan
 * commands, Eloquent observers, enum casts by string) — the scan records where each name
 * was looked for so a hit can be judged rather than assumed.
 */
$root = dirname(__DIR__, 3);
$searchDirs = ['app', 'bootstrap', 'config', 'database', 'routes', 'tests', 'tools', 'resources', 'lang'];
$looseFiles = ['composer.json', 'package.json', 'phpunit.xml', 'pint.json', 'phpstan.neon', 'artisan',
    'DEPLOYMENT.md', 'AGENTS.md', 'README.md'];

$corpus = [];   // path => contents, excluding the class files themselves
$classFiles = [];
foreach ($searchDirs as $dir) {
    if (! is_dir($root.'/'.$dir)) { continue; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (! $f->isFile()) { continue; }
        $ext = $f->getExtension();
        if (! in_array($ext, ['php', 'js', 'jsx', 'blade', 'json', 'md', 'mjs'], true) && $ext !== 'php') { continue; }
        $rel = substr($f->getPathname(), strlen($root) + 1);
        if (! str_starts_with($rel, 'app'.DIRECTORY_SEPARATOR)) {
            $corpus[$rel] = file_get_contents($f->getPathname());
        }
    }
}
foreach ($looseFiles as $lf) {
    if (is_file($root.'/'.$lf)) { $corpus[$lf] = file_get_contents($root.'/'.$lf); }
}
foreach (glob($root.'/.github/workflows/*.yml') ?: [] as $y) {
    $corpus['.github/'.basename(dirname($y)).'/'.$y] = file_get_contents($y);
}
foreach (glob($root.'/Documentation/*.md') ?: [] as $m) {
    $corpus['Documentation/'.basename($m)] = file_get_contents($m);
}

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile() && $f->getExtension() === 'php') {
        $classFiles[substr($f->getPathname(), strlen($root) + 1)] = file_get_contents($f->getPathname());
    }
}

$dead = [];
foreach ($classFiles as $rel => $own) {
    $name = basename($rel, '.php');
    if ($name === 'Http' || $name === 'Kernel') { continue; }
    $hits = [];
    foreach ($corpus as $where => $body) {
        if (preg_match('/\b'.preg_quote($name, '/').'\b/', $body)) { $hits[] = $where; }
    }
    // A class that references itself (e.g. self::CASE) still needs an outside user.
    if ($hits === []) {
        // Also check other class files in app/, which the corpus excludes.
        foreach ($classFiles as $otherRel => $otherBody) {
            if ($otherRel === $rel) { continue; }
            if (preg_match('/\b'.preg_quote($name, '/').'\b/', $otherBody)) { $hits[] = $otherRel; }
        }
    }
    if ($hits === []) { $dead[$rel] = $name; }
}

echo count($classFiles)." classes scanned, ".count($dead)." named by no other file:\n";
foreach ($dead as $rel => $name) {
    echo '  '.$rel."\n";
}
