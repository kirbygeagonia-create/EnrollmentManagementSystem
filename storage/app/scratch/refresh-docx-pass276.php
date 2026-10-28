<?php

/**
 * Pass 276: two §28.3 figures that this batch moved, read from the instrument that prints them.
 *
 * The new monitor page added JSX of the shape lane 49 counts, so its blind-spot tally aged — the
 * same mechanism pass 251 recorded ("lane 49's blind-spot count went 339 → 342 because the new JSX
 * added lines of the shape it counts"). `lane59-instrument-counts.php` named both figures and
 * quoted what the tool prints today; this pass types those two numbers and nothing else, so the
 * document cannot disagree with the instrument it cites.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass276.php [--apply]
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

// [needle, replacement, the instrument, the figure it prints today]
$edits = [
    ['20 guards whose dash', '21 guards whose dash', 'lane49-zero-shown-as-absence.php', 21],
    ['345 lines where', '354 lines where', 'lane49-zero-shown-as-absence.php', 354],
];

// Re-read the instrument in this run: a figure typed from the previous run's console memory is how
// lane 58's multi-total defect is born. The tool prints its tally; the value must still be what it says.
$out = (string) shell_exec('cd '.escapeshellarg($root).' && '
    .'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe '.escapeshellarg('storage/app/scratch/lane49-zero-shown-as-absence.php').' 2>&1');
preg_match('/(\d+)\s+guards?\b.*?(\d+)\s+lines?\b/isu', $out, $m);
if (count($m) < 3) {
    foreach ($edits as $e) {
        if (! preg_match('/'.preg_quote((string) $e[3], '/').'\b/', $out)) {
            fwrite(STDERR, "REFUSED: lane 49's output does not contain {$e[3]} — re-read the tool before typing.\n");

            exit(1);
        }
    }
} else {
    $printed = [(int) $m[1], (int) $m[2]];
    $wanted = [$edits[0][3], $edits[1][3]];
    if ($printed !== $wanted) {
        fwrite(STDERR, 'REFUSED: lane 49 prints '.$printed[0].' guards and '.$printed[1]
            .' lines this run; this pass would type '.$wanted[0].' and '.$wanted[1].".\n");

        exit(1);
    }
}

$zip = new ZipArchive;

if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ($edits as [$from, $to]) {
    $n = substr_count($xml, $from);
    if ($n !== 1) {
        fwrite(STDERR, "REFUSED: needle [{$from}] occurs {$n} time(s), expected exactly 1.\n");

        exit(1);
    }
    if (substr_count($xml, $to) !== 0) {
        fwrite(STDERR, "REFUSED: the replacement [{$to}] is already present; this pass already ran.\n");

        exit(1);
    }
    $xml = str_replace($from, $to, $xml);
}

foreach ($edits as [$from, $to]) {
    if (substr_count($xml, $from) !== 0 || substr_count($xml, $to) !== 1) {
        fwrite(STDERR, "REFUSED: after-check failed for [{$to}].\n");

        exit(1);
    }
}

foreach ([['<w:p', '</w:p>'], ['<w:t', '</w:t>'], ['<w:tc', '</w:tc>'], ['<w:tr', '</w:tr>'], ['<w:tbl', '</w:tbl>']] as [$open, $close]) {
    $a = preg_match_all('/'.preg_quote($open, '/').'(?=[\s>])/', $xml);
    $b = substr_count($xml, $close);
    if ($a !== $b) {
        fwrite(STDERR, "REFUSED: unbalanced {$open} ({$a} against {$b}).\n");

        exit(1);
    }
}

if (simplexml_load_string($xml) === false) {
    fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses.\n");

    exit(1);
}

printf("%stype %d instrument figures into §28.3 (%s)\n", $apply ? '' : 'DRY RUN — ', count($edits),
    implode(' / ', array_column($edits, 'to')));

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass276-backup-'.date('Ymd-His').'.docx';

if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup to {$backup}.\n");

    exit(1);
}

$w = new ZipArchive;

if ($w->open($docx, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "REFUSED: cannot reopen for writing. Restore from {$backup}.\n");

    exit(1);
}
$w->addFromString('word/document.xml', $xml);
$w->close();

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();

if ($read !== $xml) {
    fwrite(STDERR, "REFUSED: the archive did not read back the bytes it was handed. Restore from {$backup}.\n");

    exit(1);
}

echo "\npass 276 applied: §28.3's lane 49 figures now equal what lane 49 prints.\n";
