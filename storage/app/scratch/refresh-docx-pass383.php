<?php

/**
 * Pass 383 — §24's column-aggregate cross-check row, which pass 382 left one short.
 *
 * The row claims the per-table headings add up to the columns the server holds. `seatKey` is a real column to
 * `information_schema`, so the `enrollments` heading moved 23 → 24 and the domain total beside it moved 437 → 438.
 * The figure is taken from `lane60-ratio-claims.php`'s own verdict rather than typed from arithmetic: this pass
 * refuses unless the checker reports exactly the pair it is about to write, which is the only way a re-taken total
 * differs from a guessed one.
 *
 * Dry run by default; --apply writes through the engine.
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass383-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $GLOBALS['argv'], true);

$from = '437 columns documented for 56 domain tables';
$to = '438 columns documented for 56 domain tables';

// Ask the census checker what the headings should total, and refuse to type a figure it does not report.
chdir($root);
$out = (string) shell_exec(escapeshellarg($php).' storage/app/scratch/lane60-ratio-claims.php --run 2>&1');
if (! preg_match('/column-aggregate\s+line \d+\s+documentHeadings\.domainStatedColumns claimed (\d+), the server answers (\d+)/', $out, $m)) {
    if (str_contains($out, 'VERDICT: clean') || ! str_contains($out, 'column-aggregate')) {
        echo "the aggregate row already agrees with the server (no STALE line for it) — nothing to write.\n";
        exit(0);
    }
    fwrite(STDERR, "REFUSED: lane60 ran but its column-aggregate verdict could not be read; its tail was:\n"
        .substr($out, -700)."\n");
    exit(1);
}
if ((int) $m[1] !== 437 || (int) $m[2] !== 438) {
    fwrite(STDERR, 'REFUSED: the checker reports '.$m[1].' claimed against '.$m[2]
        .' on the server, not the 437 → 438 pair this pass was written for. Re-read before typing.\n');
    exit(1);
}
printf("lane60 confirms: headings state %s, the server answers %s\n", $m[1], $m[2]);

$res = docxApply($src, $backup, [[$from, $to, null, 1]], [], $apply);
if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    return;
}
if ($res === false) {
    fwrite(STDERR, "REFUSED: the engine would not apply this pass.\n");
    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    fwrite(STDERR, "REFUSED: could not re-open the docx.\n");
    exit(1);
}
$body = (string) $zip->getFromName('word/document.xml');
$zip->close();
$text = html_entity_decode(preg_replace('/<[^>]+>/', '', $body) ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
foreach ([$from => 0, $to => 1] as $needle => $want) {
    $got = substr_count($text, $needle);
    if ($got !== $want) {
        fwrite(STDERR, 'POST-READ FAILED: `'.substr($needle, 0, 40).'…` occurs '.$got.', expected '.$want.".\n");
        exit(1);
    }
}
echo "read back: the aggregate row states 438 and the retired figure is gone.\n";
