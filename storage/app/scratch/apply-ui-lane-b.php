<?php
/**
 * Lane B — put the three off-scale font sizes onto the type scale.
 *
 * A rename, not a restyle: text-[11px] -> text-2xs and so on, where the token is defined as a
 * plain string so it emits `font-size` and nothing else. The measured expectation is asserted
 * before anything is written: 104 occurrences, 58 + 43 + 3, and none left afterwards.
 */
$root = str_replace('\\', '/', dirname(__DIR__, 3).'/resources/js/Pages');
$apply = in_array('--apply', $argv, true);

$map = [
    'text-[11px]' => 'text-2xs',
    'text-[10px]' => 'text-3xs',
    'text-[9px]' => 'text-4xs',
];
$expected = ['text-[11px]' => 43, 'text-[10px]' => 58, 'text-[9px]' => 3];

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.jsx')) {
        $files[] = str_replace('\\', '/', $f->getPathname());
    }
}
sort($files);

$tally = array_fill_keys(array_keys($map), 0);
$changed = 0;

foreach ($files as $file) {
    $src = (string) file_get_contents($file);
    $new = $src;
    $here = [];

    foreach ($map as $from => $to) {
        $n = substr_count($new, $from);

        if ($n > 0) {
            $here[] = "{$from}×{$n}";
            $tally[$from] += $n;
            $new = str_replace($from, $to, $new);
        }
    }

    if ($here === []) {
        continue;
    }

    $changed++;
    printf("  %-40s %s\n", str_replace($root.'/', '', $file), implode('  ', $here));

    if ($apply) {
        file_put_contents($file, $new);
    }
}

printf("\n%d file(s) touched, %d occurrence(s) replaced\n", $changed, array_sum($tally));

$fail = false;
foreach ($expected as $from => $want) {
    if ($tally[$from] !== $want) {
        echo "MISMATCH: {$from} seen {$tally[$from]}, expected {$want}\n";
        $fail = true;
    }
}

$total = array_sum($tally);
if ($total !== 104) {
    echo "MISMATCH: {$total} occurrences, expected 104\n";
    $fail = true;
}

if ($apply) {
    $left = 0;
    foreach ($files as $file) {
        $left += preg_match_all('/text-\[(?:9|10|11)px\]/', (string) file_get_contents($file));
    }
    echo $left === 0
        ? "post-check: no off-scale text-[Npx] remains under Pages\n"
        : "POST-CHECK FAILED: {$left} still present\n";
    $fail = $fail || $left !== 0;
}

if ($fail) {
    fwrite(STDERR, "REFUSE: counts did not match the measurement\n");
    exit(1);
}

echo $apply ? "WROTE\n" : "DRY RUN — pass --apply\n";
