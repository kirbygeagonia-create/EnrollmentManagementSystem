<?php

/**
 * Docx pass 389 — the six rows §24 and §23.6.1 owe the schema.
 *
 * Two findings, both read out of the instruments rather than recalled:
 *
 *  (1) `lane44-unique-keys.php` reports `uq_enrollments_active_seat` as a MISS: the seat key returned
 *      to live `ems` with `2026_11_01_1900000000_return_the_active_seat_key_now_that_fixtures_hold_one_seat_per_term.php`
 *      after the eight fixture files it had been withdrawn for were repaired, and §23.6.1 claims to
 *      list EVERY domain unique key. One row is added here; its count sentence is a text edit and
 *      belongs to pass 390.
 *
 *  (2) `lane189-weigh-column-claims.php` compares each §24 table's own heading with the number of
 *      column rows beneath it and found four tables one row short — students, admissions, enrollments,
 *      notifications. Their headings were moved to the server's new count by the batches that added
 *      `rowVersion`, `rejectionReason`, `requestKey` and `dedupeKey`, and no instrument compares a
 *      heading with the rows under it, so the tables silently kept listing one fewer column than they
 *      promise. `lane60-ratio-claims.php` binds a heading to `information_schema` and agreed with all
 *      four, which is exactly the blind spot: the heading was true and the table under it was not.
 *      Five rows are added (enrollments is short by two, because `seatKey` is new and `requestKey`
 *      never arrived).
 *
 * A new row goes through a clone (the recipe `refresh-docx-pass251b.php` carries): locate it, clone
 * it, empty every run of every cell — a cloned multi-run cell otherwise carries the template's tail —
 * and write the supplied values into the first run's formatting. Rows are located by the table they
 * belong to: the pass indexes every `X — N columns` heading paragraph by byte offset and takes the
 * LAST `<w:tr>` between that heading and the next, so an anchor that occurs in several tables
 * (`updated_at | timestamp | yes` is one in four of them) cannot send a row to the wrong place.
 *
 * Every type, nullability and key mark is read from `information_schema` at run time and the pass
 * refuses a column it cannot find there, so a row cannot state a schema the server does not hold.
 * Nothing reaches the file unless all six rows resolved. Usage: php storage/app/scratch/refresh-docx-pass389.php [--apply]
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if (! in_array($arg, ['--apply', '--debug'], true)) {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply and --debug.\n");
        exit(1);
    }
}

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};

$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

/**
 * The text of one `<w:tr>` as the anchor rule reads it: cells joined WITH a separator. A row's runs
 * concatenate directly, so `rowVersion` `int unsigned` become one word unless the cells are read apart.
 */
$rowText = static function (string $row) use ($flatten): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);

    return implode(' ', array_map($flatten, $cm[0]));
};

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: the docx did not open (ZipArchive answers an error code, not false, for a file that is not an archive).\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
if ($xml === '') {
    fwrite(STDERR, "REFUSED: word/document.xml read empty.\n");
    exit(1);
}

// ---------------------------------------------------------------- the schema, from the server
$env = [];
foreach ((file($root.'/.env') ?: []) as $line) {
    if (preg_match('/^([A-Z_]+)=(.*)$/', trim($line), $m)) {
        $env[$m[1]] = trim($m[2], " \t\"'");
    }
}
$dbName = $env['DB_DATABASE'] ?? 'ems';
$mysqli = new mysqli('localhost', $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '', $dbName);
if ($mysqli->connect_error) {
    fwrite(STDERR, "REFUSED: {$dbName} answered nothing (".$mysqli->connect_error."), and every cell this pass writes is a schema fact.\n");
    exit(1);
}
$schema = $mysqli->real_escape_string($dbName);
$columnRow = static function (string $table, string $column) use ($mysqli, $dbName, $schema): array {
    $res = $mysqli->query('select COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA '
        ."from information_schema.COLUMNS where TABLE_SCHEMA='{$schema}' and TABLE_NAME='".$mysqli->real_escape_string($table)."' "
        ."and COLUMN_NAME='".$mysqli->real_escape_string($column)."'");
    if ($res === false) {
        fwrite(STDERR, "REFUSED: the schema question for {$table}.{$column} failed: ".$mysqli->error."\n");
        exit(1);
    }
    $row = $res->fetch_assoc();
    if ($row === null) {
        fwrite(STDERR, "REFUSED: {$dbName} holds no column {$table}.{$column}; a §24 row may not name one.\n");
        exit(1);
    }

    return $row;
};

