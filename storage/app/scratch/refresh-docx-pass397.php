<?php

/**
 * Docx pass 397 — the §1.1 revision row for the guide-answer census, the widened artefact gate and ADS §P09's second half.
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
$body = 'ADS problem 9’s second half is built, the artefact census learned to read bare names, and the guide’s own questions now have a file of answers. '
    .'The Registrar queue’s last three per-record reads — the passed set, the approved credits and the declared prerequisites — come from '
    .'`App\Support\ReadinessBatch`, built once from the page’s own rows after pagination and threaded through the checklist as an optional '
    .'argument that defaults to the fresh read, so the policy, the record page and the blocking reason keep calling one predicate. '
    .'`storage/app/scratch/lane191-attribute-registrar-queries.php` then attributes each of the three to one statement per page and answers '
    .'0 repeating statements per row — and refuses its own verdict, because a five-row queue page no longer holds a repeat to weigh; the '
    .'refusal is the reading, and the before-and-after count lives in a case. Two findings came out of the falsifier rather than the code: '
    .'the equality case could not detect an inverted ceiling filter until a third record whose prerequisite was sat and FAILED was added, '
    .'because two records that agree are an agreement and not a measurement; and a leg aimed at cases that call the helper directly can '
    .'never see whether the queue builds one, so the wiring now has its own source-reading case. '
    .'`storage/app/scratch/mutate-g-p09-readiness-batch.php` plants each of the six ways to be wrong, kills all six, and restores the tree '
    .'by hash. The second lane is a gate’s own widening: `lane194-ads-artefact-citations.php` required a directory separator, so it judged '
    .'131 of the 189 path citations its own run now counts. Read against a basename index built from git’s list, with a spaced-span reader, '
    .'AMBIGUOUS in place of a first hit and exemptions by class, it found a test class that has never existed, a phantom log named inside '
    .'the very sentence that records it as phantom, and two basenames answering fourteen and two files — all four re-taken by reading, not '
    .'shifted. Its harness grew to seventeen legs and found three bugs in itself first, among them backticks used as the regex delimiter, '
    .'which had made the code-span reader match every filename in prose and invent two ambiguities out of nothing. The third lane answers '
    .'the guide in the guide’s frame: `Documentation/GUIDE-PROBLEM-ANSWERS-ADS.md` carries the 106 guide questions across the 24 problems, '
    .'each problem’s Applicability mark and its different-workflow line, the twelve concept rows and their explanation, the twenty-five '
    .'presentation sub-items, the defense sentences, the four fill-ins and the six task verbs, and '
    .'`storage/app/scratch/lane195-count-guide-answers.php` reads that population from the guide’s own dump, compares every Applicability '
    .'mark against the P register’s verdict word rather than letting two documents answer one question, and refuses until each item carries '
    .'an answer of substance — proved by `mutate-lane195-guide-answers.php` at eighteen legs, whose first run caught a five-character answer '
    .'of this batch’s own and a leg anchored on the sentence the tool replaced instead of the sentence it writes. Two register corrections '
    .'travel with the batch: the traceability row that cited the phantom test class now names the delivered T01 pair, and four rows of the '
    .'requirement matrix still reading Not built about shipped work now name their artifacts, which moves the matrix census printed by '
    .'`storage/app/scratch/lane94-matrix-claims.php` from seven not-built to three — and all three wait on the school or the owner, not on code.';

$anchor = '2026-11-01 The active-seat key is on the school’s engine again';
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

$newRows = $rowsForAnchor($read, $date.' ADS problem 9’s second half is built');
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
