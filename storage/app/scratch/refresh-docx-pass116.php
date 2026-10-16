<?php

/**
 * Pass 116 — one citation my own insertion moved, and one sentence about what the check now means.
 *
 * `Preflight.php` grew 43 lines below line 238, so the disk rule the document cites at `:302-325` is now at
 * 344-367 (measured: `uploadDiskIsPrivate()` opens at 344 and its brace closes at 367 — cited by meaning,
 * not by arithmetic, which is the rule AGENTS.md writes because a shifted range can stay in bounds and land
 * on something else). The three citations before line 238 — `:83-99`, `:117-132`, `:147-174` — were checked
 * and are unshifted, and the sweep tool cannot tell any of this, since it only proves a range is in bounds.
 *
 * The second half is not a retime: the app now forces its own session zone, so `Clocks agree` reads OK even
 * on a server nobody configured. The sentence has to say the note is the signal, or the next operator reads
 * green and stops looking.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$zip = new ZipArchive;
$zip->open($src) !== true && exit(1);
$original = $xml = $zip->getFromName('word/document.xml');
$zip->close();

$fails = 0;
$old = 'the disk rule at :302-325)';
$new = 'the disk rule at :344-367; since 2026-10-14 the application also forces its own session zone, so an '
    .'unconfigured server reads OK here and the row then carries a note naming both zones and the clients '
    .'left outside it — that note, not the colour of the line, is what the operator acts on)';

if (substr_count($xml, $old) !== 1) {
    fwrite(STDERR, 'REFUSE: the disk-rule citation is not where this pass expects ('.substr_count($xml, $old)."]\n");
    exit(1);
}
$off = strpos($xml, $old);
$xml = substr_replace($xml, $new, $off, strlen($old));

$L = file($root.'/app/Console/Commands/Preflight.php');
$span = implode(' ', array_map('trim', array_slice($L, 343, 24)));
$guards = [
    'the new range is inside the disk rule' => str_contains($span, 'private function uploadDiskIsPrivate(): array')
        && str_contains($span, "'Upload disk'"),
    'the new range does not run past the file' => 367 <= count($L),
    'the three earlier citations were left alone' => substr_count($xml, 'Preflight.php:83-99') === 1
        && substr_count($xml, ':117-132') === 1 && substr_count($xml, ':147-174') === 1,
    'the stale range is gone' => substr_count($xml, ':302-325') === 0,
    'the note sentence landed once' => substr_count($xml, 'that note, not the colour of the line') === 1,
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'no table row gained or lost' => substr_count($original, '<w:tr>') === substr_count($xml, '<w:tr>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'no markdown backticks crept in' => substr_count($xml, '`') === substr_count($original, '`'),
    'the document still parses' => simplexml_load_string($xml) !== false,
];

foreach ($guards as $name => $ok) {
    $ok ? printf("guard ok %s\n", $name) : (fwrite(STDERR, "REFUSE guard: {$name}\n") + $fails++);
}

printf("\ncitation byte %d  :302-325 → :344-367 (+%d bytes)\n", $off, strlen($new) - strlen($old));
if ($fails > 0) {
    exit(1);
}
if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}
copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-14-pass116.docx');
$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();
printf("WROTE (%+d bytes)\n", strlen($xml) - strlen($original));
