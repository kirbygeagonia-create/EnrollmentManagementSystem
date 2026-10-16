<?php

/**
 * Re-measure every blade-template line reference in the Registrar decision sheet.
 * A bare `:NN` inherits the last file named on the same line, which is how the sheet is written,
 * so the parser tracks that instead of assuming each reference carries its own filename.
 */

$root = dirname(__DIR__, 3);
$sheet = $root.'/Documentation/Registrar-Print-Decision-Sheet-2026-10-11.md';
$lines = file($sheet);
if ($lines === false) {
    exit("cannot read sheet\n");
}

$refs = [];
// A bare `:NN` inherits the last file named anywhere above it, so the running file has to survive
// across lines and reset at each section heading. Dropping it to null per line made every bare
// reference in the wording tables invisible to this check.
$running = null;
foreach ($lines as $n => $line) {
    if (str_starts_with(ltrim($line), '## ')) {
        $running = null;
    }
    if (! preg_match_all('/([a-z-]+\.blade\.php)?(?<!\d):(\d+)(?:-(\d+))?/', $line, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        continue;
    }
    $current = $running;
    foreach ($m as $set) {
        if ($set[1][0] !== '') {
            $current = $set[1][0];
        }
        if ($current === null) {
            continue;
        }
        // An optional group that did not participate is *absent*, not ''. Treating it as '' would
        // turn every single-line reference into a range ending at 0 and print a shifted window.
        $toRaw = isset($set[3]) ? $set[3][0] : '';
        $refs[] = [$current, (int) $set[2][0], $toRaw === '' ? null : (int) $toRaw, $n + 1];
    }
    $running = $current;
}

$missingFile = 0;
foreach ($refs as [$file, $from, $to, $sheetLine]) {
    $path = $root.'/resources/views/prints/'.$file;
    if (! is_file($path)) {
        echo "NO SUCH FILE  sheet line {$sheetLine}: {$file}\n";
        $missingFile++;

        continue;
    }
    $src = file($path);
    $last = min($to ?? $from, count($src));
    $text = trim(implode(' | ', array_map('trim', array_slice($src, $from - 1, $last - $from + 1))));
    $claim = trim($lines[$sheetLine - 1] ?? '');
    printf("%-24s :%-4s%-4s sheet:%-4d %s\n%-24s %-5s      %s\n",
        $file, $from, $to ?? '', $sheetLine, substr($text, 0, 120),
        '', '', '   claim> '.substr($claim, 0, 120));
}

printf("\n%d references checked, %d point at a file that does not exist\n", count($refs), $missingFile);
