<?php

/**
 * Pass 284: the G-34 register row said its cases were pinned but not proved able to fail. They are proved now.
 *
 * mutate-g34-clearance-reason-tests.php answers `ALL KILLED: 4 leg(s), 0 bad` — requiredness dropped, the
 * ten-character floor dropped, the requirement re-pointed at an approval, and the desk's own word not
 * persisted, each killing its own named case, with a green control before and after. A register row that
 * understates what has since been proved is still a false row, so it is re-taken rather than left.
 *
 * The cell was written by pass 281 as ONE text run, which is why a plain contiguous needle can address it;
 * a needle spanning a styled span would have gone through the cell kind instead.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass284.php [--apply]
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

$from = '**No mutant harness has been run over these five cases yet** — they are pinned, not proved able to fail, and the register says so.';
$to = 'Proved the same day by `mutate-g34-clearance-reason-tests.php`: four planted defects — requiredness dropped, the ten-character floor dropped, the requirement re-pointed at an approval, and the desk’s own word not persisted — each killing its own named case, with a green control before and after. The cross-office 403 case belongs to the policy, so no leg here would notice a policy widening.';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

if (substr_count($xml, $from) !== 1) {
    fwrite(STDERR, 'REFUSED: the needle occurs '.substr_count($xml, $from).' time(s) in document.xml.'
        ." It is matched as one run because pass 281 wrote the cell that way.\n");

    exit(1);
}

$backup = $root.'/storage/app/scratch/pass284-backup-'.date('Ymd-His').'.docx';
if ($apply && ! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: no backup at {$backup}.\n");

    exit(1);
}

printf("%s: G-34's row takes its own proof\n", $apply ? 'APPLY' : 'DRY RUN');
docxApply($docx, $apply ? $backup : $root.'/storage/app/scratch/pass284-dry.docx', [[$from, $to]], [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();

if (substr_count($read, $from) !== 0 || substr_count($read, $to) !== 1) {
    fwrite(STDERR, "REFUSED READ-BACK: old ".substr_count($read, $from).', new '.substr_count($read, $to)
        ."; restore from {$backup}.\n");

    exit(1);
}

echo "\npass 284 applied: the register states what the instrument now proves.\n";
