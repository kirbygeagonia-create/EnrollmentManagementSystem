<?php

/**
 * Pass 286: §1.1 records G-39's closure and the two instrument findings it produced.
 *
 * Cloned from this batch's own newest row (the register-cascade row pass 283 wrote), so the date cell and the
 * record cell go through the same read-back guards as every other §1.1 row this session: one run per cell, the
 * template's rPr kept, and each cloned cell required to read back as the text handed to it.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass286.php [--apply]
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
    $cells = $cellsOf($row);
    if (! isset($cells[$index])) {
        fwrite(STDERR, "REFUSED: the row has no cell {$index}.\n");

        exit(1);
    }
    $at = 0;
    $target = -1;
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm, PREG_OFFSET_CAPTURE);
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

$anchor = '2026-10-29 The register cascade';
$tpl = $rowsStartingWith($xml, $anchor);
if (count($tpl) !== 1) {
    fwrite(STDERR, 'REFUSED: the cascade row matched '.count($tpl)." rows, expected 1.\n");

    exit(1);
}
[$rStart, $rEnd, $template] = $tpl[0];

$mine = '2026-10-29 One tone map for one status';
if ($rowsStartingWith($xml, $mine) !== []) {
    fwrite(STDERR, "REFUSED: this pass's own row is already in the document.\n");

    exit(1);
}

$record = 'G-39 closed. The certificate screen carried its own six-key status-tone map, which had never learned either '
    .'return state, so a record parked by the return act printed a neutral badge on paper while every desk showed it as '
    .'a hold; it now reads the single shared enrollment-tone map and the local helper is deleted rather than left '
    .'beside it as a second answer. Pinned by a test that reads the sources — the repository has no JavaScript test '
    .'suite, so this is the method §24’s year-level pairing already used: one case fails if ANY page re-declares the '
    .'enrollment vocabulary, one that the certificate page reads the shared map, one that the shared map carries both '
    .'return states. Two findings came out of closing it, and both are instrument findings rather than code ones. The '
    .'sweep had claimed every print view re-declared the tone map; the scan that found the one hold-out cleared all '
    .'the others, so the matrix row was re-taken whole rather than left stating a superseded census — a claim about a '
    .'set is refuted by enumerating the set, not by fixing one member of it. And deleting the construct aged the '
    .'pointer written against it: the citation gate went red on a range in a file that had become twelve lines shorter, '
    .'and re-pointing the number alone would have left the sentence still promising a map that no longer exists. So the '
    .'re-take covered the cell, not the digit — and the guard now in the test would have caught the original defect at '
    .'the moment the second return state was added, which is the whole argument for a pin over a fix. Still owed, and '
    .'named as owed: the read records for pointers whose keys aged when passes 281 and 282 inserted register rows — a '
    .'read key hashes a window of the flat dump that crosses row boundaries, so a pointer can age without any code '
    .'line moving under it — and the lookup order in the record generator, which consults the newest dated map first '
    .'and then the older one without a guard, so a key present in both ends wearing the older date. That is the '
    .'prefix-inheritance defect arriving through order instead of through the file-keyed fallback.';

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
foreach (['One tone map for one status', 'a claim about a set is refuted by enumerating the set', 'order instead of through the file-keyed fallback'] as $want) {
    if (substr_count($xml, $want) < 1) {
        fwrite(STDERR, "REFUSED: the edited document does not contain [{$want}].\n");

        exit(1);
    }
}

printf("%s: §1.1 records G-39 (%d bytes of record)\n", $apply ? 'APPLY' : 'DRY RUN', strlen($record));
if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass286-backup-'.date('Ymd-His').'.docx';
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

echo "\npass 286 applied: §1.1 carries G-39 and the two instrument findings.\n";
