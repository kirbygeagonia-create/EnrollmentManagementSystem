<?php

/**
 * Read-only: print the guide form's paragraph text in a window, with the offsets a writer would anchor on.
 *
 * lane201 numbered only non-empty paragraphs and lane202 numbered all of them, so any builder that plans by
 * index is planning in an index space its own next probe will disagree with. This tool prints BOTH: the ordinal
 * and the byte offset, and the raw text of each paragraph between two text anchors given on the command line.
 *
 * Usage: php storage/app/scratch/lane204-read-guide-window.php [--from=<text>] [--count=<n>] [--all]
 */
$root = dirname(__DIR__, 3);
chdir($root);
$src = 'C:/Users/ADMIN/Downloads/ADS_Final_Project_Guide_EMS_updated.docx';
$from = 'Problem 1';
$count = 40;
$all = false;

foreach (array_slice($GLOBALS['argv'], 1) as $a) {
    if ($a === '--all') {
        $all = true;
    } elseif (preg_match('/^--from=(.*)$/', $a, $m)) {
        $from = $m[1];
    } elseif (preg_match('/^--count=(.*)$/', $a, $m)) {
        $count = (int) $m[1];
    } else {
        fwrite(STDERR, "REFUSED: this tool does not read the flag {$a}\n");
        exit(1);
    }
}

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    fwrite(STDERR, "REFUSED: {$src} did not open as an archive.\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
if ($xml === '') {
    fwrite(STDERR, "REFUSED: word/document.xml read empty.\n");
    exit(1);
}

$paras = [];
preg_match_all('/<w:p[ >].*?<\/w:p>/s', $xml, $pm, PREG_OFFSET_CAPTURE);
foreach ($pm[0] as $o) {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $o[0], $tm);
    $text = html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1);
    $paras[] = ['start' => $o[1], 'len' => strlen($o[0]), 'text' => trim($text)];
}

$start = 0;
if (! $all) {
    $at = null;
    foreach ($paras as $i => $p) {
        if (str_contains($p['text'], $from)) {
            $at = $i;
            break;
        }
    }
    if ($at === null) {
        fwrite(STDERR, "REFUSED: no paragraph contains '{$from}' — the anchor text is the writer's claim, so print what is actually there with --all.\n");
        exit(1);
    }
    $start = $at;
    $limit = min(count($paras), $at + $count);
} else {
    $limit = count($paras);
}

printf("%d paragraphs read; printing %d from ordinal %d\n", count($paras), $limit - $start, $start);
for ($i = $start; $i < $limit; $i++) {
    $p = $paras[$i];
    printf("#%03d @%d len=%d | %s\n", $i, $p['start'], $p['len'], $p['text'] === '' ? '(empty)' : $p['text']);
}