/** @return array{0:string,1:string,2:string,3:string,4:string,5:string} the six §24 cells for one column */
$cellsFor = static function (string $table, string $column, string $note = '') use ($columnRow): array {
    $c = $columnRow($table, $column);
    $default = $c['COLUMN_DEFAULT'];
    if ($default === null) {
        $default = '';
    } elseif (strcasecmp($default, 'CURRENT_TIMESTAMP') === 0) {
        $default = 'CURRENT_TIMESTAMP';
    }

    return [$column, $c['COLUMN_TYPE'], $c['IS_NULLABLE'] === 'YES' ? 'yes' : 'no',
        $c['COLUMN_KEY'] === '' ? '' : ($c['COLUMN_KEY'] === 'UNI' ? 'UQ' : ($c['COLUMN_KEY'] === 'PRI' ? 'PK' : 'IDX')),
        $default, $note];
};

// ---------------------------------------------------------------- the engine's own row builders
$rowsForAnchor = static function (string $xml, string $anchor) use ($rowText): array {
    $hits = [];
    $at = 0;
    while (($start = strpos($xml, '<w:tr', $at)) !== false) {
        $end = strpos($xml, '</w:tr>', $start);
        if ($end === false) {
            break;
        }
        $end += 7;
        if (strncmp($rowText(substr($xml, $start, $end - $start)), $anchor, strlen($anchor)) === 0) {
            $hits[] = [$start, $end];
        }
        $at = $end;
    }

    return $hits;
};

/**
 * The byte range of the LAST `<w:tr>` that belongs to the table headed `$headingPrefix — N columns`,
 * or null when the heading or its rows cannot be found. Scoping by heading is what keeps a repeated
 * row prefix out of the wrong table.
 */
$lastRowOfTable = static function (string $xml, string $tableName) use ($flatten): ?array {
    // Re-scanned on every call on purpose: each insert shifts the byte offsets below it, so a cached
    // paragraph index would locate the next table with the previous row's offsets.
    $headings = [];
    preg_match_all('/<w:p>.*?<\/w:p>/s', $xml, $pm, PREG_OFFSET_CAPTURE);
    $paragraphs = $pm[0];
    foreach ($paragraphs as $p) {
        $off = (int) $p[1];
        $t = $flatten($p[0]);
        if (preg_match('/^'.preg_quote($tableName, '/').'\s*—\s*\d+ columns$/u', $t)) {
            $headings[] = [$off, $t];
        }
    }
    if (count($headings) !== 1) {
        fwrite(STDERR, "REFUSED: the heading '{$tableName} — N columns' resolved to ".count($headings)." paragraph(s), expected exactly 1.\n");
        exit(1);
    }
    $from = $headings[0][0];
    $next = null;
    foreach ($paragraphs as $p) {
        $off = (int) $p[1];
        if ($off <= $from) {
            continue;
        }
        $t = $flatten($p[0]);
        if (preg_match('/^[a-z0-9_]+\s*—\s*\d+ columns$/u', $t) || preg_match('/^\d+\.\d+/u', $t)) {
            $next = $off;
            break;
        }
    }
    $last = null;
    $at = $from;
    while (($start = strpos($xml, '<w:tr', $at)) !== false) {
        $end = strpos($xml, '</w:tr>', $start);
        if ($end === false) {
            break;
        }
        $end += 7;
        if ($next !== null && $start >= $next) {
            break;
        }
        $last = [$start, $end];
        $at = $end;
    }
    if ($last === null) {
        fwrite(STDERR, "REFUSED: no <w:tr> was found under the heading for {$tableName}, so the table cannot be extended.\n");
        exit(1);
    }

    return $last;
};

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

