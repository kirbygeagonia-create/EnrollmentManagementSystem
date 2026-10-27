<?php

/**
 * The eighth citation problem, and the one pass 258's own list missed.
 *
 * `sweep-docx-citations.php` reported 7 line problems after this batch moved lines; pass 258 re-pointed
 * seven and left one, because the sweep prints a problem per *line it can bind* and this row's range
 * appeared once. §28's BR8 + BR34 row says "RegistrarPolicy.php:63-69 and the same rule again at
 * `RegistrarController.php:121-138`".
 *
 * Reading HEAD's copy of the file shows the pointer was never about the clearance rule: lines 121-138 at
 * HEAD are the `Inertia::render('Registrar/Show', …)` props array. It landed on live lines of the right
 * file, so no bounds sweep, no meaning sweep and no §-reference checker could see it — the shape this file
 * calls a wrong target. What the row means is where the controller asks the same question the policy asks,
 * which is `blockingReasons()`: the verdict at :232, the refusal branch at :234, the sentence it writes at
 * :235. Live 121 is now a blank line, so the stale number finally became visible once the G-1 and G-10
 * props pushed the array down.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass258b.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass258b-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$controller = 'app/Http/Controllers/Registrar/RegistrarController.php';
$lines = explode("\n", file_get_contents($root.'/'.$controller));

$assertions = [
    [206, 'private function blockingReasons(', 'the method that re-applies the gate for the desk'],
    [232, "EnrollmentReadiness::clearanceVerdict", 'the same call the policy makes'],
    [234, "! \$clearance['passed']", 'the branch that acts on the verdict'],
    [235, "\$reasons['clearance_verified']", 'the sentence the desk reads when the clearance fails'],
    [121, '', 'the old head: a blank line today, a props array at HEAD — never a clearance rule'],
];

foreach ($assertions as [$number, $mustContain, $why]) {
    $text = trim($lines[$number - 1] ?? '');

    if ($mustContain === '' ? $text !== '' : ! str_contains($text, $mustContain)) {
        fwrite(STDERR, "REFUSED: {$controller}:{$number} reads '{$text}', expected '{$mustContain}' — {$why}\n");

        exit(1);
    }

    printf("ok   RegistrarController.php:%-5d %s\n", $number, $text === '' ? '(blank line)' : $text);
}

// And what HEAD really held at the old number — printed, because the claim this pass repairs is about a
// pointer that was wrong by meaning while every checker agreed with it.
$headPath = $root.'/storage/app/scratch/lane79-head-RegistrarController.php';

if (! is_file($headPath)) {
    fwrite(STDERR, "REFUSED: the HEAD export is missing. Take it first with:\n"
        .'  git show HEAD:app/Http/Controllers/Registrar/RegistrarController.php > storage/app/scratch/lane79-head-RegistrarController.php'."\n");

    exit(1);
}

$head = explode("\n", file_get_contents($headPath));
$headWindow = trim(implode(' ', array_slice($head, 120, 18)));

foreach (['Inertia::render', 'blockingReasons', 'gradeHistory'] as $needle) {
    if (! str_contains($headWindow, $needle)) {
        fwrite(STDERR, "REFUSED: HEAD's :121-138 does not contain {$needle}, so this pass reads a different layout than the one the row was written against.\n");

        exit(1);
    }
}

if (str_contains($headWindow, 'clearanceVerdict')) {
    fwrite(STDERR, "REFUSED: HEAD's :121-138 already holds the clearance verdict, so the old pointer was true and this pass has nothing to repair.\n");

    exit(1);
}

echo "\nHEAD :121-138 is the Registrar/Show props array (Inertia::render, blockingReasons, gradeHistory) and names no clearance verdict.\n\n";

docxApply($docx, $backup, [['RegistrarController.php:121-138', 'RegistrarController.php:232-235', null, 1]], [], $apply);

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$zip = new ZipArchive;

if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED (post-write): cannot open the written archive for reading.\n");

    exit(1);
}

$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach (['RegistrarController.php:121-138' => 0, 'RegistrarController.php:232-235' => 1] as $needle => $expected) {
    $seen = substr_count($xml, $needle);

    if ($seen !== $expected) {
        fwrite(STDERR, "REFUSED (post-write): '{$needle}' occurs {$seen} time(s), expected {$expected}.\n");

        exit(1);
    }

    printf("after %-32s occurs %d as intended\n", $needle, $seen);
}

echo "\npass 258b applied: the BR8 + BR34 row now names the controller's own clearance verdict.\n";
