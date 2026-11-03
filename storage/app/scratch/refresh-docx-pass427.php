<?php

/**
 * Pass 427 — the §1.1 row for the answered guide form, the use-case overview and the MySQL-only office residue.
 *
 * One row, no count edits: this batch moved no wired/owed figure, no register row and no schema census, and the
 * document holds no claim about the figure set (measured: the word `figure` appears 58 times in the flat dump and
 * never beside use-case, diagram, PNG or actor). So the pass writes the record and refuses anything else.
 *
 * The row is written on the archive bytes FIRST and only then does `docxApply()` run — the engine re-reads the file
 * from disk, so a row written in memory would be discarded (the pass-252 lesson).
 *
 * Usage: php storage/app/scratch/refresh-docx-pass427.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$apply = false;
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
    } else {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

$docx = $root.'/Documentation/EMS Complete Documentation.docx';

// The day comes from the school's own civil clock, read through a child so this file needs no bootstrap of its own.
$schoolToday = trim((string) shell_exec('"'.PHP_BINARY.'" -r "require \'vendor/autoload.php\'; $a=require_once \'bootstrap/app.php\'; $k=$a->make(Illuminate\Contracts\Console\Kernel::class); $k->bootstrap(); echo App\Support\SchoolTime::today();"'));
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $schoolToday) !== 1) {
    $fail("the school's civil day answered [{$schoolToday}], which is not a date — fix the reader, do not type a date.");
}
$date = $schoolToday;

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};
$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
$rowText = static function (string $row) use ($flatten): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);

    return implode(' ', array_map($flatten, $cm[0]));
};
// Anchored by TITLE, not date-plus-title: the newest row carries the previous school day.
$rowsForTitle = static function (string $xml, string $title) use ($rowText): array {
    $hits = [];
    $at = 0;
    while (($start = strpos($xml, '<w:tr', $at)) !== false) {
        $end = strpos($xml, '</w:tr>', $start);
        if ($end === false) {
            break;
        }
        $end += 7;
        if (str_contains($rowText(substr($xml, $start, $end - $start)), $title)) {
            $hits[] = [$start, $end];
        }
        $at = $end;
    }

    return $hits;
};
$readPart = static function (string $docx, string $part) use ($fail): string {
    $zip = new ZipArchive;
    if ($zip->open($docx) !== true) {
        $fail("could not open {$part} inside the docx.");
    }
    $body = $zip->getFromName($part);
    $zip->close();
    if (! is_string($body) || $body === '') {
        $fail("{$part} read empty.");
    }

    return $body;
};

$clauses = [
    'The answered copy of the ADS guide form was re-delivered, and the defect the owner saw was real: the box a person reads was empty on the paper even though every count in the build agreed. Word accepts a paragraph written between a cell\'s close and its row\'s close, silently moves it out of the box, and reports nothing; the writer now places each analysis answer immediately before the `</w:tc>` that closes the cell it is filling, refuses any other insert point by offset, and validates every one of its insertion points before a single byte is written. `storage/app/scratch/mutate-lane203-guide-form.php` plants the retired offset and requires the refusal naming it, and the read-back `lane207-verify-guide-copy.php` re-locates each problem\'s own window in the built document and asks whether that problem\'s answer sits inside it. Its positive control is the school\'s blank form, which the reader refuses with 24 defects, so a reader that could clear an empty copy cannot exist.',
    'Part IV\'s concept rows were re-taken against the delivered cases rather than softened: five rows that said only "Explained" now carry the demonstration or implementation mark, and each names the test class that carries it. Because two documents answering "does this concept apply" is the defect this register has refused all year, the marks were written by `lane231-flip-concept-verdicts.php`, which validates every needle and refuses a replacement identical to it, and the answers file\'s artefact claims were weighed by `lane235-check-guide-claims.php`: file, class and method claims resolve against the tree, outside-tree paths are named and printed rather than silently exempted, and an empty population refuses the run.',
    'The long answers were cut to a direct sentence first, then measured: the guide\'s own reader prints the longest answer and the mean, and the writer now breaks an over-long paragraph at sentence boundaries in the cell rather than letting the paper carry a wall of text. The chunker refuses a split that loses more than a tenth of the words, because a summariser that silently drops a clause is a second definition of the answer.',
    'The use-case figures gained a readable sheet and lost a false claim about it. `use-case-roles-at-desks` draws one band per desk and one plate per role, 26 lines where the complete sheet draws 121 associations, with the roles that work at more desks on the right and the fewer on the left as the owner asked. It was first delivered as crossing-free, and it was not: the edge loop was written as a destructuring `for` over the keys of a Map, a string is iterable, so every pair bound its first character, every lookup missed, and mermaid drew 26 lines between two invented nodes named `undefined` and `BANDundefined` — a picture of 30 disconnected boxes that still produced a size, a manifest entry and an index link. The reader that saw it asks what the drawing tool believes it was handed: how many columns the drawn nodes occupy. The generator now refuses before drawing any pair that names no plate or no band, and that refusal fired again on the fix itself, catching an actor-id map that had been rewritten for one side only. Measured after both repairs: 26 edges and 10 crossing pairs on the readable sheet. The crossings that remain are the model\'s, not the tool\'s — the Student works at eight of the twelve desks, so no order of the busy roles separates their lines — and the guide now states that in its own section on reading the figures, beside the census `lane234-count-edge-crossings.mjs` prints for all 35 sheets.',
    'CI\'s MySQL job went red where this box was green, on the office directory, and the cause was a factory hook rather than a query. `StaffusersFactory` ensured its office existed before inserting an account, which is right for a foreign key and wrong for a table a case counts: on a schema whose auto-increment had already been spent, the hook added a fourth office row to a population the fixture had written three, while SQLite\'s rolled-back transaction rewinds the counter and never shows the extra. The hook now leaves a null office alone — an account bounded by nothing is a decision this file records, not an oversight — and the directory\'s own fixture writes its accounts with no office, so three offices are three offices on either driver. That is the spent-counter lesson arrived at from a seeder-shaped hook rather than a literal id.',
    'Every case file and every falsifier in this record prints its own total, so none is written here; the suite figures are read from the run that carries them.',
];
$body = implode(' ', $clauses);

// No citation-shaped token may be invented by this row's own prose.
if (preg_match('/[A-Za-z0-9_]+\.(php|jsx|js|blade\.php|json|mjs|md):\d/', $body) === 1) {
    $fail('the new row carries a File.php:NN citation this pass cannot verify — spell the pointer in words.');
}

$xml = $readPart($docx, 'word/document.xml');
if (substr_count($xml, $escape($body)) !== 0) {
    $fail('this pass has already run: its own row text is in the document.');
}

$rows = $rowsForTitle($xml, 'The twenty-seventh and twenty-eighth list tables sort');
if (count($rows) !== 1) {
    $fail('the newest revision row matched '.count($rows).' row(s) by title, expected exactly 1 — re-read §1.1 before cloning.');
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
$cellJobs = [];
foreach ($cm2[0] as $j => $x) {
    $cellJobs[] = [$j, (int) $x[1], strlen($x[0]), $x[0]];
}
// Descending offsets: one offset cannot carry two edits, and a cell rewritten earlier would move the next.
usort($cellJobs, static fn (array $a, array $b): int => $b[1] <=> $a[1]);
foreach ($cellJobs as [$j, $at, $len, $src]) {
    $cell = $src;
    preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
    foreach (array_slice($pm[0], 1) as $extra) {
        $cell = str_replace($extra, '', $cell);
    }
    preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $cellXml = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
    $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
        .$escape($values[$j]).'</w:t></w:r></w:p>', $cellXml);
    if (substr_count($new, '<w:t xml:space="preserve">') !== 1) {
        $fail("the replacement for cell {$j} holds more than one text run — read it before cloning.");
    }
    $clone = substr_replace($clone, $new, $at, $len);
}
if (substr_count($clone, '<w:tc>') !== 2) {
    $fail('the clone lost or gained a cell.');
}
// A cloned cell inherits EVERY run of its template: require the flattened clone to equal what was supplied.
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $clone, $cm3);
foreach ($cm3[0] as $j => $cell) {
    if ($flatten($cell) !== $values[$j]) {
        $fail('cell '.($j + 1)." of the clone does not read back as the text supplied — it holds [\n".
            mb_substr($flatten($cell), 0, 160).'…]');
    }
}
$xml2 = substr_replace($xml, $clone, $end, 0);

printf("stage 1 built: one §1.1 row (%d bytes of body). %s\n", strlen($body), $apply ? 'applying' : 'DRY RUN');

if (! $apply) {
    // The dry run must prove the row would land uniquely: the body occurs zero times today and the date cell is a row.
    if (substr_count($xml2, $escape($body)) !== 1) {
        $fail('the assembled bytes do not hold the new row exactly once.');
    }
    echo "DRY RUN — nothing written.\n";
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass427-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    $fail('the backup copy could not be written.');
}
$zip = new ZipArchive;
if ($zip->open($docx, ZipArchive::CREATE) !== true) {
    $fail('the docx did not reopen for writing the row.');
}
$zip->addFromString('word/document.xml', $xml2);
$zip->close();

// ------------------------------------------------- read back
$read = $readPart($docx, 'word/document.xml');
$found = $rowsForTitle($read, 'The answered copy of the ADS guide form was re-delivered');
if (count($found) !== 1) {
    unlink($backup);
    copy($backup, $docx);
    $fail('the new row did not come back as exactly one row — the backup was restored.');
}
if (substr_count($read, $escape($body)) !== 1) {
    unlink($backup);
    copy($backup, $docx);
    $fail('the row text is not present exactly once after the write — the backup was restored.');
}
printf("WROTE one §1.1 row dated %s; backup at %s\n", $date, basename($backup));
