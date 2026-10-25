<?php

/**
 * Read a cited span: print the lines a pointer lands on, each one's enclosing construct, and refuse a target
 * whose file or line the repository cannot show.
 *
 * The anchor checker binds a pointer to an identifier named in the sentence. Most of the remaining queue is
 * sentences that name none — so the only check left is the other direction: ask the file what owns the cited
 * lines and read that against the claim. This tool prints the answer for a target given on the command line so
 * the read is of the code, not of a summary line.
 *
 *   php storage/app/scratch/lane70-read-span.php ExamController.php:355-362 WorkflowService.php:255
 *   php storage/app/scratch/lane70-read-span.php --functions   # only the enclosing construct of each endpoint
 */
$root = dirname(__DIR__, 3);
$functionsOnly = false;
$targets = [];
foreach ($argv as $a) {
    if ($a === '--functions') {
        $functionsOnly = true;

        continue;
    }
    if (preg_match('/^(.+?):(\d+)(?:-(\d+))?$/u', $a, $m) === 1) {
        $targets[] = [$m[1], (int) $m[2], (int) ($m[3] ?? $m[2])];
    }
}
if ($targets === []) {
    echo "usage: php storage/app/scratch/lane70-read-span.php File.php:NN[-MM] [...]\n";
    exit(1);
}

$index = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
        $index[basename($f->getPathname())][] = $f->getPathname();
    }
}
foreach (['resources/js/Components/ui', 'resources/js/Pages', 'resources/js/Pages/Auth'] as $extra) {
    foreach (is_dir($root.'/'.$extra) ? new RecursiveDirectoryIterator($root.'/'.$extra, FilesystemIterator::SKIP_DOTS) : [] as $f) {
        if ($f->isFile() && str_ends_with($f->getFilename(), '.jsx')) {
            $index[basename($f->getPathname())][] = $f->getPathname();
        }
    }
}
foreach (['database/seeders' => '.php', 'resources/views/print' => '.blade.php', 'resources/views' => '.blade.php'] as $dir => $ext) {
    foreach (is_dir($root.'/'.$dir) ? new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS) : [] as $f) {
        if ($f->isFile() && str_ends_with($f->getFilename(), $ext)) {
            $index[basename($f->getPathname())][] = $f->getPathname();
        }
    }
}

foreach ($targets as [$base, $from, $to]) {
    $paths = array_unique($index[$base] ?? []);
    if ($paths === []) {
        printf("REFUSED: no file named %s in app/, resources/js, database/seeders or resources/views.\n", $base);
        exit(1);
    }
    if (count($paths) > 1) {
        printf("REFUSED: %s is ambiguous (%s) — name the path.\n", $base, implode(', ', array_map(fn ($p) => str_replace($root.'/', '', $p), $paths)));
        exit(1);
    }
    $path = $paths[0];
    $fl = (array) file($path);
    $n = count($fl);
    if ($to > $n || $from > $n) {
        printf("REFUSED: %s has %d line(s); %s:%d is past the end.\n", str_replace($root.'/', '', $path), $n, $base, max($from, $to));
        exit(1);
    }
    printf("=== %s:%d-%d  (%d lines in the file)\n", str_replace($root.'/', '', $path), $from, $to, $n);
    $owner = static function (array $fl, int $line, string $path): string {
        $pat = str_ends_with($path, '.jsx')
            ? '/^\s*(?:export\s+)?(?:default\s+)?(?:function|const)\s+(\w+)|^\s*const\s+(\w+)\s*=\s*(?:\([^)]*\)|\w)\s*=>|\/\*\s*Routes?\s*to/u'
            : '/^\s{0,8}(?:public|private|protected|static)?\s*function\s+(\w+)|class\s+(\w+)|\/\*\*|public function\s+(\w+)/u';
        for ($i = $line; $i >= 1; $i--) {
            if (preg_match($pat, (string) ($fl[$i - 1] ?? ''), $m) === 1) {
                for ($k = 1; $k < count($m); $k++) {
                    if (($m[$k] ?? '') !== '') {
                        return ($k >= 3 ? 'method ' : 'construct ').$m[$k].' (own line '.$i.')';
                    }
                }
            }
        }

        return 'no enclosing construct found';
    };
    if ($functionsOnly) {
        printf("  :%d  %s\n  :%d  %s\n", $from, $owner($fl, $from, $path), $to, $owner($fl, $to, $path));

        continue;
    }
    printf("  start is inside: %s\n  end   is inside: %s\n", $owner($fl, $from, $path), $owner($fl, $to, $path));
    for ($i = $from; $i <= $to; $i++) {
        printf("%5d | %s\n", $i, rtrim((string) ($fl[$i - 1] ?? '')));
    }
    echo "\n";
}
exit(0);
