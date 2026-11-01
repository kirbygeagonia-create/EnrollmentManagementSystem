<?php

/**
 * Docx pass 393 — §23.6.2 must stop listing rules the server now enforces.
 *
 * The section is titled "The duplication rules the server does not enforce". Two of its rows say the schema holds
 * nothing for a rule that got a unique key on 2026-11-01:
 *   "One active enrollment per student per term" — `uq_enrollments_active_seat`, returned the same day the fixture
 *     census proved the product's own fixtures were the only thing writing the state it forbids;
 *   "One assessment per student per term" — `uq_studentassessments_enrollment`, added by the same batch and never
 *     removed from this list, which is why a section that is a BOUNDARY rots quietly: `lane44-unique-keys.php`
 *     weighs §23.6.1 (the list of keys that exist) and stayed silent about the list of keys that do not.
 * Both rows are deleted here, and the section's own lead sentence — "in three cases the answer is nothing" — moves
 * to the count the surviving rows support. The engine refuses any pass that moves a `<w:tr>`, so this is a
 * delete-then-write pass on the archive itself, with the surviving rows compared one by one against the original
 * bytes: a deletion that also clipped a neighbour is the half-applied register this file refuses.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass393.php [--apply]
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
$rowText = static function (string $row) use ($flatten): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);

    return implode(' ', array_map($flatten, $cm[0]));
};
$allRows = static function (string $xml): array {
    $rows = [];
    $at = 0;
    while (($start = strpos($xml, '<w:tr', $at)) !== false) {
        $end = strpos($xml, '</w:tr>', $start);
        if ($end === false) {
            break;
        }
        $end += 7;
        $rows[] = [$start, $end];
        $at = $end;
    }

    return $rows;
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

// The two rows to delete, each by the prefix of its FIRST cell — the cell text alone would also match §23.9 and
// the §28 register, which carry the same rule in prose.
$deletions = [
    'One active enrollment per student per term |',
    'One assessment per student per term |',
];
// A row's flattened text joins cells with a space, so a prefix naming the first TWO cells is what pins the section.
$anchors = [
    'One active enrollment per student per term enrollments has no unique key beyond its primary key',
    'One assessment per student per term studentassessments has no unique key',
];
$leadNeedle = 'and in three cases the answer is nothing.';
$leadNew = 'and in two cases the answer is nothing.';

$surviving = [];
foreach ($anchors as $i => $anchor) {
    $hits = [];
    foreach ($allRows($xml) as [$s, $e]) {
        if (strncmp($rowText(substr($xml, $s, $e - $s)), $anchor, strlen($anchor)) === 0) {
            $hits[] = [$s, $e];
        }
    }
    if (count($hits) !== 1) {
        $fail('the row anchor ['.substr($anchor, 0, 46).'] matched '.count($hits).' row(s), expected exactly 1.');
    }
    $surviving[] = $hits[0];
    printf("ok   located row %d: %s\n", $i + 1, substr($anchor, 0, 60));
}
if (substr_count($xml, $leadNeedle) !== 1) {
    $fail('the section lead sentence occurred '.substr_count($xml, $leadNeedle).' time(s), expected exactly 1 — '
        .'its count claim cannot be re-taken without reading it.');
}

// Deleted HIGH to LOW so the first removal does not shift the second row's offsets.
$beforeRowCount = count($allRows($xml));
$mutated = $xml;
foreach (array_reverse($surviving) as [$s, $e]) {
    $mutated = substr_replace($mutated, '', $s, $e - $s);
}
$mutated = str_replace($leadNeedle, $leadNew, $mutated);

$afterRows = $allRows($mutated);
if (count($afterRows) !== $beforeRowCount - 2) {
    $fail('the delete left '.count($afterRows).' rows where '.($beforeRowCount - 2).' were expected.');
}
foreach ($anchors as $anchor) {
    foreach ($afterRows as [$s, $e]) {
        if (strncmp($rowText(substr($mutated, $s, $e - $s)), $anchor, strlen($anchor)) === 0) {
            $fail('a row this pass deletes is still present after the write.');
        }
    }
}

// Every row that survives must be byte-identical to its original: a substring removal that clipped a neighbour, or
// an offset applied twice, would otherwise pass a count check.
$originalRows = [];
foreach ($allRows($xml) as [$s, $e]) {
    $originalRows[] = substr($xml, $s, $e - $s);
}
$survivorTexts = [];
foreach ($afterRows as [$s, $e]) {
    $survivorTexts[] = substr($mutated, $s, $e - $s);
}
$dropped = array_values(array_filter($originalRows, static function (string $row) use ($anchors, $rowText): bool {
    foreach ($anchors as $anchor) {
        if (strncmp($rowText($row), $anchor, strlen($anchor)) === 0) {
            return true;
        }
    }

    return false;
}));
$keptOriginal = array_values(array_diff($originalRows, $dropped));
if (count($keptOriginal) !== count($survivorTexts)) {
    $fail('the surviving-row multiset does not line up: '.count($keptOriginal).' before, '.count($survivorTexts).' after.');
}
foreach ($keptOriginal as $i => $row) {
    if ($row !== $survivorTexts[$i]) {
        $fail('surviving row '.($i + 1).' changed bytes — only two rows were meant to move. Its text reads: '
            .substr($rowText($row), 0, 90));
    }
}

printf("%s2 row(s) deleted, the lead sentence re-taken, %d rows proved byte-identical.\n",
    $apply ? '' : 'DRY RUN — ', count($survivorTexts));
if (! $apply) {
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass393-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    $fail('could not write the backup.');
}
$zip = new ZipArchive;
if ($zip->open($docx, ZipArchive::CREATE) !== true) {
    $fail('could not reopen the docx for writing.');
}
$zip->addFromString('word/document.xml', $mutated);
$zip->close();

$again = new ZipArchive;
if ($again->open($docx) !== true) {
    $fail('the written docx did not reopen.');
}
$read = (string) $again->getFromName('word/document.xml');
$again->close();

$bad = [];
if (count($allRows($read)) !== $beforeRowCount - 2) {
    $bad[] = 'the written file holds '.count($allRows($read)).' rows';
}
foreach ($anchors as $anchor) {
    foreach ($allRows($read) as [$s, $e]) {
        if (strncmp($rowText(substr($read, $s, $e - $s)), $anchor, strlen($anchor)) === 0) {
            $bad[] = 'the row ['.$anchor.'] is still listed';
        }
    }
}
if (substr_count($read, $leadNew) !== 1 || substr_count($read, $leadNeedle) !== 0) {
    $bad[] = 'the lead sentence did not read back as the re-taken count';
}
if ($bad !== []) {
    fwrite(STDERR, "REFUSED: read-back failed:\n  ".implode("\n  ", $bad)."\nRestored from {$backup}.\n");
    if (! copy($backup, $docx)) {
        fwrite(STDERR, "REFUSED: the restore failed — repair from {$backup} by hand.\n");
    }
    exit(1);
}
printf("read-back: two rows gone, the lead sentence re-taken, %d rows intact; backup %s\n",
    count($allRows($read)), basename($backup));
