<?php

/**
 * Docx pass 254 — §22.2's Total row, which its own table disproves.
 *
 * The module table lists fourteen modules, one row each, Count column second. Read off the dump on
 * 2026-10-27 the counts are 8, 6, 6, 6, 5, 11, 5, 4, 5, 15, 7, 12, 1, 3 — 94 — and live `ems` answers
 * 94 rows in `permissions`. The Total row still reads 93, because pass 252 moved the exam module's
 * cell from 4 to 5 and nobody moved the sum. No checker can see this shape: `lane60` binds a figure
 * only where the sentence names its instrument, the citation sweeps judge lines not meaning, and
 * `lane64` weighs a section's set membership rather than a table's arithmetic.
 *
 * The engine's `cell` kind is not usable here: its row anchor must be unique in `word/document.xml`,
 * and a row whose leading cell reads "Total" is not unique (§23.3's inventory has one), while a bare
 * "93" needle is not either. So the row is rewritten by BYTE OFFSET, the way pass 251b writes cloned
 * cells: find the row whose cells flatten to exactly "Total 93", require it to be the only one, locate
 * the second cell's `<w:t>` and replace the digits, then re-read the whole table out of the edited
 * bytes and require sum(rows) == Total == the server's count.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass254.php [--apply]
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

$zip = new ZipArchive;
if (! $zip->open($docx)) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};

/** Every `<w:tr>` in the document, with its cells' flattened text. */
$tableRows = static function (string $xml) use ($flatten): array {
    $out = [];
    $at = 0;
    while (($start = strpos($xml, '<w:tr', $at)) !== false) {
        $end = strpos($xml, '</w:tr>', $start);
        if ($end === false) {
            break;
        }
        $end += 7;
        $row = substr($xml, $start, $end - $start);
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
        $cells = array_map(static fn (string $c): string => preg_replace('/\s+/', ' ', html_entity_decode(trim($flatten($c)), ENT_QUOTES | ENT_XML1)), $cm[0]);
        $out[] = ['start' => $start, 'end' => $end, 'cells' => $cells];
        $at = $end;
    }

    return $out;
};

$rows = $tableRows($xml);

// 1 — the row to repair: §22.2 is a three-column table (Module | Count | Permission names) and the
// Total row leaves the third cell empty, so the predicate reads the first two cells, not the tuple.
$targets = array_values(array_filter(
    $rows,
    static fn (array $r): bool => ($r['cells'][0] ?? '') === 'Total' && ($r['cells'][1] ?? '') === '93'
));
if (count($targets) !== 1) {
    fwrite(STDERR, 'REFUSED: expected exactly one row leading Total | 93, found '.count($targets).".\n");
    exit(1);
}
$total = $targets[0];

// 2 — the table it belongs to: the run of rows whose second cell is a small integer and whose first
// is a module word. Located by walking UP from the Total row until the header leaves the table.
$idx = array_search($total, $rows, true);
$moduleCounts = [];
for ($i = $idx - 1; $i >= 0; $i--) {
    $c = $rows[$i]['cells'];
    if (count($c) !== 3) {
        break;
    }
    if (! preg_match('/^\d+$/', $c[1]) || ! preg_match('/^[a-z]+$/', $c[0])) {
        break;
    }
    $moduleCounts[$c[0]] = (int) $c[1];
}
ksort($moduleCounts);
if (count($moduleCounts) !== 14) {
    fwrite(STDERR, 'REFUSED: the module table above the Total row read '.count($moduleCounts)
        .' module rows, expected 14 — the boundary moved, so the sum is not this table\'s.\n');
    exit(1);
}
$sum = array_sum($moduleCounts);

// 3 — the server's own count, taken now rather than recalled.
$server = (int) trim((string) shell_exec('C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe -u root ems -N -e "SELECT COUNT(*) FROM permissions;" 2>&1'));

printf("module rows read: %d, sum = %d\nserver answers permissions: %d\nTotal cell now reads: %s\n",
    count($moduleCounts), $sum, $server, $total['cells'][1]);

if ($sum !== 94 || $server !== 94) {
    fwrite(STDERR, "REFUSED: the table and the server must both answer 94 before this pass writes.\n");
    exit(1);
}

// 4 — rewrite the digits inside that row's second cell, by offset.
$cellStart = strpos($xml, '<w:tc>', $total['start']);
$cellStart = strpos($xml, '<w:tc>', $cellStart + 6);
if ($cellStart === false || $cellStart >= $total['end']) {
    fwrite(STDERR, "REFUSED: could not locate the second cell of the Total row.\n");
    exit(1);
}
$cellEnd = strpos($xml, '</w:tc>', $cellStart);
$cell = substr($xml, $cellStart, $cellEnd - $cellStart);
if ($flatten($cell) !== '93') {
    fwrite(STDERR, "REFUSED: the cell this pass would edit reads '".$flatten($cell)."', not 93.\n");
    exit(1);
}
if (substr_count($cell, '>93<') !== 1) {
    fwrite(STDERR, 'REFUSED: the digits 93 sit in '.substr_count($cell, '>93<').' run(s) of that cell; one offset cannot carry two edits.\n');
    exit(1);
}
$newCell = str_replace('>93<', '>94<', $cell);
$edited = substr_replace($xml, $newCell, $cellStart, $cellEnd + 7 - $cellStart);

// 5 — re-read the repaired table out of the edited bytes: the same row must now say Total | 94.
$after = array_values(array_filter(
    $tableRows($edited),
    static fn (array $r): bool => ($r['cells'][0] ?? '') === 'Total' && ($r['cells'][1] ?? '') === '94'
));
if (count($after) !== 1) {
    fwrite(STDERR, 'REFUSED: after the edit, '.count($after).' row(s) read Total | 94; expected 1.\n');
    exit(1);
}
if (substr_count($edited, '<w:tr') !== substr_count($xml, '<w:tr')) {
    fwrite(STDERR, "REFUSED: the edit changed the number of rows.\n");
    exit(1);
}

foreach ($moduleCounts as $m => $n) {
    echo '  ',str_pad((string) $m, 12),$n,"\n";
}

if (! $apply) {
    echo "\nDRY RUN — pass --apply to write\n";
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass254-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup.\n");
    exit(1);
}

$zip = new ZipArchive;
if (! $zip->open($docx, ZipArchive::CREATE)) {
    fwrite(STDERR, "REFUSED: cannot reopen for writing.\n");
    exit(1);
}
$zip->addFromString('word/document.xml', $edited);
$zip->close();

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();
if ($read !== $edited) {
    fwrite(STDERR, "REFUSED: the archive did not read back the bytes it was handed.\n");
    exit(1);
}

echo 'backup: '.basename($backup)."\nwritten: the Total row now reads 94, matching its own fourteen rows and the server.\n";
