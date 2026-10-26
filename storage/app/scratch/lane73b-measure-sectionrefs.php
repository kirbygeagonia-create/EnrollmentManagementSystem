<?php

/**
 * Read-only, for the figure lane 37 prints: which `§` reference disappeared between HEAD and the working tree?
 *
 * The sweep's *checked* count is its own classification, not a census of the text (AGENTS.md), so a fall of one is
 * read by comparing the raw token multisets of both versions — not by guessing which sentence was rewritten. Each
 * version is decoded the way the dumper decodes it, and the tokens are counted with the same pattern the sweep
 * uses, so a difference here is a difference in the document and not in this probe.
 */
$root = 'C:/Users/ADMIN/OneDrive/Documents/3rd_Year/EnrollmentManagementSystem';
$paths = ['HEAD' => getenv('TEMP').'/head-ems.docx', 'WORK' => $root.'/Documentation/EMS Complete Documentation.docx'];

$flat = [];
foreach ($paths as $label => $path) {
    $zip = new ZipArchive;
    if ($zip->open($path) !== true) {
        exit("REFUSED: $label copy cannot be opened as a zip — $path\n");
    }
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    if ($xml === '') {
        exit("REFUSED: $label copy holds no word/document.xml.\n");
    }
    preg_match_all('/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>/us', $xml, $tm);
    $flat[$label] = html_entity_decode(implode(' ', $tm[1]), ENT_QUOTES | ENT_XML1);
    printf("%s: document.xml %d chars, flattened %d\n", $label, strlen($xml), strlen($flat[$label]));
}

// The sweep's own token shape: a section number, optionally with sub-levels.
$tokens = static function (string $s): array {
    preg_match_all('/§\s*\d+(?:\.\d+)*/u', $s, $m);

    return $m[0];
};

$a = $tokens($flat['HEAD']);
$b = $tokens($flat['WORK']);
printf("raw § tokens: HEAD %d, WORK %d, delta %+d\n\n", count($a), count($b), count($b) - count($a));

$ca = array_count_values($a);
$cb = array_count_values($b);
$diff = false;
foreach (array_unique(array_merge(array_keys($ca), array_keys($cb))) as $k) {
    $x = $ca[$k] ?? 0;
    $y = $cb[$k] ?? 0;
    if ($x !== $y) {
        printf("  %-12s HEAD %d → WORK %d\n", $k, $x, $y);
        $diff = true;
    }
}
if (! $diff) {
    echo "  no per-reference count moved: the two versions hold the same § tokens the same number of times\n";
}

// Where each changed token sits, read from the working dump the sweep actually parses.
foreach (array_unique(array_merge(array_keys($ca), array_keys($cb))) as $k) {
    if (($ca[$k] ?? 0) === ($cb[$k] ?? 0)) {
        continue;
    }
    $lines = explode("\n", file_get_contents($root.'/storage/app/scratch/docx-flat.txt'));
    foreach ($lines as $i => $line) {
        if (str_contains($line, $k)) {
            printf("\n  %s on dump line %d: %s\n", $k, $i + 1, mb_substr(trim($line), 0, 220));
        }
    }
}
