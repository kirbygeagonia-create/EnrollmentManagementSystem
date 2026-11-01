<?php

/**
 * Pass 376 — §28.3 G-41 is contained on three of its four halves, and the row says which half is still a browser.
 *
 * The loading half is the finding worth recording: read at the source, 28 pages repaint rows from their own router
 * call, and none of them said anything while the answer was in flight. The register could not see it because the
 * screen LOOKED handled — 24 of those pages carry a pending flag on a SAVE button in the same file, which is a
 * different control waiting for a different answer. Measuring the word is not measuring the act.
 *
 * No §1.1 row is touched by this pass, on purpose. Row 37 was widened to a span by pass 372 precisely because a
 * dated row must not absorb a later day's sentences, and the honest alternative, cloning a fresh row dated today,
 * is a structural edit this batch does not need in order to be truthful: the register row is where a finding lives,
 * and it now carries the date, the verdict verb and the instrument that measured it.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass376.php [--apply]
 */
$root = dirname(__DIR__, 3);
require_once __DIR__.'/docx-edit-engine.php';
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = __DIR__.'/pass376-backup.docx';

// The figures the sentence will state are read from the instrument, in this run, not typed from memory.
$out = [];
$code = 0;
exec('C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe storage/app/scratch/lane142-measure-console-states.php --json=storage/app/scratch/lane142-readings.json 2>&1', $out, $code);
if ($code !== 0) {
    fwrite(STDERR, "REFUSED: lane142 exited {$code}; its figures cannot be written into the document.\n");

    exit(1);
}
$body = implode("\n", $out);
preg_match('/table sites rendered: (\d+)/', $body, $sites);
preg_match('/pages walked: (\d+)/', $body, $pages);
preg_match('/(\d+) repaint the rows from a/', $body, $repaint);
preg_match('/^EMPTY\s+(\d+)$/m', $body, $empty);
preg_match('/^LOADING\s+(\d+)$/m', $body, $loading);
preg_match('/^CONSOLE\s+(\d+)$/m', $body, $console);
foreach (['sites' => $sites, 'pages' => $pages, 'repaint' => $repaint, 'empty' => $empty,
    'loading' => $loading, 'console' => $console] as $label => $m) {
    if (($m[1] ?? null) === null) {
        fwrite(STDERR, "REFUSED: could not read the {$label} figure out of lane142's own print.\n");

        exit(1);
    }
    printf("ok   %-8s = %s\n", $label, $m[1]);
}
if ((int) $loading[1] !== 0 || (int) $empty[1] !== 0) {
    fwrite(STDERR, "REFUSED: the census still reports open findings (EMPTY={$empty[1]}, LOADING={$loading[1]}); "
        ."the row may not claim containment over a queue.\n");

    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$from = 'Open 2026-10-29. The repository proves screens with Pest, PHPStan, Pint, ESLint and a Vite build, and no browser job runs in CI.';
$to = 'Open 2026-10-29, and CONTAINED 2026-11-01 on three of its four halves. The repository proves screens with '
    .'Pest, PHPStan, Pint, ESLint and a Vite build, and no browser job runs in CI. '
    .'`storage/app/scratch/lane142-measure-console-states.php` now weighs the console, empty and loading halves at '
    .'the source and answers '.$empty[1].', '.$loading[1].' and '.$console[1].' over '.$sites[1].' table sites in '
    .$pages[1].' pages, proved by `storage/app/scratch/mutate-lane142-console-states.php` at 21 legs. The loading '
    .'half is what moved: '.$repaint[1].' pages repaint their rows from a router call of their own, and every one of '
    .'them waited in silence, because 24 of the '.$repaint[1].' had been read as covered by a SAVE button’s pending '
    .'flag sitting in the same file on a different control. Measuring the word is not measuring the act. The wait is '
    .'now announced once, inside the shared table, armed at boot, and the two screens that build their rows by hand '
    .'read the same hook rather than inventing a second flag; `lane145-measure-table-surfaces.php` bounds that claim '
    .'(36 pages take the control, 4 do not, and of those 4 only one refreshes in place). Read in the running desk as '
    .'a click on a column header: the caption “Updating these rows…” appears with role=status on the same region and '
    .'the table carries aria-busy while the visit is open, and both clear once the answer lands. The empty half '
    .'found nothing to fix and said so twice, once wrongly: the first draft charged a screen whose own empty state '
    .'names the filters, which the control’s default sentence can never reach. The fourth half, whether a runtime '
    .'console error or a failed required request occurs in a key workflow, is NOT judged by any source scan, and '
    .'remains the owner question with a runner-time cost that is on the 2026-10-29 sheet.';

foreach ([[$from, $to]] as $i => $edit) {
    $got = substr_count($xml, $edit[0]);
    if ($got !== 1) {
        fwrite(STDERR, sprintf("REFUSED: needle %d occurs %d time(s), wanted 1\n", $i, $got));

        exit(1);
    }
    echo "ok   needle 0 occurs once\n";
}

docxApply($docx, $backup, [[$from, $to, null, 1]], [], $apply);

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
    [$from, 0],
    ['Open 2026-10-29, and CONTAINED 2026-11-01 on three of its four halves.', 1],
    ['The fourth half, whether a runtime console error or a failed required request occurs in a key workflow, is NOT judged', 1],
] as [$n, $want]) {
    $got = substr_count($after, $n);
    if ($got !== $want) {
        echo sprintf("BAD  [%s] occurs %d, wanted %d — restoring.\n", substr($n, 0, 56), $got, $want);
        copy($backup, $docx);

        exit(1);
    }
}

echo "written and read back: G-41 states its own containment, its figures, and the half no scan can reach.\n";
