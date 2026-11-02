<?php

/**
 * Docx pass 404 — the §1.1 revision row for two closures that landed on the same day: the ADS guide's own form
 * answered in a second Word copy, and §28.3 G-37 closed by naming the act on the audit row.
 *
 * Same shape as pass 400 and pass 391: the row is a CLONE of the newest revision row (the engine refuses any pass
 * that moves a `<w:tr>`), every run of every cell is emptied before the two values are written into the first run's
 * formatting, and order is asserted on read-back so a clone cannot state a false chronology.
 *
 * No `File.php:NN` token is written, because this row describes files and decisions, not their layout on a day —
 * a historical line number in a living row is a citation the gate will judge against a tree that has moved since.
 * No figure is typed that an instrument prints either: the act count is quoted beside the class that holds it and
 * the audit-column counts beside the server that answers them, which is the convention §1.1 already follows.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass404.php [--apply]
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

// The act count is read from the code the row describes, never recalled: a sentence in this document that states
// how many acts exist goes stale the day a family gains a word.
$actSource = (string) file_get_contents($root.'/app/Support/AuditAct.php');
preg_match_all("/public const [A-Z_]+ = '([a-z.]+)';/", $actSource, $am);
$acts = count(array_unique($am[1]));
if ($acts < 1) {
    $fail('the act vocabulary read back as nothing from app/Support/AuditAct.php.');
}

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
$body = 'The ADS guide form is answered in a second Word copy, and the audit trail names the act. '
    .'`Documentation/ADS_Final_Project_Guide_EMS_ANSWERED.docx` is built from the guide’s own form by '
    .'`storage/app/scratch/lane203-fill-guide-form.php`, which reads the answers from '
    .'`Documentation/GUIDE-PROBLEM-ANSWERS-ADS.md` rather than typing them twice, ticks every box whose OWN label was '
    .'ruled rather than by position, and refuses the run when a required field is missing from the answer file; '
    .'`storage/app/scratch/lane207-verify-guide-copy.php` then reads the finished copy window by window and refuses '
    .'the blank form as its own positive control, and '
    .'`storage/app/scratch/mutate-lane203-guide-form.php` proves the builder by planting the defects it exists to '
    .'catch, including the one its first run made: a heading pattern whose terminator could cross a line, which read '
    .'forty-eight applicability rows where the form holds twenty-four and still printed a clean tally. '
    .'§28.3 G-37 closed the same day on the owner’s ruling of the 2026-10-29 sheet’s item 4 — name the act, one word '
    .'list. `App\Support\AuditAct` now holds that vocabulary ('.$acts.' named acts) beside the map from each desk route '
    .'to the act it performs, and `App\Http\Middleware\NameAuditAct` names the act for every row one request writes, '
    .'so the log answers "who voided a receipt" without reading eight domain tables while a plain edit keeps the event '
    .'word. Two readings came out of it and both are this document’s oldest defect in a new place: the audit desk’s own '
    .'Action filter typed its word list, nine of which named an act nothing writes — measured against the server, the '
    .'column held only the three event words — so the list is now built from the vocabulary the handler sends rather '
    .'than typed on the screen; and the claim this register had carried for weeks, that prompt §9 is not the audit '
    .'section, was false as written: the prompt was read, §9 is titled for audit logs, and its own subsection '
    .'enumerates the seven families the register named. A pointer into a document outside this repository cannot be '
    .'weighed by any gate here, so the row states what was read rather than a line number.';

$anchor = '2026-11-02 The cancellation act is now broken on purpose';
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

printf("one revision row built (%d bytes of text, %d acts read from the code). %s\n",
    strlen($body), $acts, $apply ? 'applying' : 'DRY RUN');
if (! $apply) {
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass404-backup-'.date('Ymd-His').'.docx';
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

// The read-back asks for THIS row's own opening words. A needle inherited from the cloned pass names that pass's
// sentence, which the previous row still holds — the guard then finds the old row, compares it with the new body
// and refuses a correct write (pass 394's and pass 398's defect, met from the same direction).
$newRows = $rowsForAnchor($read, $date.' The ADS guide form is answered in a second Word copy');
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
