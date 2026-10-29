<?php

/**
 * Pass 279: §1.1 records the prompt sweep of 2026-10-29.
 *
 * One two-cell §1.1 row, cloned from the newest row with the helpers pass 275 and pass 278 use: match the
 * row by its FLATTENED prefix, write each cell by offset as one run keeping its rPr, and refuse if a
 * cloned cell does not read back as the text handed to it.
 *
 * The engineering-register rows this sweep produced (G33 to G42) are deliberately NOT written here.
 * Adding a §28.3 or §28.5 row moves every place that counts those registers — the family range, the
 * §29.4 count cells, §28.8's tallies — and pass 173's lesson is that a row added before its own count
 * was re-measured leaves the document contradicting itself. That cascade gets its own pass, read back
 * against the numbers, not folded into a revision row.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass279.php [--apply]
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

$rowText = static function (string $row) use ($flatten): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);

    return implode(' ', array_map($flatten, $cm[0]));
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
        fwrite(STDERR, "REFUSED: the row has no cell {$index} (it has ".count($cm[0]).").\n");

        exit(1);
    }

    $out = $row;
    $targets = [];
    foreach ($cm[0] as $j => $x) {
        $targets[] = [$j, (int) $x[1], strlen($x[0]), $x[0]];
    }
    usort($targets, static fn (array $a, array $b): int => $b[1] <=> $a[1]);

    foreach ($targets as [$j, $at, $len, $src]) {
        if ($j !== $index) {
            continue;
        }
        $cell = $src;
        preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
        foreach (array_slice($pm[0], 1) as $extra) {
            if ($flatten($extra) !== '') {
                fwrite(STDERR, "REFUSED: cell {$index} holds a second paragraph with text — read it before rewriting.\n");

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
            fwrite(STDERR, "REFUSED: the replacement for cell {$index} holds more than one text run.\n");

            exit(1);
        }
        $out = substr_replace($out, $new, $at, $len);
    }

    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $out, $check);
    if ($flatten($check[0][$index]) !== $text) {
        fwrite(STDERR, "REFUSED: cell {$index} did not come back as the text written; it reads ["
            .$flatten($check[0][$index])."].\n");

        exit(1);
    }

    return $out;
};

$date = '2026-10-29';

$record = 'Completion sweep of the development prompt, and the register that had never read it: the '
    .'requirement-to-code matrix carried rows for fourteen headings while the prompt enumerates three hundred '
    .'fifty-seven requirement lines across fifty-five headings, so whole sections had never been walked at '
    .'all — counted from '
    .'the prompt file rather than recalled, because a denominator remembered is a denominator that drifts. The '
    .'sweep closed nothing quietly and produced ten new register items, G33 through G42, continuing the '
    .'numbering rather than reusing an id §28.3 already holds. Two are fixed in this batch. A clearance line '
    .'may now be refused only with a reason of at least ten characters, and a reasonless refusal moves '
    .'neither the line nor the clearance’s overall status, which is the failure mode: the desk had refused a '
    .'student on a blank cell; the case that keeps the fix honest is the one showing an approval with '
    .'nothing typed still passes. A deactivated account is refused at the login screen by a test that also '
    .'authenticates the same account once it is active again, so the pair cannot pass by the password '
    .'failing. Eight remain: an admission rejected with no reason anywhere, which needs the column the table '
    .'does not have; a notice with no title and no link, so a desk reads a sentence and hunts for the record; '
    .'nothing stopping a duplicate notice when a queued listener retries; the audit trail storing create, '
    .'update and delete rather than the seven action families the prompt names — naming them needs no '
    .'migration, since the action column is a varchar, which is why the question is put as a vocabulary '
    .'question and not a schema one; who may read clinical notes as opposed to the clinic’s outcome, which is '
    .'the Data Protection Officer’s; a print page re-declaring a status tone map that has not learned the two '
    .'return states; no list table sorting at all; and §14’s clause about console errors and empty, loading '
    .'and responsive states, which has no instrument and is therefore unverified rather than passing. Five of '
    .'these go to the school on Documentation/School-Decision-Sheet-2026-10-29.md, beside the one half of the '
    .'closed login test that waits on the school’s own session cut-off — six items in all, '
    .'each with what the answer costs and how it comes back off. Two findings came out of checking the sweep’s own evidence: the '
    .'audit gate and the open-item inventory both kept their document lists by hand, so the sheet issued with '
    .'this batch would have been read by neither, and both now bind to the directory and refuse when it '
    .'matches nothing — proved by planting a dated file, watching both react, and removing it. And three of '
    .'the claims this sweep was handed did not survive opening the files, which is recorded rather than '
    .'buried: no bulk update bypasses the audit observer anywhere in app, an override act does exist at the '
    .'Evaluation desk, and only one print page re-declares a tone map, not all of them.';

$anchor = '2026-10-28 Server-side dates';
$templates = $rowsStartingWith($xml, $anchor);
if (count($templates) !== 1) {
    fwrite(STDERR, 'REFUSED: the §1.1 anchor row ['.$anchor.'] matched '.count($templates)." row(s), expected exactly 1.\n");

    exit(1);
}
[$rStart, $rEnd, $template] = $templates[0];
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template, $rc);
if (count($rc[0]) !== 2) {
    fwrite(STDERR, 'REFUSED: the §1.1 template row holds '.count($rc[0])." cells; this pass writes a two-cell row.\n");

    exit(1);
}
if ($rowsStartingWith($xml, $date.' Completion sweep') !== []) {
    fwrite(STDERR, "REFUSED: this pass's own §1.1 row is already in the document.\n");

    exit(1);
}

$rowClone = $template;
foreach ([$date, $record] as $index => $value) {
    $rowClone = $writeCell($rowClone, $index, $value);
}
if ($rowClone === $template) {
    fwrite(STDERR, "REFUSED: the cloned §1.1 row is byte-identical to its template.\n");

    exit(1);
}
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $rowClone, $checkCells);
foreach ($checkCells[0] as $ci => $cellXml) {
    $want = $ci === 0 ? $date : $record;
    if ($flatten($cellXml) !== $want) {
        fwrite(STDERR, "REFUSED: cloned cell {$ci} reads [".mb_substr($flatten($cellXml), 0, 120)."].\n");

        exit(1);
    }
}

$xml = substr_replace($xml, $rowClone, $rEnd, 0);

foreach ([['<w:p', '</w:p>'], ['<w:t', '</w:t>'], ['<w:tc', '</w:tc>'], ['<w:tr', '</w:tr>'], ['<w:tbl', '</w:tbl>'], ['<w:r', '</w:r>']] as [$open, $close]) {
    $a = preg_match_all('/'.preg_quote($open, '/').'(?=[\s>])/', $xml);
    $b = substr_count($xml, $close);
    if ($a !== $b) {
        fwrite(STDERR, "REFUSED: the edited document is unbalanced in {$open} ({$a} against {$b}).\n");

        exit(1);
    }
}

if (simplexml_load_string($xml) === false) {
    fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses.\n");

    exit(1);
}

foreach (['Completion sweep of the development prompt', 'G33 through G42', 'no migration'] as $want) {
    if (substr_count($xml, $want) < 1) {
        fwrite(STDERR, "REFUSED: the edited document does not contain [{$want}].\n");

        exit(1);
    }
}

printf("%s§1.1 takes the prompt sweep (%d bytes of record)\n", $apply ? '' : 'DRY RUN — ', strlen($record));

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass279-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup to {$backup}.\n");

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
    fwrite(STDERR, "REFUSED: the archive did not read back the bytes it was handed. Restore from {$backup}.\n");

    exit(1);
}

echo "\npass 279 applied: §1.1 records the prompt sweep and the ten new register items.\n";
