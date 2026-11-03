<?php
/**
 * What is actually inside each problem's analysis box in the answered guide copy?
 *
 * The owner reports the answers are gone. This opens `word/document.xml`, walks every `Problem N —` heading, and
 * prints the flattened text of the tables that follow it, so the question "did the text land in the box, beside the
 * box, or nowhere" is answered by the bytes rather than by the writer's own log.
 *
 * Read-only: it writes nothing. Usage: php storage/app/scratch/lane228-read-analysis-boxes.php [--copy=<path>]
 */
$root = dirname(__DIR__, 3);
chdir($root);
$copy = $root.'/Documentation/ADS_Final_Project_Guide_EMS_ANSWERED.docx';
require_once $root.'/storage/app/scratch/printed-words.php';

foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if (preg_match('#^--copy=(.*)$#s', $arg, $m)) {
        $copy = $m[1];
    } elseif (preg_match('#^--problem=\d+$#', $arg) || preg_match('#^--longest=\d+$#', $arg)
        || preg_match('#^--over=\d+$#', $arg) || $arg === '--ballots') {
        // Read below, where the region, the ranked answers, the word cap and the ballot census are printed.
        // `--over=N` sits in this list because a flag the parser refuses is a feature that never ran: the cap is read
        // further down, and `mutate-lane228-guide-readers.php` proves the same flag both refuses and passes
        // depending on its number, which is the only reading that shows it is honoured rather than accepted.
    } else {
        fwrite(STDERR, "REFUSED: this tool does not read the flag {$arg}\n");
        exit(1);
    }
}
if (! is_file($copy)) {
    fwrite(STDERR, "REFUSED: {$copy} is not on disk.\n");
    exit(1);
}

$z = new ZipArchive;
if ($z->open($copy) !== true) {
    fwrite(STDERR, "REFUSED: {$copy} did not open as an archive.\n");
    exit(1);
}
$xml = (string) $z->getFromName('word/document.xml');
$z->close();
if ($xml === '') {
    fwrite(STDERR, "REFUSED: word/document.xml read empty.\n");
    exit(1);
}

// Flatten exactly the way the edit engine does: match each text run, concatenate the runs, then decode entities.
// (The run pattern is spelled in code below; it must not be quoted in a comment here, because a closing PHP tag
// inside a single-line comment ends the block — which is how this file first printed its own source.)
$flatten = static function (string $chunk): string {
    preg_match_all('#<w:t(?: [^>]*)?>(.*?)</w:t>#s', $chunk, $m);

    return trim(html_entity_decode(implode('', $m[1]), ENT_QUOTES | ENT_XML1));
};

$onPaper = substr_count($xml, '☑');
$blank = substr_count($xml, '☐');
echo basename($copy).' — '.number_format(strlen($xml))." bytes of document.xml\n";
echo 'ballots on the whole page: ☑ '.$onPaper.'   ☐ '.$blank."\n";
foreach (['Concept involved:', 'Applicability:', 'Design:', 'Implementation:', 'Test or demonstration:', 'Evidence:'] as $label) {
    echo '  field label on the page: '.str_pad($label, 24).' x '.substr_count($xml, $label)."\n";
}

// Every problem heading, in document order, with the tables that follow it.
preg_match_all('#<w:p[ >].*?</w:p>#s', $xml, $pm, PREG_OFFSET_CAPTURE);
$heads = [];
foreach ($pm[0] as $o) {
    $text = $flatten($o[0]);
    if (preg_match('/^Problem (\d+) —(.*)$/u', $text, $h)) {
        $heads[] = ['n' => (int) $h[1], 'start' => $o[1], 'end' => $o[1] + strlen($o[0]), 'text' => $text];
    }
}
echo 'problem headings found: '.count($heads)."\n";
if (count($heads) !== 24) {
    echo "NOTE: this is not the 24-problem form the writer plans against.\n";
}

preg_match_all('#<w:tbl>.*?</w:tbl>#s', $xml, $tm, PREG_OFFSET_CAPTURE);
$tables = $tm[0];