// ---------------------------------------------------------------- the six rows
// [table-or-null, anchor prefix (when not a table heading), values]
$plan = [
    ['key' => ['uq_enrollments_request_key requestKey', [
        'uq_enrollments_active_seat',
        'studentId, termId, seatKey',
        'One ACTIVE enrollment per student per term. `EnrollmentIssuer::seatHolder()` already refused it at every creation point; this is the same rule on the server, so a second route, a raw `Enrollments::create` or a restored dump cannot write a pair.',
        '`seatKey` is a VIRTUAL generated column, non-null exactly when `enrollmentStatus <> \'dropped\'` and a term is named, and MySQL ignores NULLs in a unique index — so a drop releases the seat and the student the Registrar dropped may enroll again in the same term, which is ruling 17’s behaviour enforced without a trigger. STORED was refused with error 1215 on this table because eleven others point at it and a STORED column rebuilds the table; VIRTUAL adds in place, measured. Added 2026-11-01 for the Advanced Database Systems guide’s problem 3, withdrawn the same day when CI’s MySQL job refused the fixture files that wrote two active seats for one student and one term, and returned the same day once those eight files were repaired and rehearsed on a scratch MySQL schema. SQLite compiles no `ALTER TABLE … ADD COLUMN … GENERATED`, so the key exists only on the school’s driver and `tests/Feature/Enrollment/DatabaseLevelEnrollmentRulesTest.php` asserts the driver-appropriate fact instead of skipping.',
    ]]],
    ['students' => $cellsFor('students', 'rowVersion', 'the optimistic lock §14 asks for: a save whose version does not match the row is refused, never silently overwritten')],
    ['admissions' => $cellsFor('admissions', 'rejectionReason', 'required on a rejection (prompt §6.5, G33); a rejection filed before the column exists simply shows no reason')],
    ['notifications' => $cellsFor('notifications', 'dedupeKey', 'the notice guard: `App\Support\NoticeDelivery::once()` keys it on the act and the recipient, and the index is what refuses a worker that takes the job again')],
    ['enrollments' => $cellsFor('enrollments', 'requestKey', 'the issuance guard: one key, one enrollment, so a retried send answers the record the first send created (the guide’s problem 17)')],
    ['enrollments' => $cellsFor('enrollments', 'seatKey', 'VIRTUAL generated from `studentId`, `termId` and `enrollmentStatus`; NULL on a dropped row or an unnamed term, which is what lets the seat key in §23.6.1 ignore a released seat')],
];

$report = [];
$failures = [];
$debug = in_array('--debug', $argv, true);
foreach ($plan as $entry) {
    if (isset($entry['key'])) {
        [$anchor, $values] = $entry['key'];
        $rows = $rowsForAnchor($xml, $anchor);
        if (count($rows) !== 1) {
            $failures[] = "key anchor '{$anchor}' matched ".count($rows).' row(s), expected exactly 1';
            continue;
        }
        $insertAt = $rows[0][1];
        $cellCount = 4;
    } else {
        $table = array_key_first($entry);
        $values = $entry[$table];
        $cellCount = 6;
        // Re-located per row, so the second enrollments column lands after the first rather than
        // before it.
        $rows = [$lastRowOfTable($xml, $table)];
        $insertAt = $rows[0][1];
    }
    if (count($values) !== $cellCount) {
        $failures[] = 'the row for '.$values[0].' carries '.count($values).' values, expected '.$cellCount;
        continue;
    }
    $rowStart = strrpos(substr($xml, 0, $insertAt), '<w:tr');
    if ($rowStart === false) {
        $failures[] = 'no <w:tr> opens before the insert point for '.$values[0];
        continue;
    }
    $template = substr($xml, $rowStart, $insertAt - $rowStart);
    $clone = $buildRow($template, $values, $values[0]);
    $xml = substr_replace($xml, $clone, $insertAt, 0);
    $report[] = sprintf('ok   %-24s -> %d cell(s)', $values[0], count($values));
}

