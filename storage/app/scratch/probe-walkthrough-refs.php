<?php

/**
 * Do the walkthrough's own pointers point anywhere?
 *
 * Row 15.1 stranded an office because the dataset did not support a step. A step can strand the
 * same way when the row telling you about it does not exist, and this file cross-references itself
 * constantly (§2.8, §16.8, §12R.1, §15R.4…). Every identifier defined in a table's first cell is
 * collected, then every identifier referenced in prose is looked up in that set.
 */

$path = dirname(__DIR__, 3).'/Documentation/Per-Office-UAT-Walkthrough.md';
$lines = file($path);
if ($lines === false) {
    exit("cannot read walkthrough\n");
}

$defined = [];      // id => line number, from the first cell of a table row
$sections = [];     // "## 12." style

foreach ($lines as $i => $line) {
    if (preg_match('/^## (\d+)\./', $line, $m)) {
        $sections[(int) $m[1]] = true;
    }
    // A row definition looks like: | 12R.1 | do this | ... |  or  | 5.1 | ...
    if (preg_match('/^\|\s*((?:\d+R?\.\d+)|(?:\d+\.\d+[a-z]?))\s*\|/', $line, $m)) {
        $defined[strtolower($m[1])] = $i + 1;
    }
}

// References: §-forms, bare row ids inside prose, and "row 12.10" style.
$refs = [];
foreach ($lines as $i => $line) {
    preg_match_all('/§(\d+R?\.\d+)/', $line, $m, PREG_SET_ORDER);
    foreach ($m as $set) {
        $refs[] = [strtolower($set[1]), $i + 1, '§-row'];
    }
    preg_match_all('/§(\d+)(?![.\d])/', $line, $m2, PREG_SET_ORDER);
    foreach ($m2 as $set) {
        $refs[] = ['section:'.(int) $set[1], $i + 1, '§-section'];
    }
    preg_match_all('/\brow(?:s)? (\d+R?\.\d+)/i', $line, $m3, PREG_SET_ORDER);
    foreach ($m3 as $set) {
        $refs[] = [strtolower($set[1]), $i + 1, 'prose row'];
    }
}

$missing = 0;
$checked = 0;

foreach (array_unique($refs, SORT_REGULAR) as [$id, $lineNo, $kind]) {
    $checked++;
    if (str_starts_with($id, 'section:')) {
        $n = (int) substr($id, 8);
        // §22, §28 and §1 are the docx's registers and this file's own preface; only 2..18 are rows here.
        $isOwn = $n >= 2 && $n <= 18;
        if ($isOwn && ! isset($sections[$n])) {
            $missing++;
            printf("  MISSING section §%-3d referenced on line %d\n", $n, $lineNo);
        }

        continue;
    }
    if (! isset($defined[$id])) {
        $missing++;
        printf("  MISSING %-6s (%s) referenced on line %d\n", strtoupper($id), $kind, $lineNo);
    }
}

printf("\n%d identifiers defined; %d references checked; %d point at nothing\n", count($defined), $checked, $missing);

// Sign-off lines are the sections an office actually closes; compare with what §1 demands.
$signoffs = 0;
foreach ($lines as $line) {
    if (str_starts_with($line, 'Sign-off:')) {
        $signoffs++;
    }
}
preg_match('/All ([a-z]+|(\d+)) sections/i', implode('', $lines), $m);
printf("%d sign-off lines exist; §1's pass criterion says \"All %s sections\".\n", $signoffs, $m[1] ?? '???');
