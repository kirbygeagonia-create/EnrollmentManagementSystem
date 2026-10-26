<?php

/**
 * Docx pass 251b — the ROWS the G-3 crediting decision adds.
 *
 * The engine refuses any pass that moves a `<w:tr>`, so a new register row goes through a clone
 * (the recipe `refresh-docx-pass62.php` established): locate the row whose FIRST cell is exactly
 * the anchor text, require that row to be unique in the document, clone it, empty every run of
 * every cell — a cloned multi-run cell otherwise carries the template's tail into the new row —
 * and write the supplied values into the first run's formatting.
 *
 * Twelve rows, because twelve is what the document's own exhaustive claims require:
 *   §24's dictionary lists every column of a table, so four rows for `creditedsubjects` and three
 *   for `transferacademicrecords`, each in the order the SERVER holds them (measured, not the
 *   order of the migration's declarations — MySQL placed the three evidence columns in the
 *   reverse of the declaration order, and §24 is written from `information_schema`);
 *   §23.3's foreign-key inventory claims every key, so one row for `creditedsubjects.decidedBy`;
 *   §23.4.2's list claims every SET NULL column, so one row there too;
 *   the §7 enum table says it is read from the database, so `creditDecision` gets a row;
 *   §13.1 says the department "works an enrollment through four actions, then signs", and a fifth
 *   act now exists; §1.1 records the pass itself.
 *
 * Each row is located, cloned and inserted on the in-memory bytes in plan order — seven anchors
 * are rows this same pass creates — and the file is written only when all twelve resolved, so a
 * half-inserted register cannot happen. The pass is one-shot: it declines once its own §1.1 row is
 * present. Usage: php storage/app/scratch/refresh-docx-pass251b.php [--apply]
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

$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

/**
 * @return int[] every <w:tr> whose flattened text STARTS with $anchor (whitespace collapsed)
 *
 * The anchor is a row prefix, not a cell value: `remarks` alone is a column of four different
 * tables, and `creditedsubjects.transferRecordId` opens a row in both the foreign-key inventory
 * and the key-purpose table. A row that names its second and third cells is unique.
 */
$rowsForAnchor = static function (string $xml, string $anchor) use ($flatten): array {
    // Cells are joined WITH a separator. A row's runs concatenate directly (`creditedUnits`
    // `decimal(3,1)` `no` become one word), so an anchor spelled like the table's text matches
    // nothing until the cells are read apart.
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

/** Build one row from a template row, writing $values into its cells. */
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
        // A §1.1 cell can carry a TRAILING EMPTY paragraph (pass 250's clone left one). It is
        // dropped below, but only when it holds no text: a cell whose second paragraph says
        // something is a cell this pass does not understand, and that must be read, not cloned.
        foreach (array_slice($pm[0], 1) as $extra) {
            if ($flatten($extra) !== '') {
                fwrite(STDERR, "REFUSED: a cell of the row anchored on '{$anchor}' holds a SECOND paragraph with text — read it before cloning.\n");
                exit(1);
            }
        }
    }

    // Cells are replaced BY OFFSET. Two cells of one row can hold the same text — the
    // foreign-key rows write `NO ACTION` twice — and `str_replace()` replaces every identical
    // copy, so the second value would land in the first cell's twin and the row would come out
    // with one cell short of what the pass intended.
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

// Every row is inserted immediately AFTER its anchor, so a table that needs several new rows in
// a known order is planned in REVERSE: each insert pushes the previous one down. The anchors are
// row prefixes unique in the document, measured before this pass ran.
$plan = [
    ['creditedUnits decimal(3,1) no', ['decisionReason', 'varchar(500)', 'yes', '', '', '']],
    ['creditedUnits decimal(3,1) no', ['decidedDate', 'date', 'yes', '', '', '']],
    ['creditedUnits decimal(3,1) no', ['decidedBy', 'int', 'yes', 'IDX', '', '-> staffusers.userId']],
    ['creditedUnits decimal(3,1) no', ['creditDecision', 'enum(approved|rejected)', 'no', '', 'approved', '']],

    ['subjectNameAtOldSchool varchar(150) no', ['previousSubjectCode', 'varchar(50)', 'yes', '', '', '']],
    ['subjectNameAtOldSchool varchar(150) no', ['termTaken', 'varchar(100)', 'yes', '', '', '']],
    ['subjectNameAtOldSchool varchar(150) no', ['syllabusReference', 'varchar(255)', 'yes', '', '', '']],

    ['creditedsubjects.transferRecordId transferacademicrecords.transferRecordId', ['creditedsubjects.decidedBy', 'staffusers.userId', 'SET NULL', 'CASCADE']],
    ['clinicrecords.clinicStaffId staffusers Which clinic staff member', ['creditedsubjects.decidedBy', 'staffusers', 'Which department account approved or declined the equivalency — a row backfilled before the decision column exists names nobody (G-3, §7.3)']],
    ['enrolledsubjects.status App\Enums\EnrolledSubjectStatus', ['creditedsubjects.creditDecision', 'App\Enums\CreditDecision', 'approved, rejected', '§7.3 (G-3): only an approved row covers a subject']],

    ['Process credits', ['Decide a credit', 'The department approves or declines each equivalency row by row; a decline must say why, and only an approved row covers a subject. An account without the crediting right is refused the control and the route alike.', 'creditedsubjects.creditDecision, decidedBy, decidedDate, decisionReason ( EvaluationController.php:852-877 ).']],

    ['2026-10-26 Draft intake, built', ['2026-10-26', 'Per-row crediting decided (development prompt §7.3, gap G-3). creditedsubjects grew creditDecision (approved | rejected), decidedBy, decidedDate and decisionReason, and transferacademicrecords grew previousSubjectCode, termTaken and syllabusReference — all nullable, because a school that has not produced the paper is not made to invent one. Ruled the same day: the department that already holds evaluation.credits.process decides, so no new permission and no second signature. Only an APPROVED row covers a subject — one `approved()` scope, read by the prerequisite gate, the mandatory-block diff and the Registrar’s readiness panel alike — while the Reference Data delete guard deliberately keeps counting declined rows. Pinned by tests/Feature/Evaluation/CreditEquivalencyDecisionTest.php (14 cases) and proved by storage/app/scratch/mutate-g3-crediting-decision-tests.php (16 legs — including the schema default that lets a row filed before the column keep counting, and the delete guard told to count approved rows only, which is the one place the decision must NOT narrow the census). Measured before it was written: live ems held 0 rows in either table, 459 columns, 101 foreign keys, 130 indexes. Two citations in this document were found false by meaning while its ranges were being re-measured: EvaluationController.php:58,190 named a blank line and a ternary arm, and the bare :959-1000 offered confirmClearance() while those lines sit inside store() and approvedPassSlip(); both were re-taken against the live file.']],
];

// One-shot: the pass declines when its own §1.1 row is already in the document.
if (str_contains($xml, 'Per-row crediting decided (development prompt')) {
    fwrite(STDERR, "REFUSED: pass 251b's own revision row is already in the document.\n");
    exit(1);
}

// Rows are validated and inserted in PLAN ORDER on the in-memory bytes, because seven of the
// twelve anchors are rows this same pass creates (`decidedBy` is only findable after
// `creditDecision` exists). A separate pre-flight against the ORIGINAL document would refuse its
// own chain. Nothing reaches the file unless every row resolved: the loop above the write is the
// only place a failure can still be free.
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
    $report[] = sprintf('ok   %-34s -> %d cell(s)', "'{$anchor}'", count($values));
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

$backup = $root.'/storage/app/scratch/pass251b-backup-'.date('Ymd-His').'.docx';
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
