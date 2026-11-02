<?php

/**
 * Put the orphan association back where it belongs: inside the global boundary diagram, once.
 *
 * The first repair (lane209) replaced an association LINE that several desk blocks also contain, so `str_replace`
 * wrote the new edge into every one of them — and the figure parser refused with "names a node that is never
 * declared", which is the guard doing exactly its job. The lesson is the one this repository already writes for
 * docx needles: a needle's count is not its owner list. A block-scoped edit is the only safe shape here.
 *
 * This pass removes every stray insertion, then re-inserts exactly one inside the §3 block's own span, and proves
 * the placement by re-reading the span rather than the whole file. Usage: php
 * storage/app/scratch/lane210-place-orphan-association.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
$apply = in_array('--apply', array_slice($GLOBALS['argv'], 1), true);
$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

$file = 'Documentation/Use-Case-Diagram-Guide.md';
$text = (string) file_get_contents($root.'/'.$file);
$edge = 'A_Staff --- UC_Srch360';
$strays = substr_count($text, $edge);
if ($strays < 1) {
    $fail('no insertion of the orphan association exists — this pass would have nothing to place.');
}
printf("read: %d occurrence(s) of [%s]; the file has %d mermaid block(s)\n", $strays, $edge, substr_count($text, "```mermaid"));

// Strip every inserted line, then re-add exactly one inside the global block.
$stripped = preg_replace('/\n    '.preg_quote($edge, '/').'/u', '', $text);
if (substr_count($stripped, $edge) !== 0) {
    $fail('the stray lines did not all come out; refusing to write a half-cleaned file.');
}
$text = $stripped;

// Locate the §3 block's own span: heading, then its fenced mermaid block.
$headPos = strpos($text, '## 3. SEAIT EMS Global System Boundary Diagram');
if ($headPos === false) {
    $fail('the global boundary heading is gone — the document changed shape.');
}
$open = strpos($text, '```mermaid', $headPos);
if ($open === false) {
    $fail('no mermaid fence follows the global heading.');
}
$openEnd = strpos($text, "\n", $open) + 1;
$close = strpos($text, '```', $openEnd);
if ($close === false) {
    $fail('the global block has no closing fence.');
}
$span = substr($text, $openEnd, $close - $openEnd);
if (substr_count($span, 'UC_Srch360') !== 1) {
    $fail('the global block declares UC_Srch360 '.substr_count($span, 'UC_Srch360').' time(s), wanted exactly 1.');
}
// The actor may already be in place from the previous repair — that insertion used a unique anchor and landed
// once, which is correct. So the rule is "exactly one", not "none": add it when absent, refuse when doubled.
$actorDecl = '        A_Staff["Institutional Staff (Any Desk)"]';
$actorAnchor = '        A_AdmStaff["System Administrator"]';
$haveActor = substr_count($span, 'A_Staff[');
if ($haveActor === 0) {
    if (substr_count($span, $actorAnchor) !== 1) {
        $fail('the actor-panel anchor occurs '.substr_count($span, $actorAnchor).' time(s) inside the global block, wanted 1.');
    }
    $spanNew = str_replace($actorAnchor, $actorAnchor."\n".$actorDecl, $span);
} elseif ($haveActor !== 1) {
    $fail('the global block declares A_Staff '.$haveActor.' time(s), wanted 0 or 1.');
} else {
    $spanNew = $span;
}

if (preg_match_all('/^    A_[A-Za-z0-9]+ --- UC_[A-Za-z0-9]+$/m', $spanNew, $mm) < 1) {
    $fail('the global block holds no association line to anchor the new one.');
}
$last = end($mm[0]);
if (substr_count($spanNew, "\n".$last) < 1) {
    $fail('the chosen anchor line cannot be located in the block text.');
}
// Replace only the LAST occurrence inside the span, so the insertion cannot drift into an earlier panel.
$at = strrpos($spanNew, "\n".$last);
$spanNew = substr_replace($spanNew, "\n".$last."\n    ".$edge, $at, strlen("\n".$last));

if (substr_count($spanNew, $edge) !== 1) {
    $fail('the block now holds '.substr_count($spanNew, $edge).' copies of the association, wanted 1.');
}
if (substr_count($spanNew, 'A_Staff[') !== 1) {
    $fail('the block now declares the staff actor '.substr_count($spanNew, 'A_Staff[').' time(s), wanted 1.');
}
$out = substr($text, 0, $openEnd).$spanNew.substr($text, $close);
if (substr_count($out, $edge) !== 1) {
    $fail('the whole file holds '.substr_count($out, $edge).' copies of the association, wanted exactly 1.');
}

printf("planned: 1 actor line and 1 association inserted inside the §3 block (%d bytes → %d bytes)\n",
    strlen((string) file_get_contents($root.'/'.$file)), strlen($out));

if (! $apply) {
    echo "DRY RUN — nothing written.\n";
    exit(0);
}
$backup = $root.'/storage/app/scratch/lane210-backup-'.date('Ymd-His').'.md';
if (! copy($root.'/'.$file, $backup)) {
    $fail('the backup copy could not be written.');
}
$w = @file_put_contents($root.'/'.$file, $out);
if ($w !== strlen($out)) {
    copy($backup, $root.'/'.$file);
    $fail('the write returned '.var_export($w, true).' — restored from '.$backup);
}
$again = (string) file_get_contents($root.'/'.$file);
if (substr_count($again, $edge) !== 1 || substr_count($again, 'A_Staff["Institutional Staff (Any Desk)"]') < 1) {
    copy($backup, $root.'/'.$file);
    $fail('read-back refused — restored from '.$backup);
}
printf("written; the association exists once, inside the global block; backup %s\n", basename($backup));
