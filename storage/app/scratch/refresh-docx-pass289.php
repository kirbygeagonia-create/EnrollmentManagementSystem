<?php

/**
 * Pass 289: §28.8's "who owes what" table has to agree with the column this batch widened.
 *
 * Pass 281 put an actor in §28.3's "Who must act" column for G-33, G-35, G-37, G-41 and G-42. That is the
 * column `lane69-owes-table-check.php` counts, and CI runs it as a step — so the table that claims to be the
 * same tally went from agreeing at 2 to naming 2 against 7. The gate was right and I missed it locally by not
 * running the checker before pushing, which is the same failure this file names about every other instrument:
 * a green citation sweep is not a green gate list.
 *
 * Rows are located by their FLATTENED prefix (cells joined with a space), which is unique here —
 * `Registrar 0 None —` and `Project owner 1 G-13` — and cells are rewritten by offset keeping each cell's
 * rPr, with a read-back requirement, as pass 281/282 do.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass289.php [--apply]
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
$writeCell = static function (string $row, int $index, string $text) use ($flatten, $escape): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm, PREG_OFFSET_CAPTURE);
    if (! isset($cm[0][$index])) {
        fwrite(STDERR, "REFUSED: the row has no cell {$index}.\n");

        exit(1);
    }
    $src = $cm[0][$index][0];
    $at = (int) $cm[0][$index][1];
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
    $out = substr_replace($row, $new, $at, strlen($src));
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $out, $check);
    if ($flatten($check[0][$index]) !== $text) {
        fwrite(STDERR, "REFUSED: cell {$index} reads [".mb_substr($flatten($check[0][$index]), 0, 80)."].\n");

        exit(1);
    }

    return $out;
};

$jobs = [
    [
        'prefix' => 'Registrar 0 None',
        'cells' => [
            1 => '3',
            2 => 'G-33 (only its vocabulary: fixed list or free text), G-35 (where a notice takes the desk), '
                .'G-37 (the audit action word list) — all three are asked on '
                .'Documentation/School-Decision-Sheet-2026-10-29.md with what each answer costs and how it '
                .'comes back off; the mechanisms behind G-33 and G-34 are built and pinned.',
        ],
    ],
    [
        'prefix' => 'Project owner 1 G-13',
        'cells' => [
            1 => '3',
            2 => 'G-13 (install-time, DEPLOYMENT.md §2 — where the proxy instruction lives; ems:preflight now '
                .'answers whether the scheme can be decided, and since the same date the value it reads is the '
                .'TRUSTED_PROXIES env var), plus G-41 (whether a browser check belongs in the pipeline) and the '
                .'remaining half of G-42 (how long a signed-in session lives after a desk deactivates the '
                .'account) — both on the 2026-10-29 sheet.',
        ],
    ],
];

foreach ($jobs as $job) {
    $hits = $rowsStartingWith($xml, $job['prefix']);
    if (count($hits) !== 1) {
        fwrite(STDERR, 'REFUSED: the row ['.$job['prefix'].'] matched '.count($hits)." rows, expected 1.\n");

        exit(1);
    }
    [$start, $end, $row] = $hits[0];
    if (count($cellsOf($row)) !== 3) {
        fwrite(STDERR, 'REFUSED: the ['.$job['prefix'].'] row holds '.count($cellsOf($row))." cells.\n");

        exit(1);
    }
    $new = $row;
    foreach ($job['cells'] as $i => $text) {
        $new = $writeCell($new, $i, $text);
    }
    $xml = substr_replace($xml, $new, $start, $end - $start);
    echo 'rewrote         ['.$job['prefix'].'] → '.$job['cells'][1]." owed\n";
}

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

printf("%s: §28.8's owed table now lists the seven the column names\n", $apply ? 'APPLY' : 'DRY RUN');
if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass289-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: no backup.\n");

    exit(1);
}
$w = new ZipArchive;
if ($w->open($docx, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "REFUSED: cannot reopen. Restore from {$backup}.\n");

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

echo "\npass 289 applied.\n";
