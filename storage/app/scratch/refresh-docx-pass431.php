<?php

/**
 * Pass 431 — the §1.1 row for the last two wireable desk screens.
 *
 * Cloned from the newest revision row by TITLE rather than by date-plus-title (that row's own cell was widened the
 * day it was written, so an anchor joined to a date refuses a correct write the moment the school's day moves), and
 * written on the archive bytes because `docxApply()` re-reads the file from disk and would discard an in-memory row.
 *
 * No figure that a harness prints is quoted here: the case class and its falsifier print their own totals, and lane
 * 58's rule for a revision row applies to a tool's census.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass431.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);

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
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
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

$clauses = [
    'The permission list and the role list are wired, and they were the last two screens the shape census left '
    .'orderable. Each paints only stored columns, so each offers exactly what it paints — the name, the module and '
    .'the guard on the permission list, the name and the description on the role list — and the role screen\'s '
    .'permission count stays out of the offer set because the browser computes it from a loaded relation, which is '
    .'§28.3\'s filter defect reached from the sort side. Both open A to Z rather than newest-first, because a '
    .'catalogue is read top to bottom while a queue is read from the latest act, and the row\'s own key is the '
    .'tie-break so two equally-named rows cannot shuffle between pages.',
    'The owed queue is now stated by shape rather than by count alone: the planner prints, for every page it leaves '
    .'owed, whether its action paginates or hands the browser a bounded set, and today all six remaining pages are '
    .'bounded sets. A header over one of those would order the rows already on screen and present that as the whole '
    .'list, so the honest register sentence says which six and why rather than carrying a smaller number.',
    'One residual is recorded rather than smoothed. The gap row enumerates the wired screens and its list ends at the '
    .'academic term list; the two names above belong in that sentence too, but its entries sit in styled runs, so the '
    .'phrase that reads continuously in the dump occurs zero times in the archive and a text edit cannot reach it. '
    .'Rewriting that cell through the engine would flatten its styled runs, so the enumeration is left for a pass '
    .'written for the cell kind, and this row carries the two screens in the meantime. A membership list that stops '
    .'growing is the same defect as a figure that stops growing, which is why it is named here instead of being '
    .'silently dropped.',
    'Every case file and every falsifier in this record prints its own total, so none is written here; the figures for '
    .'the delivered run are read from the run that carries them.',
];
$body = implode(' ', $clauses);

if (preg_match('/[A-Za-z0-9_]+\.(php|jsx|js|blade\.php|json|mjs|md):\d/', $body) === 1) {
    $fail('the new row carries a File.php:NN citation this pass cannot verify — spell the pointer in words.');
}

$xml = $readPart($docx, 'word/document.xml');
if (substr_count($xml, $escape($body)) !== 0) {
    $fail('this pass has already run: its own row text is in the document.');
}

$rows = $rowsForTitle($xml, 'The answered copy of the ADS guide form was re-delivered');
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
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $clone, $cm3);
foreach ($cm3[0] as $j => $cell) {
    if ($flatten($cell) !== $values[$j]) {
        $fail('cell '.($j + 1)." of the clone does not read back as the text supplied — it holds [\n"
            .mb_substr($flatten($cell), 0, 160).'…]');
    }
}
$xml2 = substr_replace($xml, $clone, $end, 0);

printf("stage 1 built: one §1.1 row (%d bytes of body) dated %s. %s\n", strlen($body), $date, $apply ? 'applying' : 'DRY RUN');

if (! $apply) {
    if (substr_count($xml2, $escape($body)) !== 1) {
        $fail('the assembled bytes do not hold the new row exactly once.');
    }
    echo "DRY RUN — nothing written.\n";
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass431-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    $fail('the backup copy could not be written.');
}
$zip = new ZipArchive;
if ($zip->open($docx, ZipArchive::CREATE) !== true) {
    $fail('the docx did not reopen for writing the row.');
}
$zip->addFromString('word/document.xml', $xml2);
$zip->close();

$read = $readPart($docx, 'word/document.xml');
if (count($rowsForTitle($read, 'The permission list and the role list are wired')) !== 1) {
    copy($backup, $docx);
    $fail('the new row did not come back as exactly one row — the backup was restored.');
}
if (substr_count($read, $escape($body)) !== 1) {
    copy($backup, $docx);
    $fail('the row text is not present exactly once after the write — the backup was restored.');
}
printf("WROTE one §1.1 row dated %s; backup at %s\n", $date, basename($backup));