// `--longest=N` ranks the answer paragraphs actually printed on the paper by word count. The owner's ruling is that
// an answer must be clear and direct, so this is the census that says which ones are not — measured on the delivered
// copy rather than on the markdown, because the writer is what decides what a reader sees.
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if (! preg_match('#^--longest=(\d+)$#', $arg, $lm)) {
        continue;
    }
    $limit = max(1, (int) $lm[1]);
    $rows = [];
    $totalWords = 0;
    $seen = 0;
    preg_match_all('#<w:p[ >].*?</w:p>#s', $xml, $lm2, PREG_OFFSET_CAPTURE);
    foreach ($lm2[0] as $o) {
        $text = preg_replace('/\s+/u', ' ', $flatten($o[0])) ?? '';
        if (! preg_match('/^(Answer:\s*|Concept involved:|Design:|Implementation:|Test or demonstration:|Evidence:|Applicability:|Workflow difference|Be prepared|The system)/u', $text)) {
            continue;
        }
        // The same unit the writer enforces, from the same module — see `printed-words.php`. A cap measured in one
        // unit and judged in another is a cap that reads as enforced while it binds nothing.
        $w = printed_words($text);
        $totalWords += $w;
        $seen++;
        $rows[] = ['w' => $w, 'at' => $o[1], 't' => $text];
    }
    usort($rows, static fn (array $a, array $b): int => $b['w'] <=> $a['w']);
    echo $seen.' answer paragraph(s) on the paper, '.$totalWords.' words total, average '
        .($seen > 0 ? (int) round($totalWords / $seen) : 0)." words\n";
    foreach (array_slice($rows, 0, $limit) as $r) {
        echo str_pad((string) $r['w'], 5).' words  @'.str_pad((string) $r['at'], 7).'  '.mb_substr($r['t'], 0, 420)."…\n";
    }

    // The owner's ruling is that an answer must be clear and direct. `--over=N` turns that into a refusal rather
    // than a preference: the paper is judged by its longest printed answer, and the names below are the work list.
    $cap = null;
    foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
        if (preg_match('#^--over=(\d+)$#', $arg, $cm)) {
            $cap = (int) $cm[1];
        }
    }
    if ($cap !== null) {
        $long = array_values(array_filter($rows, static fn (array $r): bool => $r['w'] > $cap));
        echo 'answers over '.$cap.' words: '.count($long).' of '.$seen."\n";
        foreach ($long as $r) {
            echo '  @'.str_pad((string) $r['at'], 7).'  '.str_pad((string) $r['w'], 4).' words  '
                .mb_substr($r['t'], 0, 110)."…\n";
        }
        if ($long !== []) {
            fwrite(STDERR, 'REFUSED: '.count($long)." answer(s) exceed the {$cap}-word cap; the paper is meant to read "
                ."clear and direct, so trim them at the source (`Documentation/GUIDE-PROBLEM-ANSWERS-ADS.md`) rather "
                ."than rewording the copy.\n");
            exit(1);
        }
    }
    exit(0);
}

// `--ballots` prints every paragraph that carries a ballot character, in document order: this is the census that
// answers "which option boxes does this form actually offer on this row, and which did we tick". The rows do NOT
// share one vocabulary, so a verdict word that the row cannot show is a defect the tick planner must refuse.
if (in_array('--ballots', array_slice($GLOBALS['argv'], 1), true)) {
    preg_match_all('#<w:p[ >].*?</w:p>#s', $xml, $bm, PREG_OFFSET_CAPTURE);
    $tickTotal = 0;
    foreach ($bm[0] as $o) {
        $text = preg_replace('/\s+/u', ' ', $flatten($o[0])) ?? '';
        if (! preg_match('/[☐☑]/u', $text)) {
            continue;
        }
        $ticks = substr_count($text, '☑');
        $tickTotal += $ticks;
        echo str_pad((string) $o[1], 8).' tick='.$ticks.'  '.mb_strimwidth($text, 0, 190, '…')."\n";
    }
    echo 'rows with ballots: above; total ticked cells: '.$tickTotal."\n";
    exit(0);
}

