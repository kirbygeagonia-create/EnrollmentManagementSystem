<?php
// Every File.php:NN(-NN)? citation in the docx body, checked against the repository: the
// file must exist somewhere in the source tree, and the cited line must be inside it.
$z = new ZipArchive(); $z->open(dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx');
$xml = $z->getFromName('word/document.xml'); $z->close();
$flat = preg_replace('/<[^>]+>/', ' ', $xml);

$index = [];
$lines = [];
foreach (['app', 'database', 'routes', 'config', 'tests', 'bootstrap', 'resources/views', 'vendor/spatie/laravel-permission/src'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (! $f->isFile() || $f->getExtension() !== 'php') {
            continue;
        }
        $index[basename($f->getPathname())] ??= $f->getPathname();
    }
}

// The class must include '-' or every hyphenated file is reported twice: the real name and a
// phantom tail. clearance-slip.blade.php came back as "NO SUCH FILE slip.blade.php" sixteen times,
// which is exactly the noise that hides a genuinely missing file.
preg_match_all('#([\w.-]+\.php):(\d{2,4})(?:-(\d{2,4}))?#', $flat, $m, PREG_SET_ORDER);
$seen = [];
$missing = 0;
$range = 0;
foreach ($m as $c) {
    [$all, $file, $from, $to] = array_pad($c, 4, '');
    $key = $all;
    if (isset($seen[$key])) {
        continue;
    }
    $seen[$key] = 1;
    if (! isset($index[$file])) {
        echo "NO SUCH FILE   {$all}\n";
        $missing++;
        continue;
    }
    $lines[$index[$file]] ??= count(file($index[$file]));
    $hi = max((int) $from, (int) ($to ?: $from));
    if ($hi > $lines[$index[$file]]) {
        printf("OUT OF RANGE   %-44s cites to line %d, %s has %d\n", $all, $hi, $index[$file], $lines[$index[$file]]);
        $range++;
    }
}
echo "\n".count($seen)." distinct citations; {$missing} name a file that does not exist; {$range} cite past the end of the file\n";
// Two names this project deleted outright. $index holds files that EXIST, so a name in it is not a
// ghost; a ghost is cited-but-absent, which {$missing} already counts. Printed as facts so nobody
// has to re-read this file to know which way round the check runs.
foreach (['CaptureProfileRequest.php', 'DevReferenceDataSeeder.php'] as $maybe) {
    $cited = false;
    foreach (array_keys($seen) as $key) {
        if (str_starts_with($key, $maybe.':')) {
            $cited = true;

            break;
        }
    }
    printf("  %-28s exists=%-3s cited=%s\n", $maybe, isset($index[$maybe]) ? 'yes' : 'no', $cited ? 'yes' : 'no');
}
