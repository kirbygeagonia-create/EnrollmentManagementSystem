<?php

/**
 * Pass 304: the G-34 register row says what the harness now does.
 *
 * The authority document's §28.3 row for G-34 closes with "The cross-office 403 case belongs to the policy, so no
 * leg here would notice a policy widening." That was true when it was written. On 2026-10-30 the harness grew a
 * fifth leg that reaches into `ClearancePolicy::approveRequirement()` and removes the office test, and the
 * cross-office case died for it — so the row's last sentence is now a false statement about the build, in the one
 * document the school treats as authoritative. The row's count of planted defects moves with it, because a row
 * that understates what has since been proved is still a false row.
 *
 * Every needle is asserted to occur exactly once before anything is written, and the pass refuses the whole run on
 * any miss: a half-applied register is worse than an unrevised one, because both halves read as live prose.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass304.php [--apply]
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

require __DIR__.'/docx-edit-engine.php';

$edits = [
    ['four planted defects', 'five planted defects'],
    ['and the desk’s own word not persisted — each killing its own named case',
        'the desk’s own word not persisted, and the policy’s office test removed — each killing its own named case'],
    ['The cross-office 403 case belongs to the policy, so no leg here would notice a policy widening.',
        'The fifth leg reaches past the controller into ClearancePolicy::approveRequirement() and removes its office test, so a widening there kills the cross-office case rather than passing unnoticed.'],
];

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answered an error code, not true).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ($edits as [$needle, $want]) {
    $hits = substr_count($xml, $needle);
    if ($hits !== 1) {
        fwrite(STDERR, 'REFUSED: ['.mb_substr($needle, 0, 60).'] occurs '.$hits
            ." time(s), expected exactly 1 — nothing written.\n");

        exit(1);
    }
    echo 'ok   needle once: '.mb_substr($needle, 0, 62)."\n";
}

printf("%s: 3 text edits on §28.3's G-34 row\n", $apply ? 'APPLY' : 'DRY RUN');

$backup = $root.'/storage/app/scratch/pass304-backup-'.date('Ymd-His').'.docx';

if ($apply && ! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup to {$backup}.\n");

    exit(1);
}

docxApply($docx, $apply ? $backup : $root.'/storage/app/scratch/pass304-dry.docx', $edits, [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$check = new ZipArchive;
if ($check->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: the written docx will not open.\n");

    exit(1);
}
$after = (string) $check->getFromName('word/document.xml');
$check->close();

foreach ([['five planted defects', 1], ['four planted defects', 0], ['no leg here would notice', 0],
    ['the policy’s office test removed', 1], ['ClearancePolicy::approveRequirement()', null]] as [$token, $expect]) {
    $n = substr_count($after, $token);
    if ($expect !== null && $n !== $expect) {
        fwrite(STDERR, "REFUSED: [$token] occurs {$n} time(s) after the write, expected {$expect}; backup is {$backup}.\n");

        exit(1);
    }
    printf("after: %-46s %d\n", $token, $n);
}

echo "WRITTEN and verified against the archive.\n";
