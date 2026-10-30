<?php

/**
 * Read-only: does the requirement matrix cover every heading the development prompt enumerates?
 *
 * The first audit pass of this register scored 14 of the prompt's headings, and lane 86 recorded why: a register
 * built from the gaps it found cannot find an unvisited section, so a section with no gap id is reported as silence.
 * That lesson is now a measurement rather than a warning, because the matrix has since grown three more sweeps of
 * its own (§7, §8 and the gap tables) and a coverage claim that was true after one pass is not true after four.
 *
 * It answers three questions and refuses before answering any of them wrongly:
 *   population — how many headings carry enumerated requirement lines, and how many lines each holds;
 *   coverage — whether the matrix names that section as a number, as a parent (§6 for §6.4), or not at all;
 *   and the totals, so UNVISITED is a count the reader can check rather than an absence the tool asserts.
 *
 * A parent citation is NOT counted as coverage: §6.4 and §6 are different requirements, and the register's own
 * numbering convention (§NN <letter>-<number> collides across chapters) is why one section number can silently
 * answer for another. PARENT-ONLY rows are printed for a reader, not cleared.
 *
 * Usage: php storage/app/scratch/lane92-prompt-coverage.php [--prompt=<path>] [--matrix=<path>] [--all]
 */

$root = dirname(__DIR__, 3);
// argv[0] is this script's own path, not an argument: seeding the prompt from it made the probe read itself, find
// no `#` headings in its own source, and refuse — which is the only reason the defect was caught at all.
$args = array_slice($argv, 1);
$showAll = false;
$trace = false;
foreach ($args as $a) {
    if (str_starts_with($a, '--prompt=')) {
        $prompt = substr($a, 9);
    } elseif (str_starts_with($a, '--matrix=')) {
        $matrix = substr($a, 9);
    } elseif ($a === '--all') {
        $showAll = true;
    } elseif ($a === '--trace') {
        $trace = true;
    } else {
        fwrite(STDERR, "REFUSED: unknown flag {$a} — this probe reads --prompt=, --matrix=, --all and --trace only.\n");

        exit(1);
    }
}
if (! isset($prompt) || $prompt === '') { $prompt = 'C:\Users\ADMIN\Downloads\EnrollmentManagementSystem_Optimized_AI_Development_Prompt.md'; }
$matrix ??= $root.'/Documentation/Prompt-Requirement-Matrix-2026-10-26.md';

foreach (['prompt' => $prompt, 'matrix' => $matrix] as $what => $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "REFUSED: the {$what} file is missing at {$path} — a coverage verdict read from nothing is a pass about nothing.\n");

        exit(1);
    }
}

// ---- the prompt's own structure -------------------------------------------------------------------------------

$promptLines = explode("\n", str_replace("\r\n", "\n", (string) file_get_contents($prompt)));
$headings = [];
$current = null;
foreach ($promptLines as $text) {
    if (preg_match('/^#{1,6}\s+(.*)$/', $text, $m)) {
        $title = trim($m[1]);
        // A heading's number is whatever leads it: "6.4 Returns, holds…" or "4. Required Initial Audit".
        $num = preg_match('/^(\d+(?:\.\d+)*)[.\s]/', $title, $nm) === 1 ? $nm[1] : '';
        $current = ['num' => $num, 'title' => $title, 'lines' => 0, 'lineTexts' => []];
        $headings[] = $current;

        continue;
    }
    if ($current === null) {
        continue;
    }
    if (preg_match('/^\s*(?:[-*]\s+|\d+\.\s+|\[[ xX]\]\s+)/', $text)) {
        $i = count($headings) - 1;
        $headings[$i]['lines']++;
        $headings[$i]['lineTexts'][] = $text;
    }
}

$numbered = array_values(array_filter($headings, fn (array $h): bool => $h['num'] !== ''));
$populated = array_values(array_filter($headings, fn (array $h): bool => $h['lines'] > 0));
$totalLines = array_sum(array_column($headings, 'lines'));

if ($headings === [] || $totalLines === 0) {
    fwrite(STDERR, "REFUSED: the prompt yielded no headings and no enumerated lines — the reader matched nothing.\n");

    exit(1);
}

// ---- what the matrix names ------------------------------------------------------------------------------------

