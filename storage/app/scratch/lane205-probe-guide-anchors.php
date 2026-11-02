<?php

/**
 * Read-only: print how the guide form's own anchors pair up, before any writer trusts the pairing.
 *
 * The answered copy writes one answer block into one problem's cell and ticks one box in that problem's own
 * applicability row. If the two lists are paired by position and one list contains an extra member (a legend,
 * a table-of-contents heading, a row whose label differs), the builder silently writes Problem 7's analysis
 * under Problem 3 and ticks a box the school never saw asked. A count that agrees is not a pairing that
 * agrees, so this tool prints, per problem: the heading paragraph, the row it pairs with, the next `If N/A…`
 * line after that row, and the enclosing table cell for the answer text.
 *
 * It refuses (exit 1, cause named) when the three lists do not each hold 24 members, when a problem's `If N/A…`
 * line sits BEFORE its own applicability row or belongs to the NEXT problem's window, or when the enclosing
 * `<w:tc>` cannot be found — every one of which is a pairing the writer must not attempt.
 *
 * Usage: php storage/app/scratch/lane205-probe-guide-anchors.php [--guide=<path>]
 */
$root = dirname(__DIR__, 3);
chdir($root);
$src = 'C:/Users/ADMIN/Downloads/ADS_Final_Project_Guide_EMS_updated.docx';

foreach (array_slice($GLOBALS['argv'], 1) as $a) {
    if (preg_match('/^--guide=(.*)$/', $a, $m)) {
        $src = $m[1];
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
    $paras[] = ['start' => $o[1], 'end' => $o[1] + strlen($o[0]), 'text' => trim($text)];
}
if (count($paras) === 0) {
    fwrite(STDERR, "REFUSED: no paragraphs read from {$src}.\n");
    exit(1);
}
$refuse = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

$heads = array_values(array_filter($paras, static fn (array $p): bool => preg_match('/^Problem \d+ —/u', $p['text']) === 1));
$applic = array_values(array_filter($paras, static fn (array $p): bool => str_contains($p['text'], 'Applicability:') && str_contains($p['text'], '☐')));
$nas = array_values(array_filter($paras, static fn (array $p): bool => str_contains($p['text'], 'If N/A or different from our workflow')));

printf("lists read: %d `Problem N —` headings, %d applicability rows, %d `If N/A…` lines over %d paragraphs\n",
    count($heads), count($applic), count($nas), count($paras));
if (count($heads) !== 24) {
    $refuse(count($heads).' headings, expected 24.');
}
if (count($applic) !== 24) {
    $refuse(count($applic).' applicability rows, expected 24.');
}
if (count($nas) !== 24) {
    $refuse(count($nas).' If-N/A lines, expected 24.');
}

$bad = 0;
foreach ($heads as $i => $h) {
    $a = $applic[$i];
    $n = $nas[$i] ?? null;
    // Every member of the triple for problem i must lie inside the window this heading owns: from its own
    // start up to the next heading's start (the last runs to the end of the body).
    $windowEnd = $heads[$i + 1]['start'] ?? strlen($xml);
    $inWindow = static fn (?array $p): bool => $p !== null && $p['start'] >= $h['start'] && $p['start'] < $windowEnd;
    $tcClose = $n !== null ? strpos($xml, '</w:tc>', $n['end']) : false;
    $tcOpen = $n !== null ? strrpos($xml, '<w:tc>', $n['start'] - strlen($xml)) : false;
    $cellLen = ($tcOpen !== false && $tcClose !== false) ? $tcClose - $tcOpen : -1;
    $ticks = 0;
    if ($ticks === 0 && $n !== null) {
        $rowXml = substr($xml, $a['start'], $a['end'] - $a['start']);
        $ticks = substr_count($rowXml, '☐');
    }
    $ok = $inWindow($a) && $inWindow($n) && $tcOpen !== false && $tcClose !== false && $ticks === 3;
    if (! $ok) {
        $bad++;
    }
    printf("P%02d head@%d | applic@%d %s | ifNA@%s %s | cell=%s len=%d | row ☐ count=%d => %s\n",
        $i + 1, $h['start'], $a['start'], $inWindow($a) ? 'in' : 'OUT',
        $n === null ? 'none' : (string) $n['start'], $n === null ? '-' : ($inWindow($n) ? 'in' : 'OUT'),
        $tcOpen === false ? 'none' : 'found', $cellLen, $ticks, $ok ? 'ok' : 'BAD');
}
printf("\npairing: %d of 24 problems carry heading+row+If-N/A in one window with a 3-box row and an enclosing cell\n", 24 - $bad);
if ($bad > 0) {
    fwrite(STDERR, "REFUSED: {$bad} problem window(s) do not pair; a writer must not proceed on a positional list that is not windowed.\n");
    exit(1);
}
echo "OK\n";
