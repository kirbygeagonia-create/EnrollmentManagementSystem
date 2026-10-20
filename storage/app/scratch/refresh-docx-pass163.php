<?php

/**
 * Pass 163 — one count in §29's lane narrative was written as the harness's number instead of the report's.
 *
 * The paragraph says the pipeline's desk report "carries four Ledger lines", which is the number of lines
 * `mutate-lane54-report.php` owns. Measured against the file it describes: `git show HEAD:tools/seed_full_demo_pipeline.php`
 * defines six `'Ledger —' =>` keys (the two lane 53 left plus the four lane 54 added) and the working tree defines
 * seven, because lane 56 added the receipt it had to re-mark. The same sentence was already corrected in AGENTS.md
 * and DEPLOYMENT.md by storage/app/scratch/lane56e-fix-report-count.php; this is the authority document following the
 * code in the same batch rather than two passes later.
 *
 * Evidence gate: the batch's own harness log must be green before this writes. DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};

// The measure this sentence is being re-taken from: the pipeline's own bytes, at HEAD and in the working tree.
$headTool = $root.'/storage/app/scratch/lane56e-head-pipeline.php';
$live = (string) file_get_contents($root.'/tools/seed_full_demo_pipeline.php');
$headBytes = (string) @file_get_contents($headTool);
if ($headBytes === '') {
    echo "REFUSED: the HEAD export of the pipeline is missing at {$headTool} — this pass cannot measure a count without both.\n";
    exit(1);
}
$headCount = substr_count($headBytes, "'Ledger —");
$liveCount = substr_count($live, "'Ledger —");
$guard($headCount === 6, "HEAD defines six `'Ledger —' =>` report keys (read {$headCount})");
$guard($liveCount === 7, "the working tree defines seven (read {$liveCount})");

$evidence = (string) @file_get_contents($root.'/storage/app/scratch/lane56-mut-ledger-rerun.txt');
$guard(substr_count($evidence, '  ok  ') === 11 && substr_count($evidence, 'FAIL') === 0
    && substr_count($evidence, 'planted receipt moved the audit rule') === 1,
    'lane 56’s harness log reads 11 ok, no FAIL, and its own conclusion line');

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$partCount = $zip->numFiles;
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$new = $xml;

$old = 'so its desk report now carries four Ledger lines — an unpriceable fee sheet, a paid step it had to write, '
    .'a returning claim with no year behind it, receipts exceeding their sheet — and each was proved able to be '
    .'non-zero by handing it exactly that defect, one case to a freshly built schema, with a clean control beside it '
    .'reading 0 on all four';
$newText = 'so its desk report now carries a Ledger line for each state it repairs — six after that pass, and a '
    .'seventh since lane 56 added the receipt it had to re-mark — and the four this harness owns (an unpriceable fee '
    .'sheet, a paid step it had to write, a returning claim with no year behind it, receipts exceeding their sheet) '
    .'were each proved able to be non-zero by handing it exactly that defect, one case to a freshly built schema, '
    .'with a clean control beside it reading 0 on all four';

$n = substr_count($xml, $old);
$guard($n === 1, sprintf('the sentence is one contiguous run, present once (read %d)', $n));
$guard(! str_contains($newText, '<') && ! str_contains($newText, '>'), 'the replacement carries no raw angle bracket');
$guard(str_contains($xml, 'reading 0 on all four'), 'the harness’s own four-claim survives beside it');

if ($n === 1) {
    $new = str_replace($old, $newText, $xml);
}
$guard(substr_count($new, 'carries four Ledger lines') === 0, 'the old count is gone from the document');
$guard($new !== $xml, 'the bytes were rewritten ('.(strlen($new) - strlen($xml)).' length change)');

if ($fails > 0) {
    printf("\n%d guard(s) failed — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN. Re-run with --apply.\n";
    exit(0);
}

$backup = $root.'/storage/app/scratch/ems-docx-pre-pass163.docx';
copy($src, $backup);
$out = $root.'/Documentation/.ems-docx-pass163.tmp';
$write = new ZipArchive();
if ($write->open($out, ZipArchive::CREATE) !== true) {
    echo "REFUSED: cannot build the replacement archive.\n";
    exit(1);
}
$read = new ZipArchive();
$read->open($src);
for ($i = 0; $i < $read->numFiles; $i++) {
    $name = $read->getNameIndex($i);
    $write->addFromString($name, $name === 'word/document.xml' ? $new : $read->getFromIndex($i));
}
$write->close();
$read->close();
if (! rename($out, $src)) {
    echo "REFUSED: could not move the new archive; backup at {$backup}.\n";
    exit(1);
}
$check = new ZipArchive();
$check->open($src);
$guard($check->numFiles === $partCount, 'the archive still holds '.$partCount.' parts');
$round = (string) $check->getFromName('word/document.xml');
$check->close();
$guard(hash('sha256', $round) === hash('sha256', $new), 'word/document.xml on disk is the bytes this pass built');
if ($fails > 0) {
    printf("\n%d guard(s) failed after writing; backup at %s\n", $fails, $backup);
    exit(1);
}

printf("\nPass 163 written; backup at %s\n", $backup);
exit(0);
