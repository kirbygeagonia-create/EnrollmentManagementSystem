<?php

/**
 * Pass 437 — the import screen's record: the catalogue figures it moved, and the §1.1 row.
 *
 * Three sentences state the live RBAC catalogue, and adding `block.import` made each of them false as written.
 * The counts were read off `ems` after the reseed (97 permissions, 311 grants, 97 distinct granted) rather than
 * typed from arithmetic, and the reseed itself ran against a dump taken minutes earlier.
 *
 * The row is written on the archive bytes FIRST and only then does `docxApply()` run — the engine re-reads the
 * file from disk, so a row written in memory would be discarded (the pass-252 lesson).
 *
 * Usage: php storage/app/scratch/refresh-docx-pass437.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$apply = in_array('--apply', array_slice($GLOBALS['argv'], 1), true);
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$mysql = 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
if (! is_file($mysql)) {
    $fail('no mysql binary to ask the live server, so the catalogue figures would be typed from memory.');
}

// The three numbers come from the database they describe.
$out = trim((string) shell_exec(escapeshellarg($mysql).' -u root ems -N -e '.escapeshellarg(
    'select (select count(*) from permissions), (select count(*) from role_permissions), (select count(distinct permissionId) from role_permissions)'
).' 2>&1'));
if (preg_match('/^(\d+)\s+(\d+)\s+(\d+)$/', $out, $m) !== 1) {
    $fail("live ems answered [{$out}], which is not three counts — read the server before writing a figure.");
}
[$permissions, $grants, $distinctGranted] = [(int) $m[1], (int) $m[2], (int) $m[3]];
if ($distinctGranted !== $permissions) {
    $fail("the catalogue holds {$permissions} permissions and only {$distinctGranted} are granted to any role; an unseated right is a finding, not a number to publish.");
}

$schoolToday = trim((string) shell_exec('"'.PHP_BINARY.'" -r "require \'vendor/autoload.php\'; $a=require_once \'bootstrap/app.php\'; $k=$a->make(Illuminate\Contracts\Console\Kernel::class); $k->bootstrap(); echo App\Support\SchoolTime::today();"'));
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $schoolToday) !== 1) {
    $fail("the school's civil day answered [{$schoolToday}], which is not a date.");
}
$date = $schoolToday;

$flatten = static function (string $fragment): string {
    preg_match_all('#<w:t(?: [^>]*)?>(.*?)</w:t>#s', $fragment, $tm);

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

$countEdits = [
    [
        'from' => 'The live catalogue is 96 permissions and 308 grants after the reseed, with no orphan grant left behind.',
        'to' => "The live catalogue is {$permissions} permissions and {$grants} grants after the reseed, with no orphan grant left behind.",
        'expect' => 1,
    ],
    [
        'from' => '96 permissions across 15 modules',
        'to' => "{$permissions} permissions across 15 modules",
        'expect' => 2,
    ],
    [
        'from' => 'Every one of the 96 permissions',
        'to' => "Every one of the {$permissions} permissions",
        'expect' => 1,
    ],
];

$clauses = [
    'The timetable import has a desk screen now, and the rule it applies is the owner’s: fill the gaps, refuse the conflicts. '
        .'`TimetableImportController` keeps the uploaded sheet on the private disk — the same root the served-folder ruling left unserved — '
        .'calls `ems:import:timetable` for the verdicts, and shows them. Publish sends back the key the preview minted, and that act deletes '
        .'the sheet, so a second click is answered by no sheet at all rather than by a second week.',
    '`block.import` is the right, seated with the blocking desk and the office heads; the ability is `blocking.importSheet`, named apart on '
        .'purpose, because Spatie registers Gate::before and an ability wearing a permission’s name would be granted before any policy body ran.',
    'One definition of the rules: the screen reads what the command decided. The payload carries named fields rather than the console table’s '
        .'cell order, and a case asserts the two surfaces agree on rows read, rows refused, schedules, meeting lines and the skipped set — '
        .'a page rebuilt from positions breaks silently the day a column is added.',
    'What is deliberately not built, and said here rather than left to be inferred: no XLSX reader (the ruling named a spreadsheet or a text '
        .'sheet, and a spreadsheet saves as CSV), no reading of a photographed timetable, and no per-row college scope, because the single-row '
        .'schedule path applies none either and a bulk act scoped apart from its row-by-row twin is one rule with two answers.',
    'The reseed moved the live catalogue, so the sentences that stated it were re-taken from the server in this pass rather than by arithmetic, '
        .'and the grant table answers every permission seated to at least one role. Every case and falsifier in this record prints its own total, '
        .'so none is written here.',
];
$body = implode(' ', $clauses);

if (preg_match('/[A-Za-z0-9_]+\.(php|jsx|js|blade\.php|json|mjs|md):\d/', $body) === 1) {
    $fail('the new row carries a File.php:NN citation this pass cannot verify — spell the pointer in words.');
}

$xml = $readPart($docx, 'word/document.xml');
if (substr_count($xml, $escape($body)) !== 0) {
    $fail('this pass has already run: its own row text is in the document.');
}
foreach ($countEdits as $i => $e) {
    $n = substr_count($xml, $escape($e['from']));
    if ($n !== $e['expect']) {
        $fail("count edit {$i} matches {$n} time(s), expected {$e['expect']}: [{$e['from']}]");
    }
    if (substr_count($xml, $escape($e['to'])) !== 0) {
        $fail("count edit {$i} already applied — the replacement occurs in the archive.");
    }
}

$rows = $rowsForTitle($xml, 'The two load ceilings the school has never numbered');
if (count($rows) !== 1) {
    $fail('the newest revision row matched '.count($rows).' row(s) by title, expected exactly 1 — re-read §1.1 before cloning.');
}
[$start, $end] = $rows[0];
$template = substr($xml, $start, $end - $start);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template, $cm);
if (count($cm[0]) !== 2) {
    $fail('the revision row this pass clones holds '.count($cm[0]).' cells, expected 2.');
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

printf("stage 1 built: one §1.1 row and %d count sentence(s) (server says %d permissions, %d grants). %s\n",
    count($countEdits), $permissions, $grants, $apply ? 'applying' : 'DRY RUN');

if (! $apply) {
    if (substr_count($xml2, $escape($body)) !== 1) {
        $fail('the assembled bytes do not hold the new row exactly once.');
    }
    echo "DRY RUN — nothing written.\n";
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass437-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    $fail('the backup copy could not be written.');
}
$zip = new ZipArchive;
if ($zip->open($docx, ZipArchive::CREATE) !== true) {
    $fail('the docx did not reopen for writing the row.');
}
$zip->addFromString('word/document.xml', $xml2);
$zip->close();

docxApply($docx, $backup, array_map(
    static fn (array $e): array => [$e['from'], $e['to'], null, $e['expect']],
    $countEdits
), [], true);

// ------------------------------------------------- read back
$read = $readPart($docx, 'word/document.xml');
if (count($rowsForTitle($read, 'The timetable import has a desk screen now')) !== 1) {
    unlink($backup);
    copy($backup, $docx);
    $fail('the new row did not come back as exactly one row — the backup was restored.');
}
foreach ($countEdits as $e) {
    if (substr_count($read, $escape($e['from'])) !== 0) {
        unlink($backup);
        copy($backup, $docx);
        $fail('a retired figure is still in the document: ['.$e['from'].'] — the backup was restored.');
    }
}
if (substr_count($read, $escape('96 permissions')) !== 0 || substr_count($read, $escape('308 grants')) !== 0) {
    unlink($backup);
    copy($backup, $docx);
    $fail('an older catalogue figure survives somewhere this pass did not reach — the backup was restored.');
}
printf("WROTE one §1.1 row dated %s and %d count sentence(s); backup at %s\n",
    $date, count($countEdits), basename($backup));
