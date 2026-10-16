<?php

/**
 * Pass 81 citation probe — for every PrintService.php / bootstrap/app.php line number the
 * docx cites, name the method each end of the range sits in, in the committed file and in
 * the working tree. Matching numbers are not enough: what has to match is the statement.
 *
 * Usage: php probe-docx-pass81-cites.php
 */

$root = dirname(__DIR__, 3);

$zip = new ZipArchive();
$zip->open($root.'/Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$text = html_entity_decode(preg_replace('/<[^>]+>/', '', $xml), ENT_QUOTES | ENT_XML1);

/** @return list<array{0:int,1:int,2:string}> [from, to, raw] */
function citesFor(string $text, string $file): array
{
    preg_match_all('/'.preg_quote($file, '/').':(\d+)(?:\s*[-–]\s*(\d+))?/', $text, $m, PREG_SET_ORDER);

    return array_map(
        fn (array $d): array => [(int) $d[1], isset($d[2]) && $d[2] !== '' ? (int) $d[2] : (int) $d[1], $d[0]],
        $m
    );
}

/** Find the function whose body contains the given 1-based line. */
function methodAt(string $src, int $line): string
{
    $lines = explode("\n", $src);
    $best = 'TOP-LEVEL';

    foreach ($lines as $i => $text) {
        if (preg_match('/^\s*(?:public|protected|private|)\s*(?:static\s+)?function\s+(\w+)/', $text, $m)) {
            if ($i + 1 <= $line) {
                $best = $m[1];
            }
        }
    }

    return $best;
}

function showLine(string $src, int $line): string
{
    $lines = explode("\n", $src);

    return trim($lines[$line - 1] ?? '<past end of file>');
}

$targets = [
    'app/Services/PrintService.php' => 'PrintService.php',
    'bootstrap/app.php' => 'bootstrap/app.php',
];

foreach ($targets as $rel => $citedAs) {
    $old = shell_exec(sprintf('git -C %s show HEAD:%s', escapeshellarg($root), escapeshellarg($rel)));
    $new = file_get_contents($root.'/'.$rel);
    $oldLines = count(explode("\n", $old));
    $newLines = count(explode("\n", $new));

    echo "\n=== {$citedAs}  (committed {$oldLines} lines, working {$newLines} lines)\n";

    $seen = [];

    foreach (citesFor($text, $citedAs) as [$from, $to, $raw]) {
        if (isset($seen[$raw])) {
            continue;
        }
        $seen[$raw] = true;

        $oldFrom = methodAt($old, $from);
        $oldTo = methodAt($old, $to);
        $newFrom = methodAt($new, $from);
        $newTo = methodAt($new, $to);

        printf(
            "%-34s old[%s..%s] %s→%s | new[%s..%s] %s→%s  %s\n",
            $raw,
            $from,
            $to,
            $oldFrom,
            $oldTo,
            $from,
            $to,
            $newFrom,
            $newTo,
            ($oldFrom === $newFrom && $oldTo === $newTo) ? 'same method at the same numbers' : 'NUMBERS MOVED'
        );
        echo '     old-first: '.substr(showLine($old, $from), 0, 96)."\n";
        echo '     new-first: '.substr(showLine($new, $from), 0, 96)."\n";
    }
}
