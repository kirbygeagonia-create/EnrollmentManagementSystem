<?php

/**
 * Pass 382 — the two unique keys `2026_11_01_1100000000_add_database_level_enrollment_rules.php` put on live `ems`,
 * and every figure in the authority document that counts them.
 *
 * §23.6.1 declares itself complete, so a key the server holds and the table omits is a rule a future reader will
 * assume the database does not enforce — the exact defect `lane44-unique-keys.php` exists to refuse, and it refused
 * three things the moment the migration ran: the two rows and the sentence beside them.
 *
 * Four figures move with two keys and one column, and each is re-taken from the server rather than computed here:
 *   - §23.6.1's own tally: ten keys in eight tables -> twelve keys in ten tables
 *   - §23.6.1 gains two rows (cloned from the scholarship key's row, so the table keeps its cell geometry)
 *   - §24's `enrollments — 23 columns` -> 24, because `seatKey` is a real column to information_schema
 *   - the census sentence: 479 columns / 136 indexes / 182 constraints -> 480 / 138 / 184 (64 tables and 105
 *     foreign keys are unchanged, and lane 60 says so)
 *
 * Rows first, written straight to the archive; the text edits go through `docxApply()` afterwards, because the
 * engine re-reads `document.xml` from disk and would discard an in-memory row it never wrote.
 *
 * Dry run by default. --apply writes, after a timestamped backup.
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $GLOBALS['argv'], true);

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: the docx would not open.\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$flatten = static function (string $chunk): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $chunk, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)) ?? '');
};
$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

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

$buildRow = static function (string $row, array $values, string $anchor) use ($flatten, $escape): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
    if (count($cm[0]) !== count($values)) {
        fwrite(STDERR, 'REFUSED: the row anchored on \''.$anchor.'\' has '.count($cm[0])
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
                fwrite(STDERR, "REFUSED: a cell of the row anchored on '{$anchor}' holds a SECOND paragraph with "
                    ."text — read it before cloning.\n");
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

    // Containment is not enough: the flattened clone must equal exactly what the pass supplied, or the template's
    // tail runs travelled into the new row (pass 173's §1.1 defect).
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $clone, $check);
    $written = array_map($flatten, $check[0]);
    if ($written !== array_map(static fn (string $v): string => trim(preg_replace('/\s+/', ' ', $v) ?? ''), $values)) {
        fwrite(STDERR, "REFUSED: the clone of '{$anchor}' reads back as `"
            .implode(' | ', $written)."' rather than what the pass supplied.\n");
        exit(1);
    }

    return $clone;
};

// One-shot guards: neither new key may already be in the document.
foreach (['uq_studentassessments_enrollment', 'uq_enrollments_active_seat'] as $key) {
    if (substr_count($xml, $key) > 0) {
        fwrite(STDERR, "REFUSED: `{$key}` already appears ".substr_count($xml, $key)
            ." time(s) in the document — this pass would duplicate its own row.\n");
        exit(1);
    }
}

// Built from a list of sentences joined with one space: a multi-line concatenation in this file has now dropped
// its `.` twice in one sitting, and a pass that cannot parse is a pass that writes nothing — which is the only
// good outcome available.
$seatNote = implode(' ', [
    '`seatKey` is a VIRTUAL generated column that is non-null exactly when',
    "`enrollmentStatus <> 'dropped'`, and MySQL ignores NULLs in a unique index — so a drop releases the seat and"
    ." the student the Registrar dropped may enroll again in the same term, which is ruling 17's behaviour"
    .' enforced without a trigger.',
    'Added 2026-11-01 for the Advanced Database Systems guide’s problem 3; before it the rule was'
    .' `EnrollmentIssuer::seatHolder()` alone, which a second route or a concurrent request could bypass.',
]);

$sheetNote = implode(' ', [
    'Added 2026-11-01, closing DI-2 of Documentation/System-Design-Quality-Review.md, which had stood open since'
    .' 2026-09-15: nothing but application code refused a second sheet, so a restored dump or a regressed guard'
    .' could leave one account with two totals and the screens that sum them had no way to know which was the sheet.',
]);

// The template row holds FOUR cells — key, columns, purpose, and a scope note the flat dump cannot show because the
// dumper drops empty cells and joins what remains. A pass that supplied three would have written a short row that
// every reader of §23.6.1 would silently mis-parse, which is the row-geometry defect pass 251b recorded for §1.1.
// Every row is inserted immediately after its anchor, so the plan is in REVERSE for the order wanted on the page.
$anchor = 'uq_student_scholarship_term studentId, scholarshipTypeId, termId';
$plan = [
    [$anchor, ['uq_enrollments_active_seat', 'studentId, termId, seatKey',
        'One ACTIVE enrollment per student per term.', $seatNote]],
    [$anchor, ['uq_studentassessments_enrollment', 'enrollmentId',
        'One fee sheet per enrollment.', $sheetNote]],
];

$failures = [];
$report = [];
foreach ($plan as [$anc, $values]) {
    $rows = $rowsForAnchor($xml, $anc);
    if (count($rows) !== 1) {
        $failures[] = "anchor '{$anc}' matched ".count($rows).' row(s), expected exactly 1';

        continue;
    }
    [$start, $end] = $rows[0];
    $row = substr($xml, $start, $end - $start);
    $clone = $buildRow($row, $values, $anc);
    $xml = substr_replace($xml, $clone, $end, 0);
    $report[] = sprintf('ok   %-34s -> %d cell(s)', basename($anc), count($values));
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

if (! $apply) {
    echo "DRY RUN — 2 row(s) would be inserted and 3 sentences rewritten.\n";

    return;
}

$backup = $root.'/storage/app/scratch/pass382-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup.\n");
    exit(1);
}
$w = new ZipArchive;
if ($w->open($docx, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "REFUSED: the docx would not reopen for writing.\n");
    exit(1);
}
$w->addFromString('word/document.xml', $xml);
$w->close();
$again = new ZipArchive;
$again->open($docx);
if ((string) $again->getFromName('word/document.xml') !== $xml) {
    $again->close();
    fwrite(STDERR, "REFUSED: the archive did not read back the bytes it was handed.\n");
    exit(1);
}
$again->close();
echo "rows written; backup ".basename($backup)."\n";

require $root.'/storage/app/scratch/docx-edit-engine.php';

$countsFrom = 'Counts: 64 tables (56 EMS domain + 8 framework), 479 columns, 105 foreign-key constraints, '
    .'136 indexes, and 182 table constraints in all.';
$countsTo = 'Counts: 64 tables (56 EMS domain + 8 framework), 480 columns, 105 foreign-key constraints, '
    .'138 indexes, and 184 table constraints in all.';

foreach ([$countsFrom, 'Ten keys in eight tables. ', 'enrollments — 23 columns'] as $needle) {
    $c = substr_count($xml, $needle);
    if ($c !== 1) {
        fwrite(STDERR, 'REFUSED: the text needle `'.substr($needle, 0, 46).'…` occurs '.$c." time(s) in the file the "
            ."rows were just written to; the pass stops before touching it.\n");
        exit(1);
    }
}

docxApply($docx, $root.'/storage/app/scratch/pass382-sentences-'.date('Ymd-His').'.docx', [
    [$countsFrom, $countsTo, null, 1],
    ['Ten keys in eight tables. ', 'Twelve keys in ten tables. ', null, 1],
    ['enrollments — 23 columns', 'enrollments — 24 columns', null, 1],
], [], true);

// Read back the four claims the pass makes, from the archive rather than the zip bytes.
$r = new ZipArchive;
if ($r->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: could not re-open for the read-back.\n");
    exit(1);
}
$body = (string) $r->getFromName('word/document.xml');
$r->close();
$text = html_entity_decode(preg_replace('/<[^>]+>/', '', $body) ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
foreach ([$countsTo => 1, 'Twelve keys in ten tables. ' => 1, 'enrollments — 24 columns' => 1,
    $countsFrom => 0, 'Ten keys in eight tables. ' => 0, 'enrollments — 23 columns' => 0,
    'uq_enrollments_active_seat' => 1, 'uq_studentassessments_enrollment' => 1] as $needle => $want) {
    $got = substr_count($text, $needle);
    if ($got !== $want) {
        fwrite(STDERR, 'POST-READ FAILED: `'.substr($needle, 0, 44).'…` occurs '.$got.', expected '.$want.".\n");
        exit(1);
    }
}
echo "all eight read-back assertions hold.\n";
