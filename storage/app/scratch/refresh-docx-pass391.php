<?php

/**
 * Docx pass 391 — the §1.1 revision row for the seat key's return and the §24 row omissions it exposed.
 *
 * The row is a clone (locate the newest revision row, empty every run of every cell, write the two
 * values into the first run's formatting) because the engine refuses any pass that moves a `<w:tr>`.
 * Order is asserted on read-back: this document's revision table must end with the day this batch
 * wrote, and a clone that landed above its neighbour would state a false chronology that no bounds
 * check can see.
 *
 * No figure is typed that an instrument prints: the key count and the schema figures are quoted as
 * the readings that answered them, and the harness's own leg total is left to the harness. No
 * citation-shaped token (a file name plus a colon and a line number) is written, because this row
 * describes files, not their layout on a day.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass391.php [--apply]
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

$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};
$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
$rowText = static function (string $row) use ($flatten): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);

    return implode(' ', array_map($flatten, $cm[0]));
};
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

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    $fail('the docx did not open.');
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
if ($xml === '') {
    $fail('word/document.xml read empty.');
}

$date = '2026-11-01';
$body = 'The active-seat key is on the school’s engine again, and §24 was found to list fewer columns than its own headings promise. '
    .'`uq_enrollments_active_seat` had been withdrawn the same day it was delivered because CI’s MySQL job refused test files writing '
    .'two active seats for one student and one term. The census that then read every enrollment call site in `tests/` — '
    .'`storage/app/scratch/lane187-census-fixture-seats.php`, which binds each student and term to where the value actually came from '
    .'and exits 1 rather than counting a site whose provenance it cannot bind — answered eight files, not the six CI happened to reach '
    .'first, and its falsifier `storage/app/scratch/mutate-lane187-provenance.php` carries one leg per promise and prints its own leg '
    .'total. Each file was repaired to name a later term, drop the prior seat, or take a second student; the six CI first reached '
    .'rehearsed green on a scratch MySQL schema with `DB_CONNECTION` pinned; and '
    .'`database/migrations/2026_11_01_1900000000_return_the_active_seat_key_now_that_fixtures_hold_one_seat_per_term.php` re-added the '
    .'VIRTUAL column and the key behind a duplicate guard that names any pair it finds and drops the column again if the index add '
    .'throws. Live `ems` was dumped first, then migrated, then re-read: 37 enrollments, 24 students and 14 fee sheets, identical to the '
    .'counts taken before the write. `lane44-unique-keys.php` then read thirteen keys in ten tables, and its complaint set — one absent '
    .'row and one count sentence, nothing else — is what this batch’s two document passes wrote against; each refused its own run until '
    .'the set it had parsed equalled the set the instrument tallied. The second finding belongs to this document: '
    .'`storage/app/scratch/lane189-weigh-column-claims.php` compares each §24 table’s heading with the number of column rows beneath it, '
    .'and four tables were one row short — `students` without `rowVersion`, `admissions` without `rejectionReason`, `notifications` '
    .'without `dedupeKey`, `enrollments` without `requestKey` — because every batch that added a column moved the heading and no '
    .'instrument compared a heading with the rows under it. `lane60-ratio-claims.php` bound each heading to the server and agreed with '
    .'all four, which is exactly the blind spot: the heading was true and the table beneath it was not. Five column rows were added, '
    .'the census figures moved to what the server answers, and §23.6.1’s register and its count sentence followed. Two readings travel '
    .'with the batch. The note paragraphs that list an edition’s first-documented columns are split into one run per column name, so no '
    .'text needle reaches them and the repair clones a neighbour run rather than flattening the paragraph’s styled spans. And the pass '
    .'that added the rows first refused its own write: its read-back compared a whole-row text built by joining runs with nothing '
    .'against a row built by joining cells with a separator, so six correct rows read back as absent and the document was restored from '
    .'its own backup — which is what a refusal that names its difference is for. What is owed, stated rather than smoothed: the census’s '
    .'unbound sites are a reader’s queue, the enrollment-day load’s contention phase has not been re-run since the return so its refusal '
    .'count is a reading of the earlier live-key moment, and P13’s term-unit ceiling still waits on the school’s number.';

$anchor = '2026-10-31 The ENUM ruling reached';
$rows = $rowsForAnchor($xml, $anchor);
if (count($rows) !== 1) {
    $fail('the newest revision row matched '.count($rows).' row(s), expected exactly 1.');
}

if (str_contains($xml, '<w:t xml:space="preserve">'.$escape($body).'</w:t>')) {
    $fail('this pass has already run: its own row text is in the document.');
}

[$start, $end] = $rows[0];
$template = substr($xml, $start, $end - $start);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template, $cm);
if (count($cm[0]) !== 2) {
    $fail('the revision row this pass clones holds '.count($cm[0]).' cells, expected 2.');
}
foreach ($cm[0] as $cell) {
    preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
    foreach (array_slice($pm[0], 1) as $extra) {
        if ($flatten($extra) !== '') {
            $fail('a cell of the revision row holds a SECOND paragraph with text — read it before cloning.');
        }
    }
}

$values = [$date, $body];
$clone = $template;
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template, $cm2, PREG_OFFSET_CAPTURE);
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
    $bodyXml = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
    $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
        .$escape($values[$j]).'</w:t></w:r></w:p>', $bodyXml);
    if (substr_count($new, '<w:t xml:space="preserve">') !== 1) {
        $fail("the replacement for cell {$j} holds ".substr_count($new, '<w:t xml:space="preserve">')." text runs.");
    }
    $clone = substr_replace($clone, $new, $at, $len);
}
if (substr_count($clone, '<w:tc>') !== 2) {
    $fail('the clone lost or gained a cell.');
}
$xml = substr_replace($xml, $clone, $end, 0);

printf("one revision row built (%d bytes of text). %s\n", strlen($body), $apply ? 'applying' : 'DRY RUN');
if (! $apply) {
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass391-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    $fail('could not write the backup.');
}
$zip = new ZipArchive;
if ($zip->open($docx, ZipArchive::CREATE) !== true) {
    $fail('could not reopen the docx for writing.');
}
$zip->addFromString('word/document.xml', $xml);
$zip->close();

$again = new ZipArchive;
if ($again->open($docx) !== true) {
    $fail('the written docx did not reopen.');
}
$read = (string) $again->getFromName('word/document.xml');
$again->close();

$newRows = $rowsForAnchor($read, $date.' The active-seat key is on the school’s engine again');
$ok = count($newRows) === 1
    && $rowText(substr($read, $newRows[0][0], $newRows[0][1] - $newRows[0][0])) === $date.' '.$body;
if (! $ok) {
    fwrite(STDERR, "REFUSED: the row did not read back as written (".count($newRows)
        ." match(es)). Restored from {$backup}.\n");
    if (! copy($backup, $docx)) {
        fwrite(STDERR, "REFUSED: the restore failed — repair from {$backup} by hand.\n");
    }
    exit(1);
}

// Chronology, scoped to the table: the new row must be the LAST row of THIS table, so a clone
// inserted above its neighbour cannot state a false order. The next `<w:tr>` only matters when it
// sits before this table closes — the row after `</w:tbl>` belongs to another table entirely.
$tblEnd = strpos($read, '</w:tbl>', $newRows[0][1]);
$nextTr = strpos($read, '<w:tr', $newRows[0][1]);
if ($nextTr !== false && $tblEnd !== false && $nextTr < $tblEnd) {
    $following = substr($read, $nextTr, strpos($read, '</w:tr>', $nextTr) + 7 - $nextTr);
    if (preg_match('/^\d{4}-\d{2}-\d{2}/u', $rowText($following)) === 1) {
        fwrite(STDERR, "REFUSED: another revision row follows this batch's row inside the same table, so the register no longer "
            ."ends with the day it was written. Restored from {$backup}.\n");
        if (! copy($backup, $docx)) {
            fwrite(STDERR, "REFUSED: the restore failed — repair from {$backup} by hand.\n");
        }
        exit(1);
    }
}

printf("read-back: the row is present, exact and last in the table; backup %s\n", basename($backup));
