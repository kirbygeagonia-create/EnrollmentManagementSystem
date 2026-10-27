<?php

/**
 * The docx pointers into `AdmissionController.php`, re-taken by reading after the resume edge moved the file.
 *
 * `lane76-shift-cites.php` was tried first and refused, correctly: its reference is HEAD, while these
 * numbers were keyed to the working tree, and seven of the twenty-two lines in `git diff --stat` predate the
 * edit — a uniform shift would have written a dozen live-looking-but-wrong pointers. So every value below is
 * asserted against the line it is about to name before anything is written, and the pass refuses if one
 * claim does not hold.
 *
 * Four of these are also wrong by meaning rather than only off: `:543` currently lands on the pending status
 * line, `550` on a closing brace, `554`/`555`/`557` on the derivation comment — which is precisely the shape
 * no bounds sweep can see, because the numbers land inside the right file.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass265.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass265-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$path = 'app/Http/Controllers/Admission/AdmissionController.php';
$lines = explode("\n", (string) file_get_contents($root.'/'.$path));

// [new number, must-contain, the claim the document makes of it]
$assertions = [
    519 => ['DB::transaction(', 'the one transaction the approval runs (guard SM-1)'],
    531 => ['seatHolder', 'the seat guard the bare pointer names'],
    559 => ['Enrollments::create(', 'the create the bare pointer names, and one of the two create sites'],
    572 => ['derivedYearLevel', 'the line that stamps the year level the approval derives'],
    576 => ["'studentType' => \$admission->applicantType->value", 'the copy of the applicant type onto the enrollment'],
    577 => ["'enrollmentType' => 'new'", 'written as new at intake'],
    579 => ["'enrollmentStatus' => EnrollmentStatus::Pending", 'the entry state the create assigns by assignment'],
    585 => [']);', 'the close of that create'],
    586 => ['});', 'the close of the transaction'],
    127 => ['unique:students', 'schoolIdNumber validated unique'],
    136 => ['religionId', 'religionId required at intake'],
    111 => ['public function store', 'the action that opens the student and application records in one transaction'],
    292 => ['}', 'the close of store()'],
    233 => ['semestersCompleted', 'intake zeroes the years and semesters'],
    234 => ['yearsInInstitution', 'the second of the two zeroes'],
];

foreach ($assertions as $number => [$mustContain, $why]) {
    $text = trim($lines[$number - 1] ?? '');

    if (! str_contains($text, $mustContain)) {
        fwrite(STDERR, "REFUSED: {$path}:{$number} reads '{$text}', expected to contain {$mustContain} — {$why}\n");

        exit(1);
    }

    printf("ok   AdmissionController.php:%-5d %s\n", $number, $text);
}

$zip = new ZipArchive;

if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the document for probing.\n");

    exit(1);
}

$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$edits = [
    // The approval's transaction, cited three times (§2's two-place sentence, §10.2's approval row, §18's
    // no-history paragraph). All three mean the same span, so all three move together.
    ['518-570', '519-586', null, 3],
    ['seat guard at :530', 'seat guard at :531', null, 1],
    ['the create at :543', 'the create at :559', null, 1],
    // The create block itself, as §7.1's phase row states it.
    ['537-570', '559-585', null, 1],
    // §24's enrollment column rows: the copy, the type, the status, and the level the approval stamps.
    ['AdmissionController.php:554', 'AdmissionController.php:576', null, 2],
    ['AdmissionController.php:555', 'AdmissionController.php:577', null, 1],
    ['AdmissionController.php:557', 'AdmissionController.php:579', null, 1],
    ['AdmissionController.php:550', 'AdmissionController.php:572', null, 1],
    // store(): its span, the two rules and the two zeroes.
    ['AdmissionController.php:109-290', 'AdmissionController.php:111-292', null, 1],
    ['AdmissionController.php:102', 'AdmissionController.php:127', null, 1],
    ['AdmissionController.php:130', 'AdmissionController.php:136', null, 1],
    ['AdmissionController.php:216-217', 'AdmissionController.php:233-234', null, 1],
];

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

foreach ([
    '518-570' => 0,
    'seat guard at :530' => 0,
    'the create at :543' => 0,
    '537-570' => 0,
    'AdmissionController.php:554' => 0,
    'AdmissionController.php:555' => 0,
    'AdmissionController.php:557' => 0,
    'AdmissionController.php:550' => 0,
    'AdmissionController.php:109-290' => 0,
    'AdmissionController.php:102' => 0,
    'AdmissionController.php:130' => 0,
    'AdmissionController.php:216-217' => 0,
    '519-586' => 3,
    'seat guard at :531' => 1,
    'the create at :559' => 1,
    '559-585' => 1,
    'AdmissionController.php:576' => 2,
    'AdmissionController.php:577' => 1,
    'AdmissionController.php:579' => 1,
    'AdmissionController.php:572' => 1,
    'AdmissionController.php:111-292' => 1,
    'AdmissionController.php:127' => 1,
    'AdmissionController.php:136' => 1,
    'AdmissionController.php:233-234' => 1,
] as $needle => $expected) {
    $seen = substr_count($after, $needle);

    if ($seen !== $expected) {
        fwrite(STDERR, "REFUSED (post-write): '{$needle}' occurs {$seen}, expected {$expected}.\n");

        exit(1);
    }

    printf("after %-40s %d\n", $needle, $seen);
}

echo "\npass 265 applied: the docx's pointers into AdmissionController.php answer the file as it now stands.\n";
