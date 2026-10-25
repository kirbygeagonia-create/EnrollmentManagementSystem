<?php

/**
 * Pass 214 — the G-28 row's three remaining bare ranges get their owner, once per cell.
 *
 * The row records a citation correction, so it repeats the same ranges in two cells and pass 208's naming only
 * reached one of them. Both cells are rebuilt from document.xml's own `<w:t>` runs (never from the flat dump, which
 * joins runs with a space and would invent typography), and each edit is refused unless the substitution actually
 * changes the cell and the anchor is unique.
 *
 * The lines behind the numbers were read first: AssessmentController.php:232-237 computes a grant's coverage from
 * `coverageType`, :160-168 stores the balance less that coverage, :152-158 is the note saying nothing is
 * auto-awarded at compute time, and :157 is the line that zeroes it.
 *
 *   php storage/app/scratch/refresh-docx-pass214.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass214.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$ac = (array) file($root.'/app/Http/Controllers/Assessment/AssessmentController.php');
$fails = 0;
foreach ([[152, 'Apply School Grant'], [157, '$totalScholarshipCoverage = 0;'], [160, '$remainingBalance = $totalAssessed'],
    [168, "'remainingBalance' => max(0"], [232, '$coverageAmount = $scholarshipType->coverageType'], [237, '), 2);'],
] as [$n, $needle]) {
    $t = trim($ac[$n - 1] ?? '');
    $ok = $needle === '' || str_contains($t, $needle);
    printf("  %s assess :%-4d [%s]\n", $ok ? 'ok  ' : 'BAD ', $n, mb_substr($t, 0, 58));
    $fails += $ok ? 0 : 1;
}
if ($fails > 0) {
    echo "REFUSED: a claimed line does not carry the claim.\n";
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$flatten = static function (string $block): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $block, $tm);

    return html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1);
};

$edits = [
    ['its citation moved to', '/citation moved to\s*:232-237/', 'citation moved to AssessmentController.php:232-237'],
    ['sets coverage to zero on purpose', '/reduces the bill at\s*:232-237/', 'reduces the bill at AssessmentController.php:232-237'],
];

$cellEdits = [];
foreach ($edits as $i => [$anchor, $pattern, $replacement]) {
    $n = substr_count($xml, $anchor);
    printf("\n  edit %d anchor [%s] count %d (want 1)\n", $i + 1, $anchor, $n);
    if ($n !== 1) {
        echo "REFUSED: an anchor is not unique.\n";
        exit(1);
    }
    $p = strpos($xml, $anchor);
    $start = strrpos(substr($xml, 0, $p), '<w:tc>');
    $end = strpos($xml, '</w:tc>', $p) + 7;
    if ($start === false || strpos(substr($xml, $start, $p - $start), '</w:tc>') !== false) {
        echo "REFUSED: the anchor is not inside one <w:tc>.\n";
        exit(1);
    }
    $raw = $flatten(substr($xml, $start, $end - $start));
    $current = preg_replace('/\s+/', ' ', $raw);
    $new = preg_replace($pattern, $replacement, $current);
    if ($new === null || $new === $current) {
        echo "REFUSED: the pattern matched nothing, so this edit would rewrite the cell unchanged.\n";
        exit(1);
    }
    // The prefix is compared in bytes against the engine's own (unfolded) flatten, so take it from the same string.
    // A cell that opens with a date reads `2026-10-21: …` and has no leading word to match on.
    if (preg_match('/^\s*([A-Za-z]{6,})/', $raw, $pm) === 1) {
        $prefix = $pm[1];
    } else {
        $prefix = substr(ltrim($raw), 0, 12);
        printf("  (no leading word; using the cell's first bytes as its prefix)\n");
    }
    if ($prefix === '' || substr_compare($raw, $prefix, 0, strlen($prefix)) !== 0) {
        echo "REFUSED: the prefix does not start the unfolded cell text, so it could not pick this cell.\n";
        exit(1);
    }
    printf("  prefix [%s]\n            old: %s\n            new: %s\n", $prefix,
        mb_substr($current, max(0, (int) (strpos($current, '232-237') - 34)), 78),
        mb_substr($new, max(0, (int) (strpos($new, '232-237') - 34)), 78));
    $cellEdits[] = [$anchor, $prefix, $new];
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass214.docx', [], $cellEdits, $apply);
