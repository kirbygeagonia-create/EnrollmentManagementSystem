<?php

/**
 * Pass 198 — the two rows the gloss checker still complains about.
 *
 * (a) §28.5 and §27.7 both write "a balance read through Payments::held() ( PaymentPolicy::settle() )". The
 *     parenthetical names a method and gives no line, so no instrument could bind it: the nearest citation is
 *     `AccountingController.php:260-297`, and `settle()` is not in that file. It is PaymentPolicy.php:115 —
 *     `public function settle(Staffusers $user, Studentassessments $assessment): bool`.
 * (b) §21.2's curriculumsubjects row cites `DevReferenceDataSeeder.php:690` (the create, taken in pass 194) and
 *     then glosses the re-run's delete in prose without a number. The delete is :685, one line above the create,
 *     and it is the line the cell's own sentence describes.
 *
 *   php storage/app/scratch/refresh-docx-pass198.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass198.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$policy = file($root.'/app/Policies/PaymentPolicy.php');
$seeder = file($root.'/database/seeders/DevReferenceDataSeeder.php');
$fails = 0;
foreach ([[115, 'public function settle(', $policy], [685, 'Curriculumsubjects::where(', $seeder],
    [690, 'Curriculumsubjects::create(', $seeder]] as [$n, $needle, $lines]) {
    $t = trim($lines[$n - 1] ?? '');
    $ok = str_contains($t, $needle);
    printf("  %s :%-4d [%s]  want [%s]\n", $ok ? 'ok  ' : 'BAD ', $n, mb_substr($t, 0, 60), $needle);
    if (! $ok) {
        $fails++;
    }
}
if ($fails > 0) {
    echo "\nREFUSED: a target line does not carry the claim.\n";
    exit(1);
}

$textEdits = [
    ['( PaymentPolicy::settle() )', '( PaymentPolicy.php:115 )', null, 2],
    // The opening paren lives in the previous run, so the needle is the code span alone.
    ['Curriculumsubjects::where(...)-&gt;delete()', 'DevReferenceDataSeeder.php:685'],
];

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass198.docx', $textEdits, [], $apply);
