<?php

/**
 * Pass 138c — the new revision row must not reintroduce a phrase the document retired.
 *
 * verify-docx.php pins `'five gates' => 0`: the gate list has changed membership across the revisions, so the
 * document names the checks instead of counting them. Pass 138b's prose wrote "all five gates green" and the
 * stale-string guard caught it — which is the check doing its job on text that was true but un-idiomatic.
 *
 * One contiguous run, one edit, guarded on the raw occurrence count.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$from = 'The suite stands at 725 tests and 4570 assertions, all five gates green, and the document itself '
    .'was swept by line, by meaning and by §-reference after every pass in this batch.';
$to = 'The suite stands at 725 tests and 4570 assertions, and every check the project gates a merge on is '
    .'green: Pest whole, PHPStan at zero errors, Pint in --test mode, ESLint at zero warnings and the Vite '
    .'build. The document itself was swept by line, by meaning and by §-reference after every pass in this batch.';

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open docx\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};

$escapedFrom = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $from);
$escapedTo = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $to);

$hits = substr_count($xml, $escapedFrom);
$guard($hits === 1, "the sentence occurs exactly once as one contiguous run (found {$hits})");
$guard(substr_count($xml, 'five gates') === 1, 'exactly one "five gates" exists in the document before the edit');

$new = $hits === 1 ? str_replace($escapedFrom, $escapedTo, $xml) : $xml;

$guard(! str_contains($new, 'five gates'), 'the edited document says no gate count');
$guard(substr_count($new, $escapedTo) === 1, 'the replacement landed once');
$guard(strlen($new) === strlen($xml) + strlen($escapedTo) - strlen($escapedFrom), 'the byte delta is exactly the text swap');

libxml_use_internal_errors(true);
$doc = new DOMDocument;
$guard($doc->loadXML($new) === true, 'document.xml parses');
libxml_clear_errors();

printf("\nswap: %d bytes → %d bytes\n", strlen($escapedFrom), strlen($escapedTo));

if ($fails > 0) {
    fwrite(STDERR, "{$fails} guard(s) failed — nothing written\n");
    exit(1);
}
if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-17-pass138c.docx');

$z0 = new ZipArchive();
$z0->open($src);
$partsBefore = count($z0);
$z0->close();

$zip = new ZipArchive();
if ($zip->open($src, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "cannot open the docx for updating — restore the backup\n");
    exit(1);
}
$zip->addFromString('word/document.xml', $new);
$zip->close();

$check = new ZipArchive();
$check->open($src);
$onDisk = $check->getFromName('word/document.xml');
$partsAfter = count($check);
$check->close();
if ($onDisk !== $new) {
    fwrite(STDERR, "the file on disk does not hold the edited document.xml — restore the backup\n");
    exit(1);
}
if ($partsAfter !== $partsBefore) {
    fwrite(STDERR, "the archive went from {$partsBefore} parts to {$partsAfter} — restore the backup\n");
    exit(1);
}

printf("WROTE %s — byte-verified, all %d parts present\n", $src, $partsAfter);
exit(0);
