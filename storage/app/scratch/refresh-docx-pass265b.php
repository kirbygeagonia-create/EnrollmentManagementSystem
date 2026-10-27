<?php

/**
 * The one AdmissionController pointer pass 265 deliberately withheld, taken now that its span is measured.
 *
 * Pass 265 re-took fourteen pointers and left `487-503` because the requirement-verification action had not
 * been read against the file — writing a guess into the authority document is the failure this repository
 * keeps refusing. `verifyRequirement()` opens at :494 and closes at :510, with the status write at :504,
 * which is the claim §10's row makes ("submissionStatus becomes verified or rejected with remarks").
 *
 * Usage: php storage/app/scratch/refresh-docx-pass265b.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass265b-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$path = 'app/Http/Controllers/Admission/AdmissionController.php';
$lines = explode("\n", (string) file_get_contents($root.'/'.$path));

$assertions = [
    494 => ['public function verifyRequirement(', 'the action §10 credits with verifying or rejecting a requirement'],
    496 => ["authorize('verifyRequirements'", 'the right the route asks as well'],
    504 => ["'submissionStatus' =>", 'the write the row names: verified or rejected'],
    505 => ["'remarks' => \$request->remarks", 'the remarks the row says travel with it'],
    510 => ['}', 'the close of that action'],
];

foreach ($assertions as $number => [$mustContain, $why]) {
    $text = trim($lines[$number - 1] ?? '');

    if (! str_contains($text, $mustContain)) {
        fwrite(STDERR, "REFUSED: {$path}:{$number} reads '{$text}', expected {$mustContain} — {$why}\n");

        exit(1);
    }

    printf("ok   AdmissionController.php:%-5d %s\n", $number, $text);
}

$edits = [['AdmissionController.php:487-503', 'AdmissionController.php:494-510', null, 1]];

echo "\n";

docxApply($docx, $backup, $edits, [], $apply);

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$again = new ZipArchive;
$again->open($docx);
$after = (string) $again->getFromName('word/document.xml');
$again->close();

foreach (['AdmissionController.php:487-503' => 0, 'AdmissionController.php:494-510' => 1] as $needle => $expected) {
    $seen = substr_count($after, $needle);

    if ($seen !== $expected) {
        fwrite(STDERR, "REFUSED (post-write): '{$needle}' occurs {$seen}, expected {$expected}.\n");

        exit(1);
    }

    printf("after %-34s %d\n", $needle, $seen);
}

echo "\npass 265b applied: the last AdmissionController pointer answers the file, and the sweep is back to zero.\n";
