<?php

/**
 * Pass 283: §1.1 records the register cascade (passes 281 and 282).
 *
 * The sweep's own row said the cascade "gets its own pass" — that pass has now run, and a dated §1.1 row is
 * never re-aged, so this row supersedes it by being written beside it rather than by retyping the older one.
 *
 * Cloned from the newest row, which this batch wrote: the sweep row, anchored on its date plus the first words
 * of its record. Cells are written by offset as one run keeping the template's rPr, and every cloned cell is
 * required to read back as the text handed to it (pass 173's lesson: a cloned cell keeps the template's tail).
 *
 * Usage: php storage/app/scratch/refresh-docx-pass283.php [--apply]
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
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");

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
$rowText = static function (string $row) use ($flatten, $cellsOf): string {
    return implode(' ', array_map($flatten, $cellsOf($row)));
};
$rowsStartingWith = static function (string $xml, string $prefix) use ($rowText): array {
    $hits = [];
    $at = 0;
    while (($start = strpos($xml, '<w:tr', $at)) !== false) {
        $end = strpos($xml, '</w:tr>', $start);
        if ($end === false) {
            break;
        }
        $end += 7;
        $row = substr($xml, $start, $end - $start);
        if (strncmp($rowText($row), $prefix, strlen($prefix)) === 0) {
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
    [$src, $at, ] = [$cm[0][$index][0], (int) $cm[0][$index][1], null];
    $len = strlen($src);
    $cell = $src;
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
    $out = substr_replace($row, $new, $at, $len);
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $out, $check);
    if ($flatten($check[0][$index]) !== $text) {
        fwrite(STDERR, "REFUSED: cell {$index} did not come back as written; it reads ["
            .mb_substr($flatten($check[0][$index]), 0, 90)."].\n");

        exit(1);
    }

    return $out;
};

$anchor = '2026-10-29 Completion sweep of the development prompt';
$templates = $rowsStartingWith($xml, $anchor);
if (count($templates) !== 1) {
    fwrite(STDERR, 'REFUSED: the sweep row matched '.count($templates)." rows, expected 1.\n");

    exit(1);
}
[$rStart, $rEnd, $template] = $templates[0];
if (count($cellsOf($template)) !== 2) {
    fwrite(STDERR, 'REFUSED: the §1.1 template holds '.count($cellsOf($template))." cells.\n");

    exit(1);
}

$mine = '2026-10-29 The register cascade';
if ($rowsStartingWith($xml, $mine) !== []) {
    fwrite(STDERR, "REFUSED: this pass's own row is already in the document.\n");

    exit(1);
}

$record = 'The register cascade the sweep row promised has now been written, by passes 281 and 282. '
    .'§28.3 now holds G-33 through G-43 as five-column rows — id, the gap stated once, what the build does '
    .'today led by its verdict, who must act, where it was raised — and §28.5 holds the six questions those '
    .'gaps put to the school, each stating what the repository cannot settle, what the answer costs, and how it '
    .'comes back off, pointing at the sheet rather than re-copying it, because a register that re-copies a '
    .'packet is how an answered question gets asked again. Two cells in §29.4 moved with the rows they count, '
    .'and one of them was already stale before this batch touched it: the gap-family cell stated forty-eight '
    .'against the forty-nine rows it claims to number, which is the count-that-stopped-growing defect §28.3 '
    .'G-30 exists to record — the pass prints that as its own finding instead of absorbing it. The decision '
    .'cell moved from twenty to twenty-six, and so did the living sentence that states the same figure in '
    .'words, because two copies of one number with only one following is a half-applied register. Rows are '
    .'counted by partition, not pattern: a table\'s rows are the consecutive rows of its own cell width that '
    .'follow its header, so a question-shaped row in a neighbouring table cannot inflate the figure and the '
    .'pass refuses unless the partition numbers twenty before the insert and twenty-six after. Three defects '
    .'of mine were caught before a byte was written: a closure called a helper it never captured and died on '
    .'the first line; a prefix guard carried a hand-typed length of forty-four for a forty-two-byte anchor, so '
    .'a correct table looked like a moved one; and a duplication check counted characters where the questions '
    .'carry typographic apostrophes and dashes and the bytes disagree. §28.3\'s newest row also keeps a verdict '
    .'where its header promises an actor — an inconsistency in the register, not in these passes, and the tool '
    .'that counts that column will read it until someone re-takes the row.';

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
foreach (['The register cascade', 'forty-nine rows', 'partition, not pattern'] as $want) {
    if (substr_count($xml, $want) < 1) {
        fwrite(STDERR, "REFUSED: the edited document does not contain [{$want}].\n");

        exit(1);
    }
}

printf("%s: §1.1 records the register cascade (%d bytes of record)\n", $apply ? 'APPLY' : 'DRY RUN', strlen($record));
if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass283-backup-'.date('Ymd-His').'.docx';
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

echo "\npass 283 applied: §1.1 carries the cascade, and the sweep row's promise is now a record.\n";
