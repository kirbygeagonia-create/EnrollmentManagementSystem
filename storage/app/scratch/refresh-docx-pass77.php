<?php

/**
 * Pass 77 — one number pass 76 got wrong about its own subject.
 *
 * The privacy note says the reference photographs carry "three staff members' names and
 * signatures". Reading the four forms again counts five distinct names across them (the Registrar
 * in charge printed on the slip, the instructor and the issuing clerk on the card, the evaluator
 * and the processor on the load, one of whom also signs the load's top-right box). An understated
 * count in a note whose whole point is what must not be published is the worst kind of wrong.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$from = 'three staff members’ names and signatures';
$to = 'five staff members’ names and signatures';

if (substr_count($xml, $from) !== 1) {
    fwrite(STDERR, 'REFUSE: needle seen '.substr_count($xml, $from)." times\n");
    exit(1);
}

$at = strpos($xml, $from);
$newXml = substr_replace($xml, $to, $at, strlen($from));

$parseOk = (function () use ($newXml) {
    libxml_use_internal_errors(true);
    libxml_clear_errors();
    $r = @simplexml_load_string($newXml);
    libxml_clear_errors();

    return $r !== false;
})();

$containersSame = substr_count($xml, '<w:tbl>') === substr_count($newXml, '<w:tbl>')
    && substr_count($xml, '</w:p>') === substr_count($newXml, '</w:p>');

if (! $parseOk || ! $containersSame || substr_count($newXml, $from) !== 0 || substr_count($newXml, $to) !== 1) {
    fwrite(STDERR, "REFUSE: guards (parses=".($parseOk ? 'y' : 'n').', containers='.($containersSame ? 'y' : 'n').")\n");
    exit(1);
}

printf("ok  @%d  %s → %s\n", $at, $from, $to);

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-10-pass77.docx');
$z2 = new ZipArchive();
$z2->open($src);
$z2->addFromString('word/document.xml', $newXml);
$z2->close();

$z3 = new ZipArchive();
$z3->open($src);
$back = $z3->getFromName('word/document.xml');
$z3->close();

if ($back !== $newXml) {
    fwrite(STDERR, "REFUSE: disk does not hold the edited document.xml\n");
    exit(1);
}

echo "Wrote {$src}\n";
