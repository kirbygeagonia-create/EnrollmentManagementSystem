<?php

/**
 * Pass 212 — the §24 workflow row cited three lines of the fee controller for a rule that lives in the step builder.
 *
 * ROW 2431 describes the desk that finalizes an enrollment and says the second box is signed for a first-year and
 * skipped for a continuing student, citing `:266`, `:280`, `:285`. Opened against `AssessmentController.php`: :266
 * is inside `applyScholarship()`'s balance recompute, :280-285 is the docblock of `withdrawScholarship()`. The rule
 * itself is `WorkflowService.php:52-71` — `$includesAssessment = in_array($enrollment->studentType->value,
 * ['firstYear', 'transferee'], true)`, and when it is false the box is filtered out of the list before the labels
 * are mapped. The citation was pointing at live code in a file whose name the row does not even carry; this is the
 * fourth wrong target the same method found.
 *
 * The second edit names `clearance-slip.blade.php` for the term reads in §25's slip paragraph, which pass 210's
 * naming left only partly fixed because `(:46,50` opens a run of its own.
 *
 * Both rewrites are built out of document.xml's own runs and the pass refuses unless the substitution actually
 * changed the string, so a mutation that matches nothing cannot report success. Deriving them from the flat dump
 * was the first attempt and is wrong: `dump-docx-flat.php` joins `<w:t>` runs with a space — the lane 69 fix that
 * stops `strip_tags()` welding two filenames into one — so a string copied out of the dump invents spaces the
 * document never had. The engine's dry run proved it by printing `pending→…→ assessed;` for the XML against
 * `pending →…→ assessed ; box 2` in the dump.
 *
 *   php storage/app/scratch/refresh-docx-pass212.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass212.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

// ------------------------------------------------------------------ 1. the code behind the new targets
$wf = (array) file($root.'/app/Services/WorkflowService.php');
$claims = [
    [52, 'private function stepsFor('],
    [54, "['firstYear', 'transferee']"],
    [66, 'if (! $includesAssessment)'],
    [70, '));'],
    [71, '}'],
    // what the document cites TODAY, asserted so the pass proves the wrong target rather than just claiming it
    [266, '->held()'],
];
$fails = 0;
foreach ($claims as [$n, $needle]) {
    $t = trim($wf[$n - 1] ?? '');
    // :266 belongs to AssessmentController, so check it there instead
    if ($n === 266) {
        $a = (array) file($root.'/app/Http/Controllers/Assessment/AssessmentController.php');
        $t = trim($a[265] ?? '');
    }
    $ok = str_contains($t, $needle);
    printf("  %s :%-4d [%s]\n", $ok ? 'ok  ' : 'BAD ', $n, mb_substr($t, 0, 60));
    $fails += $ok ? 0 : 1;
}
if ($fails > 0) {
    echo "REFUSED: a claimed line does not carry the claim.\n";
    exit(1);
}

// ------------------------------------------------------------------ 2. rebuild both targets from the XML itself
// NOT from the flat dump: `dump-docx-flat.php` joins `<w:t>` runs with a space (that is the lane 69 fix which
// stops `strip_tags()` welding two words into one filename), so a string copied out of the dump invents spaces the
// document never had. The engine's own dry run proved it: the cell reads `pending→…→ assessed;` in
// document.xml and `pending →…→ assessed ; box 2` in the dump. Flatten the runs the way the engine does.
$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$flatten = static function (string $block): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $block, $tm);

    return html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1);
};

$cellAnchor = 'signed for a first-year';
$paraAnchorProbe = 'term-specific (§16)';
foreach ([['cell', $cellAnchor], ['paragraph', $paraAnchorProbe]] as [$what, $a]) {
    $n = substr_count($xml, $a);
    printf("  %s anchor [%s] count %d (want 1)\n", $what, $a, $n);
    if ($n !== 1) {
        echo "REFUSED: an anchor is not unique, so the wrong block could be rewritten.\n";
        exit(1);
    }
}

// the cell
$p = strpos($xml, $cellAnchor);
$cStart = strrpos(substr($xml, 0, $p), '<w:tc>');
$cEnd = strpos($xml, '</w:tc>', $p) + 7;
if ($cStart === false || strpos(substr($xml, $cStart, $p - $cStart), '</w:tc>') !== false) {
    echo "REFUSED: the cell anchor is not inside one <w:tc>.\n";
    exit(1);
}
$target = preg_replace('/\s+/', ' ', $flatten(substr($xml, $cStart, $cEnd - $cStart)));
$cellOld = '/\(\s*:266\s*,\s*:280\s*,\s*:285\s*\)/';
$newCell = preg_replace($cellOld, '(WorkflowService.php:52-71)', $target);
if ($newCell === null || $newCell === $target) {
    echo "REFUSED: the pointer pattern matched nothing in that cell, so the rewrite would be a no-op.\n";
    exit(1);
}
printf("  cell: %d chars → %d chars\n            old tail: %s\n            new tail: %s\n",
    strlen($target), strlen($newCell), mb_substr($target, -46), mb_substr($newCell, -40));

// the paragraph
$pp = strpos($xml, $paraAnchorProbe);
$plStart = max(strrpos(substr($xml, 0, $pp), '<w:p>'), strrpos(substr($xml, 0, $pp), '<w:p '));
$plEnd = strpos($xml, '</w:p>', $pp) + 6;
if ($plStart === false || strpos(substr($xml, $plStart, $pp - $plStart), '</w:p>') !== false) {
    echo "REFUSED: the paragraph anchor is not inside one <w:p>.\n";
    exit(1);
}
$paraText = preg_replace('/\s+/', ' ', $flatten(substr($xml, $plStart, $plEnd - $plStart)));
$paraOld = '/academicYear\s*\(\s*:46\s*,\s*50/';
$newPara = preg_replace($paraOld, 'academicYear (clearance-slip.blade.php:46,50', $paraText);
if ($newPara === null || $newPara === $paraText) {
    echo "REFUSED: the slip paragraph no longer reads as expected, so nothing would be fixed.\n";
    exit(1);
}
printf("  paragraph: %d chars → %d chars\n", strlen($paraText), strlen($newPara));

$prefix = 'pending';
if (preg_match('/^[A-Za-z]{6,}/', $target, $pm) === 1) {
    $prefix = $pm[0];
}
printf("  cell prefix [%s]\n", $prefix);

$cellEdits = [[$cellAnchor, $prefix, $newCell]];
$paraEdits = [[$paraAnchorProbe, $newPara]];

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass212.docx', [], $cellEdits, $apply, $paraEdits);
