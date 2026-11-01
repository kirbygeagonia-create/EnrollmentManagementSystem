<?php

/**
 * ADS guide population reader (task #25).
 *
 * The traceability matrix must hold EVERY requirement, so its denominator is measured off the
 * guide's own structure rather than recalled from the implementation prompt that restates it.
 * This walks the flat text `lane154`-style extraction produced and prints, per problem, the
 * paragraph range it occupies, its title, its scenario, how many guide questions it asks, and
 * whether the Applies/Partially/N-A checkbox row is present. It also counts the concept and
 * presentation blocks, and refuses when a problem is found without a scenario or a checkbox,
 * because a half-read guide row would silently drop a requirement from the matrix.
 *
 * Usage: php storage/app/scratch/lane155-read-ads-guide.php [--text=<file>] [--json=<file>]
 */
$root = dirname(__DIR__, 3);
$known = ['text', 'json', 'help'];
$text = $root.'/storage/app/scratch/ads-guide-flat.txt';
$json = null;

foreach ($argv as $i => $arg) {
    if ($i === 0) {
        continue;
    }
    if (! preg_match('/^--([a-z0-9-]+)(=(.*))?$/', $arg, $m)) {
        fwrite(STDERR, "REFUSED: unreadable argument ".var_export($arg, true)."\n");
        exit(1);
    }
    if (! in_array($m[1], $known, true)) {
        fwrite(STDERR, "REFUSED: unknown flag --{$m[1]} — this script does not read it\n");
        exit(1);
    }
    if ($m[1] === 'text') {
        $text = $m[3] ?? '';
    } elseif ($m[1] === 'json') {
        $json = $m[3] ?? '';
    }
}

if ($text === '' || ! is_file($text)) {
    fwrite(STDERR, "REFUSED: the guide text is missing at {$text} — dump it first\n");
    exit(1);
}

$lines = preg_split('/\n/', file_get_contents($text));
if (count($lines) < 50) {
    fwrite(STDERR, 'REFUSED: only '.count($lines).' paragraphs read — the extraction is truncated, not short\n');
    exit(1);
}

/** Headings of the form `Problem N — Title`. */
$problems = [];
foreach ($lines as $idx => $l) {
    if (preg_match('/^\s*Problem\s+(\d+)\s+[—-]\s*(.+)$/u', $l, $m)) {
        $problems[(int) $m[1]] = ['line' => $idx + 1, 'title' => trim($m[2])];
    }
}
ksort($problems);

$n = count($problems);
if ($n === 0) {
    fwrite(STDERR, "REFUSED: no `Problem N —` heading found in the guide text\n");
    exit(1);
}

$expected = range(1, $n);
if (array_keys($problems) !== $expected) {
    fwrite(STDERR, 'REFUSED: problem ids are not 1..'.$n.' — read: '.implode(',', array_keys($problems))."\n");
    exit(1);
}

$rows = [];
$seq = array_values($problems);
foreach ($seq as $k => $p) {
    $start = $p['line'];
    $end = ($k + 1 < count($seq)) ? $seq[$k + 1]['line'] - 1 : count($lines);
    $body = array_slice($lines, $start - 1, $end - $start + 1);
    $joined = implode("\n", $body);

    $scenario = null;
    foreach ($body as $b) {
        if (preg_match('/^\s*Scenario:\s*(.+)$/iu', trim($b), $m)) {
            $scenario = trim($m[1]);
            break;
        }
    }
    $questions = 0;
    $inQuestions = false;
    foreach ($body as $b) {
        $t = trim($b);
        if (preg_match('/^Guide Questions$/iu', $t)) {
            $inQuestions = true;

            continue;
        }
        if ($inQuestions && preg_match('/^(Your Analysis|Applicability|If N\/A)/iu', $t)) {
            $inQuestions = false;
        }
        if ($inQuestions && $t !== '' && preg_match('/\?$/u', $t)) {
            $questions++;
        }
    }
    $checkbox = (bool) preg_match('/Applicability:.*☐/su', $joined);
    $solutionCells = preg_match_all('/☐/u', $joined);

    if ($scenario === null) {
        fwrite(STDERR, "REFUSED: problem {$p['title']} (line {$start}) carries no `Scenario:` line\n");
        exit(1);
    }
    if (! $checkbox) {
        fwrite(STDERR, "REFUSED: problem {$p['title']} (line {$start}) carries no Applicability checkbox row\n");
        exit(1);
    }

    $rows[] = [
        'id' => $k + 1,
        'title' => $p['title'],
        'paragraphs' => "{$start}-{$end}",
        'scenario' => $scenario,
        'guideQuestions' => $questions,
        'checkboxes' => $solutionCells,
    ];
}

printf("ADS guide §III: %d problems, ids 1..%d confirmed in order\n\n", $n, $n);
foreach ($rows as $r) {
    printf("P%02d ¶%-9s %-40s questions %d  ☐ %d\n", $r['id'], $r['paragraphs'], $r['title'], $r['guideQuestions'], $r['checkboxes']);
    printf("      scenario: %s\n", mb_substr($r['scenario'], 0, 120));
}

$totalQuestions = array_sum(array_column($rows, 'guideQuestions'));
printf("\nguide questions over the %d problems: %d\n", $n, $totalQuestions);

