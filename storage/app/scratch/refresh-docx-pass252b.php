<?php

/**
 * Docx pass 252b — the ROWS the §7.2 examination confirmation adds.
 *
 * Companion to `refresh-docx-pass252.php`, which carries the sentences and the figures. This
 * half clones rows, because the engine refuses any pass that moves a `<w:tr>` (the recipe
 * `refresh-docx-pass62.php` established and pass 251b repeated): locate the unique row whose
 * FLATTENED PREFIX is the anchor, clone it, empty every run of every cell, and write the values
 * in by cell OFFSET — two cells of one row can hold the same text, and `str_replace()` would
 * write to both.
 *
 * Seven rows, because seven is what the document's own exhaustive claims require:
 *   §24's dictionary lists every column of a table, so three rows for `examresults`, in the
 *   order the server holds them (measured through `information_schema`: resultStatus,
 *   verifiedBy, verifiedDate — appended, not chained after one another, because MySQL resolves
 *   `AFTER` inside one multi-add ALTER against the column list as the statement began);
 *   §23.3's foreign-key inventory claims every key, so one row for `examresults.verifiedBy`;
 *   §23.4's key-purpose list claims every attribution column, so one row there;
 *   the §7 enum table says it is read from the database, so `resultStatus` gets a row;
 *   §1.1 records the pass itself.
 *
 * Each new §1.1 row moves what the registers count, so the record names no citation shape: a
 * paragraph that quotes `File.php:NN` about a pointer is read as a pointer by the sweeps
 * (pass 251c's lesson), so every line number here is spelled in words.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass252b.php [--apply]
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

// One-shot: the pass declines when its own §1.1 row is already in the document.
if (str_contains($xml, 'Examination results adopted by the Registrar (development prompt')) {
    fwrite(STDERR, "REFUSED: pass 252b's own revision row is already in the document.\n");
    exit(1);
}

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};

$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

/** @return int[] every <w:tr> whose flattened row text STARTS with $anchor. */
$rowsForAnchor = static function (string $xml, string $anchor) use ($flatten): array {
    $rowText = static function (string $row) use ($flatten): string {
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);

        return implode(' ', array_map($flatten, $cm[0]));
    };

    $hits = [];
    $at = 0;
    while (($start = strpos($xml, '<w:tr', $at)) !== false) {
        $end = strpos($xml, '</w:tr>', $start);
        if ($end === false) {
            break;
        }
        $end += 7;
        $row = substr($xml, $start, $end - $start);
        if (strncmp($rowText($row), $anchor, strlen($anchor)) === 0) {
            $hits[] = [$start, $end];
        }
        $at = $end;
    }

    return $hits;
};

/** Build one row from a template row, writing $values into its cells by offset. */
$buildRow = static function (string $row, array $values, string $anchor) use ($flatten, $escape): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
    if (count($cm[0]) !== count($values)) {
        fwrite(STDERR, "REFUSED: the row anchored on '{$anchor}' has ".count($cm[0])
            .' cells, this pass writes '.count($values).".\n");
        exit(1);
    }
    foreach ($cm[0] as $cell) {
        preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
        if (count($pm[0]) === 1) {
            continue;
        }
        foreach (array_slice($pm[0], 1) as $extra) {
            if ($flatten($extra) !== '') {
                fwrite(STDERR, "REFUSED: a cell of the row anchored on '{$anchor}' holds a SECOND paragraph with text — read it before cloning.\n");
                exit(1);
            }
        }
    }

    $clone = $row;
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm2, PREG_OFFSET_CAPTURE);
    $pairs = [];
    foreach ($cm2[0] as $j => $x) {
        $pairs[] = [$j, (int) $x[1], strlen($x[0]), $x[0]];
    }
    usort($pairs, static fn (array $a, array $b): int => $b[1] <=> $a[1]);
    foreach ($pairs as [$j, $at, $len, $src]) {
        $cell = $src;
        preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
        foreach (array_slice($pm[0], 1) as $extra) {
            $cell = str_replace($extra, '', $cell);
        }
        preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
        preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
        $body = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
        $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
            .$escape($values[$j]).'</w:t></w:r></w:p>', $body);
        if (substr_count($new, '<w:t xml:space="preserve">') !== 1) {
            fwrite(STDERR, "REFUSED: the replacement for cell {$j} of '{$anchor}' holds "
                .substr_count($new, '<w:t xml:space="preserve">')." text runs.\n");
            exit(1);
        }
        $clone = substr_replace($clone, $new, $at, $len);
    }

    if (substr_count($clone, '<w:tc>') !== count($cm[0])) {
        fwrite(STDERR, "REFUSED: the clone of '{$anchor}' lost or gained a cell.\n");
        exit(1);
    }

    return $clone;
};

