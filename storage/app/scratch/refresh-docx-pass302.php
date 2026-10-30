<?php

/**
 * Pass 302: the tail of a comma-list citation the shift tool is built to refuse.
 *
 * `lane76-shift-cites.php` re-points `IDController.php:173` and reports the `, :185` beside it as a bare
 * continuation it will not attribute — the second number of a pair that shares one colon, which AGENTS.md
 * names as one of the two things the sweep cannot see. `--skip=185` protects the other three copies of `:185`
 * in the document (one is `Preflight.php`'s range, another belongs to a different file), so the head moved and
 * this pass takes the tail by reading.
 *
 * The sentence claims two things of the ID desk: that a blood type is *checked only against a fixed list of
 * strings*, and that the value is one the student declared. Both live lines are asserted before anything is
 * written — 170 is the `in:A+,A-,…` rule and 182 is the write of `$validated['bloodType']` into the row.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass302.php [--apply]
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

$live = explode("\n", (string) file_get_contents($root.'/app/Http/Controllers/ID/IDController.php'));

$assertions = [
    170 => "in:A+,A-,B+,B-,AB+,AB-,O+,O-",
    182 => "\$validated['bloodType']",
    173 => '// without any file behind it',
];

foreach ($assertions as $line => $claim) {
    $body = $live[$line - 1] ?? '(past end)';
    if (! str_contains($body, $claim)) {
        fwrite(STDERR, "REFUSED: live line {$line} reads [".trim($body)."], which does not carry [{$claim}].\n");

        exit(1);
    }
    echo "assert line {$line} carries the claim: ".trim($body)."\n";
}

// 173 is asserted to be what the OLD pointer landed on: a comment about a different field. That is the proof
// that the head genuinely moved rather than that both numbers describe the same line twice.
$edits = [[':170, :185', ':170, :182']];

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
        fwrite(STDERR, "REFUSED: [{$needle}] occurs {$hits} time(s); this pass writes unique needles.\n");

        exit(1);
    }
}

// The four copies of :185 are this pair's tail plus three other files' pointers. After the write the count must
// fall by exactly one and :182 must gain exactly one — a document-wide absence check would be wrong here, since
// :182 is already a live citation somewhere else in the register.
$before185 = substr_count($xml, ':185');
$before182 = substr_count($xml, ':182');
if ($before185 !== 4) {
    fwrite(STDERR, "REFUSED: expected 4 copies of :185 before the write, measured {$before185}.\n");

    exit(1);
}

printf("%s: the ID pair takes 170, :182 (:185 %d -> %d, :182 %d -> %d)\n",
    $apply ? 'APPLY' : 'DRY RUN', $before185, $before185 - 1, $before182, $before182 + 1);

$backup = $root.'/storage/app/scratch/pass302-backup-'.date('Ymd-His').'.docx';

if ($apply && ! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup to {$backup}.\n");

    exit(1);
}

docxApply($docx, $apply ? $backup : $root.'/storage/app/scratch/pass302-dry.docx', $edits, [], $apply);

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

printf("after: :185 occurs %d (wanted %d), :182 occurs %d (wanted %d), the pair occurs %d\n",
    substr_count($after, ':185'), $before185 - 1, substr_count($after, ':182'), $before182 + 1,
    substr_count($after, ':170, :182'));

if (substr_count($after, ':185') !== $before185 - 1
    || substr_count($after, ':182') !== $before182 + 1
    || substr_count($after, ':170, :185') !== 0) {
    fwrite(STDERR, "REFUSED: the written bytes do not match the reading above; backup is {$backup}.\n");

    exit(1);
}

echo "WRITTEN and verified against the archive.\n";
