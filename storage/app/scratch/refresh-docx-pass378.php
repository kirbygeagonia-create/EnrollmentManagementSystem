<?php

/**
 * Pass 378 — §28.3 G-35 records what was built, the measurement that says so, and the question that stays the school's.
 *
 * The row claimed the bell renders "the message text only". Measured across three surfaces it was worse than that and
 * better than that: worse, because the title, the action and the link existed on no surface at all; better, because
 * the record identifier was already travelling in the payload and was simply never painted, so part of the row was a
 * screen change rather than a write-path change.
 *
 * The census is re-run in this pass and the row is not written unless it says "no gap" — a register that states a
 * guarantee the instrument does not confirm is the defect every lane in this repository exists to refuse.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass378.php [--apply]
 */
$root = dirname(__DIR__, 3);
require_once __DIR__.'/docx-edit-engine.php';
chdir($root);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = __DIR__.'/pass378-backup.docx';

$out = [];
$code = 0;
exec('C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe storage/app/scratch/lane147-measure-notice-fields.php --rows 2>&1', $out, $code);
$body = implode("\n", $out);
if ($code !== 0 || ! str_contains($body, 'no gap: every part is written')) {
    fwrite(STDERR, "REFUSED: the notice census does not report a closed pair (exit {$code}). The row may not claim what "
        ."the instrument has not confirmed.\n");

    exit(1);
}
preg_match('/rows surface: (\d+) notification row/', $body, $rows);
if (($rows[1] ?? null) === null) {
    fwrite(STDERR, "REFUSED: the census printed no rows-surface count, so it was run without --rows.\n");

    exit(1);
}
printf("ok   census says no gap, over %s stored notice(s)\n", $rows[1]);

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$from = 'the bell renders the message text only. Destination per notice type is what the school must say, and the sheet prices the answer as one template change.';
$to = 'the bell rendered the message text and a moment, and nothing else. Measured across three surfaces before it '
    .'was fixed (`lane147-measure-notice-fields.php`, proved by `mutate-lane147-notice-fields.php` at 18 legs), the '
    .'picture was worse than the row said and better than it sounded: the title, the action and the link existed on '
    .'no surface, while the record identifier was ALREADY in the payload on every notice and had simply never been '
    .'painted, so part of this finding was a screen change rather than a write-path change. Built 2026-11-01: '
    .'`App\\Support\\NoticeFacts::forEnrollment()` is the one builder of the four parts, both listeners merge it in, '
    .'and `OfficeId::deskRouteName()` is the single office-to-destination map, read from `WorkflowInbox::nextOffice()` '
    .'and the two handoff maps the bell already uses to address itself, so no new office vocabulary was introduced. '
    .'The destination is a route NAME resolved by the screen, never a path taken from a request, and what protects it '
    .'is the policy the destination route asks; the repository holds no signed-URL precedent to reach for, and '
    .'inventing one would have been a second mechanism for the same guarantee. A notice with no known waiting office '
    .'carries no link and no action sentence rather than a guess, and the bell’s substituted word `Notification` for '
    .'an absent message is gone, not re-worded. Pinned by `tests/Feature/Notification/NoticeReadabilityTest.php`, '
    .'whose last case enumerates `OfficeId::cases()` against the router so an office that gains a case without a page '
    .'fails instead of rendering a broken link. What still waits on the school is narrower than the row claimed: not '
    .'where a notice goes (that is derivable, and now derived) but the WORDING of each notice type’s destination, and '
    .'whether a desk should land on the record itself rather than on the queue that holds it.';

$got = substr_count($xml, $from);
if ($got !== 1) {
    fwrite(STDERR, "REFUSED: the anchor occurs {$got} time(s), wanted 1.\n");

    exit(1);
}
echo "ok   anchor occurs once\n";

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
    ['Built 2026-11-01', 1],
    ['not where a notice goes (that is derivable, and now derived)', 1],
] as [$n, $want]) {
    $g = substr_count($after, $n);
    if ($g !== $want) {
        echo sprintf("BAD  [%s] occurs %d, wanted %d — restoring.\n", substr($n, 0, 52), $g, $want);
        copy($backup, $docx);

        exit(1);
    }
}

echo "written and read back: G-35 states the build, the measurement, and the wording question that remains.\n";