if ($debug) {
    // Prints the intended row text against what the in-memory document actually holds, because a
    // read-back that refuses without saying how it differs sends the reader to the whole file.
    foreach ($plan as $entry) {
        $values = isset($entry['key']) ? $entry['key'][1] : $entry[array_key_first($entry)];
        $want = implode(' ', array_map(static fn ($v) => trim((string) $v), $values));
        echo 'WANT: '.substr($want, 0, 200)."\n";
        $found = $rowsForAnchor($xml, $values[0].' ');
        if ($found === []) {
            echo " GOT: (no row starts with this text)\n";
        }
        foreach ($found as [$s, $e]) {
            echo ' GOT: '.substr($rowText(substr($xml, $s, $e - $s)), 0, 200)."\n";
        }
    }
    exit(0);
}

foreach ($report as $line) {
    echo $line."\n";
}
if ($failures !== []) {
    fwrite(STDERR, "\n".count($failures)." row(s) unresolved; nothing written:\n");
    foreach ($failures as $f) {
        fwrite(STDERR, '  '.$f."\n");
    }
    exit(1);
}

printf("%s%d row(s) inserted\n", $apply ? '' : 'DRY RUN — ', count($plan));

if (! $apply) {
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass389-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup.\n");
    exit(1);
}

// One-shot guard: a row whose flattened text already equals one this pass writes means the pass has
// already run. Identity is the WHOLE row, not a cell — `rowVersion` occurs in prose and in other
// tables' notes, and a name-only test would refuse a correct first run.
$rowExists = static function (string $haystack, array $values) use ($rowText, $rowsForAnchor): bool {
    $want = implode(' ', array_map(static fn ($v) => trim((string) $v), $values));
    foreach ($rowsForAnchor($haystack, $values[0].' ') as [$s, $e]) {
        if ($rowText(substr($haystack, $s, $e - $s)) === $want) {
            return true;
        }
    }

    return false;
};

$before = new ZipArchive;
if ($before->open($backup) !== true) {
    fwrite(STDERR, "REFUSED: the backup did not open, so the pass cannot prove it is one-shot.\n");
    exit(1);
}
$beforeXml = (string) $before->getFromName('word/document.xml');
$before->close();
$already = [];
foreach ($plan as $entry) {
    $values = isset($entry['key']) ? $entry['key'][1] : $entry[array_key_first($entry)];
    if ($rowExists($beforeXml, $values)) {
        $already[] = $values[0];
    }
}
if ($already !== []) {
    fwrite(STDERR, "REFUSED: the document already holds this pass's row(s) for ".implode(', ', $already)
        ."; a second --apply would double-insert.\n");
    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($docx, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "REFUSED: could not reopen the docx for writing.\n");
    exit(1);
}
$zip->addFromString('word/document.xml', $xml);
$zip->close();

$again = new ZipArchive;
if ($again->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: the written docx did not reopen.\n");
    exit(1);
}
$read = (string) $again->getFromName('word/document.xml');
$again->close();

$bad = [];
foreach ($plan as $entry) {
    $values = isset($entry['key']) ? $entry['key'][1] : $entry[array_key_first($entry)];
    if (! $rowExists($read, $values)) {
        $bad[] = $values[0];
    }
}
if ($bad !== []) {
    fwrite(STDERR, "REFUSED: after the write, no row reads exactly as planned for: ".implode(', ', $bad)
        .". The document has been restored from {$backup}.\n");
    if (! copy($backup, $docx)) {
        fwrite(STDERR, "REFUSED: the restore failed — repair from {$backup} by hand.\n");
    }
    exit(1);
}

printf("read-back: %d row(s) present exactly as written; backup %s\n", count($plan), basename($backup));
