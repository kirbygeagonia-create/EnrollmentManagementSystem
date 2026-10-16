<?php

/**
 * Pass 80 citation probe — the three files this batch edits, and every line number the
 * docx points at inside them. A number after the edited region is now wrong by the lines
 * added, so each hit must be re-measured by meaning, not arithmetic.
 */

$root = dirname(__DIR__, 3);

$zip = new ZipArchive();
$zip->open($root.'/Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$text = html_entity_decode(preg_replace('/<[^>]+>/', '', $xml), ENT_QUOTES | ENT_XML1);

$files = ['AdmissionController.php', 'IDController.php', 'seed_full_demo_pipeline.php'];

foreach ($files as $f) {
    preg_match_all('/'.preg_quote($f, '/').':\d+(\s*[-–]\s*\d+)?/', $text, $m);
    printf("%s → %d numbered citation(s): %s\n", $f, count($m[0]), implode(', ', array_unique($m[0])));

    if (! count($m)) {
        continue;
    }

    $target = $f === 'seed_full_demo_pipeline.php'
        ? $root.'/tools/'.$f
        : null;
    if ($target === null) {
        $hits = glob($root.'/app/Http/Controllers/*/'.$f) ?: [];
        $target = $hits[0] ?? null;
    }
    $lines = file($target);
    echo "  file has ".count($lines)." lines\n";

    foreach (array_unique($m[0]) as $cite) {
        preg_match('/:(\d+)(?:\s*[-–]\s*(\d+))?/', $cite, $d);
        $from = (int) $d[1];
        $to = isset($d[2]) && $d[2] !== '' ? (int) $d[2] : $from;
        echo "  -- {$cite}\n";
        for ($i = $from; $i <= min($to, count($lines)); $i++) {
            echo '     '.str_pad((string) $i, 4, ' ', STR_PAD_LEFT).'  '.rtrim($lines[$i - 1])."\n";
        }
    }
    echo "\n";
}
