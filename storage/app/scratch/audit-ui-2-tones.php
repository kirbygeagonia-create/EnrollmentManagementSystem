<?php
/**
 * UI/UX lane 1 — do the 39 local tone maps agree with each other?
 *
 * Duplication is a maintenance smell. Two desks painting the SAME status in two different colours
 * is something a registrar sees. This prints, per status word, every distinct tone assigned to it
 * and which files assign it — so the answer is a table, not an argument.
 */
$root = str_replace('\\', '/', dirname(__DIR__, 3).'/resources/js');
$pages = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/Pages', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.jsx')) {
        $pages[str_replace('\\', '/', $f->getPathname())] = (string) file_get_contents($f->getPathname());
    }
}

// word => tone => [files]
$byWord = [];
$shared = [];

foreach ($pages as $file => $src) {
    $short = str_replace($root.'/Pages/', '', $file);
    // Every `key: 'tone'` pair inside a const ...Tone.../...Map.../tones object literal.
    if (! preg_match_all('/const\s+(\w*(?:tone|Tone|Map|tones)\w*)\s*=\s*\{(.*?)\n\s*\};/s', $src, $objs, PREG_SET_ORDER)) {
        continue;
    }
    foreach ($objs as $o) {
        $body = $o[2];
        if (! preg_match_all("/'([a-zA-Z]+)'\s*:\s*'([a-zA-Z0-9-]+)'/", $body, $pairs, PREG_SET_ORDER)) {
            continue;
        }
        foreach ($pairs as $p) {
            [, $word, $tone] = $p;
            if (in_array($tone, ['bg-', 'text-'], true)) {
                continue;
            }
            $byWord[$word][$tone][] = $short;
        }
    }
}

$divergent = 0;
foreach ($byWord as $word => $tones) {
    if (count($tones) < 2) {
        continue;
    }
    $divergent++;
    printf("DIVERGENT  %-22s %s\n", $word, implode(' | ', array_map(
        fn ($t, $files) => sprintf("%s (%d file%s: %s)", $t, count($files), count($files) > 1 ? 's' : '', implode(', ', array_slice(array_unique($files), 0, 4))),
        array_keys($tones),
        $tones
    )));
}

printf(
    "\n%d distinct status words mapped across %d pages; %d of them are painted more than one way.\n",
    count($byWord),
    count($pages),
    $divergent
);

// Where does the off-scale typography actually live?
$counts = [];
foreach ($pages as $file => $src) {
    $n = preg_match_all('/[a-z]+-\[\d+[^\]]*\]/', $src, $m);
    if ($n > 0) {
        $counts[str_replace($root.'/Pages/', '', $file)] = $n;
    }
}
arsort($counts);
$total = array_sum($counts);
printf("\n%d arbitrary-value utilities across %d page files; top offenders:\n", $total, count($counts));
foreach (array_slice($counts, 0, 10, true) as $f => $n) {
    printf("   %4d  %s\n", $n, $f);
}

// What does the shared module already offer?
echo "\nstatusLabel.js exports:\n";
$sharedSrc = (string) file_get_contents($root.'/Components/ui/statusLabel.js');
preg_match_all('/export (?:const|function) (\w+)/', $sharedSrc, $ex);
echo '   '.implode(', ', $ex[1])."\n";
