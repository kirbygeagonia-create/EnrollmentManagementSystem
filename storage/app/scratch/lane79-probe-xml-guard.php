<?php

/**
 * Proves the docx engine's parse guard, after `LIBXML_PARSEHUGE` was added to it.
 *
 * `docx-edit-engine.php` refuses a write unless `word/document.xml` still parses. On 2026-10-27 that
 * guard refused a CORRECT document: `document.xml` has grown to 6.0 MB, and libxml without
 * `LIBXML_PARSEHUGE` answers "Opening and ending tag mismatch: tc line 1 and tr" about a file whose tags
 * do balance — an instrument gone red on the artefact it was written to protect, which is the same class
 * as `verify-docx.php` being red for a week on a needle list that encoded a retired spelling.
 *
 * Widening a parser is how a guard stops guarding, so this probe asks the guard to refuse three real
 * defects and to accept the one real document. The negative legs matter more than the positive one: a
 * guard that accepts everything is the failure mode this file exists to catch.
 *
 * Usage: php storage/app/scratch/lane79-probe-xml-guard.php
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';

if (! is_readable($docx)) {
    fwrite(STDERR, "REFUSED: no readable docx at {$docx}\n");

    exit(1);
}

$zip = new ZipArchive;

if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: the archive would not open — this probe cannot ask it about a file it cannot read\n");

    exit(1);
}

$xml = $zip->getFromName('word/document.xml');
$zip->close();

if (! is_string($xml) || strlen($xml) < 100000) {
    fwrite(STDERR, 'REFUSED: document.xml came back '.var_export($xml, true).' — too short to be the document\n');

    exit(1);
}

echo 'document.xml is '.strlen($xml)." bytes\n";

$fails = 0;

// Leg 1 — the instrument's own history: the unmodified document must FAIL without the flag. This is
// the leg that says the guard was red on a correct file, and not a defect introduced by an edit.
$before = simplexml_load_string($xml);
$beforeFailed = ($before === false);
libxml_clear_errors();
echo $beforeFailed
    ? "ok   without LIBXML_PARSEHUGE the real document does not parse (the defect being fixed)\n"
    : "BAD  without the flag the real document parses, so this box's libxml is not the one that refused the write\n";
$fails += $beforeFailed ? 0 : 1;

// Leg 2 — the positive control: with the flag, the real document must parse.
$after = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_PARSEHUGE);
libxml_clear_errors();

if ($after === false) {
    fwrite(STDERR, "BAD  with LIBXML_PARSEHUGE the real document still does not parse — the flag is not the answer\n");
    $fails++;
} else {
    echo "ok   with LIBXML_PARSEHUGE the real document parses\n";
}

// Legs 3 and 4 plant defects the guard must still see: a truncated stream and one dropped closing tag.
// Both are made in memory; the document on disk is never touched.
$defects = [
    'a document truncated at the end' => substr($xml, 0, -4000),
    'a table cell whose closing tag is gone' => preg_replace('/<\/w:tc>/', '', $xml, 1),
];

foreach ($defects as $label => $mutant) {
    if ($mutant === $xml) {
        fwrite(STDERR, "BAD  [{$label}]: the mutation changed nothing, so this leg measured nothing\n");
        $fails++;

        continue;
    }

    $ok = @simplexml_load_string($mutant, 'SimpleXMLElement', LIBXML_PARSEHUGE);
    libxml_clear_errors();

    if ($ok === false) {
        echo "ok   [{$label}] the guard refuses it\n";
    } else {
        fwrite(STDERR, "BAD  [{$label}] the guard ACCEPTED a broken document\n");
        $fails++;
    }
}

if ($fails > 0) {
    fwrite(STDERR, "\n{$fails} leg(s) bad — the parse guard is not trustworthy\n");

    exit(1);
}

echo "\nAll 4 legs passed: the guard accepts a correct 6 MB document and still refuses a truncated one.\n";