$plan = [
    // §24's examresults column listing — reverse order, because each insert lands right after
    // its anchor and pushes the previous one down.
    ['examDate date no', ['verifiedDate', 'date', 'yes', '', '', '']],
    ['examDate date no', ['verifiedBy', 'int', 'yes', 'IDX', '', '-> staffusers.userId']],
    ['examDate date no', ['resultStatus', 'enum(provisional|official)', 'no', '', 'provisional', '']],

    // §23.3's foreign-key inventory claims every key on the table.
    ['examresults.termId academicterms.termId', ['examresults.verifiedBy', 'staffusers.userId', 'SET NULL', 'CASCADE']],

    // §23.4's key-purpose list claims every attribution column.
    ['creditedsubjects.decidedBy staffusers Which department account', ['examresults.verifiedBy', 'staffusers', 'Which Registrar account confirmed the result official — a row the migration adopted names nobody, and that is how the backfill stays visible']],

    // §7's enum table is read from the database, so the two values appear beside the class.
    ['creditedsubjects.creditDecision App\Enums\CreditDecision', ['examresults.resultStatus', 'App\Enums\ExamStatus', 'provisional, official', 'Only an official row is the answer an eligibility gate counts']],

    // §1.1 — the record of the pass. No citation shape in this text: see the header.
    ['2026-10-26 Per-row crediting decided (development prompt', ['2026-10-26', 'Examination results adopted by the Registrar (development prompt §7.2, gap G-4). examresults grew resultStatus (provisional or official, defaulting to provisional because that is what a desk entry IS when it is written), verifiedBy and verifiedDate; the five rows on file were backfilled to official so an applicant already approved on a recorded pass does not wake up unexamined, and their verifier stays blank, which is how a backfill is told apart from a signature. Ruled the same day: the recording office enters, the Registrar adopts, and every gate asks the adopted value — so a provisional pass no longer covers the Stage 2 prerequisite, no longer satisfies an admission approval at either stage, and no longer lets a returning student’s form be forwarded. One method answers “does this result count”, read by all four places; the Reference Data delete guard deliberately keeps counting every row, adopted or not, because a refusal still names the paper it refused. Re-recording a result sends it back to provisional and drops the signature that covered the earlier figure. Three sentences in this document that promised an unconditional exemption were re-worded, and the stage-1 prerequisite now refuses an unconfirmed row with its own words rather than calling it absent. Pinned by tests/Feature/Exam/ExamResultVerificationTest.php and proved by storage/app/scratch/mutate-g4-exam-verification-tests.php (17 planted defects over seven files, six whole-file controls green before and after). Measured before it was written: live ems held 5 examination rows, 462 columns, 102 foreign keys, 131 indexes, 94 permissions.']],
];

$report = [];
$failures = [];
foreach ($plan as [$anchor, $values]) {
    if (count($values) < 2) {
        $failures[] = "the row for '{$anchor}' carries no values";

        continue;
    }
    $rows = $rowsForAnchor($xml, $anchor);
    if (count($rows) !== 1) {
        $failures[] = "anchor '{$anchor}' matched ".count($rows).' row(s), expected exactly 1';

        continue;
    }
    [$start, $end] = $rows[0];
    $row = substr($xml, $start, $end - $start);
    $clone = $buildRow($row, $values, $anchor);
    $xml = substr_replace($xml, $clone, $end, 0);
    $report[] = sprintf('ok   %-44s -> %d cell(s)', "'{$anchor}'", count($values));
}

foreach ($report as $line) {
    echo $line."\n";
}
if ($failures !== []) {
    fwrite(STDERR, "\n".count($failures)." anchor(s) unresolved; nothing written:\n");
    foreach ($failures as $f) {
        fwrite(STDERR, '  '.$f."\n");
    }
    exit(1);
}

printf("%s%d row(s) inserted\n", $apply ? '' : 'DRY RUN — ', count($plan));

if (! $apply) {
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass252b-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup.\n");
    exit(1);
}

$zip = new ZipArchive;
if (! $zip->open($docx, ZipArchive::CREATE)) {
    fwrite(STDERR, "REFUSED: cannot reopen for writing.\n");
    exit(1);
}
$zip->addFromString('word/document.xml', $xml);
$zip->close();

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();
if ($read !== $xml) {
    fwrite(STDERR, "REFUSED: the archive did not read back the bytes it was handed.\n");
    exit(1);
}

echo 'backup: '.basename($backup)."\nwritten.\n";
