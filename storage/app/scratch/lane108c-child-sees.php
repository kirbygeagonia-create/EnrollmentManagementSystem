<?php

/**
 * Does the CHILD process see a marker the parent just wrote? Prints SEES / NOT SEES and exits 0 either way.
 *
 * `mutate-lane108c-narrowed-audit.php` needs this because a leg that prints "the tool reported no MISSING" is
 * ambiguous: either the classifier cannot see the shape, or the measurement ran against bytes the child never
 * read. The parent's own re-read cannot settle that — it is the same process that wrote.
 *
 * Usage: php storage/app/scratch/lane108c-child-sees.php <file> <marker>
 */
$args = array_slice($argv, 1);
if (count($args) !== 2) {
    fwrite(STDERR, "REFUSED: needs <file> and <marker>.\n");

    exit(2);
}
[$file, $marker] = $args;
if (! is_file($file)) {
    echo 'NO-FILE';
    exit(0);
}
$src = (string) file_get_contents($file);
echo str_contains($src, $marker) ? 'SEES' : 'NOT SEES';
printf(' [%d byte(s) read]%s', strlen($src), PHP_EOL);
