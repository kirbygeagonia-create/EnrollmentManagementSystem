<?php

/**
 * Pass 280: the schema figures the rejection-reason column moved.
 *
 * G33 added `admissions.rejectionReason`, so §24's per-table heading reads one short and the census
 * sentence that adds every column in the domain tables reads one short too. Both figures were printed
 * by `lane60-ratio-claims.php --run` against live `ems` on 2026-10-29; neither is typed from memory.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass280.php [--apply]
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

// What the server answers, read now rather than recalled: the heading figure and the aggregate.
$live = (function (): array {
    $out = shell_exec('C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe -u root -N -B ems'
        .' -e "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE();'
        .' SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=\'admissions\';" 2>&1');
    $nums = array_values(array_filter(array_map('intval', preg_split('/\r\n|\r|\n/', trim((string) $out))), static fn (int $n): bool => $n > 0));
    if (count($nums) !== 2) {
        fwrite(STDERR, 'REFUSED: the server did not answer two counts; it said ['.trim((string) $out)."].\n");

        exit(1);
    }

    return $nums;
})();

[$allColumns, $admissionsColumns] = $live;
echo "server: {$allColumns} columns in schema, {$admissionsColumns} on admissions\n";

$edits = [
    ['12 columns', $admissionsColumns.' columns'],
    ['477 columns', $allColumns.' columns'],
];

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error code, not true).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$editsBefore = [];
foreach ($edits as [$from, $to]) {
    if (substr_count($xml, $from) !== 1) {
        fwrite(STDERR, 'REFUSED: ['.$from.'] occurs '.substr_count($xml, $from).' time(s); a pass writes unique needles.'."\n");

        exit(1);
    }
    if ($from === $to) {
        fwrite(STDERR, "REFUSED: the needle and its replacement are the same text — nothing would move.\n");

        exit(1);
    }
    // A replacement that already occurs elsewhere in the document cannot be post-checked by absence or by
    // an absolute count: `13 columns` is a heading of two other tables. The honest after-check is a delta.
    $editsBefore[$to] = substr_count($xml, $to);
}

$backup = $root.'/storage/app/scratch/pass280-backup-'.date('Ymd-His').'.docx';

if ($apply && ! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup to {$backup}.\n");

    exit(1);
}

printf("%s: §24 heading and the census sentence take the server's counts\n", $apply ? 'APPLY' : 'DRY RUN');
docxApply($docx, $apply ? $backup : $root.'/storage/app/scratch/pass280-dry.docx', $edits, [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();

foreach ($edits as [$from, $to]) {
    $want = $editsBefore[$to] + 1;
    if (substr_count($read, $from) !== 0 || substr_count($read, $to) !== $want) {
        fwrite(STDERR, 'REFUSED READ-BACK: ['.$from.'] '.substr_count($read, $from).' left (want 0), ['.$to.'] '
            .substr_count($read, $to)." left (want {$want}); restore from {$backup}.\n");

        exit(1);
    }
}

echo "\npass 280 applied: §24 names the column and the census adds it.\n";
