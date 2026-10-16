<?php
/**
 * Does the acceptance script still render as tables after the 7.17 edit?
 *
 * A markdown table breaks silently: one stray pipe inside a cell splits it into an extra column
 * and GitHub/Word shows a shifted row, which is the worst failure mode for a document that a
 * tester follows line by line at a desk. So: group contiguous | ... | lines into tables and check
 * every row against the column count its own header row declares. Also flag an unbalanced ** on
 * any edited-style line, since a forgotten closer bolds the rest of a section.
 *
 * Read-only.
 */

$file = $argv[1] ?? dirname(__DIR__, 3).'/Documentation/Per-Office-UAT-Walkthrough.md';
$lines = file($file, FILE_IGNORE_NEW_LINES);

$tables = 0;
$bad = 0;
$i = 0;
$count = $lines;

while ($i < count($count)) {
    $line = $count[$i];
    if (! preg_match('/^\|/', $line)) {
        $i++;
        continue;
    }
    // Collect the contiguous block of pipe lines: that is one table.
    $start = $i;
    while ($i < count($count) && preg_match('/^\|/', $count[$i])) {
        $i++;
    }
    $block = array_slice($count, $start, $i - $start);
    if (count($block) < 2) {
        continue;
    }
    $tables++;
    $cols = fn (string $l) => substr_count(preg_replace('/\\\\\|/', '', $l), '|');
    // The header is row 0; row 1 is the |---|---| separator, whose count is what the header
    // declares. Compare every row to that.
    $want = $cols($block[0]);
    $sepOk = count($block) > 1 && $cols($block[1]) === $want;
    foreach ($block as $r => $row) {
        $got = $cols($row);
        if ($got !== $want) {
            $bad++;
            printf("BAD COLUMNS  line %d (table starting %d): %d pipes, header says %d\n   %s\n",
                $start + $r + 1, $start + 1, $got, $want, mb_substr($row, 0, 120));
        }
    }
    if (! $sepOk) {
        $bad++;
        printf("NO SEPARATOR   table at line %d: row 2 is %s\n", $start + 1, mb_substr($block[1] ?? '', 0, 60));
    }
}

// Unbalanced emphasis, judged on the LOGICAL block: markdown lets a ** run span wrapped lines
// inside one paragraph, so counting per physical line cries wolf. A blank line, a table row, or
// the start of a new list item ENDS the current block — and ends it by flushing, not by silently
// carrying the old text forward, which is what an earlier draft of this check did.
$blocks = [];          // [startLine => concatenated text]
$cur = -1;
foreach ($lines as $n => $line) {
    $startsNew = trim($line) === '' || preg_match('/^\|/', $line)
        || preg_match('/^\s*(\d+\.|[-*])\s/', $line);
    if ($startsNew) {
        $cur = -1;
    }
    if (trim($line) === '' || preg_match('/^\|/', $line)) {
        continue;
    }
    if ($cur === -1) {
        $cur = $n;
        $blocks[$cur] = '';
    }
    $blocks[$cur] .= $line;
}
foreach ($blocks as $startLine => $text) {
    if (($k = substr_count($text, '**')) % 2 !== 0) {
        $bad++;
        printf("ODD ** IN BLOCK   starting line %d (%d markers): %s\n",
            $startLine + 1, $k, mb_substr($text, 0, 120));
    }
}

printf("\ntables=%d  problems=%d  lines=%d\n", $tables, $bad, count($lines));
echo $bad === 0 ? "OK — every table row matches its header, no stray emphasis\n" : "SEE ABOVE\n";
