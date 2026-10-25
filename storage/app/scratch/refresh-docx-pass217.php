<?php

/**
 * Pass 217 — three register pointers whose owners were verified line by line.
 *
 *   `:466-490`  the Evaluation issue row cites that file's profile update and `Guardians::create()`; the workflow
 *               rows and `formSignedDate` are written in EvaluationController.php:796-811 — the transaction that
 *               sets the date, calls `createWorkflow()` when no workflow exists, then signs the Department
 *               Evaluation step. Unique in document.xml, so a text edit reaches it.
 *   `:468-479`  the split-subject limit row. The rule is stated in code at BlockingController.php:524-531
 *               ("ponytail: one scheduleId per subject row — a lecture+lab subject's second meeting can't be
 *               stamped"). Also unique, so a text edit.
 *   `:328`      the student-type row cites AdmissionController.php:328, an upload record's `uploadedDate`, for the
 *               copy at AdmissionController.php:411. `:328` occurs six times in the file and `:328 )` once — and
 *               the one copy of `:328 )` is the *print* paragraph's citation for a different controller, which is
 *               how pass 215 collided. So this one goes through the cell kind: a unique phrase in the row picks the
 *               row, the cell's own leading words pick the cell, and the text is rebuilt from that cell's runs.
 *
 * Every new string comes out of document.xml, never the flat dump (which joins runs with a space), and the pass
 * refuses unless each substitution changes its block and each anchor is unique.
 *
 *   php storage/app/scratch/refresh-docx-pass217.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass217.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$code = [
    'adm' => 'app/Http/Controllers/Admission/AdmissionController.php',
    'eval' => 'app/Http/Controllers/Evaluation/EvaluationController.php',
    'block' => 'app/Http/Controllers/Blocking/BlockingController.php',
];
$L = [];
foreach ($code as $k => $rel) {
    $L[$k] = (array) file($root.'/'.$rel);
}
$claims = [
    ['adm', 411, "'studentType' => \$admission->applicantType->value"],
    ['adm', 328, "'uploadedDate' => now(),"],
    ['eval', 798, "'formSignedDate' => now(),"],
    ['eval', 803, 'createWorkflow($enrollment)'],
    ['eval', 808, 'signStepByOffice($enrollment->enrollmentworkflow'],
    ['eval', 811, "return back()->with('success', 'Evaluation signed"],
    ['block', 524, 'Assign to block and schedule'],
    ['block', 529, 'one scheduleId per subject row'],
    ['block', 531, 'schedule pivot is the upgrade path'],
];
$fails = 0;
foreach ($claims as [$k, $n, $needle]) {
    $t = trim($L[$k][$n - 1] ?? '');
    $ok = str_contains($t, $needle);
    printf("  %s %-6s :%-4d [%s]\n", $ok ? 'ok  ' : 'BAD ', $k, $n, mb_substr($t, 0, 56));
    $fails += $ok ? 0 : 1;
}
if ($fails > 0) {
    printf("\nREFUSED: %d claimed line(s) do not carry the claim.\n", $fails);
    exit(1);
}

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

// ---------------------------------------------------------------- 1. the two unique ranges
$textEdits = [
    [':466-490', 'EvaluationController.php:796-811'],
    [':468-479', 'BlockingController.php:524-531'],
];
foreach ($textEdits as [$from, $to]) {
    $n = substr_count($xml, $from);
    printf("  needle %-12s count %d (want 1)\n", $from, $n);
    if ($n !== 1) {
        echo "REFUSED: a range is not unique.\n";
        exit(1);
    }
}

// ---------------------------------------------------------------- 2. the student-type cell
$rowAnchor = 'two vocabularies happen to share';
if (substr_count($xml, $rowAnchor) !== 1) {
    echo "REFUSED: the row anchor is not unique.\n";
    exit(1);
}
$p = strpos($xml, $rowAnchor);
$rowStart = strrpos(substr($xml, 0, $p), '<w:tr');
$rowEnd = strpos($xml, '</w:tr>', $p) + 7;
if ($rowStart === false || strpos(substr($xml, $rowStart, $p - $rowStart), '</w:tr>') !== false) {
    echo "REFUSED: the row anchor is not inside one row.\n";
    exit(1);
}
$row = substr($xml, $rowStart, $rowEnd - $rowStart);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
$hit = [];
$prefix = null;
$oldText = null;
$newText = null;
foreach ($cm[0] as $cellXml) {
    $flat = $flatten($cellXml);
    if (! str_contains($flat, ':328')) {
        continue;
    }
    $hit[] = mb_substr($flat, 0, 28);
    if (preg_match('/^\s*([A-Za-z]{6,})/', $flat, $pm) === 1) {
        $prefix = $pm[1];
        $oldText = $flat;
        $newText = str_replace(':328', 'AdmissionController.php:411', $flat);
    }
}
printf("  cells in the row carrying :328: %d\n", count($hit));
foreach ($hit as $h) {
    printf("      [%s]\n", $h);
}
if (count($hit) !== 1 || $prefix === null || $newText === $oldText) {
    echo "REFUSED: the pointer is not in exactly one pickable cell of that row.\n";
    exit(1);
}
printf("  prefix [%s]\n            old: %s\n            new: %s\n", $prefix, mb_substr((string) $oldText, 0, 64), mb_substr((string) $newText, 0, 76));

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass217.docx', $textEdits, [[$rowAnchor, $prefix, (string) $newText]], $apply);
