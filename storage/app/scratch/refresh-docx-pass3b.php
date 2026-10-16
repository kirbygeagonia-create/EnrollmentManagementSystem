<?php

/**
 * Pass 3b — the table cells that span several runs, for the dead-code batch.
 *
 * Same rule as the other passes: text only, inside an existing run or an existing cell,
 * every target resolved and reported before anything is written, and --apply to move.
 */

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$tmp = sys_get_temp_dir().'/ems-docx3b-'.uniqid().'.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$fails = 0;

function esc(string $s): string
{
    return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
}

function spanFor(string $xml, string $anchor, string $open, string $close): array
{
    $from = 0;
    while (($pos = strpos($xml, $anchor, $from)) !== false) {
        $start = strrpos(substr($xml, 0, $pos), $open);
        $end = strpos($xml, $close, $pos);
        if ($start !== false && $end !== false
            && strpos(substr($xml, $start, $pos - $start), $close) === false) {
            return [$start, $end + strlen($close)];
        }
        $from = $pos + strlen($anchor);
    }
    return [false, false];
}

function rawEdit(string &$xml, string $needle, string $to, int $want, string $label): void
{
    global $fails;
    $n = substr_count($xml, $needle);
    if ($n !== $want) {
        $fails++;
        echo "raw  FAIL {$label}: found {$n}x, want {$want}x\n";
        return;
    }
    $xml = str_replace($needle, $to, $xml);
    echo "raw  ok   {$label} ({$n}x)\n";
}

/** Rewrite one cell (matched by the start of its text) of the row holding $anchor. */
function cellEdit(string &$xml, string $anchor, string $cellPrefix, string $to, string $label): void
{
    global $fails;
    [$s, $e] = spanFor($xml, $anchor, '<w:tr', '</w:tr>');
    if ($s === false) {
        $fails++;
        echo "cell FAIL {$label}: row for anchor not found\n";
        return;
    }
    $row = substr($xml, $s, $e - $s);
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm, PREG_OFFSET_CAPTURE);
    $hits = [];
    foreach ($cm[0] as $i => $cell) {
        preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell[0], $tm);
        if (strncmp(implode('', $tm[1]), $cellPrefix, min(strlen($cellPrefix), 50)) === 0) {
            $hits[] = $i;
        }
    }
    if (count($hits) !== 1) {
        $cells = [];
        foreach ($cm[0] as $c) {
            preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $c[0], $tm);
            $cells[] = str_replace("\n", '\\n', substr(implode('', $tm[1]), 0, 24));
        }
        $fails++;
        echo "cell FAIL {$label}: matched ".count($hits)." cells; row holds: ".implode(' ~ ', $cells)."\n";
        return;
    }
    $cell = $cm[0][$hits[0]][0];
    if (substr_count($cell, '<w:p>') !== 1) {
        $fails++;
        echo "cell FAIL {$label}: cell holds ".substr_count($cell, '<w:p>')." paragraphs\n";
        return;
    }
    preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $newCell = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
    $newCell = str_replace('</w:p>', '<w:r>'.$pr[0].'<w:t xml:space="preserve">'.esc($to).'</w:t></w:r></w:p>', $newCell);
    $row = substr_replace($row, $newCell, $cm[0][$hits[0]][1], strlen($cell));
    $xml = substr($xml, 0, $s).$row.substr($xml, $e);
    echo "cell ok   {$label}  [was ".str_replace("\n", '\\n', substr($cellPrefix, 0, 30))."]\n";
}

// §25 print matrix, Enrollment Certificate row: the queued printer never existed.
cellEdit($xml, 'printEnrollmentCertificate()', 'printEnrollmentCertificate()',
    'PrintService::printEnrollmentCertificate() (PrintService.php:115) — called directly by the Registrar certificate route, synchronously',
    '25 matrix: certificate PDF entry point');

// §25 print matrix, Class Card row: same job, same absence.
cellEdit($xml, 'printClassCards()', 'printClassCards()',
    'PrintService::printClassCards() (PrintService.php:137) — returns one PDF per subject to the Registrar class-card route',
    '25 matrix: class card PDF entry point');

// §26 remediation table, the row that asked "dispatch it or delete it".
cellEdit($xml, 'Reach the PDF path or delete it', 'dispatch PrintEnrollmentDocuments',
    'Deleted rather than wired. The queued printer and the class-card batch job went on 2026-10-01: every desk print route already renders its PDF synchronously, so the job had no caller and no route needed one.',
    '26 remediation: smallest change');
cellEdit($xml, 'Reach the PDF path or delete it', 'PrintService.php, app/Jobs',
    'app/Jobs/PrintEnrollmentDocuments.php (removed), app/Jobs/BatchPrintClassCards.php (removed), PrintService.php:115',
    '26 remediation: files');

echo "\n".($fails === 0 ? "all targets resolved" : "{$fails} failure(s)")."\n";
if ($fails > 0) {
    fwrite(STDERR, "not writing\n");
    exit(1);
}
if (! $apply) {
    echo "dry run — pass --apply to write\n";
    exit(0);
}
copy($src, $tmp);
$z2 = new ZipArchive();
$z2->open($tmp);
$z2->addFromString('word/document.xml', $xml);
$z2->close();
rename($tmp, $src);
echo "APPLIED\n";
