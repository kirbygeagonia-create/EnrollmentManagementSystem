<?php
// Post-write check for pass 12: does the tag imbalance match the untouched backup, and
// did any rewritten cell lose its run properties?

$files = [
    'backup' => dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-04-pass12.docx',
    'current' => dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx',
];

foreach ($files as $label => $path) {
    $z = new ZipArchive();
    if ($z->open($path) !== true) {
        echo "$label: CANNOT OPEN\n";

        continue;
    }
    $xml = $z->getFromName('word/document.xml');
    $z->close();

    printf(
        "%-8s chars=%d  <w:t ≥%d  </w:t>=%d  <w:r ≥%d  <w:rPr>=%d\n",
        $label,
        strlen($xml),
        preg_match_all('/<w:t[ >]/', $xml),
        preg_match_all('/<\/w:t>/', $xml),
        preg_match_all('/<w:r[ >]/', $xml),
        preg_match_all('/<w:rPr>/', $xml)
    );
}

// The cells pass 12 rewrote carry these opening words; print their run's properties.
$markers = [
    'Closed 2026-10-03 by ruling 2.',
    'Closed 2026-10-04.',
    'Closed 2026-10-03 by rulings 4 and 5.',
    'Closed 2026-10-03 by ruling 3,',
    'Closed 2026-10-03 by ruling 11.',
    'Closed 2026-10-03. Recording a course-specific',
    'Closed 2026-10-04 by ruling 1.',
    'Closed in code',
];

$z = new ZipArchive();
$z->open($files['current']);
$xml = $z->getFromName('word/document.xml');
$z->close();

foreach ($markers as $marker) {
    $pos = strpos($xml, $marker);
    if ($pos === false) {
        echo "MISSING: {$marker}\n";

        continue;
    }
    $cellStart = strrpos(substr($xml, 0, $pos), '<w:tc>');
    $cell = substr($xml, $cellStart, $pos - $cellStart + 260);
    $hasRpr = strpos($cell, '<w:rPr>') !== false;
    $fonts = [];
    if (preg_match_all('/<w:rFonts[^>]*w:ascii="([^"]+)"/', $cell, $m)) {
        $fonts = array_unique($m[1]);
    }
    $sz = preg_match('/<w:sz w:val="(\d+)"/', $cell, $sm) ? $sm[1] : '—';
    printf("ok  %-52s rPr=%s font=%s sz=%s\n", substr($marker, 0, 50), $hasRpr ? 'yes' : 'NO', implode(',', $fonts) ?: '—', $sz);
}
