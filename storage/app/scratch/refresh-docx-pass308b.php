<?php

/**
 * Pass 308b — the two places that COUNT the register, which pass 308's seven new rows left standing.
 *
 * §28.1's identifier index still read `G-1 … G-43` and §29.4's family table still counted 60 rows for
 * G / D / U / X / N. Neither moves on its own when a row is added — AGENTS.md records this exact defect twice
 * (pass 173 wrote a count and then appended the row; the P-17 batch moved ten places for one row) — so the
 * register contradicts itself until both are re-taken from the bytes the previous pass wrote.
 *
 * They are a separate pass because `docxApply()` opens the archive itself: a pass that rewrote document.xml
 * before calling the engine had written nothing, and pass 252 learned that the hard way.
 *
 * Both figures were MEASURED, not typed from arithmetic: the family count came from pass 308's own re-read of
 * the written document (67 rows, ids asserted in order), and the index range's end is the last id the same scan
 * found. Usage: php storage/app/scratch/refresh-docx-pass308b.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

require __DIR__.'/docx-edit-engine.php';

$textEdits = [
    // §28.1's identifier index: the G family now runs to G-50.
    ['G-1 … G-43', 'G-1 … G-50'],
];

$cellEdits = [
    // §29.4's family table, the row whose own third cell ends "…notification gaps".
    ['notification gaps', '60', '67'],
];

docxApply($docx, $root.'/storage/app/scratch/pass308b-dry.docx', $textEdits, $cellEdits, $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot re-open the written archive.\n");

    exit(1);
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

$flat = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};

// Read the register back one more time and require the two counts to equal what the rows themselves say.
$ids = [];
$at = 0;
while (($start = strpos($after, '<w:tr', $at)) !== false) {
    $end = strpos($after, '</w:tr>', $start);
    if ($end === false) {
        break;
    }
    $end += 7;
    $at = $end;
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', substr($after, $start, $end - $start), $cm);
    $first = $flat($cm[0][0] ?? '');
    if (preg_match('/^(G)-(\d+)$/', $first, $m)) {
        $ids[] = (int) $m[2];
    }
}
$lastG = $ids === [] ? 0 : max($ids);
$familyRows = 0;
$at = 0;
while (($start = strpos($after, '<w:tr', $at)) !== false) {
    $end = strpos($after, '</w:tr>', $start);
    if ($end === false) {
        break;
    }
    $end += 7;
    $at = $end;
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', substr($after, $start, $end - $start), $cm);
    if (preg_match('/^[GDUXN]-\d+$/', $flat($cm[0][0] ?? ''))) {
        $familyRows++;
    }
}

printf("rows scanned: %d G-family ids, last is G-%d; the five families together hold %d rows\n",
    count($ids), $lastG, $familyRows);

foreach ([
    ['G-1 … G-43', 0, 'the old index range must be gone'],
    ['G-1 … G-50', 1, 'the new index range must be present once'],
] as [$needle, $want, $why]) {
    if (($hits = substr_count($after, $needle)) !== $want) {
        fwrite(STDERR, "REFUSED ({$why}): [{$needle}] occurs {$hits}, want {$want}.\n");

        exit(1);
    }
}

if ($lastG !== 50) {
    fwrite(STDERR, "REFUSED: the register's last G id is G-{$lastG}, not G-50 — the index range says something the rows do not.\n");

    exit(1);
}

// The §29.4 cell is judged against the rows, not against the number this pass typed.
$cellSeen = substr_count($after, '>67</w:t>') + substr_count($after, 'xml:space="preserve">67<');
printf("the family cell now reads 67 (%d run(s) carry that text), and the rows answer %d\n", $cellSeen, $familyRows);
if ($familyRows !== 67) {
    fwrite(STDERR, "REFUSED: §29.4 would state 67 while the rows answer {$familyRows}.\n");

    exit(1);
}

echo "APPLIED: the index range and the family count follow the rows.\n";
