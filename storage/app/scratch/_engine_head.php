<?php

/**
 * One-shot refresh of Documentation/EMS Complete Documentation.docx against the build
 * as of 2026-10-01.
 *
 * The document is the project's authority (owner ruling, 2026-09-29), so a sentence it
 * still asserts about a removed route or a dead notification channel becomes a false
 * requirement the moment the code changes. Every edit here is text inside an existing
 * <w:t> run or an existing table cell: no element is added or removed, so the file
 * keeps its styles, tables and bookmarks.
 *
 *   rawEdit   — whole-run needle, unique in the XML (or an expected count)
 *   paraEdits — one paragraph (found by an anchor); each edit names the run by its
 *               exact current text, so a wrong assumption fails instead of overwriting
 *   rowEdits  — one table row (found by an anchor); each edit names the cell by the
 *               start of its text and rewrites it as a single run in the cell's style
 *
 * The dry run prints what every target currently holds. Nothing is written while any
 * edit fails, and only --apply moves the file.
 */

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$tmp = sys_get_temp_dir().'/ems-docx-'.uniqid().'.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open source\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$failures = [];
$applied = 0;

function esc(string $t): string
{
    return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $t);
}

function show(string $s): string
{
    return str_replace(["\n", "\t"], ['\\n', '\\t'], $s);
}

function report(string $kind, bool $ok, string $label, string $note = ''): void
{
    global $applied, $failures;
    if ($ok) {
        $applied++;
        echo str_pad($kind, 5).' ok   '.substr($label, 0, 44)."\n        ".substr($note, 0, 150)."\n";
    } else {
        $failures[] = $label;
        echo str_pad($kind, 5).' FAIL '.substr($label, 0, 44)."\n        !! ".$note."\n";
    }
}

function rawEdit(string &$xml, string $needle, string $replacement, int $expected, string $label): void
{
    $count = substr_count($xml, $needle);
    if ($count !== $expected) {
        report('raw', false, $label, "found {$count}x, expected {$expected}x: ".substr(show($needle), 0, 90));
        return;
    }
    $xml = str_replace($needle, $replacement, $xml);
    report('raw', true, $label, "{$count}x  ".substr(show($needle), 0, 80));
}

/** Enclosing element span holding the anchor, or [false, false]. */
function spanFor(string $xml, string $anchor, string $open, string $close): array
{
    // An anchor phrase can occur in prose before it occurs in a table. Take the first
    // occurrence that is actually inside the element, not the first occurrence.
    $from = 0;
    while (($pos = strpos($xml, $anchor, $from)) !== false) {
        $start = strrpos(substr($xml, 0, $pos), $open);
        $end = strpos($xml, $close, $pos);
        if ($start !== false && $end !== false
            // Containment: nothing may close the element between its opening tag and
            // the anchor, or the anchor belongs to a different element than the span.
            && strpos(substr($xml, $start, $pos - $start), $close) === false) {
            return [$start, $end + strlen($close)];
        }
        $from = $pos + strlen($anchor);
    }
    return [false, false];
}

/** Rewrite the cells of one row's XML; each edit is [start of cell text, new text]. */
function rewriteCells(string $row, array $edits, ?string &$error): ?string
{
    foreach ($edits as [$prefix, $to]) {
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm, PREG_OFFSET_CAPTURE);
        $hits = [];
        foreach ($cm[0] as $i => $cell) {
            preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell[0], $tm);
            if (strncmp(implode('', $tm[1]), $prefix, min(strlen($prefix), 50)) === 0) {
                $hits[] = $i;
            }
        }
        if (count($hits) !== 1) {
            $cells = [];
            foreach ($cm[0] as $c) {
                preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $c[0], $tm);
                $cells[] = show(substr(implode('', $tm[1]), 0, 26));
            }
            $error = 'cell matches for ['.substr(show($prefix), 0, 50).']: '.count($hits)
                .' | row cells: '.implode(' ~ ', $cells);
            return null;
        }
        $cell = $cm[0][$hits[0]][0];
        if (substr_count($cell, '<w:p>') !== 1) {
            $error = 'cell ['.substr(show($prefix), 0, 40).'] holds '.substr_count($cell, '<w:p>').' paragraphs';
            return null;
        }
        preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
        preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
        $newRun = '<w:r>'.$pr[0].'<w:t xml:space="preserve">'.esc($to).'</w:t></w:r>';
        $newCell = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
        $newCell = str_replace('</w:p>', $newRun.'</w:p>', $newCell);
        $row = substr_replace($row, $newCell, $cm[0][$hits[0]][1], strlen($cell));
    }
    return $row;
}

/**
 * @param array<int,array{0:string,1:string}> $edits each [exact current run text, new text]
 */
function paraEdits(string &$xml, string $anchor, array $edits, string $label): void
{
    [$s, $e] = spanFor($xml, $anchor, '<w:p>', '</w:p>');
    if ($s === false) {
        report('para', false, $label, 'anchor or containing paragraph not found: '.substr(show($anchor), 0, 70));
        return;
    }
    $region = substr($xml, $s, $e - $s);
    $seen = [];
    foreach ($edits as [$expect, $to]) {
