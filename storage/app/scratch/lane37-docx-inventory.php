<?php

/**
 * The docx inventory lane 37 works from: every numbered heading, every finding row with the section it
 * physically sits under, and the text of every block in document order.
 *
 * It is its own file because two sweeps need it: the one that checks the document's own `§` references, and the
 * one that checks the references PHP comments and the markdown files point INTO the document. The §28 G-9 class
 * of defect — a citation naming a real section and a real finding id that are not about the same thing — is
 * only detectable if the id is resolved against the row that carries it, so there must be exactly one place
 * that reads the document.
 *
 * usage: $inv = require 'lane37-docx-inventory.php';   (optional $argv[1] overrides the file, for mutants)
 *
 * @return array{sections: array<string, array{title: string, kind: int}>, rows: array<string, list<array{section: string, chapter: string, text: string, row: string}>>, blocks: list<string>}
 */

$root = dirname(__DIR__, 3);
$args = array_values(array_filter(array_slice($argv ?? [], 1), fn ($a) => ! str_starts_with($a, '--')));
$src = $args[0] ?? $root.'/Documentation/EMS Complete Documentation.docx';

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open {$src}\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

/** Concatenate every <w:t> in a block and decode what the markup escaped. */
$textOf = function (string $block): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $block, $t);

    return html_entity_decode(implode(' ', $t[1] ?? []), ENT_QUOTES | ENT_XML1);
};

$body = substr($xml, (int) strpos($xml, '<w:body>'));
preg_match_all('/<w:tr\b.*?<\/w:tr>|<w:p\b.*?<\/w:p>/s', $body, $blocks, PREG_SET_ORDER);

$sections = [];        // "25.4" => ['title' => ..., 'kind' => 2]
$rowsById = [];        // "P-14" => [ ['section' => "25.4", 'chapter' => "25", ...], ... ]
$blockTexts = [];
$current = null;       // the section a block physically sits under

foreach ($blocks as $b) {
    $block = $b[0];
    $text = trim($textOf($block));
    if ($text === '') {
        continue;
    }
    $blockTexts[] = $text;

    if (str_starts_with($block, '<w:p')) {
        if (! preg_match('/<w:pStyle w:val="(Heading[123])"/', $block, $st)) {
            continue;
        }
        // A section number may carry a letter: the acceptance script numbers its desk-action rows 12R.1 …
        // 14R.2, and the docx cites them — a grammar of `\d+(\.\d+)*` reads "§14R.2" as "§14" and calls it
        // resolved, which is a clean verdict bought by not looking.
        if (! preg_match('/^(\d+[A-Z]?(?:\.\d+)*)\s+(\S.*)$/u', $text, $h)) {
            continue;   // a heading with no number cannot be a reference target
        }
        $sections[$h[1]] = ['title' => $h[2], 'kind' => (int) substr($st[1], -1)];
        $current = $h[1];

        continue;
    }

    // A table row: its first cell may open with a finding id.
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $block, $cm);
    if ($cm[0] === []) {
        continue;
    }
    $first = trim($textOf($cm[0][0]));
    if (preg_match('/^([A-Z]{1,3}-\d{1,3})\b/u', $first, $id)) {
        $rowsById[$id[1]][] = [
            'section' => $current ?? '???',
            'chapter' => explode('.', (string) ($current ?? '???'))[0],
            'text' => mb_substr($first, 0, 60),
            'row' => mb_substr(preg_replace('/\s+/u', ' ', $text), 0, 90),
        ];
    }
}

return ['sections' => $sections, 'rows' => $rowsById, 'blocks' => $blockTexts];
