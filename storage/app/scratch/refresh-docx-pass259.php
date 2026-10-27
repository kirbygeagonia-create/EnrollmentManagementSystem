<?php

/**
 * The schema and permission figures this batch moved: 63 tables to 64, 102 foreign keys to 105, 94
 * permissions to 95.
 *
 * G-10 added `enrollmentreturns` (15 columns, 3 foreign keys, 1 of them SET NULL) and the
 * `enrollment.return` permission. `lane60-ratio-claims.php --run` read live `ems` on 2026-10-27 and named
 * 24 stale sentences across the docx, AGENTS.md and Build_Plan.md; this pass writes the document half from
 * that reading. Every figure it types is taken from `lane60-readings.json`, and the pass refuses to write
 * if a needle is not the number of copies the reading implies — a half-applied census is the worst shape,
 * because §23 and §24 then disagree with each other rather than with the server.
 *
 * Deliberately NOT in this pass: the §24 dictionary row for `enrollmentreturns` itself, the two §24
 * aggregate cells ("420 columns documented for 55 domain tables", "54 Eloquent models for 55 domain
 * tables"), the §24.17/§24.18 emptiness sets and the §1.1 record row. Those claims become true only when
 * the row exists, so they travel with the row clone in pass 260 and `lane60` still names them until then.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass259.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass259-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$readingsPath = $root.'/storage/app/scratch/lane60-readings.json';

if (! is_file($readingsPath)) {
    fwrite(STDERR, "REFUSED: {$readingsPath} is missing. Run lane60-ratio-claims.php --run first; this pass types no figure it has not read off the server.\n");

    exit(1);
}

$readings = json_decode(file_get_contents($readingsPath), true);

if (! is_array($readings) || ! isset($readings['columns'])) {
    fwrite(STDERR, "REFUSED: the readings file does not decode to the shape this pass reads.\n");

    exit(1);
}

// Every number written below, bound to the reading that answers it. A literal that stops matching the
// server is a refusal, not a typo.
$required = [
    'tables' => 64,
    'domainTables' => 56,
    'frameworkTables' => 8,
    'columns' => 477,
    'foreignKeys' => 105,
    'indexes' => 135,
    'constraints' => 181,
    'tablesByOwnMigrations' => 51,
    'eloquentModels' => 55,
];

foreach ($required as $key => $expected) {
    $seen = $readings[$key] ?? null;

    if ($seen !== $expected) {
        fwrite(STDERR, "REFUSED: the reading for {$key} answers ".var_export($seen, true).", not {$expected}. Re-run lane60 before typing figures.\n");

        exit(1);
    }

    printf("read %-26s %d\n", $key, $seen);
}

foreach ([['deleteRules', 'cascade', 4], ['deleteRules', 'setNull', 29], ['deleteRules', 'noAction', 71], ['deleteRules', 'restrict', 1]] as [$group, $rule, $expected]) {
    $seen = $readings[$group][$rule] ?? null;

    if ($seen !== $expected) {
        fwrite(STDERR, "REFUSED: the reading for deleteRules.{$rule} answers ".var_export($seen, true).", not {$expected}.\n");

        exit(1);
    }

    printf("read %-26s %d\n", "fk-{$rule}", $seen);
}

$perms = (int) trim(shell_exec('C:\\\\laragon\\\\bin\\\\mysql\\\\mysql-8.4.3-winx64\\\\bin\\\\mysql.exe -u root ems -N -B -e "SELECT COUNT(*) FROM permissions;" 2>&1') ?? '');

if ($perms !== 95) {
    fwrite(STDERR, "REFUSED: live ems answers {$perms} permission rows, not the 95 this pass is about to type.\n");

    exit(1);
}

printf("read %-26s %d\n", 'permissions(live sql)', $perms);

$zip = new ZipArchive;

if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the document for needle probing.\n");

    exit(1);
}

$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

// Two of these rows share a label with a row in another table ('Tables in the live database' heads a
// §24.19 row as well as the §30.1 row, and `enrollment.approve, enrollment.drop` opens two permission
// lists). The engine's cell kind rewrites EVERY row holding the anchor, so the anchor for those two is
// taken from the row's own bytes: the one row that also carries the digit cell being moved.
function rowAnchors(string $xml, string $label, string $digit): array
{
    $hits = [];
    $scan = 0;

    while (($p = strpos($xml, $label, $scan)) !== false) {
        $start = strrpos(substr($xml, 0, $p), '<w:tr');
        $end = strpos($xml, '</w:tr>', $p) + 7;
        $scan = $p + 1;

        if ($start === false || strpos(substr($xml, $start, $p - $start), '</w:tr>') !== false) {
            continue;
        }

        $row = substr($xml, $start, $end - $start);
        $digitPos = strpos($row, '>'.$digit.'<');

        if ($digitPos === false) {
            continue;
        }

        // The anchor runs only as far as the digit cell's own close. Cutting it short matters: this pass
        // also rewrites a sentence in that same row's third cell, and an anchor spanning it would be
        // stale by the time the cell kind came to resolve.
        $hits[] = substr($row, $p - $start, $digitPos + strlen('>'.$digit.'<'));
    }

    if (count($hits) < 1) {
        fwrite(STDERR, "REFUSED: no row labelled \"{$label}\" carries a cell reading {$digit}.\n");

        exit(1);
    }

    printf("anchor %-34s %d row(s) carry the %s cell, %d bytes each\n", substr($label, 0, 32), count($hits), $digit, strlen($hits[0]));

    return $hits;
}

// The §22.2 Total cell holds only the digits, so its anchor is the row that carries both `Total` and
// `94` — not a bare run needle, which several Total rows share.
$totalAnchors = rowAnchors($xml, 'Total</w:t>', '94');

if (count($totalAnchors) !== 1) {
    fwrite(STDERR, "REFUSED: the Total row carrying a 94 cell resolved to ".count($totalAnchors)." rows; §22.2's total cannot be addressed safely.\n");

    exit(1);
}

$totalAnchor = $totalAnchors[0];

// Both the §30.1 row and its §24.19 twin state the same census, so every row holding the label moves —
// one copy left at 63 would have the document call its own table count by two numbers.
$tablesRows = count(rowAnchors($xml, 'Tables in the live database</w:t>', '63'));

$counts = $readings['documentHeadings'];

printf("\n§24 headings today: %d read, %d agree, %d stated columns (%d domain). Pass 260 owns those two cells.\n\n",
    $counts['read'], $counts['agree'], $counts['statedColumns'], $counts['domainStatedColumns']
);

$edits = [
    // §23's census sentence.
    ['63 tables (55 EMS domain + 8 framework)', '64 tables (56 EMS domain + 8 framework)', null, 1],
    ['462 columns, 102 foreign-key constraints, 131 indexes', '477 columns, 105 foreign-key constraints, 135 indexes', null, 1],
    ['177 table constraints in all', '181 table constraints in all', null, 1],
    // §23.3's delete-rule census and the SET NULL list.
    ['102 constraints: 69 refuse', '105 constraints: 71 refuse', null, 1],
    ['28 null the child key', '29 null the child key', null, 1],
    ['These 28 keys', 'These 29 keys', null, 1],
    // §13 and §30.1's cross-checks of the dictionary against the migration parse.
    ['for 57 of the 63 tables documented here', 'for 58 of the 64 tables documented here', null, 1],
    // §30.1's own title and its two reconciliation rows. The cover line and §24.19's own sentence both
    // quote the application-table count, so both move together — one copy would leave the document
    // calling its own figure by two names.
    ['Fifty-five, and the sixty-three tables in the database', 'Fifty-six, and the sixty-four tables in the database', null, 1],
    ['55 application tables', '56 application tables', null, 2],
    ['holds 63 tables in all', 'holds 64 tables in all', null, 1],
    ['55 domain + 8 framework', '56 domain + 8 framework', null, 1],
    ['55 + 8', '56 + 8', null, 1],
    ['50 a scan of this project', '51 a scan of this project', null, 1],
    // §24.19's partition sentence: two tables now postdate the baseline wave.
    ['the other 1, shiftingrequests, came 2026-10-03', 'the other 2, shiftingrequests and enrollmentreturns, came 2026-10-03 and 2026-10-27', null, 1],
];

$cells = [
    ['Domain tables in the live database', '55', '56'],
    ['Created by this project', '50', '51'],
    ['Tables in the live database', '63', '64', $tablesRows],
    ['Reconciled position', '63', '64'],
    ['Live tables against the migration parse', '57 of 63 tables agree column for column; 6 differ', '58 of 64 tables agree column for column; 6 differ'],
    // §22.2: the enrollment module gains enrollment.return, so its count and its list both move. The
    // anchor is the row's full list, because `enrollment.approve, enrollment.drop` opens two permission
    // rows in this document and the cell kind would rewrite both.
    ['enrollment.approve, enrollment.drop, enrollment.studentdata.record, print.certificate', '7', '8'],
    ['enrollment.approve, enrollment.drop, enrollment.studentdata.record, print.certificate', 'enrollment.approve', 'enrollment.approve, enrollment.drop, enrollment.return, enrollment.studentdata.record, print.certificate, print.classCard, print.enrollmentForm, print.subjectLoad'],
    // §22.2's Total row: fourteen module cells above it now sum to 95.
    [$totalAnchor, '94', '95'],
];

echo "\n";

docxApply($docx, $backup, $edits, $cells, $apply);

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$zip = new ZipArchive;

if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED (post-write): cannot open the written archive for reading.\n");

    exit(1);
}

$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ([
    '63 tables (55 EMS domain' => 0,
    '462 columns, 102 foreign-key constraints, 131 indexes' => 0,
    '177 table constraints in all' => 0,
    '102 constraints: 69 refuse' => 0,
    'These 28 keys' => 0,
    'for 57 of the 63 tables documented here' => 0,
    'Fifty-five, and the sixty-three tables in the database' => 0,
    '55 application tables' => 0,
    '55 domain + 8 framework' => 0,
    '55 + 8' => 0,
    '50 a scan of this project' => 0,
    'the other 1, shiftingrequests, came 2026-10-03' => 0,
    '>94<' => 0,
    '64 tables (56 EMS domain' => 1,
    '477 columns, 105 foreign-key constraints, 135 indexes' => 1,
    '181 table constraints in all' => 1,
    '105 constraints: 71 refuse' => 1,
    'These 29 keys' => 1,
    'for 58 of the 64 tables documented here' => 1,
    'Fifty-six, and the sixty-four tables in the database' => 1,
    '56 application tables' => 2,
    '56 domain + 8 framework' => 1,
    '51 a scan of this project' => 1,
    'the other 2, shiftingrequests and enrollmentreturns' => 1,
    '>95<' => 1,
    'enrollment.approve, enrollment.drop, enrollment.return, enrollment.studentdata.record' => 1,
] as $needle => $expected) {
    $seen = substr_count($after, $needle);

    if ($seen !== $expected) {
        fwrite(STDERR, "REFUSED (post-write): '{$needle}' occurs {$seen} time(s), expected {$expected}.\n");

        exit(1);
    }

    printf("after %-56s %d as intended\n", substr($needle, 0, 54), $seen);
}

echo "\npass 259 applied: the schema census and the permission total now answer the server.\n";
