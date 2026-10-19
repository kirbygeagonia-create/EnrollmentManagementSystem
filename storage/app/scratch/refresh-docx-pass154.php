<?php

/**
 * Pass 154 — undo the two double-escapes pass 153 introduced.
 *
 * The edit engine escapes `&` in a text replacement and refuses a replacement carrying a raw `<` or `>`, which
 * together mean a code span with an arrow cannot be handed to it already-escaped: `&gt;` came back as
 * `&amp;gt;`, which Word would print as the literal text "&gt;". Measured against the pre-153 backup, which held
 * zero of either sequence, so these two are mine and nothing else is.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$edits = [
    ['Course &amp;amp; Year', 'Course &amp; Year'],
    ['$clearance-&amp;gt;termEnrollment()', '$clearance-&gt;termEnrollment()'],
];

$zip = new ZipArchive();

if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open docx\n");

    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$before = ['tr' => substr_count($xml, '</w:tr>'), 'tc' => substr_count($xml, '<w:tc>'), 'p' => substr_count($xml, '</w:p>'), 't' => substr_count($xml, '</w:t>')];
$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    $ok || $fails++;
};

$new = $xml;

foreach ($edits as [$from, $to]) {
    $n = substr_count($new, $from);
    $guard($n === 1, sprintf('"%s" occurs %d time(s), expected exactly 1', $from, $n));

    if ($n === 1) {
        $new = str_replace($from, $to, $new);
    }
}

foreach ($edits as [$from, $to]) {
    $guard(substr_count($new, $from) === 0, "the double escape \"$from\" is gone");
    $guard(substr_count($new, $to) >= 1, "\"$to\" is on the page");
}

// Nothing may remain double-escaped anywhere in the document, not just in the two spans above.
foreach (['&amp;amp;', '&amp;gt;', '&amp;lt;'] as $s) {
    $guard(substr_count($new, $s) === 0, "no $s survives anywhere in document.xml");
}

$after = ['tr' => substr_count($new, '</w:tr>'), 'tc' => substr_count($new, '<w:tc>'), 'p' => substr_count($new, '</w:p>'), 't' => substr_count($new, '</w:t>')];
$guard($after === $before, 'containers unmoved');

libxml_use_internal_errors(true);
$doc = new DOMDocument;
$guard($doc->loadXML($new) === true, 'document.xml parses');
libxml_clear_errors();

if ($fails > 0) {
    fwrite(STDERR, "{$fails} guard(s) failed — nothing written\n");

    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";

    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-18-pass154.docx');

$z1 = new ZipArchive();

if ($z1->open($src, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "cannot open the docx for updating — restore the backup\n");

    exit(1);
}

$z1->addFromString('word/document.xml', $new);
$z1->close();

$check = new ZipArchive();
$check->open($src);
$onDisk = $check->getFromName('word/document.xml');
$parts = count($check);
$check->close();

if ($onDisk !== $new || $parts !== 14) {
    fwrite(STDERR, "the file on disk does not hold the edit (parts: $parts) — restore the backup\n");

    exit(1);
}

printf("WROTE %s — byte-verified, all %d parts present\n", $src, $parts);