// `--problem=N` dumps the WHOLE region between that heading and the next, every block in order, so the question
// "which box did the text land in, and is some other box still empty" is answered by the layout rather than by a count.
$only = null;
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if (preg_match('#^--problem=(\d+)$#', $arg, $m)) {
        $only = (int) $m[1];
    }
}
if ($only !== null) {
    $at = null;
    $until = strlen($xml);
    foreach ($heads as $i => $h) {
        if ($h['n'] === $only) {
            $at = $h;
            $until = $heads[$i + 1]['start'] ?? strlen($xml);
        }
    }
    if ($at === null) {
        fwrite(STDERR, "REFUSED: no `Problem {$only} —` heading in this copy.\n");
        exit(1);
    }
    echo "=== region of Problem {$only}: ".($until - $at['end'])." bytes after the heading ===\n";

    $blocks = [];
    preg_match_all('#<w:tbl>.*?</w:tbl>#s', $xml, $t2, PREG_OFFSET_CAPTURE);
    foreach ($t2[0] as $t) {
        if ($t[1] >= $at['end'] && $t[1] < $until) {
            $blocks[] = ['kind' => 'TBL', 'start' => $t[1], 'raw' => $t[0]];
        }
    }
    preg_match_all('#<w:p[ >].*?</w:p>#s', $xml, $p2, PREG_OFFSET_CAPTURE);
    foreach ($p2[0] as $p) {
        if ($p[1] < $at['end'] || $p[1] >= $until) {
            continue;
        }
        // A paragraph inside a table is that table's own cell text; listing it again would read as a second block.
        $insideTable = false;
        foreach ($blocks as $b) {
            if ($b['kind'] === 'TBL' && $p[1] >= $b['start'] && $p[1] < $b['start'] + strlen($b['raw'])) {
                $insideTable = true;
                break;
            }
        }
        if (! $insideTable) {
            $blocks[] = ['kind' => 'P', 'start' => $p[1], 'raw' => $p[0]];
        }
    }
    usort($blocks, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);

    foreach ($blocks as $i => $b) {
        if ($b['kind'] === 'P') {
            $text = preg_replace('/\s+/', ' ', $flatten($b['raw'])) ?? '';
            echo '  '.str_pad('#'.$i, 4).' P    '.($text === '' ? '(empty paragraph)' : mb_strimwidth($text, 0, 120, '…'))."\n";
            continue;
        }
        $rows = substr_count($b['raw'], '<w:tr');
        $cells = substr_count($b['raw'], '<w:tc>');
        $text = preg_replace('/\s+/', ' ', $flatten($b['raw'])) ?? '';
        echo '  '.str_pad('#'.$i, 4)." TBL  rows={$rows} cells={$cells} ".($text === '' ? '(NO TEXT ANYWHERE)' : mb_strimwidth($text, 0, 120, '…'))."\n";
    }
    exit(0);
}

foreach ($heads as $h) {
    $next = [];
    foreach ($tables as $t) {
        if ($t[1] >= $h['end']) {
            $next[] = $t;
            if (count($next) === 2) {
                break;
            }
        }
    }
    $row = $next[0] ?? null;
    $na = $next[1] ?? null;
    $rowText = $row === null ? 'NO TABLE' : $flatten($row[0]);
    $naText = $na === null ? 'NO TABLE' : $flatten($na[0]);
    $gap = $row === null || $na === null ? 0 : (int) ($na[1] - ($row[1] + strlen($row[0])));
    $between = $row === null || $na === null ? '' : $flatten(substr($xml, $row[1] + strlen($row[0]), max(0, $gap)));

    echo '--- Problem '.$h['n']." ---\n";
    echo '  box1: '.(mb_strimwidth(preg_replace('/\s+/', ' ', $rowText) ?? '', 0, 150, '…'))."\n";
    echo '  between (outside the tables): '.(mb_strimwidth(preg_replace('/\s+/', ' ', $between) ?? '', 0, 150, '…'))."\n";
    echo '  box2: '.(mb_strimwidth(preg_replace('/\s+/', ' ', $naText) ?? '', 0, 150, '…'))."\n";
}
