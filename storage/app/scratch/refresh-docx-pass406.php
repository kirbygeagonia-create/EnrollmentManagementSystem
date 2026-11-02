<?php

/**
 * Docx pass 406 — the §1.1 revision row for §28.3 G-42s second half that landed on the same day: the ADS guide's own form
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
 * Usage: php storage/app/scratch/refresh-docx-pass406.php [--apply]
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
$body = implode(' ', [
    'A deactivated account now stops at its next request, and both doors say one sentence.',
    '§28.3 G-42 carried a half-closed verdict: the login screen refused a closed account while nothing else in the tree read the status column — measured by grepping the word across app/ — so a desk switched off while its tab was open kept acting for the life of its cookie.',
    'The owner ruled item 2 of the 2026-10-29 sheet the same day it was asked — stop at the next request — and the web group now carries RejectDeactivatedAccount, which asks the account its own status on each request and reads the status, never the role, because the prompt asks that the two stay distinct.',
    'The refusal says the sentence the login screen has always said, from one holder in App\\Support\\DenialReason, and a case reads the login source so a literal copy cannot reappear there: two explanations of one state is how a drift between the doors becomes invisible.',
    'It carries the desk input with it and signs the session out, and the record is untouched, so another account can finish the act.',
    'One half of the ruling is realised differently and the sheet says so rather than the difference being smoothed: the desk lands on the sign-in screen, not on its own record, because the session that would carry it there has just been closed.',
    'The new cases are directional on both sides of the flip, so a guard that refused everything fails as surely as one that refuses nothing; the falsifier withholds its session-invalidation leg with the reason written in the file, because logout() already ends the signed-in state and a plant that only drops the store invalidation cannot fail.',
    'Closing the row moved the §28.8 tally for the Project owner from three owed items to two and made the §28.5 question read answered; registering a middleware is an import line, so four number tokens in the authority document and three in the markdown aged and were re-taken by reading both endpoints, and the markdown gate flagged none of them.',
]);

$anchor = '2026-11-02 The ADS guide form is answered in a second Word copy';
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

$backup = $root.'/storage/app/scratch/pass406-backup-'.date('Ymd-His').'.docx';
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
$newRows = $rowsForAnchor($read, $date.' A deactivated account now stops at its next request');
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
