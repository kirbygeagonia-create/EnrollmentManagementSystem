<?php

/**
 * Pass 107b — the quotation marks pass 107 typed.
 *
 * The document quotes a console line with “curly” double quotes everywhere it does so (the same sentence
 * carries “Created all 14 functional desk and faculty roles”). Pass 107 inserted two quotes with ASCII
 * apostrophes, so the paragraph now shows two typographies side by side. Nothing else changes: one
 * contiguous slice, occurrence count asserted, structure guards re-run.
 *
 * Proved by mutate-pass107b-guard.php before its clean verdict is believed. DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open source\n");
    exit(1);
}
$original = $xml = $zip->getFromName('word/document.xml');
$zip->close();

$fails = 0;

$from = "'Created N permissions across M modules' and 'Created N desk and faculty roles with module permissions'";
$to = "\u{201C}Created N permissions across M modules\u{201D} and \u{201C}Created N desk and faculty roles "
    ."with module permissions\u{201D}";

$n = substr_count($xml, $from);
if ($n !== 1) {
    fwrite(STDERR, "REFUSE: anchor appears {$n} time(s), expected 1\n");
    exit(1);
}

$at = strpos($xml, $from);
if (substr($xml, $at, strlen($from)) !== $from) {
    fwrite(STDERR, "REFUSE: the slice at byte {$at} is not the anchor\n");
    exit(1);
}

// The quote characters must stay inside the run that holds them, and no tag may be swallowed: the span
// this replaces contains no '<', which is what makes a whole-slice swap safe here.
if (str_contains($from, '<')) {
    fwrite(STDERR, "REFUSE: the anchor contains markup, so a slice swap is the wrong tool\n");
    exit(1);
}

$xml = substr_replace($xml, $to, $at, strlen($from));
printf("replaced at byte %d: %d → %d chars\n", $at, strlen($from), strlen($to));

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'no table row gained or lost' => substr_count($original, '<w:tr>') === substr_count($xml, '<w:tr>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'no markdown backticks crept in' => substr_count($xml, '`') === substr_count($original, '`'),
    // Exactly four ASCII apostrophes were traded for four curly quotes — nothing else moved.
    'the ASCII pair is gone' => substr_count($xml, $from) === 0,
    'the sentence reads with the document’s own quotes' => substr_count($xml, "\u{201C}Created N permissions across M modules\u{201D}") === 1
        && substr_count($xml, "\u{201C}Created N desk and faculty roles with module permissions\u{201D}") === 1,
    'the rest of the paragraph is intact' => str_contains($xml, 'are read from the database at RbacSeeder.php:198 and :483 instead of typed in here.')
        && str_contains($xml, 'seventeen Role::updateOrCreate calls run from RbacSeeder.php:223 to :474'),
    'no stray ASCII quote count shift' => substr_count($xml, "'") === substr_count($original, "'") - 4,
    'the document still parses' => simplexml_load_string($xml) !== false,
];

foreach ($guards as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $fails++;
    } else {
        printf("guard ok %s\n", $name);
    }
}

if ($fails > 0) {
    printf("\n%d problem(s) — nothing was written\n", $fails);
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-13-pass107b.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
