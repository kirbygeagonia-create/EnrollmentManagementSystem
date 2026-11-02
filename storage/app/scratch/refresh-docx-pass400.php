<?php

/**
 * Docx pass 400 — the §1.1 revision row for the cancellation act's forced failure (guide T06, and problem 6's third question).
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
$body = 'The cancellation act is now broken on purpose, and the harness that proves it broke found its own anchors '
    .'colliding. Guide problem 6 asks what happens when ONE cancellation operation fails, and three documents had '
    .'answered that with a promise. The ninth case of `tests/Feature/Registrar/EnrollmentDropTest.php` registers a '
    .'query listener on the act’s own connection, lets the status transition and its history row be written, throws at '
    .'the load retire, then re-reads the status, every line’s status, the reason column and the history count — and all '
    .'four come back unchanged. `storage/app/scratch/mutate-t06-drop-atomicity-tests.php` proves that case bites by '
    .'planting three defects, and its first run refused every leg for a reason belonging to the harness rather than to '
    .'the code: the closure signature line and the whole subject-line update block each occur twice in that controller, '
    .'because the approve act writes the same shape, so a document-wide needle would have planted the fault in the '
    .'sibling method and reported the promise as surviving. The harness now locates the act’s own byte span and asserts '
    .'uniqueness inside it — the mutation-harness form of a needle that turns out to be another citation’s tail. Two '
    .'more rules came out of the same run: a leg must require the named case to fail with its own assertion sentence, '
    .'because a plant that does not compile also prints a failure; and one plant is withheld with its reason written '
    .'into the file, because hoisting only the reason update outside the transaction cannot be seen by a case whose '
    .'fault fires earlier, which makes that leg decoration. The closure moved six places: the Group-D row, the census '
    .'row, the detail block and the scenario table in the traceability matrix; problem 6’s test, evidence and Q6.3 lines '
    .'in the answer file, which now cites a measurement instead of promising one; the owed-demonstration membership '
    .'lists, down to the two that genuinely wait on the school; the concept-compliance limitation, which now says '
    .'precisely what is proven (the issuing act and the cancellation act) and what is not (two separate subject-line '
    .'inserts inside the proposal loop); and the changelog’s instrument table. Two figures were removed as they were '
    .'being written — a harness leg total and a plant count — because a number about what a tool prints rots the day '
    .'the tool grows and no instrument binds a checker that is not in its own table; the plants are named instead.';

$anchor = '2026-11-02 The failure scenario the guide asks for';
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
$newRows = $rowsForAnchor($read, $date.' The cancellation act is now broken on purpose');
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
