<?php
/**
 * Change every no-op X.reset({…}) into X.setData({…}), and refuse to do it unless the count is
 * exactly the 33 sites the audit resolved.
 *
 * The audit (audit-reset-literals.php) proved two things that make a plain string swap safe:
 *   - every literal lists exactly the keys its own useForm() declares, in the same order, so
 *     setData (which REPLACES the whole data object, where reset left it alone) drops no field; and
 *   - the '{' always sits on the same line as the call, so replacing the method name changes no
 *     line count — which matters because the authority document cites line ranges inside these
 *     very components.
 *
 * A bulk edit that reports a count rather than asserting one is how a rename quietly misses a file.
 */

$expected = 33;

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3).'/resources/js'));
foreach ($it as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.jsx')) {
        $files[] = $f->getPathname();
    }
}
sort($files);

$apply = in_array('--apply', $argv, true);
$total = 0;
$changed = 0;

foreach ($files as $file) {
    $src = file_get_contents($file);
    $count = substr_count($src, '.reset({');
    if ($count === 0) {
        continue;
    }
    $total += $count;
    $new = str_replace('.reset({', '.setData({', $src);

    // The swap must not change a single line: cite stability, not tidiness.
    if (substr_count($new, "\n") !== substr_count($src, "\n")) {
        fwrite(STDERR, "REFUSE: line count moved in {$file}\n");
        exit(1);
    }
    if (substr_count($new, '.setData({') !== $count || str_contains($new, '.reset({')) {
        fwrite(STDERR, "REFUSE: not every site converted in {$file}\n");
        exit(1);
    }

    printf("  %-58s %d site(s)\n", basename($file), $count);
    $changed++;

    if ($apply) {
        file_put_contents($file, $new);
    }
}

printf("\n%s: files=%d sites=%d expected=%d\n",
    $apply ? 'WROTE' : 'DRY RUN', $changed, $total, $expected);

if ($total !== $expected) {
    fwrite(STDERR, "REFUSE: found {$total} sites, expected exactly {$expected} — re-run the audit\n");
    exit(1);
}
if (! $apply) {
    echo "pass --apply to write\n";
}
