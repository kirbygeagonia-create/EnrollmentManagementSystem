<?php

/**
 * Pass 288: the row written minutes ago says seven names; the instrument prints eight.
 *
 * Counted from the tool's own summary line (`FINDINGS: 8 column name(s) …`) rather than from the earlier
 * screenful, which listed eight rows while the sentence was being written one line at a time. A figure in the
 * authority document has to come from the reading that answers it, even when the reading is five minutes old
 * and the mistake is brand new.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass288.php [--apply]
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

$tool = $root.'/storage/app/scratch/lane87-date-write-columns.php';
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
chdir($root);
$out = (string) preg_replace('/\e\[[0-9;]*m/', '', (string) shell_exec(escapeshellarg($php).' '.escapeshellarg($tool).' 2>&1'));

if (preg_match('/FINDINGS: (\d+) column name\(s\).*?(\d+) unbound write\(s\)/s', $out, $m) !== 1) {
    fwrite(STDERR, "REFUSED: the instrument printed no FINDINGS line, so its figure cannot be quoted.\n");

    exit(1);
}
$names = (int) $m[1];
$unbound = (int) $m[2];
echo "instrument says: {$names} column names, {$unbound} unbound writes\n";

$from = 'enumerates the family at seven names across 48 write sites';
$to = 'enumerates the family at '.$names.' names across 48 write sites, and prints '.$unbound
    .' write(s) whose column no migration declares as UNBOUND rather than clearing them';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

if (substr_count($xml, $from) !== 1) {
    fwrite(STDERR, 'REFUSED: the needle occurs '.substr_count($xml, $from)." time(s).\n");

    exit(1);
}

$backup = $root.'/storage/app/scratch/pass288-backup-'.date('Ymd-His').'.docx';
if ($apply && ! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: no backup.\n");

    exit(1);
}

printf("%s: G-43's figure is taken from the tool that prints it\n", $apply ? 'APPLY' : 'DRY RUN');
docxApply($docx, $apply ? $backup : $root.'/storage/app/scratch/pass288-dry.docx', [[$from, $to]], [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();
if (substr_count($read, $from) !== 0 || substr_count($read, $to) !== 1) {
    fwrite(STDERR, 'REFUSED READ-BACK: old '.substr_count($read, $from).', new '.substr_count($read, $to).".\n");

    exit(1);
}

echo "\npass 288 applied.\n";
