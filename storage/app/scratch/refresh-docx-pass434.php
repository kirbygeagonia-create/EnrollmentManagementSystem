<?php

/**
 * Pass 434 — the two §28.5 load rows re-taken, and the batch's own §1.1 row.
 *
 * Both register rows state, in the present tense, that no ceiling exists. That stopped being true when the
 * rule landed, and it is the shape no gate in this repository sees: no figure moved, no status word moved, no
 * pointer left the page — only the mechanism sentence changed. The four needles below were each proved to be
 * contiguous in `word/document.xml` before this pass was written, so they travel as `text` edits rather than a
 * cell rewrite that would flatten the styled runs inside those cells.
 *
 * The new §1.1 row is written on the archive bytes FIRST and only then does `docxApply()` run — the engine
 * re-reads the file from disk, so a row written in memory would be discarded (the pass-252 lesson).
 *
 * Usage: php storage/app/scratch/refresh-docx-pass434.php [--apply]
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

$schoolToday = trim((string) shell_exec('"'.PHP_BINARY.'" -r "require \'vendor/autoload.php\'; $a=require_once \'bootstrap/app.php\'; $k=$a->make(Illuminate\Contracts\Console\Kernel::class); $k->bootstrap(); echo App\Support\SchoolTime::today();"'));
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $schoolToday) !== 1) {
    $fail("the school's civil day answered [{$schoolToday}], which is not a date — fix the reader, do not type a date.");
}
$date = $schoolToday;

$flatten = static function (string $fragment): string {
    // `#` as the delimiter, not `/`: an unescaped `</w:t>` closes a slash-delimited pattern on the `w`,
    // preg_match_all answers false, and $tm[1] is null — a fatal two frames above the cause.
    preg_match_all('#<w:t(?: [^>]*)?>(.*?)</w:t>#s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', (array) ($tm[1] ?? [])), ENT_QUOTES | ENT_XML1)));
};
$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
$rowText = static function (string $row) use ($flatten): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);

    return implode(' ', array_map($flatten, $cm[0]));
};
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

// ------------------------------------------------ the two register rows
$row13 = 'but no figure for a term ceiling exists anywhere in app/: grepping minUnits, maxUnits, maxLoad and unitLimit answers nothing, so the proposal loop cannot refuse an overloaded student and no screen warns one.';
$row13b = 'The number is the school’s, and inventing a default would let the application certify a rule the school never stated.';
$row14 = 'so the record can ANSWER how many units one instructor carries at any moment but nothing decides when that is too many.';
$row14b = 'The school has never stated the figure, so the guard is deliberately absent rather than guessed.';

$new13 = 'and a term ceiling is now a rule the build holds: `App\Support\LoadLimits` reads the settings row '
    .'`maxStudyLoadUnits` through `config(\'settings.*\')` and answers null for a blank, zero or unreadable value, '
    .'so the proposal save refuses above the figure naming the units, the ceiling and what the record already carries.';
$new13b = 'Both rows are seeded blank by the owner’s ruling of '.$date.' — settings rows plus enforcement, off until '
    .'the school sets a figure — which is why nothing is refused on a number nobody typed, and why inventing a '
    .'default would still be the application certifying a rule the school never stated.';
$new14 = 'and the total is counted and capped: `App\Support\LoadLimits::instructorUnits()` sums one instructor’s units '
    .'inside one term, and `BlockingController` refuses a schedule that would carry an instructor past the settings '
    .'row `maxTeachingLoadUnits`. The row being edited is excluded from that total, so an assignment already over an '
    .'unnumbered ceiling can still be corrected downward.';
$new14b = 'The figure is the school’s and its row is seeded blank by the ruling of '.$date.', so the guard is present '
    .'and silent rather than absent.';

foreach ([$row13, $row13b, $row14, $row14b] as $n) {
    if ($n === '') {
        $fail('an empty needle was declared — the source text above must be copied from the document, not retyped.');
    }
}

// ------------------------------------------------ the §1.1 row
$clauses = [
    'The two load ceilings the school has never numbered are rules the build holds, and they bind nothing until a '
        .'figure is typed. `App\Support\LoadLimits` reads the two `settings` rows through `config(\'settings.*\')`, '
        .'answers null for a blank, zero or unreadable value, and both desk acts ask it: the subject proposal refuses '
        .'above a student’s ceiling naming the units, the ceiling and what the record already carries, and a schedule '
        .'save refuses above an instructor’s per-term total, excluding the row being edited so an over-full assignment '
        .'can still be corrected downward.',
    'The owner ruled the shape on this date — settings rows plus enforcement, off until the school sets a figure — '
        .'and both rows are seeded blank, so no student or instructor is refused on a number nobody typed. Units are '
        .'counted as the standing service counts them, `lectureUnits` plus `labUnits` over distinct subjects, measured '
        .'in both files rather than assumed.',
    'What moved with the mechanism was prose, and it is the class no gate sees: four present-tense absence claims '
        .'were re-taken across the ADS deliverables ("no figure for a term ceiling exists anywhere in app/", "is not '
        .'currently computed or capped anywhere in app/", "no limit is defined", "nothing in app/ holds a '
        .'faculty-load predicate"). No figure moved, no status word moved and no pointer left its page; only the '
        .'mechanism sentence changed, which is why the register rows themselves were re-taken in the same batch as '
        .'the code.',
    'The decision sheet’s items 10 and 11 now carry ANSWERED as to the mechanism and still wait on the number, so '
        .'the header tally moved with them and `lane69-open-inventory.php` reports the two in agreement. The ADS '
        .'matrix’s P13 row leads partly built where it had led not built, and `lane200-check-report-buckets.php` '
        .'answers both id lists in agreement with the census that reads the matrix’s own lead words — the not-built '
        .'bucket now holds nothing.',
    'The pointer cascade this batch aged was moved by reading, not arithmetic: the shift tool resolved fifty-nine '
        .'tokens into the evaluation controller and thirty-six into the blocking controller and refused six items, '
        .'and each refusal was then read in both versions of its file. Two of them were wrong by meaning before the '
        .'batch touched the file — a window that opened inside the method before the one its sentence named, and a '
        .'roster filter that pointed at the term clause while the sentence said the year level — and six of the '
        .'twenty-three markdown pairs were one row short of the construct their own sentences name, which no bounds '
        .'check can see.',
    'Every case and falsifier in this record prints its own total, so none is written here; the suite figures are '
        .'read from the run that carries them.',
];
$body = implode(' ', $clauses);

if (preg_match('/[A-Za-z0-9_]+\.(php|jsx|js|blade\.php|json|mjs|md):\d/', $body) === 1) {
    $fail('the new row carries a File.php:NN citation this pass cannot verify — spell the pointer in words.');
}

$xml = $readPart($docx, 'word/document.xml');
foreach ([$row13, $row13b, $row14, $row14b] as $n) {
    if (substr_count($xml, $escape($n)) !== 1) {
        $fail('a register needle does not occur exactly once in the archive (escaped form): ['.mb_substr($n, 0, 70).'…]');
    }
}
if (substr_count($xml, $escape($body)) !== 0) {
    $fail('this pass has already run: its own row text is in the document.');
}

$rows = $rowsForTitle($xml, 'The permission list and the role list are wired');
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
    preg_match_all('/<w:p(?:\s[^>]*)?>.*?<\/w:p>/s', $cell, $pm);
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
usort($cellJobs, static fn (array $a, array $b): int => $b[1] <=> $a[1]);
foreach ($cellJobs as [$j, $at, $len, $src]) {
    $cell = $src;
    preg_match_all('/<w:p(?:\s[^>]*)?>.*?<\/w:p>/s', $cell, $pm);
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
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $clone, $cm3);
foreach ($cm3[0] as $j => $cell) {
    if ($flatten($cell) !== $values[$j]) {
        $fail('cell '.($j + 1)." of the clone does not read back as the text supplied — it holds [\n"
            .mb_substr($flatten($cell), 0, 160).'…]');
    }
}
$xml2 = substr_replace($xml, $clone, $end, 0);

printf("stage 1 built: four §28.5 rewrites and one §1.1 row (%d bytes of body). %s\n",
    strlen($body), $apply ? 'applying' : 'DRY RUN');

if (! $apply) {
    if (substr_count($xml2, $escape($body)) !== 1) {
        $fail('the assembled bytes do not hold the new row exactly once.');
    }
    echo "DRY RUN — nothing written.\n";
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass434-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    $fail('the backup copy could not be written.');
}
$zip = new ZipArchive;
if ($zip->open($docx, ZipArchive::CREATE) !== true) {
    $fail('the docx did not reopen for writing the row.');
}
$zip->addFromString('word/document.xml', $xml2);
$zip->close();

// The engine re-reads the file from disk, so the row is on disk before its edits run.
$textEdits = [
    [$row13, $new13],
    [$row13b, $new13b],
    [$row14, $new14],
    [$row14b, $new14b],
];
docxApply($docx, $backup, $textEdits, [], true);

// ------------------------------------------------- read back
$read = $readPart($docx, 'word/document.xml');
if (count($rowsForTitle($read, 'The two load ceilings the school has never numbered')) !== 1) {
    unlink($backup);
    copy($backup, $docx);
    $fail('the new §1.1 row did not come back as exactly one row — the backup was restored.');
}
foreach ([$row13, $row13b, $row14, $row14b] as $retired) {
    if (substr_count($read, $escape($retired)) !== 0) {
        unlink($backup);
        copy($backup, $docx);
        $fail('a retired register sentence is still in the document — the backup was restored: ['
            .mb_substr($retired, 0, 60).'…].');
    }
}
foreach ([$new13, $new13b, $new14, $new14b, $body] as $fresh) {
    if (substr_count($read, $escape($fresh)) !== 1) {
        unlink($backup);
        copy($backup, $docx);
        $fail('a new phrase does not read back exactly once — the backup was restored: ['
            .mb_substr($fresh, 0, 60).'…] at '.substr_count($read, $escape($fresh)).'.');
    }
}
printf("WROTE one §1.1 row dated %s and four §28.5 rewrites; backup at %s\n", $date, basename($backup));