// §IV+ — the concept and presentation requirements the matrix must also carry.
$concepts = [];
foreach ($lines as $idx => $l) {
    if (preg_match('/^\s*([A-Z])\.\s+(.+)/u', trim($l), $m) && strlen($m[2]) < 60) {
        $concepts[] = ['line' => $idx + 1, 'letter' => $m[1], 'name' => trim($m[2])];
    }
}
printf("lettered subsections (A. …) read: %d\n", count($concepts));
foreach ($concepts as $c) {
    printf("   ¶%-5d %s. %s\n", $c['line'], $c['letter'], $c['name']);
}

$numbered = [];
foreach ($lines as $idx => $l) {
    // Delimited with # because the class holds a slash: with /.../ the slash ends the pattern and
    // the following `+-]` is read as modifiers ("Unknown modifier '+'"), which warns and matches nothing.
    if (preg_match('#^\s*(\d{1,2})\.\s+([A-Z][A-Za-z &/+-]{3,50})$#u', trim($l), $m)) {
        $numbered[] = ['line' => $idx + 1, 'n' => (int) $m[1], 'name' => trim($m[2])];
    }
}
if (count($numbered) === 0) {
    fwrite(STDERR, "REFUSED: no numbered requirement list read — the pattern matched nothing, which is not the same as the guide having none\n");
    exit(1);
}
echo "\nnumbered requirement lists read:\n";
foreach ($numbered as $r) {
    printf("   ¶%-5d %d. %s\n", $r['line'], $r['n'], $r['name']);
}

// §IV is a two-column table flattened to alternating lines: the concept name, then its status
// checkboxes. The name is the line IMMEDIATELY above a status line — taking two above lands on the
// previous concept's status row and prints checkboxes as names, which is what the first cut did.
$status = '/^\s*☐\s*(Implemented|Backup|Explained|Query)/u';
$conceptRows = [];
foreach ($lines as $idx => $l) {
    if (! preg_match($status, trim($l))) {
        continue;
    }
    $name = trim($lines[$idx - 1] ?? '');
    if ($name === '' || preg_match($status, $name) || $name === 'Database Concept' || $name === 'Status') {
        fwrite(STDERR, "REFUSED: a status row at ¶".($idx + 1)." has no concept name above it (read ".var_export($name, true).")\n");
        exit(1);
    }
    $conceptRows[] = ['line' => $idx, 'name' => $name, 'statuses' => substr_count(trim($l), '☐')];
}
printf("\n§IV concept rows read from the guide's own table: %d\n", count($conceptRows));
foreach ($conceptRows as $c) {
    printf("   ¶%-5d %s\n", $c['line'], $c['name']);
}
if (count($conceptRows) < 10) {
    fwrite(STDERR, "REFUSED: only ".count($conceptRows)." concept rows read — the table was not walked, not that the guide is short\n");
    exit(1);
}

// §V presentation areas: a `N. Title` heading plus the requirement lines under it, until the next heading.
$presentation = [];
$areas = [];
foreach ($numbered as $r) {
    if ($r['line'] > 300) {
        $areas[] = $r;
    }
}
foreach ($areas as $k => $a) {
    $from = $a['line'];
    $to = ($k + 1 < count($areas)) ? $areas[$k + 1]['line'] - 1 : count($lines);
    $items = [];
    foreach (array_slice($lines, $from, $to - $from) as $b) {
        $t = trim($b);
        // The eighth area runs to the end of the file, so the body must stop where the guide's own
        // next chapter starts; otherwise §VI's defense question and §VII's signature lines are
        // counted as presentation requirements and the denominator the matrix cites is inflated.
        if (preg_match('/^([IVX]{1,3})\.\s+[A-Z]/u', $t) || preg_match('/^Final Note to Students$/u', $t)) {
            break;
        }
        if ($t !== '' && ! preg_match('/^☐|^Applicability|^If N\/A/u', $t) && mb_strlen($t) < 130) {
            $items[] = $t;
        }
    }
    $presentation[] = ['n' => $a['n'], 'title' => $a['name'], 'line' => $from, 'items' => $items];
}
printf("\n§V presentation areas read: %d\n", count($presentation));
$presItems = 0;
foreach ($presentation as $p) {
    printf("   ¶%-5d %d. %s — %d requirement line(s)\n", $p['line'], $p['n'], $p['title'], count($p['items']));
    foreach ($p['items'] as $it) {
        printf("        · %s\n", mb_substr($it, 0, 96));
    }
    $presItems += count($p['items']);
}
printf("\ntotals: %d problems, %d guide questions, %d concepts, %d presentation areas carrying %d lines\n",
    $n, $totalQuestions, count($conceptRows), count($presentation), $presItems);

if ($json !== null) {
    if (file_put_contents($json, json_encode(['problems' => $rows, 'questions' => $totalQuestions,
        'subsections' => $concepts, 'numbered' => $numbered, 'concepts' => $conceptRows,
        'presentation' => $presentation, 'presentationItems' => $presItems],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
        fwrite(STDERR, "REFUSED: could not write {$json}\n");
        exit(1);
    }
    printf("\nwrote %s\n", $json);
}