$matrixText = str_replace("\r", '', (string) file_get_contents($matrix));
$exact = [];
$proseOnly = [];
// Coverage is a CLAIM about a section, and this register makes claims in a table row. A `§NN` appearing in prose is
// not one — measured the hard way: the section that records which headings were unvisited names each of them with
// its sigil, and a prose-reading census then reported them visited. So prose tokens are collected into PROSE-ONLY
// and printed, and only a table column whose header names the prompt can mark a heading covered.
if (preg_match_all('/§\s*(\d+(?:\.\d+)*)/', $matrixText, $m)) {
    foreach ($m[1] as $n) {
        $proseOnly[$n] = true;
    }
}
$matrixLines = explode("\n", str_replace("\r\n", "\n", $matrixText));
// A bare cell of digits is NOT a prompt section: the gap tables carry a phase column whose cells read `5`, and
// reading every numeric cell would credit §5 with coverage it never claimed. The register's own rule applies — a
// word is a verdict only in a column that can hold one — so the column is found by its header and only that column
// is read.
$tables = 0;
$columnsRead = 0;
$cellOf = function (string $line): array {
    $line = trim($line);
    if (! str_starts_with($line, '|')) {
        return [];
    }

    return array_map('trim', explode('|', trim($line, '|')));
};
$promptCol = null;
foreach ($matrixLines as $i => $line) {
    $cells = $cellOf($line);
    $next = $matrixLines[$i + 1] ?? '';
    if ($cells !== [] && $cellOf($next) !== [] && preg_match('/^[\s|:-]+$/', trim($next, '|'))) {
        // A header row followed by the separator row: this table's prompt column, if it has one.
        $tables++;
        $promptCol = null;
        foreach ($cells as $k => $h) {
            // Only a column whose header IS the section column. The earlier rule also accepted a header merely
            // starting with `§` or containing "prompt s", which let a phase or risk column be read as a citation
            // and would have reported 46 of 48 headings covered — coverage manufactured by the reader's own breadth.
            $flat = trim(str_replace(['**', '`'], '', $h));
            // A per-line table whose header names the section it enumerates (`§4 line`) also scores that section,
            // even though its cells are prose the numeric census cannot bind. Without this the matrix's §4
            // checklist table read as UNVISITED and the tool invented a hole in the register it was measuring.
            if ($flat === '§' || $flat === 'Sections' || $flat === 'Section'
                || preg_match('/^prompt\s*(§|sections?)$/iu', $flat) === 1
                || preg_match('/^§(\d+(?:\.\d+)*)\s+(line|requirement|item)s?$/iu', $flat, $sub) === 1) {
                $promptCol = $k;
                $columnsRead++;
                if (isset($sub[1])) {
                    $exact[$sub[1]] = true;
                }
                if ($trace) {
                    printf("    TABLE header col %d = %s   (header: %s)\n", $k, var_export($flat, true), mb_substr($h, 0, 46));
                }
            }
        }

        continue;
    }
    if ($cells === [] || $promptCol === null || ! isset($cells[$promptCol])) {
        continue;
    }
    if ($trace) {
        // Printed per data row: the chosen column's literal bytes. The census once reported 10 of 11 tables
        // exposing a section column and then credited zero headings, which can only be seen by looking at what
        // the reader actually holds.
        printf("    TRACE %s col %d = %s\n", $h1ref ?? '?', $promptCol, var_export($cells[$promptCol], true));
    }
    foreach (preg_split('/\s*,\s*/', $cells[$promptCol]) as $tok) {
        // `/u` is load-bearing here: `§` is two bytes (0xC2 0xA7), and without it the `?` binds only to the second
        // byte, so the pattern silently REQUIRES the sigil and rejects every bare `5.1` cell. The census read 140
        // section cells and credited zero headings until this flag was added.
        if (preg_match('/^(?:§)?\s*(\d+(?:\.\d+)*)$/u', $tok, $tm)) {
            $exact[$tm[1]] = true;
        }
    }
}
printf("prompt-section columns found in %d of %d markdown table(s) in the matrix\n", $columnsRead, $tables);
if ($columnsRead === 0) {
    fwrite(STDERR, "REFUSED: no matrix table names a prompt-section column, so coverage could not be attributed by column.\n");

    exit(1);
}
// (The old refusal on an empty `$exact` stood here, before the column scan filled it — a guard that judged a
// variable the next block had not yet written, which refused a matrix it had not finished reading.)

// A prompt heading with no number of its own cannot be cited by a section cell at all — that is why the UNNUMBERED
// bucket exists. But the register can still answer the heading's requirement LINES one by one, and a line is a
// claim the tool can bind without inventing a section number: so the text of each enumerated line is normalised on
// both sides and looked for in the matrix. Full set found = LINE-COVERED, some = LINE-PARTIAL, none = UNNUMBERED.
// Matching is verbatim rather than fuzzy on purpose; a loosened matcher is how a census starts crediting work that
// was never scored, and §9 of the matrix already records that error being made once.
$norm = static function (string $s): string {
    $s = strtolower(str_replace(['**', '`'], '', $s));
    $s = preg_replace('/^\s*(?:[-*]\s+|\d+\.\s+|\[[ xX]\]\s+)/', '', $s);
    $s = preg_replace('/[^a-z0-9]+/u', ' ', $s);

    return trim((string) $s);
};

