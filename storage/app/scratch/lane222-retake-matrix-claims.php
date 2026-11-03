<?php

/**
 * Re-takes every wired/owed claim in the requirement matrix for the twenty-eighth sortable table (the academic term
 * list), and widens the row that still enumerated only eighteen screens.
 *
 * Driven by `lane140-measure-wired-screens.php --check-matrix`, which named eight stale claims — three of them the
 * count itself and four in the §8 / §14 coverage rows that the checker's previous one-row scope never opened.
 * Nothing here is arithmetic from memory: each figure is what the tree answered on the run printed above.
 *
 * Every needle is validated before anything is written (a half-applied register is this repository's oldest defect),
 * and a needle whose REPLACEMENT is already in the file is reported ALREADY APPLIED rather than corrupting a row.
 *
 * Usage: php storage/app/scratch/lane222-retake-matrix-claims.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);

$apply = false;
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
    } else {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this script reads only --apply.\n");
        exit(1);
    }
}

$path = $root.'/Documentation/Prompt-Requirement-Matrix-2026-10-26.md';
$body = (string) file_get_contents($path);

$edits = [
    ['twenty-seven of the 36 pages that render the shared table', 'twenty-eight of the 36 pages that render the shared table'],
    ['the twenty-seven wired pages call', 'the twenty-eight wired pages call'],
    ['twenty-seven of thirty-six tables use it', 'twenty-eight of thirty-six tables use it'],
    ['eighteen screens use it and the other eighteen still show headings with no order',
        'twenty-eight screens use it and the other eight still show headings with no order'],
    ['Eighteen of the 36 screens that render the shared table now sort',
        'Twenty-eight of the 36 screens that render the shared table now sort'],
    ['the other 18 pages offer headings, filtering, search and row actions but no order',
        'the other 8 pages offer headings, filtering, search and row actions but no order'],
    ['That 18 counts pages with no sortable header', 'That 8 counts pages with no sortable header'],
    ['The plan for the nine screens still owed', 'The plan for the eight screens still owed'],
    // A NINTH claim the widened numeral reader found after the first eight were fixed: this row was written "9 of
    // the 36" and the tool could read `18` but not `9`, so the sentence was invisible, not correct.
    ['9 of the 36 pages using the shared table still offer no order',
        '8 of the 36 pages using the shared table still offer no order'],
    ['the majors list and the grade-scale bands;',
        'the majors list, the grade-scale bands, the workflow monitor, the fee-type catalogue, the scholarship '
        .'catalogue, the office directory, the rooms directory, the block roster, the intake and clearance '
        .'requirement catalogues, the curriculum catalogue and the academic term list;'],
];

$plan = [];
$refused = [];
foreach ($edits as $i => [$from, $to]) {
    if ($from === $to) {
        $refused[] = 'edit '.($i + 1).': the replacement is identical to the needle';

        continue;
    }
    $hits = substr_count($body, $from);
    $already = substr_count($body, $to);
    if ($hits === 0 && $already >= 1) {
        echo sprintf("edit %d  ALREADY APPLIED  [%s]\n", $i + 1, mb_substr($to, 0, 62));

        continue;
    }
    if ($hits !== 1) {
        $refused[] = sprintf('edit %d: needle occurs %d time(s), wanted 1 [%s]', $i + 1, $hits, mb_substr($from, 0, 62));

        continue;
    }
    $plan[] = [$from, $to];
}

if ($refused !== []) {
    foreach ($refused as $r) {
        fwrite(STDERR, "REFUSED: {$r}\n");
    }
    fwrite(STDERR, 'NOTHING WRITTEN: '.count($plan).' plan item(s) resolved, '.count($refused)." refused.\n");
    exit(1);
}

printf("%d edit(s) resolved, 0 refused. %s\n", count($plan), $apply ? 'applying' : 'DRY RUN');

if (! $apply) {
    exit(0);
}

$backup = $root.'/storage/app/scratch/lane222-matrix-backup-'.date('Ymd-His').'.md';
if (copy($path, $backup) === false) {
    fwrite(STDERR, "REFUSED: the backup copy could not be written.\n");
    exit(1);
}

foreach ($plan as [$from, $to]) {
    $before = substr_count($body, $from);
    $body = str_replace($from, $to, $body);
    if (substr_count($body, $from) !== 0 || substr_count($body, $to) !== $before) {
        copy($backup, $path);
        fwrite(STDERR, "REFUSED: the replacement for [".mb_substr($from, 0, 50)."] did not land; restored from {$backup}.\n");
        exit(1);
    }
}

if (file_put_contents($path, $body) === false) {
    copy($backup, $path);
    fwrite(STDERR, "REFUSED: the write failed; restored from {$backup}.\n");
    exit(1);
}

printf("written; %d edit(s) applied, each retired needle reads 0; backup %s\n", count($plan), basename($backup));
echo "Now re-run lane140-measure-wired-screens.php --check-matrix and expect AGREES.\n";
