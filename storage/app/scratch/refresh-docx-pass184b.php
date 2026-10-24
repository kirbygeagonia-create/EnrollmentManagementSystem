<?php

/**
 * Pass 184b — the four pointers into the two files pass 184's build changed.
 *
 * The build added six lines to PrintSlip.jsx and eight to clearance-slip.blade.php, so every citation into
 * them had to be re-read rather than re-added to. The line sweep caught one (it landed on a blank), and
 * reading caught a second that the sweep could not: §25.4 P-10's evidence cell cited
 * clearance-slip.blade.php:38-55 for "the enrollment that matches clearancePeriod.termId", but those lines
 * print Document No., Semester, Academic Year and Student Name — none of which reads an enrollment. The rows
 * that do are the @php block calling termEnrollment() and the Course & Year line under it, which is where the
 * pointer goes now. A citation one construct short still lands on a live line, which is why the range check
 * passed it for as long as it did.
 *
 *   php storage/app/scratch/refresh-docx-pass184b.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass184b.php --apply  # write
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$fails = 0;
$say = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD ', $what);
    if (! $ok) {
        $fails++;
    }
};

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

/**
 * Each fix names the construct its sentence is about, so a wrong target is refused before the write: the
 * range is checked against the file it points into, not against arithmetic.
 */
$fixes = [
    [
        '§25.6 Receipt, the blade',
        'clearance-slip.blade.php:103-105',
        'clearance-slip.blade.php:111-113',
        'resources/views/prints/clearance-slip.blade.php',
        111, 113, ['Received by Registrar', 'receivedByUser', 'receivedDate'],
    ],
    [
        '§25.6 Receipt, the desk page',
        'PrintSlip.jsx:94-99',
        'PrintSlip.jsx:100-104',
        'resources/js/Pages/Clearance/PrintSlip.jsx',
        100, 104, ['receivedDate', 'receivedByUser'],
    ],
    [
        '§25.4 P-10, the blade',
        'clearance-slip.blade.php:38-55',
        'clearance-slip.blade.php:64-82',
        'resources/views/prints/clearance-slip.blade.php',
        64, 82, ['termEnrollment()', 'Course & Year:'],
    ],
    [
        '§25.4 P-10, the desk page',
        'PrintSlip.jsx:30-36',
        'PrintSlip.jsx:37-44',
        'resources/js/Pages/Clearance/PrintSlip.jsx',
        37, 44, ['Course:', 'courseLabel', 'Year Level:', 'formatYearLevel'],
    ],
];

echo "=== each new range is read out of the file it names ===\n";
foreach ($fixes as [$what, $from, $to, $path, $lo, $hi, $must]) {
    $lines = explode("\n", (string) file_get_contents($root.'/'.$path));
    $span = implode("\n", array_slice($lines, $lo - 1, $hi - $lo + 1));
    $say(count($lines) >= $hi, $what.': the file has '.count($lines)." lines, enough for :{$hi}");
    foreach ($must as $needle) {
        $say(str_contains($span, $needle), $what.': the span contains '.$needle);
    }
    $say(! str_contains($span, "\n\n"), $what.': the span holds no blank line');
    $c = substr_count($xml, $from);
    $say($c === 1, $what.': the stale pointer occurs '.$c.' time(s), expected 1');
}

$staged = $xml;
foreach ($fixes as [$what, $from, $to]) {
    $staged = str_replace($from, $to, $staged);
    $say(substr_count($staged, $to) === 1, $what.': the new pointer is in once');
    $say(! str_contains($staged, $from), $what.': the stale pointer is gone');
}
$say(substr_count($staged, 'clearance-slip.blade.php:') === substr_count($xml, 'clearance-slip.blade.php:')
    && substr_count($staged, 'PrintSlip.jsx:') === substr_count($xml, 'PrintSlip.jsx:'),
    'no citation into either file was lost or duplicated — only its line numbers moved');
$say(@(new DOMDocument)->loadXML($staged) !== false, 'the staged document.xml parses');
if ($fails > 0) {
    printf("\n%d guard(s) failed — NOTHING WRITTEN.\n", $fails);
    exit(1);
}

if (! $apply) {
    echo "\nDRY RUN: four pointers would be re-pointed into the two files the build changed.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass184b.docx');
$z = new ZipArchive();
if ($z->open($src) !== true) {
    exit("REFUSED: the docx cannot be reopened for the write.\n");
}
if ($z->getFromName('word/document.xml') !== $xml) {
    exit("REFUSED: document.xml changed under this pass since it was read — nothing written.\n");
}
$z->addFromString('word/document.xml', $staged);
$z->close();
echo "WROTE {$src}\n";
