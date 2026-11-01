<?php

/**
 * Pass 372 — a dated §1.1 row had become a running record, and this batch's own checker proved it.
 *
 * Row 37 leads `2026-10-29 One sort mechanism, one wired queue`. Five days of sentences have been appended into it by
 * passes 359 through 370 — the fifteenth, sixteenth, seventeenth and eighteenth wired screens, all of which joined on
 * 2026-10-31 and 2026-11-01 — so the row's own date no longer covers what the row states. That is lane 60's and lane
 * 72's rule ("a dated row must not be re-aged to a newer figure") met from the other side: the figure was not re-typed,
 * the row was silently widened past its date by prose.
 *
 * The defect became visible because a new instrument had to learn the dated-row exemption. lane140 exempts a claim
 * carried by a row whose leading cell is a bare date, and its first run exempted row 37's `34 of the 36 pages` — right
 * answer, wrong reason: that row is no longer only 2026-10-29. The repair is the cell, not the prose: the row states
 * the span it actually covers, and the newest sentence names the day it describes rather than `the same day`, which a
 * range makes ambiguous.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass372.php [--apply]
 */
$root = dirname(__DIR__, 3);
require_once __DIR__.'/docx-edit-engine.php';
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = __DIR__.'/pass372-backup.docx';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

// The day the row now ends on, read from the school's clock rather than typed: the box runs ~19 h ahead of an outside
// host (lane 74) and the document's own dates are school-day dates.
$schoolToday = trim((string) shell_exec('C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe -r "require \'vendor/autoload.php\'; $a=require_once \'bootstrap/app.php\'; $k=$a->make(Illuminate\Contracts\Console\Kernel::class); $k->bootstrap(); echo App\Support\SchoolTime::today();"'));
if (! preg_match('/\A20\d\d-\d\d-\d\d\z/', $schoolToday)) {
    fwrite(STDERR, "REFUSED: the school's clock answered ['{$schoolToday}'], which is not a date, so the row's new "
        ."span cannot be written from it.\n");

    exit(1);
}
printf("ok   the school's today reads %s\n", $schoolToday);

$spanEnd = substr($schoolToday, 5);            // 11-01 out of 2026-11-01, the way the document spells a range
$edits = [
    ['2026-10-29 One sort mechanism, one wired queue',
        '2026-10-29 – '.$spanEnd.' One sort mechanism, one wired queue', null, 1],
    ['The grade-scale bands joined as the eighteenth the same day',
        'The grade-scale bands joined as the eighteenth on '.$schoolToday, null, 1],
];

foreach ($edits as $i => $edit) {
    $got = substr_count($xml, $edit[0]);
    if ($got !== 1) {
        fwrite(STDERR, sprintf("REFUSED: needle %d occurs %d time(s), wanted 1 [%s]\n", $i, $got, substr($edit[0], 0, 60)));

        exit(1);
    }
    echo sprintf("ok   needle %d occurs once\n", $i);
}

docxApply($docx, $backup, $edits, [], $apply);

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot reopen the docx.\n");

    exit(1);
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ([
    ['2026-10-29 One sort mechanism', 0],
    ['One sort mechanism, one wired queue', 1],
    ['The grade-scale bands joined as the eighteenth the same day', 0],
    ['joined as the eighteenth on '.$schoolToday, 1],
    // The row is still exempt from lane140 only because its cell still LEADS with a date — assert that shape.
    ['2026-10-29 – '.$spanEnd, 1],
] as [$n, $want]) {
    $got = substr_count($after, $n);
    if ($got !== $want) {
        echo sprintf("BAD  [%s] occurs %d, wanted %d — restoring.\n", substr($n, 0, 56), $got, $want);
        copy($backup, $docx);

        exit(1);
    }
}

echo "written and read back: the row's date now spans what the row states, and the newest sentence carries its own day.\n";
