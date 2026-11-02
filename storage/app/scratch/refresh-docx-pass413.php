<?php

/**
 * Pass 413 — §28.3 G-40's three living counts, moved by the nineteenth wired screen.
 *
 * The workflow monitor's list now sorts through `App\Support\ListSort`, so the register's tally of wired tables and
 * its tally of owed tables both moved. `lane140-measure-wired-screens.php --check-docx` named all three phrases and
 * refused the run; this pass writes exactly those three and re-runs the instrument as its own read-back, so the
 * claim "the register now agrees with the tree" is the checker's verdict rather than this pass's arithmetic.
 *
 * The dated §1.1 rows are deliberately untouched: `lane140` reports them as that day's record, and re-typing a
 * figure inside a dated row would state a number that was not true on the row's own day.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass413.php [--apply]
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

// The reader that finds the part: `docxApply` opens the archive itself and exports no reader, and
// `ZipArchive::open()` answers a truthy error CODE for a file it cannot open, so the guard is `!== true`.
$readPart = static function (string $docx, string $part) use ($fail): string {
    $zip = new ZipArchive;
    if ($zip->open($docx) !== true) {
        $fail("could not open {$part} inside the docx.");
    }
    $xml = (string) $zip->getFromName($part);
    $zip->close();
    if ($xml === '') {
        $fail("{$part} read back empty.");
    }

    return $xml;
};

$pairs = [
    ['the eighteen wired pages call', 'the nineteen wired pages call'],
    ['eighteen of the thirty-six tables that render the shared component', 'nineteen of the thirty-six tables that render the shared component'],
    ['18 of the 36 pages that use the shared table still offer no order', '17 of the 36 pages that use the shared table still offer no order'],
];

$textEdits = [];
foreach ($pairs as [$from, $to]) {
    $hits = substr_count($readPart($docx, 'word/document.xml'), $from);
    if ($hits !== 1) {
        $fail("pre-flight: [{$from}] occurred {$hits} time(s), wanted exactly 1.");
    }
    if (substr_count($readPart($docx, 'word/document.xml'), $to) !== 0) {
        $fail("pre-flight: the replacement [".mb_substr($to, 0, 40).'…] is already in the document — this pass has run.');
    }
    $textEdits[] = [$from, $to];
}

docxApply($docx, $root.'/storage/app/scratch/pass413-backup.docx', $textEdits, [], $apply);

if (! $apply) {
    exit(0);
}

$xml = $readPart($docx, 'word/document.xml');
foreach ($pairs as $i => [$from, $to]) {
    if (substr_count($xml, $from) !== 0 || substr_count($xml, $to) !== 1) {
        $fail('read-back: edit '.($i + 1)." did not land as claimed (old {$from} / new {$to}).");
    }
}

// The instrument that owns the claim, re-run as the pass's own proof.
$cmd = '"'.PHP_BINARY.'" '.escapeshellarg($root.'/storage/app/scratch/dump-docx-flat.php').' 2>&1';
exec($cmd, $dumped);
$check = [];
exec('"'.PHP_BINARY.'" '.escapeshellarg($root.'/storage/app/scratch/lane140-measure-wired-screens.php')
    .' --check-docx --dump='.escapeshellarg($root.'/storage/app/scratch/docx-flat.txt').' 2>&1', $check, $code);
$out = preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $check));
if ($code !== 0 || ! str_contains($out, 'AGREES')) {
    $fail("lane140 still disagrees after the write (exit {$code}):\n".mb_substr($out, -600));
}

$agree = implode('; ', preg_grep('/AGREES|judged|STALE/', $check) ?: []);
printf("written; three counts re-taken and lane140 --check-docx reads AGREES.\n  checker said: %s\n", trim((string) preg_replace('/\s+/', ' ', $agree)));
