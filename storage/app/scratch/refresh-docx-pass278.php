<?php

/**
 * Pass 278: §1.1 records lane 85 — three server-side places that answered the daemon's day.
 *
 * The code moved in commit f087208 and the authority document has not followed it yet, which is the
 * defect this file's own rule names ("when the code moves, the docx is edited to follow in the same
 * batch of work"). This pass adds ONE two-cell row to §1.1 and changes no other sentence, because the
 * document never stated the three things that were wrong: no §-section described the issue stamp, the
 * certificate's fallback date, or the terms filter's day.
 *
 * Helpers are lifted from refresh-docx-pass275.php unchanged: rows are located by their FLATTENED
 * prefix (a cell value alone is not unique), a cell is written by offset as one run keeping its own
 * rPr, and a trailing empty paragraph in a cloned cell is dropped only when it holds no text.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass278.php [--apply]
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

/** @return array<int, array{0:int,1:int,2:string}> every <w:tr> whose flattened text starts with $prefix */
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

/** Write one cell of a row as a single run, keeping that cell's own run properties. */
$writeCell = static function (string $row, int $index, string $text) use ($flatten, $escape, $rowText): string {
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

$assertOnce = static function (string $xml, string $needle, string $what) {
    $n = substr_count($xml, $needle);
    if ($n !== 1) {
        fwrite(STDERR, "REFUSED: {$what} occurs {$n} time(s), expected exactly 1: [{$needle}]\n");

        exit(1);
    }
};

// ── the record ────────────────────────────────────────────────────────────────────────────────
$date = '2026-10-28';

$record = 'Server-side dates: three places answered the machine’s day, and the one instrument that '
    .'polices that rule opened only the browser. EnrollmentIssuer::issue() stamped an enrollment’s '
    .'formIssuedDate from now()->toDateString(), so a form issued at 02:00 Manila carries the day before '
    .'onto the paper the Registrar prints; RegistrarController’s certificate falls back to the same value '
    .'when a record holds no issue date; and ReferenceDataController::terms() filtered Active against '
    .'Inactive by comparing that value with a term’s startDate and endDate, so a term open at the school '
    .'read as closed for eight hours. All three now ask SchoolTime::today(), which is what '
    .'Academicterms::covering() and the dashboard’s active-term tile always did. The rule itself is older '
    .'than this finding: it is written down, and lane 50 — the checker that owns it — bound only cells '
    .'under resources/js and never read a PHP file, so the server side of its own rule was invisible. It '
    .'now reads app/ and tools/ as well, reporting UTC-DAY-STAMP, UTC-DAY-COMPARE and PHP-UNBOUND, and '
    .'tools/ matters because PHPStan covers app only and pint.json excludes tools, so nothing else in '
    .'the repository opens that directory at all. Three new cases freeze the clock and assert the UTC '
    .'reading is different, so none can pass by both clocks agreeing: PrintControllerTest answers 26 '
    .'tests over 155 assertions and ReferenceDataCrudTest 13 over 186, and '
    .'storage/app/scratch/mutate-lane85-school-day-tests.php puts each defect back and requires its own '
    .'named case to fail. Two defects came out of the harness legs rather than out of the checker’s '
    .'silence: a pattern built by concatenation had lost its closing slash, so it warned and answered '
    .'nothing, and the bound between a where( clause and the hoisted day read on to the next semicolon — '
    .'inside a fluent chain there is none until the last line, so it accused semester, a real column that '
    .'is not a day, and reported a clean tree. The same legs found that mutate-lane50-checker.php had '
    .'been red for weeks while the checker it proves was green, because a refusal prints no problem '
    .'count and null is never zero; it now treats a refusal as no counted problems and requires every '
    .'unbound cell to be itemised. Also in this batch: the five day-stamps in tools/seed_demo_data.php, '
    .'and that file’s header no longer directs a reader to the September database dump, which left the '
    .'repository on 2026-10-26 — this was the fourth place still naming it, after the two demo-day guides '
    .'and the pipeline’s own banner were re-pointed.';

// ── the §1.1 row ──────────────────────────────────────────────────────────────────────────────
$anchor = '2026-10-27 Prompt §6.1 and §6.3';
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
if ($rowsStartingWith($xml, $date.' Server-side dates') !== []) {
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

// The cloned row must not inherit the template's prose in a later run (pass 173's defect).
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $rowClone, $checkCells);
foreach ($checkCells[0] as $ci => $cellXml) {
    $text = $flatten($cellXml);
    $want = $ci === 0 ? $date : $record;
    if ($text !== $want) {
        fwrite(STDERR, "REFUSED: cloned cell {$ci} reads [".mb_substr($text, 0, 120)."] — it did not come back as written.\n");

        exit(1);
    }
}

$xml = substr_replace($xml, $rowClone, $rEnd, 0);

// ── guards on the whole edited document ───────────────────────────────────────────────────────
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

// The row is a record, so the two class figures it states must be the ones the run printed.
foreach (['26 tests over 155 assertions', '13 over 186', 'UTC-DAY-STAMP', 'Server-side dates'] as $want) {
    if (substr_count($xml, $want) < 1) {
        fwrite(STDERR, "REFUSED: the edited document does not contain [{$want}].\n");

        exit(1);
    }
}

printf("%s§1.1 takes the lane 85 record (%d bytes)\n", $apply ? '' : 'DRY RUN — ', strlen($record));

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass278-backup-'.date('Ymd-His').'.docx';

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

echo "\npass 278 applied: §1.1 records the server-side school-day fixes.\n";
