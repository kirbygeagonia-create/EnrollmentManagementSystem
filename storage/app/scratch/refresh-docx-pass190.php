<?php

/**
 * Pass 190 — name the file behind a bare pointer whose number no longer holds what the row claims.
 *
 * §28's settlement row reads `remainingBalance = max(0, assessed − coverage − waived − paid) = 0 ( :195-215 )`.
 * The pointer names no file, so no instrument has ever judged it: the lane 69 blind list surfaced it, the
 * anchor search put it in `EvaluationController.php` (wrong file, because a neighbouring row talks about
 * Evaluation), and reading the code answered the real location — `applyScholarship()` is
 * AssessmentController.php:189, it writes the grant at :256-263 and the recomputed balance at :269-272, while
 * :195-215 is its validation and duplicate-check front half. So the citation is re-pointed onto the arithmetic
 * the sentence states, and the file is named so the next drift is a failure rather than an invisible line.
 *
 *   php storage/app/scratch/refresh-docx-pass190.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass190.php --apply  # write
 */
$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$fails = 0;
$say = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD ', $what);
    if (! $ok) {
        $fails++;
    }
};

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

// Verified against the file, not inferred: the grant insert and the balance update the sentence describes.
$controller = (string) file_get_contents($root.'/app/Http/Controllers/Assessment/AssessmentController.php');
$cl = explode("\n", $controller);
$say(str_contains($cl[188], 'applyScholarship'), 'line 189 is applyScholarship() — '.trim($cl[188]));
$say(str_contains($cl[255], 'Studentscholarships::create'), 'line 256 creates the grant');
$say(str_contains($cl[270], 'remainingBalance'), 'line 271 is the recomputed balance the row states');
$say(str_contains($cl[194], 'scholarshipTypeId') || str_contains($cl[196], 'Scholarshiptypes'),
    'and :195-215 really is the validation and lookup front half');

$from = ':195-215';
$to = 'AssessmentController.php:256-272';
$n = substr_count($xml, $from);
$say($n === 1, 'the bare pointer was found exactly once (count '.$n.')');
// The document already cites this method's arithmetic once, in §28's scholarship row, for the same fact — so
// after this edit the range appears twice and that is agreement between two registers, not a duplicate defect.
$already = substr_count($xml, $to);
$say($already === 1, 'the named range is cited once before this pass, by the row that says the same thing');
foreach (['<', '>', '&'] as $ch) {
    $say(! str_contains($to, $ch), 'the replacement carries no raw '.$ch);
}
$staged = str_replace($from, $to, $xml);
$say(substr_count($staged, $to) === 2, 'both registers now cite the same span ('.substr_count($staged, $to).')');
$say(substr_count($staged, $from) === 0, 'no bare pointer of that number survives');
$say(@(new DOMDocument)->loadXML($staged) !== false, 'the staged document.xml parses');
printf("\n  staged byte delta: %+d\n", strlen($staged) - strlen($xml));
printf("%d guard(s) failed.\n", $fails);
if ($fails > 0) {
    echo "NOTHING WRITTEN.\n";
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: the settlement row's pointer would name AssessmentController.php and move onto its own arithmetic.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass190.docx');
$z = new ZipArchive;
if ($z->open($src) !== true) {
    echo "REFUSED: the docx cannot be reopened for the write — nothing written.\n";
    exit(1);
}
if ($z->getFromName('word/document.xml') !== $xml) {
    echo "REFUSED: document.xml changed under this pass since it was read — nothing written.\n";
    exit(1);
}
$z->addFromString('word/document.xml', $staged);
$z->close();
echo "WROTE {$src}\n";
