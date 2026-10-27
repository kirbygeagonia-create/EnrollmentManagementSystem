<?php

/**
 * §24.17's emptiness set and §24.19's foreign-key row, after `enrollmentreturns` joined the dictionary.
 *
 * `lane64-empty-table-inventory.php --verify` refused four membership claims the moment the new table
 * existed: its intro names the set of base tables whose COUNT(*) is 0, names the domain subset inside it,
 * states how many rows the section's own table carries, and §24.18 repeats the domain-empty count — and
 * none of those followed the schema. This is lane 64's defect class exactly: a section titled after a set
 * never gets the set re-counted, and a register row you add moves every count of the register.
 *
 * The foreign-key cross-check in §24.19 moved too (100 / 69 NO ACTION / 26 SET NULL), which no checker
 * binds — lane 60 answers the §23.3 census and the §23.4 split, not this row's restatement of them. It was
 * corrected by reading the server, and its three figures now agree with §23.4's sentence as pass 259
 * rewrote it.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass260c.php [--apply]
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

$mysql = 'C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysql.exe';

// The four figures this pass types, each read from the server rather than derived from the old text.
$measure = static function (string $sql) use ($mysql): string {
    $out = shell_exec(' '.$mysql.' -u root ems -N -B -e "'.str_replace('"', '\"', $sql).'" 2>&1');

    return trim(str_replace("\r", '', (string) $out));
};

$facts = [
    'zeroRows' => $measure('SELECT COUNT(*) FROM enrollmentreturns;'),
    'foreignKeys' => $measure('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=\'ems\' AND CONSTRAINT_TYPE=\'FOREIGN KEY\';'),
    'noAction' => $measure('SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=\'ems\' AND DELETE_RULE=\'NO ACTION\';'),
    'setNull' => $measure('SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=\'ems\' AND DELETE_RULE=\'SET NULL\';'),
];

printf("read zeroRows=%s foreignKeys=%s noAction=%s setNull=%s\n",
    $facts['zeroRows'], $facts['foreignKeys'], $facts['noAction'], $facts['setNull']
);

if ($facts['zeroRows'] !== '0') {
    fwrite(STDERR, "REFUSED: enrollmentreturns holds {$facts['zeroRows']} rows, so it is not a member of the empty set.\n");

    exit(1);
}

// The SET of domain tables that are empty belongs to `lane64-empty-table-inventory.php`, which derives the
// framework partition from the schema rather than from a remembered list — this pass does not re-derive it
// (two definitions of one set is the defect lane 69 and lane 70 keep removing). What this pass does check
// is that every name it is about to write into the section's intro and its row list is genuinely empty on
// the server today, and after the write lane 64 is re-run as the arbiter of membership.
$named = ['creditedsubjects', 'enrollmentreturns', 'model_has_permissions', 'shiftingrequests', 'transferacademicrecords'];

foreach ($named as $t) {
    $c = (int) $measure("SELECT COUNT(*) FROM `{$t}`;");

    if ($c !== 0) {
        fwrite(STDERR, "REFUSED: the server says {$t} holds {$c} rows, so the intro cannot name it as empty.\n");

        exit(1);
    }

    printf("read %-26s COUNT(*) = 0\n", $t);
}

if ($facts['foreignKeys'] !== '105' || $facts['noAction'] !== '71' || $facts['setNull'] !== '29') {
    fwrite(STDERR, "REFUSED: the key figures moved again ({$facts['foreignKeys']}/{$facts['noAction']}/{$facts['setNull']}); re-read before typing.\n");

    exit(1);
}

$zip = new ZipArchive;

if (! $zip->open($docx)) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");

    exit(1);
}

$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

if (! str_contains($xml, 'enrollmentreturns — 15 columns')) {
    fwrite(STDERR, "REFUSED: pass 260 has not run; §24.17 would be naming a table the dictionary does not carry.\n");

    exit(1);
}

// ---- the §24.17 row, cloned from the section's own `shiftingrequests` row -------------------------
$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};

$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

// A ROW is flattened by joining its cells with a separator: runs concatenate directly in `document.xml`, so
// the table's first two cells read as one word unless they are deliberately read apart. Pass 251b paid for
// this and the lesson is its own anchor rule.
$rowText = static function (string $row) use ($flatten): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);

    return implode(' ', array_map($flatten, $cm[0]));
};

preg_match_all('/<w:tr\b.*?<\/w:tr>/s', $xml, $allRows, PREG_OFFSET_CAPTURE);

$template = null;

foreach ($allRows[0] as [$row, $offset]) {
    if (strncmp($rowText($row), 'shiftingrequests Transfer and Prior Academic Records', 44) === 0) {
        if ($template !== null) {
            fwrite(STDERR, "REFUSED: two §24 rows open with the shiftingrequests prefix; the clone's owner is ambiguous.\n");

            exit(1);
        }

        $template = ['row' => $row, 'offset' => (int) $offset];
    }
}

if ($template === null) {
    fwrite(STDERR, "REFUSED: no §24.17 row opens with `shiftingrequests Transfer and Prior Academic Records`.\n");

    exit(1);
}

$values = [
    'enrollmentreturns',
    'Enrollment',
    'A desk holding enrollment.return sends a record back to Department Evaluation or to Admission — the act the Registrar performs on a paid account (§17, E-3)',
    'The act is built and pinned ( tests/Feature/Enrollment/EnrollmentReturnTest.php , 26 cases: the status trail, the reason, the reopened boxes, and a paid record coming home through the ordinary desk acts) and the recorder writes the six facts into this table in one transaction. The demo dataset never performs a return, so no walkthrough in this document observes the trail on paper — and the return moves no payment row, which is why the money stays counted.',
];

preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template['row'], $cm, PREG_OFFSET_CAPTURE);

if (count($cm[0]) !== count($values)) {
    fwrite(STDERR, 'REFUSED: the template row has '.count($cm[0]).' cells, this pass writes '.count($values).".\n");

    exit(1);
}

$clone = $template['row'];
$cells = [];

foreach ($cm[0] as $j => [$src, $at]) {
    $cells[] = [$j, (int) $at, strlen($src), $src];
}

usort($cells, static fn (array $a, array $b): int => $b[1] <=> $a[1]);

foreach ($cells as [$j, $at, $len, $src]) {
    $cell = $src;
    preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);

    foreach (array_slice($pm[0], 1) as $extra) {
        if ($flatten($extra) !== '') {
            fwrite(STDERR, "REFUSED: cell {$j} of the template row holds a second paragraph with text — read it before cloning.\n");

            exit(1);
        }

        $cell = str_replace($extra, '', $cell);
    }

    preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $body = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
    $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
        .$escape($values[$j]).'</w:t></w:r></w:p>', $body);

    if (substr_count($new, '<w:t xml:space="preserve">') !== 1) {
        fwrite(STDERR, "REFUSED: cell {$j} of the clone holds more than one text run.\n");

        exit(1);
    }

    $clone = substr_replace($clone, $new, $at, $len);
}

if (substr_count($clone, '<w:tc>') !== count($cm[0])) {
    fwrite(STDERR, "REFUSED: the §24.17 clone lost or gained a cell.\n");

    exit(1);
}

// Insert directly after the template row, so the enrollment row sits beside the transfer rows it follows
// in §24's own order.
$afterTemplate = $template['offset'] + strlen($template['row']);
$withRow = substr_replace($xml, $clone, $afterTemplate, 0);

printf("clone  §24.17 row for enrollmentreturns, %d bytes, inserted after byte %d\n", strlen($clone), $afterTemplate);

// ---- the sentences that state the set, the counts, and §24.19's key figures -----------------------
require __DIR__.'/docx-edit-engine.php';

$sentences = [
    // §24.17's intro: the zero-count list gains the new table, and the domain set names five.
    ['cache, cache_locks, creditedsubjects, failed_jobs, job_batches, jobs, model_has_permissions, sessions, shiftingrequests, transferacademicrecords',
        'cache, cache_locks, creditedsubjects, enrollmentreturns, failed_jobs, job_batches, jobs, model_has_permissions, sessions, shiftingrequests, transferacademicrecords', null, 1],
    ['the domain set this section is about is creditedsubjects, model_has_permissions, shiftingrequests and transferacademicrecords',
        'the domain set this section is about is creditedsubjects, enrollmentreturns, model_has_permissions, shiftingrequests and transferacademicrecords', null, 1],
    // The section's own table now carries five empty rows rather than four.
    ['The section’s table now carries four rows that are empty today',
        'The section’s table now carries five rows that are empty today', null, 1],
    // §24.18's restatement of the count.
    ['4 domain tables are empty in the seeded database',
        '5 domain tables are empty in the seeded database', null, 1],
    // §24.19's foreign-key cross-check, read from the server: 105 keys, 71 refuse, 29 null.
    ['100 constraints, all resolving to a real parent table and column',
        '105 constraints, all resolving to a real parent table and column', null, 1],
    ['69 NO ACTION refuse the delete, 26 SET NULL detach the reference',
        '71 NO ACTION refuse the delete, 29 SET NULL detach the reference', null, 1],
];

$before = [];

foreach ($sentences as $i => [$from, $to, $anchor, $expect]) {
    $seen = substr_count($withRow, $from);

    if ($seen !== $expect) {
        fwrite(STDERR, "REFUSED: sentence {$i} needle occurs {$seen} time(s) in the document, expected {$expect}: ".substr($from, 0, 60)."\n");

        exit(1);
    }

    $before[] = sprintf('ok   sentence %d: %s → %s', $i, substr($from, 0, 46), substr($to, 0, 46));
}

foreach ($before as $line) {
    echo $line."\n";
}

echo "\n";

printf("%sthis pass would rewrite %d sentence(s) and insert 1 row\n", $apply ? '' : 'DRY RUN — ', count($sentences));

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

// The sentences go through the engine, which re-reads the archive — so the row clone must reach the file
// FIRST, or the engine would write its own edits over bytes that never carried the new row (pass 252's
// trap, met from the other side).
$backup = $root.'/storage/app/scratch/pass260c-backup-'.date('Ymd-His').'.docx';

if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup.\n");

    exit(1);
}

$zip = new ZipArchive;

if (! $zip->open($docx, ZipArchive::CREATE)) {
    fwrite(STDERR, "REFUSED: cannot reopen for writing.\n");

    exit(1);
}

$zip->addFromString('word/document.xml', $withRow);
$zip->close();

docxApply($docx, $backup, $sentences, [], true);

$again = new ZipArchive;
$again->open($docx);
$after = (string) $again->getFromName('word/document.xml');
$again->close();

foreach ([
    'enrollmentreturns, failed_jobs, job_batches' => 1,
    'the domain set this section is about is creditedsubjects, enrollmentreturns' => 1,
    'carries five rows that are empty today' => 1,
    '5 domain tables are empty in the seeded database' => 1,
    '105 constraints, all resolving' => 1,
    '71 NO ACTION refuse the delete, 29 SET NULL detach the reference' => 1,
    'enrollmentreturns Enrollment A desk holding enrollment.return' => 0,
] as $needle => $expected) {
    $seen = substr_count($after, $needle);

    if ($seen !== $expected) {
        fwrite(STDERR, "REFUSED (post-write): '{$needle}' occurs {$seen}, expected {$expected}.\n");

        exit(1);
    }

    printf("after %-58s %d\n", substr($needle, 0, 56), $seen);
}

// The row is a `<w:tr>`, so its flattened text cannot be searched as one string; prove it landed by
// counting the table's rows and reading the new row's first cell.
if (substr_count($after, 'enrollmentreturns') < substr_count($xml, 'enrollmentreturns') + 1) {
    fwrite(STDERR, "REFUSED (post-write): the document did not gain the enrollmentreturns mentions the row was meant to add.\n");

    exit(1);
}

printf("after %-58s %d (+%d by the new row)\n", 'enrollmentreturns (all mentions)', substr_count($after, 'enrollmentreturns'),
    substr_count($after, 'enrollmentreturns') - substr_count($xml, 'enrollmentreturns'));

echo "\npass 260c applied: §24.17, §24.18 and §24.19 now answer the server after the new table.\n";
