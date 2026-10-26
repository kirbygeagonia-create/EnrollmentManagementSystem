<?php

/**
 * Docx pass 248: §1.2 says which clock its own dates come from, and §1.1 records the pass.
 *
 * The owner ruled on 2026-10-26 that the ~19 h skew between this development box and real UTC is recorded rather
 * than corrected, so the convention belongs in §1.2 — the section that already tells a reader what a citation means.
 * §1.2 currently says citations point at the repository "as of this revision date"; this pass says where that date is
 * read from, that the gap is measured and not assumed, and that the application itself does not depend on the
 * machine's calendar because instants are UTC and the school's day is declared in config/school.php.
 *
 * Both edits use the engine's DOCUMENTED slot shape: [from, to, anchor, expect]. Slot 3 is the anchor and slot 4 is
 * the copy count, and pass 247 put an integer in slot 3 believing it was the count — which the engine resolved to one
 * target and reported as "1 target resolved uniquely". A silently half-applied pass on a sentence the document holds
 * twice is exactly what the new guard in docx-edit-engine.php now refuses, proved by
 * storage/app/scratch/lane74-probe-engine-slot3.php (case 3 refused, case 4 resolves both).
 *
 *   php storage/app/scratch/refresh-docx-pass248.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass248.php --apply
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/backup-docx-pre-pass248.docx';
$apply = in_array('--apply', $argv, true);
require_once $root.'/storage/app/scratch/docx-edit-engine.php';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: the document cannot be opened as an archive.\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$anchor12 = 'Citations point at the repository as of this revision date.';
$anchor11 = 'would state a count that was never true of either build.';

// Refuse to write anything unless both anchors are exactly where this pass believes they are.
foreach (['§1.2' => $anchor12, '§1.1' => $anchor11] as $label => $needle) {
    $n = substr_count($xml, $needle);
    if ($n !== 1) {
        fwrite(STDERR, sprintf("REFUSED: the %s anchor occurs %d time(s), expected exactly 1 — [%s]\n",
            $label, $n, substr($needle, 0, 48)));
        exit(1);
    }
    printf("ok   %s anchor unique: %s\n", $label, substr($needle, 0, 56));
}

// The sentence that already carries the claim being extended must not exist yet, or this pass would double it.
foreach ([
    'the clock sentence' => 'That date comes from the development machine',
    'the pass record' => 'Pass 248 (2026-10-26)',
] as $label => $needle) {
    if (substr_count($xml, $needle) !== 0) {
        fwrite(STDERR, sprintf("REFUSED: %s is already in the document (%d copies) — this pass has run.\n",
            $label, substr_count($xml, $needle)));
        exit(1);
    }
    printf("ok   %s not yet present\n", $label);
}

$clockSentence = ' That date comes from the development machine’s clock rather than from a network source, and the '
    .'difference is measured rather than assumed: storage/app/scratch/lane74-probe-clock-skew.php compares this box '
    .'with an outside host’s HTTP date header and on 2026-10-26 reported the box about nineteen hours ahead, so a date '
    .'written here can name a civil day later than the one a correctly set machine would record. The convention, ruled '
    .'by the owner on 2026-10-26, is to keep every dated row consistent with the clock that produced it and to name '
    .'the clock whenever a date in this document has to line up with a date outside it; a dated row is not re-aged by '
    .'hand. Nothing the application stores depends on the machine’s calendar: instants are written and compared in '
    .'UTC, and the school’s civil day is declared in config/school.php and read only through App\Support\SchoolTime.';

$recordSentence = ' Pass 248 (2026-10-26) wrote into §1.2 which clock the dates in this document come from, after '
    .'lane 74 measured that clock about nineteen hours ahead of an outside host, and hardened the editing engine to '
    .'refuse an integer in the anchor slot — a call that passed [from, to, 2] believing slot three was the expected '
    .'copy count would have corrected one copy of a sentence the document holds twice and reported success.';

$edits = [
    [$anchor12, $anchor12.$clockSentence, null, 1],
    [$anchor11, $anchor11.$recordSentence, null, 1],
];

docxApply($docx, $backup, $edits, [], $apply);

if (! $apply) {
    echo "\ndry run only — nothing was written.\n";
    exit(0);
}

// Post-write assertions, read out of the archive rather than from the engine's own report.
$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: the written document cannot be reopened.\n");
    exit(1);
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ([
    'the clock sentence' => 'That date comes from the development machine',
    'the named instrument' => 'lane74-probe-clock-skew.php',
    'the convention' => 'a dated row is not re-aged by hand',
    'the pass record' => 'Pass 248 (2026-10-26)',
    'the engine lesson' => 'refuse an integer in the anchor slot',
] as $label => $needle) {
    printf("%-22s %d copy(ies)\n", $label, substr_count($after, $needle));
}
