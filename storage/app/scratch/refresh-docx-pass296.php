<?php

/**
 * Pass 296: §1.1 records G-40's first slice — one mechanism, one wired queue, and the fixture that hid a defect.
 *
 * Cloned from the newest §1.1 row so the new row inherits that table's two-cell structure, then written by cell
 * offset with the same read-back guards as passes 292 and 294: one run per cell, the flattened cell must equal the
 * text handed to it, container counts balance, document.xml parses, and the archive reads back byte for byte.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass296.php [--apply]
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', array_slice($argv, 1), true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unknown argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$zip = new ZipArchive;
if (! $zip->open($docx)) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};
$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
$cellsOf = static function (string $row): array {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);

    return $cm[0];
};
$rowsStartingWith = static function (string $xml, string $prefix) use ($flatten, $cellsOf): array {
    $hits = [];
    $at = 0;
    while (($start = strpos($xml, '<w:tr', $at)) !== false) {
        $end = strpos($xml, '</w:tr>', $start);
        if ($end === false) {
            break;
        }
        $end += 7;
        $row = substr($xml, $start, $end - $start);
        $text = implode(' ', array_map($flatten, $cellsOf($row)));
        if (strncmp($text, $prefix, strlen($prefix)) === 0) {
            $hits[] = [$start, $end, $row];
        }
        $at = $end;
    }

    return $hits;
};
$writeCell = static function (string $row, int $index, string $text) use ($flatten, $escape, $cellsOf): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm, PREG_OFFSET_CAPTURE);
    if (! isset($cm[0][$index])) {
        fwrite(STDERR, "REFUSED: the row has no cell {$index}.\n");

        exit(1);
    }
    $at = (int) $cm[0][$index][1];
    $target = strlen($cm[0][$index][0]);
    $cell = $cm[0][$index][0];
    preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
    foreach (array_slice($pm[0], 1) as $extra) {
        if ($flatten($extra) !== '') {
            fwrite(STDERR, "REFUSED: cell {$index} holds a second paragraph with text.\n");

            exit(1);
        }
        $cell = str_replace($extra, '', $cell);
    }
    preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $body = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
    $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
        .$escape($text).'</w:t></w:r></w:p>', $body);
    if (substr_count($new, '<w:t xml:space="preserve">') !== 1) {
        fwrite(STDERR, "REFUSED: cell {$index} would hold more than one text run.\n");

        exit(1);
    }
    $out = substr_replace($row, $new, $at, $target);
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $out, $check);
    if ($flatten($check[0][$index]) !== $text) {
        fwrite(STDERR, "REFUSED: cell {$index} reads [".mb_substr($flatten($check[0][$index]), 0, 80)."].\n");

        exit(1);
    }

    return $out;
};

$anchor = '2026-10-29 What is left, measured twice';
$tpl = $rowsStartingWith($xml, $anchor);
if (count($tpl) !== 1) {
    fwrite(STDERR, 'REFUSED: the lane-69 row matched '.count($tpl)." rows, expected 1.\n");

    exit(1);
}
[$rStart, $rEnd, $template] = $tpl[0];
if (count($cellsOf($template)) !== 2) {
    fwrite(STDERR, 'REFUSED: the template row has '.count($cellsOf($template))." cells, and this pass writes two.\n");

    exit(1);
}

$mine = '2026-10-29 One sort mechanism, one wired queue';
if ($rowsStartingWith($xml, $mine) !== []) {
    fwrite(STDERR, "REFUSED: this pass's own row is already in the document.\n");

    exit(1);
}

$record = 'G-40 contained, not closed. Prompt §14 asks for sorting; every desk queue had exactly one order since it '
    .'was written. There is now one mechanism — `App\Support\ListSort` takes the column from the handler’s own '
    .'allow-list and never from the request string, because an identifier cannot be bound as a parameter, reduces the '
    .'direction to ascending or descending, joins a relation column under a private alias, appends the row’s own key '
    .'so two rows tied on the sorted value cannot arrive on both pages, and returns what it actually applied — and one '
    .'shared control, in `DataTable.jsx`, which paints a header button only for a column the handler both listed and '
    .'echoed, so a screen cannot offer an order the server would ignore. Sorting is server-side by rule, not by '
    .'preference: these lists paginate on the server, so a client sort orders the twenty rows already on screen and '
    .'presents that as the queue — the page-versus-set defect §28.3 records for tiles, arriving in a new costume. The '
    .'Registrar approval queue is wired; nothing else is. 35 of the 36 pages that render the shared table still offer '
    .'no order, and the pagination half of this row is untouched, so §28.3 keeps G-40 leading `Open` on purpose and '
    .'§28.7 keeps listing it — closing a register row on one wired screen is the overclaim this document exists to '
    .'refuse. Five cases pin it, proved by a harness that plants four ways the promise could stop meaning anything '
    .'(allow-list bypassed, direction passed through, order never echoed, order never applied), each requiring its '
    .'named case to report one failure. The fourth leg SURVIVED its first run, and the defect was the fixture: the '
    .'three students had been created in the order that made the old hard-coded newest-first sort return exactly the '
    .'surname-ascending list the case asserted, so the restored mutant passed and looked like a surviving guard. '
    .'Re-picking the fixture so the wanted order differs from both id orders made it kill. A fixture whose wrong order '
    .'agrees with the defect it is meant to catch is invisible to every green run. Two promises are named as unproved '
    .'rather than quietly dropped: the tie-break carries no case, because on SQLite two tied rows usually return in '
    .'insertion order anyway and such a leg would measure the database’s mood rather than a defect; and the sortable '
    .'set travels from the handler to the page precisely so the screen cannot drift ahead of it, which is the same '
    .'rule that retired a Verify button painted on a draft.';

$row = $writeCell($template, 0, $mine);
$row = $writeCell($row, 1, $record);
if ($row === $template) {
    fwrite(STDERR, "REFUSED: the cloned row is byte-identical to its template.\n");

    exit(1);
}
$xml = substr_replace($xml, $row, $rEnd, 0);

foreach ([['<w:p', '</w:p>'], ['<w:t', '</w:t>'], ['<w:tc', '</w:tc>'], ['<w:tr', '</w:tr>'], ['<w:tbl', '</w:tbl>'], ['<w:r', '</w:r>']] as [$o, $c]) {
    $a = preg_match_all('/'.preg_quote($o, '/').'(?=[\s>])/', $xml);
    $b = substr_count($xml, $c);
    if ($a !== $b) {
        fwrite(STDERR, "REFUSED: unbalanced {$o} ({$a} against {$b}).\n");

        exit(1);
    }
}
if (simplexml_load_string($xml) === false) {
    fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses.\n");

    exit(1);
}
foreach ([$mine, 'G-40 contained, not closed', 'measure the database’s mood rather than a defect'] as $want) {
    if (! str_contains($xml, $want)) {
        fwrite(STDERR, "REFUSED: the edited document does not contain [{$want}].\n");

        exit(1);
    }
}

printf("%s: §1.1 records G-40's first slice (%d bytes of record)\n", $apply ? 'APPLY' : 'DRY RUN', strlen($record));
if (! $apply) {
    echo "DRY RUN — nothing written.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass296-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: no backup at {$backup}.\n");

    exit(1);
}
$w = new ZipArchive;
if ($w->open($docx, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "REFUSED: cannot reopen for writing. Restore from {$backup}.\n");

    exit(1);
}
$w->addFromString('word/document.xml', $xml);
$w->close();

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();
if ($read !== $xml) {
    fwrite(STDERR, "REFUSED: the archive did not read back its bytes. Restore from {$backup}.\n");

    exit(1);
}

echo "\npass 296 applied: §1.1 carries G-40's first slice and the fixture lesson.\n";