$matrixNorm = $norm($matrixText);
if ($matrixNorm === '') {
    fwrite(STDERR, "REFUSED: the matrix normalised to nothing, so no line could be matched — read the file, not this verdict.\n");

    exit(1);
}

$rows = [];
foreach ($populated as $h) {
    if ($h['num'] === '') {
        $found = 0;
        $short = 0;
        foreach ($h['lineTexts'] as $line) {
            $n = $norm($line);
            if (mb_strlen($n) < 12) {
                $short++;

                continue;
            }
            if (str_contains($matrixNorm, $n)) {
                $found++;
            }
        }
        $judgeable = $h['lines'] - $short;
        if ($judgeable > 0 && $found === $judgeable) {
            // A heading may not be called covered on an empty reading: if every line was too short to bind, the
            // instrument measured nothing and must say so rather than report the cleanest-looking bucket.
            $key = $found > 0 ? 'LINE-COVERED' : 'UNNUMBERED';
        } elseif ($found > 0) {
            $key = 'LINE-PARTIAL';
        } else {
            $key = 'UNNUMBERED';
        }
        $rows[] = ['(none)', $h['lines'], $key, $h['title'].' ['.$found.'/'.$judgeable.' lines]'];

        continue;
    }
    $key = 'covered';
    if (! isset($exact[$h['num']])) {
        // §6.4 counts as named when the matrix says §6.4; a bare §6 does not reach a subsection, and a subsection
        // citation of §6.4 does not claim its parent heading.
        $parent = str_contains($h['num'], '.') ? substr($h['num'], 0, (int) strrpos($h['num'], '.')) : null;
        $key = $parent !== null && isset($exact[$parent]) ? 'PARENT-ONLY' : 'UNVISITED';
    }
    $rows[] = [$h['num'], $h['lines'], $key, $h['title']];
}

$counts = ['covered' => 0, 'PARENT-ONLY' => 0, 'UNVISITED' => 0, 'LINE-COVERED' => 0, 'LINE-PARTIAL' => 0, 'UNNUMBERED' => 0];
$lines = ['covered' => 0, 'PARENT-ONLY' => 0, 'UNVISITED' => 0, 'LINE-COVERED' => 0, 'LINE-PARTIAL' => 0, 'UNNUMBERED' => 0];
foreach ($rows as $r) {
    $counts[$r[2]]++;
    $lines[$r[2]] += $r[1];
}

printf("prompt headings read: %d (%d numbered, %d carrying enumerated lines)   enumerated lines: %d\n",
    count($headings), count($numbered), count($populated), $totalLines);
printf("matrix section tokens read: %d\n\n", count($exact));
foreach ($rows as [$num, $n, $verdict, $title]) {
    if ($verdict === 'covered' && ! $showAll) {
        continue;
    }
    printf("  %-12s %-6s %3d line(s)   %s\n", $verdict, '§'.$num, $n, mb_substr($title, 0, 72));
}
$shown = $showAll ? count($rows) : $counts['PARENT-ONLY'] + $counts['UNVISITED'];
printf("\nheadings printed: %d of %d%s\n", $shown, count($rows), $showAll ? '' : ' (covered rows are folded; --all prints them)');
printf("coverage: %d headings covered by section cell (%d lines), %d covered by every line quoted (%d lines), %d partially (%d lines), %d PARENT-ONLY (%d lines), %d UNVISITED (%d lines), %d UNNUMBERED (%d lines)\n",
    $counts['covered'], $lines['covered'], $counts['LINE-COVERED'], $lines['LINE-COVERED'],
    $counts['LINE-PARTIAL'], $lines['LINE-PARTIAL'], $counts['PARENT-ONLY'], $lines['PARENT-ONLY'],
    $counts['UNVISITED'], $lines['UNVISITED'], $counts['UNNUMBERED'], $lines['UNNUMBERED']);
printf("sum check: %d headings = the population %d%s\n",
    array_sum($counts), count($rows), array_sum($counts) === count($rows) ? ' — agrees' : ' — REFUSES: the buckets do not add up');
if (array_sum($counts) !== count($rows) || array_sum($lines) !== $totalLines) {
    fwrite(STDERR, "REFUSED: the buckets do not equal the population, so the coverage figure describes the classifier.\n");

    exit(1);
}
