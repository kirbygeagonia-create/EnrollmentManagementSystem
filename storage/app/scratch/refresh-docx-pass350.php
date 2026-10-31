<?php

/**
 * Docx pass 350 — §28.3 G-36 closed, and the four schema figures its migration moved.
 *
 * `lane60-ratio-claims.php --run` named exactly what the new column changed: the counts sentence (columns, indexes,
 * table constraints) and §24's own column aggregate, each one up by one and each stated in the present tense. A
 * figure that was true when it was written and is not now is the defect lanes 58-60 exist for, so each is re-taken
 * from the server lane60 read — not typed from arithmetic.
 *
 * The G-36 row's verdict cell moves to Closed and carries the shape that was built, including the leg that SURVIVED
 * this batch's own first harness run: a case that cannot see a plant is the harness's age, and here it meant no test
 * drove a real transition end to end. That is recorded in the row rather than smoothed over.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass350.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = $root.'/storage/app/scratch/pass350-backup.docx';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}
require __DIR__.'/docx-edit-engine.php';

$columns = ['478 columns', '479 columns', null, 1];
$indexes = ['135 indexes', '136 indexes', null, 1];
$constraints = ['181 table constraints in all', '182 table constraints in all', null, 1];
$aggregate = ['436 columns documented', '437 columns documented', null, 1];

$g36 = ['Open 2026-10-29 (phase 2). No unique key and no idempotency guard sits on the notification write, so a '
    .'retried job can ring the same desk twice. Buildable without a ruling; the honest shape is a key on the act and '
    .'the addressee, which is a migration and therefore waits on nothing but the decision to spend one.',
    'Closed 2026-10-31, the decision having been to spend the migration. `notifications` gained a nullable `dedupeKey` '
    .'and the unique key `uq_notifications_dedupe` — §23.6.1’s tenth entry — and one writer, `App\Support\NoticeDelivery::once()`, '
    .'is now the only path either queued listener takes. The key names the ACT and the addressee, never the message: '
    .'`EnrollmentStateMachine::transition()` hands the history row it just wrote to the event, so a retried job is '
    .'recognised while a second signing of the same box after a return stays a different notice. A null key is '
    .'delivered rather than swallowed, because a guard that loses a bell is the same loss wearing the other costume. '
    .'Pinned by `tests/Feature/Notification/NoticeDedupeTest.php` (7 cases) and proved by '
    .'`storage/app/scratch/mutate-g36-notice-dedupe-tests.php`, whose first run reported one leg SURVIVING — the leg '
    .'that plants a state machine which stops naming the act — because no case then drove a real transition end to '
    .'end; that case was written, and the promise now has two independent guards. Both drivers were rehearsed, since '
    .'MySQL and SQLite word a duplicate refusal and a NOT NULL refusal differently.', null, 1];

$day = ['DEPLOYMENT.md §2 now says what an operator must not re-enable',
    'DEPLOYMENT.md §2 now says what an operator must not re-enable. The day’s seventh half closed §28.3 G-36, the '
    .'only register row the sweep had marked buildable without a ruling: `notifications` carries a nullable '
    .'`dedupeKey` with the unique key `uq_notifications_dedupe`, both queued listeners write through one '
    .'`NoticeDelivery::once()`, and `EnrollmentStatusChanged` now carries the history row that names the act — with no '
    .'id, the notice is delivered unkeyed rather than dropped. §23.6.1 grew its tenth key and §24 its ninth '
    .'`notifications` column, and the census sentence that counts columns, indexes and table constraints was re-taken '
    .'from the server rather than incremented by hand', null, 1];

$textEdits = [$columns, $indexes, $constraints, $aggregate, $g36, $day];

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    exit("REFUSED: the docx did not open as a zip.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$bad = 0;
foreach ($textEdits as $i => [$from, $to, , $want]) {
    $n = substr_count($xml, $from);
    printf('edit %d: needle occurs %d time(s), want %d — %s%s', $i, $n, $want, substr($from, 0, 44), PHP_EOL);
    if ($n !== $want) {
        $bad++;
    }
    if (str_contains($to, '<') || str_contains($to, '>')) {
        $bad++;
        echo "  edit {$i} carries a tag character\n";
    }
}
if ($bad > 0) {
    echo "REFUSED — nothing written.\n";

    exit(1);
}

docxApply($docx, $backup, $textEdits, [], $apply);
if (! $apply) {
    echo "DRY RUN — nothing written.\n";

    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    exit("REFUSED: the written docx did not open.\n");
}
$after = html_entity_decode((string) $zip->getFromName('word/document.xml'), ENT_QUOTES | ENT_HTML5);
$zip->close();

$expect = [
    ['478 columns', 0],
    ['135 indexes', 0],
    ['181 table constraints in all', 0],
    ['436 columns documented', 0],
    ['Open 2026-10-29 (phase 2). No unique key and no idempotency guard', 0],
    ['Closed 2026-10-31, the decision having been to spend the migration', 1],
    ['The day’s seventh half closed §28.3 G-36', 1],
];
$bad = 0;
foreach ($expect as [$needle, $want]) {
    $n = substr_count($after, $needle);
    if ($n !== $want) {
        $bad++;
        printf('  %s occurs %d time(s), want %d%s', $needle, $n, $want, PHP_EOL);
    }
}
if ($bad > 0) {
    copy($backup, $docx);
    exit("REFUSED: the written document does not read back as intended; restored from {$backup}.\n");
}
echo "written, re-read: G-36 leads Closed, §23.6.1's tenth key is counted, and every schema figure names the reading that answers it.\n";
