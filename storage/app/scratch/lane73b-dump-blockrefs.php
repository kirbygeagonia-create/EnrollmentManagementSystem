<?php

/**
 * Read-only, for the figure lane 37 prints: per text block, how many §-tokens the sweep would count.
 *
 * The sweep has two counting paths and that is why its `checked` figure is a classification and not a census: in a
 * block where no finding id sits beside a §-number it counts every bare `§NN` token, and in a block where at least
 * one id does sit beside one it counts only those id-bearing matches — so a §-number in the same block as a "P-19"
 * is invisible to it. Rewriting one sentence can therefore move the figure without the document losing a pointer.
 * This dumps both counts per block so two versions can be diffed and the sentence read; it requires the inventory
 * rather than re-extracting blocks, because two definitions of "block" is the defect this file has named twice.
 */
$root = dirname(__DIR__, 3);
$inv = require __DIR__.'/lane37-docx-inventory.php';

$withId = '/§\s*(\d+[A-Z]?(?:\.\d+)*)\s*([ ,(]{0,4})((?:[A-Z]{1,3}-\d{1,3})(?:\s*(?:[,…]{1,2}\s*(?:[A-Z]{1,3}-\d{1,3}))*)?)/u';
$bareOnly = '/§\s*(\d+[A-Z]?(?:\.\d+)*)/u';

$out = [];
$sum = 0;
foreach ($inv['blocks'] as $i => $text) {
    if (! str_contains($text, '§')) {
        continue;
    }
    $idMatches = preg_match_all($withId, $text, $mm) === 0 ? 0 : count($mm[0]);
    preg_match_all($bareOnly, $text, $bm);
    $counted = $idMatches === 0 ? count($bm[0]) : $idMatches;
    $sum += $counted;
    // The key a diff can read: the block's own bytes, so a re-numbered block index never looks like a change.
    $out[] = sprintf("counted=%d bare=%d id=%d\t%s", $counted, count($bm[0]), $idMatches, mb_substr($text, 0, 96));
}

$file = $argv[2] ?? $root.'/storage/app/scratch/lane73b-blockrefs.txt';
file_put_contents($file, implode("\n", $out)."\n");
printf("%d §-carrying block(s), %d counted the way the sweep counts → %s\n", count($out), $sum, basename($file));
