<?php

/**
 * Pass 411 — §28.3 G-46's last sentence still told the reader the blocking card was owed.
 *
 * The row led `Closed 2026-10-30` for eight of its nine cards, and its closing sentence named the ninth as a school
 * question. The owner ruled that question on 2026-11-02 (sheet item 9: the card counts what its own page lists), and
 * the ruling is now built — `DeskQueues::blocking()` is the base `BlockingController::index()` builds its rows from.
 * Leaving the sentence standing would present an answered question as owed work, which is the defect `lane61`
 * catches from the status-word side and this pass catches from the prose side.
 *
 * The row's verdict cell is not re-dated: it was true on 2026-10-30 for the eight, and the new sentence carries the
 * day the ninth moved, so the row reads as a record rather than as a figure re-typed to look current.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass411.php [--apply]
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

$from = 'The remaining card is deliberately not unified: its badge counts records waiting at an office while the '
    .'page lists timetable blocks, and which subject that card should name is on the 2026-10-29 sheet.';

$to = 'The ninth card joined on 2026-11-02, when the owner ruled that sheet item 9 answered: it counts what its own '
    .'page lists. `DeskQueues::blocking()` is now the base `BlockingController::index()` builds its rows from, so the '
    .'number a coordinator reads before the click is the number of rows the desk acts on afterwards, and the card '
    .'obeys the rule the other eight already do. The enrollments waiting at that box were not deleted — that figure '
    .'is what the workflow bell addresses — and `mutate-g46-dashboard-queues.php` plants it back on the card, which '
    .'is the leg that refuses a future reader unifying the wrong subject again.';

// The two-element shape: the needle is unique document-wide (asserted below before anything is opened), so no
// anchor is needed. The four-element form reads slot 3 as an ANOTHER string that must own the needle, and
// anchor == needle resolves to nothing.
$textEdits = [[$from, $to]];

// `docxApply` opens the archive itself and there is no reader exported, so the pre-flight reads the part here —
// guarding `ZipArchive::open` with `!== true`, because it answers a truthy error CODE for a file it cannot open.
$readPart = static function (string $docx, string $part) use ($fail): string {
    $zip = new ZipArchive;
    if ($zip->open($docx) !== true) {
        $fail("could not open {$part} inside {$docx}.");
    }
    $xml = (string) $zip->getFromName($part);
    $zip->close();
    if ($xml === '') {
        $fail("{$part} read back empty.");
    }

    return $xml;
};

foreach ([[$from, 1], [$to, 0]] as [$needle, $want]) {
    $hits = substr_count($readPart($docx, 'word/document.xml'), $needle);
    if ($hits !== $want) {
        $fail("pre-flight: the needle occurring {$hits} time(s), wanted {$want}, is ".mb_substr($needle, 0, 60).'…');
    }
}

docxApply($docx, $root.'/storage/app/scratch/pass411-backup.docx', $textEdits, [], $apply);

if (! $apply) {
    exit(0);
}

$xml = $readPart($docx, 'word/document.xml');
if (substr_count($xml, $from) !== 0 || substr_count($xml, $to) !== 1) {
    $fail('the read-back did not find the retired sentence gone and the new one once.');
}

printf("read-back: the retired sentence is gone and the closure sentence sits once.\n");
