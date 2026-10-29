<?php

/**
 * Pass 289c: the two owner-decided §28.3 rows must name the actor exactly as §28.8's row is labelled.
 *
 * The text-edit route is closed: the engine refuses a replacement carrying `<` or `>`, and an XML run
 * boundary is exactly what those characters are. So the cell is rewritten whole, by offset, keeping its
 * rPr — the way pass 281/282/289 write cells — anchored on the row's own flattened prefix.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass289c.php [--apply]
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
$cellsOf = static function (string $row, bool $offsets = false): array {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm, $offsets ? PREG_OFFSET_CAPTURE : 0);

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

$ACTOR = 3;
$jobs = [
    ['G-41 Prompt §14', 'Project owner'],
    ['G-42 A deactivated account', 'Project owner (session cut-off only)'],
];

foreach ($jobs as [$prefix, $actor]) {
    $hits = $rowsStartingWith($xml, $prefix);
    if (count($hits) !== 1) {
        fwrite(STDERR, 'REFUSED: the row ['.$prefix.'] matched '.count($hits)." rows.\n");

        exit(1);
    }
    [$start, $end, $row] = $hits[0];
    $cells = $cellsOf($row, true);
    if (count($cells) !== 5) {
        fwrite(STDERR, 'REFUSED: the ['.$prefix.'] row holds '.count($cells)." cells.\n");

        exit(1);
    }
    $was = $flatten($cells[$ACTOR][0]);
    if ($was !== 'Owner' && ! str_starts_with($was, 'Owner')) {
        fwrite(STDERR, "REFUSED: the ['.$prefix.'] actor cell reads [{$was}], not an Owner spelling.\n");

        exit(1);
    }

    $src = $cells[$ACTOR][0];
    $cell = $src;
    preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
    foreach (array_slice($pm[0], 1) as $extra) {
        if ($flatten($extra) !== '') {
            fwrite(STDERR, "REFUSED: the actor cell of [$prefix] holds a second paragraph with text.\n");

            exit(1);
        }
        $cell = str_replace($extra, '', $cell);
    }
    preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $body = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
    $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
        .$escape($actor).'</w:t></w:r></w:p>', $body);
    if (substr_count($new, '<w:t xml:space="preserve">') !== 1) {
        fwrite(STDERR, "REFUSED: the actor cell of [$prefix] would hold more than one text run.\n");

        exit(1);
    }
    $rowNew = substr_replace($row, $new, (int) $cells[$ACTOR][1], strlen($src));
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $rowNew, $check);
    if ($flatten($check[0][$ACTOR]) !== $actor) {
        fwrite(STDERR, 'REFUSED: the actor cell reads ['.$flatten($check[0][$ACTOR])."].\n");

        exit(1);
    }
    $xml = substr_replace($xml, $rowNew, $start, $end - $start);
    echo "rewrote         [$prefix] actor: [$was] → [$actor]\n";
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

printf("%s: the two owner rows now agree with §28.8's label\n", $apply ? 'APPLY' : 'DRY RUN');
if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass289c-backup-'.date('Ymd-His').'.docx';
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

echo "\npass 289c applied.\n";
