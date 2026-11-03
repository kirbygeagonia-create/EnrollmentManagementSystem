<?php

/**
 * Pass 430 — the wired/owed figures in §28.3 G-40 follow the tree to thirty and six.
 *
 * The permission list and the role list came off the owed queue, so `lane140-measure-wired-screens.php` answers
 * SCREEN 30 vs HANDLER 30 with `0 wireable + 6 bounded + 0 unread = 6 owed`, and its `--check-docx` half refused the
 * run against four living claims. Every needle below was probed in `word/document.xml` before this pass was written;
 * one candidate — the enumeration of wired screens ending at the academic term list — reads continuously in the flat
 * dump and occurs ZERO times in the archive, because the names sit in styled runs. It is therefore NOT touched here:
 * a cell rewrite would flatten those runs, and the two new screens are named in this batch's own §1.1 row instead,
 * which is recorded as a residual rather than smoothed away.
 *
 * The owed sentence's tail is re-taken, not re-numbered. It said two of the eight were vendor-owned tables; today the
 * six that remain are bounded sets, and `lane215-plan-remaining-screens.php` now prints which is which per page — so
 * the sentence states that reading and leaves the older one as a dated statement about the earlier queue.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass430.php [--apply]
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

$edits = [
    [
        'the twenty-eight wired pages call',
        'the thirty wired pages call',
    ],
    [
        'twenty-eight of the thirty-six tables that render the shared component',
        'thirty of the thirty-six tables that render the shared component',
    ],
    [
        '8 of the 36 pages that use the shared table still offer no order',
        '6 of the 36 pages that use the shared table still offer no order',
    ],
    [
        'The plan for the eight screens still owed is derived by',
        'The plan for the six screens still owed is derived by',
    ],
    [
        'and two of those are vendor-owned tables this project',
        'and its reader now prints, for each page it leaves owed, whether the action paginates or hands the browser a '
        .'bounded set, because a header over a bounded set would order rows the handler never paged. The earlier '
        .'reading of that queue called two of its eight members vendor-owned tables this project',
    ],
];

// ------------------------------------------------- stage 1: prove every needle before writing anything
$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    $fail('the docx did not open as an archive.');
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
if ($xml === '') {
    $fail('word/document.xml read empty.');
}

$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
foreach ($edits as $i => [$from, $to]) {
    $hits = substr_count($xml, $escape($from));
    if ($hits !== 1) {
        $fail('edit '.($i + 1)." occurs {$hits} time(s) in the archive, expected exactly 1 — the needle has aged or the sentence is run-split.\n"
            .'           needle: '.mb_substr($from, 0, 90));
    }
    if (substr_count($xml, $escape($to)) !== 0) {
        $fail('edit '.($i + 1).' is a no-op: its replacement already occurs in the document.');
    }
}
printf("stage 1: %d needles each occur exactly once, and none of the replacements is present yet. %s\n",
    count($edits), $apply ? 'applying' : 'DRY RUN');

if (! $apply) {
    echo "DRY RUN — nothing written.\n";
    exit(0);
}

// ------------------------------------------------- stage 2: write through the engine
$backup = $root.'/storage/app/scratch/pass430-backup-'.date('Ymd-His').'.docx';
// Two-element edits: slot 3 is the ANCHOR, not a repeat of the needle. Passing the needle as its own anchor makes the
// engine look for a needle *inside* an anchor region and resolve nothing — its refusal said exactly that, and wrote
// nothing, which is the correct outcome from an incorrect instruction.
$engineEdits = [];
foreach ($edits as [$from, $to]) {
    $engineEdits[] = [$from, $to];
}
docxApply($docx, $backup, $engineEdits, [], true);

// ------------------------------------------------- stage 3: read back from the archive, not from the run's stdout
$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    $fail('the docx did not reopen for the read-back.');
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ($edits as $i => [$from, $to]) {
    $oldLeft = substr_count($after, $escape($from));
    $newCount = substr_count($after, $escape($to));
    if ($oldLeft !== 0 || $newCount !== 1) {
        printf("REFUSED: edit %d read back as retired=%d new=%d (wanted 0 and 1); restoring the backup.\n", $i + 1, $oldLeft, $newCount);
        if ($backup !== '' && is_file($backup)) {
            copy($backup, $docx);
            echo '           restored from '.basename($backup)."\n";
        }

        exit(1);
    }
    printf("edit %d read back: the retired phrase is gone and the new one occurs once\n", $i + 1);
}

printf("WROTE %d phrase edits; backup at %s\n", count($edits), basename($backup));
