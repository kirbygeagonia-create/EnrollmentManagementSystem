<?php

/**
 * Docx pass 398 — the §1.1 revision row for the observed commit-and-forced-failure pair (guide T02 / T17).
 *
 * Same shape as pass 391: the row is a CLONE of the newest revision row (the engine refuses any pass that moves a
 * `<w:tr>`), every run of every cell is emptied before the two values are written into the first run's formatting,
 * and order is asserted on read-back so a clone cannot state a false chronology. No citation-shaped token is
 * written, because this row describes files and decisions, not their layout on a day; and no figure is typed that
 * an instrument prints — each number is quoted beside the instrument that prints it, per §1.1's own convention.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass397.php [--apply]
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

$date = '2026-11-02';
$body = 'The failure scenario the guide asks for is observed on the engine rather than argued, and the sentence that '
    .'had excused it for two documents was wrong in a way worth recording. `TESTING-AND-VERIFICATION-ADS.md` carried '
    .'T02 as “not executed, and not reachable as posed”, because the only injection thought of was an INPUT that fails '
    .'mid-loop and the assign act validates its ids with exists: before the transaction opens. That reasoning stopped '
    .'one layer too early: a real mid-transaction fault is a DATABASE event, and Laravel notifies a query listener after '
    .'each statement inside that same transaction, so throwing there adds no seam to the act — the failure arrives where '
    .'the act cannot choose whether to see it. `storage/app/scratch/lane198-probe-forced-rollback.php` drives the issuing '
    .'act twice on a seeded scratch schema and prints the pair the guide names: the commit (one enrollment, one form, '
    .'seven boxes, tables +1/+1/+7) and the forced failure (the enrollment row written, the fault thrown at the next '
    .'statement, then 0 rows for that key and +0/+0/+0 across all three tables), with the row’s visibility at the instant '
    .'of the fault measured in the listener rather than assumed from statement order. '
    .'Three defects came out of building it, none of them in the engine. The probe first died on two columns it invented '
    .'(isActive on the staff table, which holds status, and termStatus on the term table, which holds no status at all), '
    .'and the framework rendered that fatal while the process still exited 0 — a dead instrument a wrapper would have '
    .'recorded as a verdict, so anything escaping the named guards is now a refusal by name with exit 1. Then its own '
    .'falsifier’s third leg found a stack overflow in the probe: the visibility read was issued BEFORE the fire-once flag '
    .'was set, so when the needle moved to a select the read re-entered the listener, fired again, and the run died with '
    .'exit 255 and no cause; the counter now moves first, which is the ordering a probe that measures its own trigger has '
    .'to get right. `storage/app/scratch/mutate-lane198-rollback-probe.php` answers seven legs and kills all seven, '
    .'including the two that prove the failure half is a measurement — a needle moved to a statement the act never issues '
    .'must make the run refuse, and a fault fired before any write must make the order line refuse. '
    .'Re-taking T02 surfaced the mirror defect in the same table: the row above it still said T01 did not exist, one day '
    .'after its two cases were delivered, because the batch that closed T01 edited the register rows and never re-read the '
    .'scenario table — a membership claim left standing while its rows moved. The owed-demonstration lists followed the '
    .'same re-take, from five rows to three (T06, T20 and the report-during-enrollment reading), the concept rows for '
    .'Transactions and for COMMIT/ROLLBACK moved to demonstrated on the issuing act while the subject-line injection stays '
    .'named as open, and `Documentation/FINAL-REPORT-ADS.md`’s two id lists were re-taken from '
    .'`storage/app/scratch/lane169-read-ads-status.php` rather than typed, which is what showed its hand-kept Built list '
    .'omitting four ids the census counts as built and its Implemented list still carrying the two that moved.';

$anchor = '2026-11-02 ADS problem 9’s second half is built';
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

$backup = $root.'/storage/app/scratch/pass397-backup-'.date('Ymd-His').'.docx';
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

// The read-back asks for THIS row's own words. The needle inherited from pass 397 named that pass's opening
// sentence, so the guard found the previous row, compared it with the new body and refused a correct write —
// pass 394's defect, met again one clone later.
$newRows = $rowsForAnchor($read, $date.' The failure scenario the guide asks for');
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

// Chronology, scoped to the table: this row must be the LAST row of THIS table.
$tblEnd = strpos($read, '</w:tbl>', $newRows[0][1]);
$nextTr = strpos($read, '<w:tr', $newRows[0][1]);
if ($nextTr !== false && $tblEnd !== false && $nextTr < $tblEnd) {
    $following = substr($read, $nextTr, strpos($read, '</w:tr>', $nextTr) + 7 - $nextTr);
    if (preg_match('/^\d{4}-\d{2}-\d{2}/u', $rowText($following)) === 1) {
        fwrite(STDERR, "REFUSED: another revision row follows this batch's row inside the same table. Restored from {$backup}.\n");
        if (! copy($backup, $docx)) {
            fwrite(STDERR, "REFUSED: the restore failed — repair from {$backup} by hand.\n");
        }
        exit(1);
    }
}

printf("read-back: the row is present, exact and last in the table; backup %s\n", basename($backup));
