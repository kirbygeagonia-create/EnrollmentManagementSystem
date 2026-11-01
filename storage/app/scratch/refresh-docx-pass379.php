<?php

/**
 * Pass 379 — the bell's college boundary reaches the authority document.
 *
 * Two sentences claim what `WorkflowInbox::actors()` answers; the method now also asks the college boundary,
 * so a sentence that says how the code behaves today would otherwise be overtaken while every line number
 * stayed live. The G-44 register row gains the half that was built, dated, and keeps leading `Open` because
 * the decline-notice question is still the school's. Every replacement is asserted to occur the expected
 * number of times BEFORE anything is written, and the pass refuses if a needle cannot land.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass379.php [--apply]
 */
$root = dirname(__DIR__, 3);
require_once $root.'/storage/app/scratch/docx-edit-engine.php';

$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass379-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

$edits = [
    [
        'answers the office’s staff userIds',
        'answers the office’s staff userIds who may reach the record',
        null,
        2,
    ],
    [
        'so what is missing is a notice for a refusal, not an addressee rule.',
        'so what is missing is a notice for a refusal, not an addressee rule. Built 2026-11-01 on the scope half, '
        .'which was measured before it was written: office 7 holds 11 active accounts and 6 of them carry a unitId '
        .'(dept.caf … dept.cte on units 1 to 6), and the Department Evaluation box addresses that office, so one '
        .'signature rang five colleges’ department desks while each of those desks lists only its own college’s '
        .'records. The bell now asks CollegeScope::holds() for the record’s college — the same predicate the list '
        .'asks — and a notice that names no record keeps the whole office, because there is no college to ask about. '
        .'Pinned by tests/Feature/Notification/NoticeCollegeBoundaryTest.php and killed per promise by '
        .'mutate-g44-notice-college-boundary.php. Whether every decline must ring a bell still waits on the school.',
        null,
        1,
    ],
];

foreach ($edits as $i => $e) {
    [$from, $to, $anchor, $expect] = $e;
    // Slot 3 may be null (the needle is its own landmark); it may NOT be an integer, which the resolver would
    // read as the anchor string "2" and land on one arbitrary occurrence — a half-applied register.
    if ($anchor !== null && ! is_string($anchor)) {
        fwrite(STDERR, "REFUSED text ".($i + 1).": slot 3 is the anchor; pass a string or null, never a number.\n");
        exit(1);
    }
    if ($from === $to) {
        fwrite(STDERR, "REFUSED text ".($i + 1).": the replacement is identical to the needle.\n");
        exit(1);
    }
}

$res = docxApply($docx, $backup, $edits, [], $apply);
// The engine's dry run prints its plan and returns nothing; only a refused WRITE is a failure. Grading the
// null as a failure would refuse a correct dry run — the same shape as a post-condition that asserts a
// document-wide absence for a token that legitimately occurs elsewhere.
if ($apply && $res === false) {
    fwrite(STDERR, "docxApply refused the write — read its own output before retrying.\n");
    exit(1);
}

if (! $apply) {
    echo "dry run: the plan above is what --apply would write; the read-back runs only against written bytes.\n";
    exit(0);
}

// Read back: each widened phrase must occur exactly the expected number of times, and the retired short
// form must be gone from the first sentence but survive as the head of the G-44 append (an append embeds
// its own needle, so a blanket "old count == before - 1" would refuse a correct write).
$xml = (function (string $path): string {
    $z = new ZipArchive();
    if ($z->open($path) !== true) {
        fwrite(STDERR, "REFUSED: cannot reopen the document after the pass.\n");
        exit(1);
    }
    $x = (string) $z->getFromName('word/document.xml');
    $z->close();

    return html_entity_decode($x, ENT_QUOTES | ENT_XML1);
})($docx);

$tallies = [
    'answers the office’s staff userIds who may reach the record' => 2,
    'Built 2026-11-01 on the scope half' => 1,
    'mutate-g44-notice-college-boundary.php' => 1,
    'no college to ask about' => 1,
];
$bad = [];
foreach ($tallies as $needle => $want) {
    $got = substr_count($xml, $needle);
    if ($got !== $want) {
        $bad[] = $needle.' occurs '.$got.' time(s), expected '.$want;
    }
}
if ($bad !== []) {
    fwrite(STDERR, "READ-BACK FAILED:\n  ".implode("\n  ", $bad)."\n");
    exit(1);
}

echo ($apply ? 'WRITTEN' : 'DRY RUN')." and read back: the two actors() sentences name the boundary, and §28.3 G-44 records the built half beside the question that remains.\n";
