<?php

/**
 * Pass 303: §1.1 records lanes 90 to 96 — two censuses, two privacy holes, and a fix that produced its own mirror.
 *
 * The register's revision log stopped at the sort mechanism (lane 88). Six lanes have landed since, and none of
 * them is in the authority document: the pagination census that retired an unmeasured claim, the coverage census
 * that measured the prompt's own population, G-48 and G-49 (a credential hash and a clinical note reaching every
 * desk), G-45's after-commit half, and G-46 (a dashboard badge and the list under it).
 *
 * Helpers are lifted from refresh-docx-pass278.php unchanged: rows are located by their FLATTENED prefix (a cell
 * value alone is not unique), a cell is written by offset as one run keeping its own rPr, a trailing empty
 * paragraph in a cloned cell is dropped only when it holds no text, and the cloned row is read back cell by cell
 * before anything is written.
 *
 * This row states no citation-shaped pointer: a `File.php:NN` literal inside a record row is a live citation to
 * the sweep, and this batch has already moved lines in four files.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass303.php [--apply]
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

// ── the record ────────────────────────────────────────────────────────────────────────────────
$date = '2026-10-30 Two censuses, two privacy holes, and a fix that made its own defect';

$record = 'Six lanes since the sort mechanism, each of them begun because a claim in this document had never been '
    .'measured rather than because the build was thought to be wrong. '

    .'**The pagination half of prompt §14 was re-weighed.** The register said two work queues carry no pagination '
    .'at all; the sentence had never been measured, and the probe written to measure it printed two fake censuses '
    .'before it said anything true — it compared backslash page paths against forward-slash render names and '
    .'resolved nothing, then asked only whether a controller FILE held a paginator, which condemned three lists '
    .'that do paginate. Read at the join where the claim lives — the page’s own rows expression, the render that '
    .'serves that page, and the assignment inside that one action, with comments and strings scanned rather than '
    .'stopped at — the thirty-six tables that use the shared component resolve into twenty-nine receiving a '
    .'paginator from their own index action, three receiving a plain collection (a day’s receipts, one '
    .'curriculum’s subjects, the settings rows) and four whose rows the browser maps over the prop it was given. '
    .'None of the three is a work queue, so the register’s claim is retired rather than corrected. The instrument '
    .'- and the twelve legs that prove it, two of which were replaced because the census did not move and a leg '
    .'that cannot fail is decoration - is in the scratch directory.'

    .'**The prompt’s own population was counted, not recalled.** A first register had rows for fourteen headings of '
    .'fifty-five because it walked the gaps it had found; the census now walks every heading and asks how many '
    .'things it enumerates and which of those the checkout answers. It reads seventy-three headings, thirty-nine '
    .'numbered, forty-eight carrying enumerated lines, three hundred fifty-seven lines in all, and scores twenty-eight '
    .'headings over two hundred twenty-nine lines as covered, with twenty unnumbered headings over one hundred '
    .'twenty-eight lines still owed. Three of the instrument’s own bugs came out before that figure could be '
    .'trusted: it read its own source rather than the prompt, a section sigil without the unicode flag silently '
    .'required the sigil and credited zero headings, and a prose mention of a section number counted as a coverage '
    .'claim — coverage is now read only from a column whose header IS the section column.'

    .'**Two privacy requirements the prompt names were unmet in code, and the sweep claim that found one of them '
    .'was itself wrong until the file was opened.** A student’s credential hash travelled in every Student 360 '
    .'payload because the student model declared no hidden fields where the staff model hides its own; and clinical '
    .'findings reached any desk that could open a student, with no field-level gate, because the controller eager '
    .'loaded the clinic relation for everyone — one of the four was disproved on the way, since a seeder line that '
    .'names a module is not a role grant. Both are closed: the hash is hidden, and the clinical load now happens '
    .'only for an account that holds the clinic read. The queue’s own duplicate-notice gap waits on a migration '
    .'nobody has been asked to spend, and its other half - notifications committing before the transaction that '
    .'queued them - is closed by having both listeners commit after.'

    .'**A dashboard badge and the list under it are now one predicate for eight of the nine cards.** Three drifts '
    .'were measured first: two cards counted the whole school while their pages are bounded by the desk’s college, '
    .'one counted only Pending while its desk has always also listed what the Registrar returned, and three counted '
    .'the wrong subject entirely - enrollments where the page lists fee sheets. The populations now come from one '
    .'class, and two desk lists were moved onto it, so those two agreements are one call rather than a duplicated '
    .'rule. The fix then produced its own mirror: a helper was added to scope two badges by college, and the pages '
    .'behind them apply no college boundary at all, so a bounded desk would have read a badge of one over a list of '
    .'two - the same contradiction wearing the repair’s clothes. The scope came out of the badge, and the question '
    .'whether those six desks SHOULD be college-bounded is recorded as dormant: on the live database the only '
    .'college-bound accounts are six department teachers holding evaluation and admission rights alone, so no '
    .'bounded account can open any of the six. The ninth card, Blocking, is a question rather than a filter and is '
    .'asked on the 2026-10-29 sheet.'

    .'**Three readings from building it, all of them this document’s own classes.** A test file that used the '
    .'attribute without its import contributed no cases at all while the suite printed its usual tally - the '
    .'discovery count and the run tally are now compared, and the report of the batch said "pinned by four cases" '
    .'when the number had been written from intent. A count-equality test written only for an account with no '
    .'college passed over a scoped-branch error, because the unbounded path returns the query untouched: pair them. '
    .'And the harness for the dashboard caught a gap in its own test rather than only proving the code - the leg '
    .'that plants the institution-wide admission count SURVIVED its first run, because the college case asserted '
    .'only the evaluation badge, so half the finding had no test until the leg refused to die. Four legs, all '
    .'killed, one of them only after its fixture was rebuilt.';

// Markdown asterisks are a drafting device in this file, not a run property: the document holds one run per cell,
// so the markers are stripped rather than written as literal characters a reader would see in Word. Stripping them
// also removes the only thing that separated the drafted paragraphs, so a sentence-final mark that ends up glued to
// the next capital gets its space back — and the count of sentences the record claims is checked, not assumed.
$record = str_replace('**', '', $record);
$record = preg_replace('/([.!?"])([A-Z])/', '$1 $2', $record);
if (preg_match('/[.!?"][A-Z]/', $record) === 1) {
    $where = preg_match('/[.!?"][A-Z]/', $record, $mm) ? $mm[0] : '?';
    fwrite(STDERR, "REFUSED: the record still welds a sentence to the next one ({$where}).\n");

    exit(1);
}
echo "record: ".strlen($record)." characters, no welded sentence boundary\n";

// ── the §1.1 row ──────────────────────────────────────────────────────────────────────────────
$anchor = '2026-10-29 One sort mechanism, one wired queue';
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
if ($rowsStartingWith($xml, $date) !== []) {
    fwrite(STDERR, "REFUSED: this pass's own §1.1 row is already in the document.\n");

    exit(1);
}

// A citation-shaped token inside a record row becomes a live pointer the sweep must then satisfy.
if (preg_match_all('/[A-Za-z]+[.]?(php|js|jsx)[:-]\d/', $record) > 0) {
    fwrite(STDERR, "REFUSED: the record text carries a citation-shaped token; spell the number in words.\n");

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

file_put_contents($root.'/storage/app/scratch/pass303-document.xml', $xml);

printf("%s: §1.1 gains the lanes 90-96 record (%d characters)\n", $apply ? 'APPLY' : 'DRY RUN', strlen($record));

$backup = $root.'/storage/app/scratch/pass303-backup-'.date('Ymd-His').'.docx';

if ($apply && ! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup to {$backup}.\n");

    exit(1);
}

// The engine writes the document from disk, so a pass that only rewrites document.xml in memory must hand the
// bytes over itself: this pass writes the archive member directly rather than through docxApply.
if ($apply) {
    $write = new ZipArchive;
    if ($write->open($docx) !== true) {
        fwrite(STDERR, "REFUSED: the written docx will not open for the row insert.\n");

        exit(1);
    }
    $write->addFromString('word/document.xml', $xml);
    $write->close();

    $back = new ZipArchive;
    if ($back->open($docx) !== true) {
        fwrite(STDERR, "REFUSED: the docx will not re-open after the write.\n");

        exit(1);
    }
    $round = (string) $back->getFromName('word/document.xml');
    $back->close();

    if ($round !== $xml) {
        fwrite(STDERR, "REFUSED: the bytes read back differ from the bytes written; backup is {$backup}.\n");

        exit(1);
    }

    echo "WRITTEN and verified byte-for-byte against the archive. Backup: {$backup}\n";

    exit(0);
}

echo "DRY RUN — nothing written. Re-run with --apply.\n";
