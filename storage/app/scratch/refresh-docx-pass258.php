<?php

/**
 * The last seven citation problems, each re-pointed by reading its live construct.
 *
 * `sweep-docx-citations.php` reported 740 citations over 162 files with seven line problems after this
 * batch moved `BlockingController.php` and `EvaluationController.php`. Four of the seven are ranges whose
 * head slid off a blank line, and three are bare continuations inside the same sentences. None of them is
 * a uniform shift, which is why the `lane76-shift-cites.php` window walk withheld them: a +14 move inside
 * `AdmissionController::approve()` sits beside a −1 move inside `RegistrarController::checklist()`, so
 * arithmetic would have cleared a stale pointer by giving it a live line belonging to something else.
 *
 * Every new number below is asserted against the line it lands on before anything is written — the same
 * discipline as passes 256 and 257.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass258.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass258-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$files = [
    'admission' => 'app/Http/Controllers/Admission/AdmissionController.php',
    'registrar' => 'app/Http/Controllers/Registrar/RegistrarController.php',
    'auth' => 'app/Providers/AuthServiceProvider.php',
];

$lines = [];

foreach ($files as $key => $path) {
    $lines[$key] = explode("\n", file_get_contents($root.'/'.$path));
}

// [file, line, must-contain, the claim that line carries]
$assertions = [
    ['admission', 518, 'DB::transaction(', 'the one transaction the approval runs — site 361 calls it guard SM-1'],
    ['admission', 520, "'admissionStatus' => 'approved'", 'site 361 claims the approval sets this inside the range'],
    ['admission', 530, 'seatHolder', 'the seat guard the bare :524 claimed'],
    ['admission', 543, 'Enrollments::create(', 'the create the bare :537 claimed'],
    ['admission', 563, "'enrollmentStatus' => EnrollmentStatus::Pending", 'site 747: pending by assignment, not through the state machine'],
    ['admission', 570, '});', 'the close of the transaction — the old range tail, still true'],
    ['registrar', 166, 'studentassessments', 'site 527: the gate reads the assessment record here'],
    ['registrar', 183, 'assessment_completed', 'the checklist key that gate is — 182 today is a different key'],
    ['registrar', 286, 'DB::transaction(', 'site 2349 names this as an atomic multi-table write'],
    ['registrar', 273, 'BR31', 'the comment that labels the derivation'],
    ['registrar', 274, '$enrollmentType = in_array', 'firstYear/transferee become new, continuing/shifter old'],
    ['registrar', 276, 'EnrollmentType::Old', 'the last line of that derivation'],
    ['registrar', 296, "'enrollmentType' => \$enrollmentType", 'the write the bare :266 claimed'],
    ['auth', 168, "Gate::define('assessment.computeAtDesk'", 'the ability that delegates to the policy'],
    ['auth', 169, 'AssessmentPolicy::class)->compute', 'site 932: the arrow the row states as "→ AssessmentPolicy::compute"'],
    ['auth', 170, '}', 'the close of that definition'],
];

foreach ($assertions as [$file, $number, $mustContain, $why]) {
    $text = trim($lines[$file][$number - 1] ?? '');

    if (! str_contains($text, $mustContain)) {
        fwrite(STDERR, "REFUSED: {$files[$file]}:{$number} reads '{$text}', expected {$mustContain} — {$why}\n");

        exit(1);
    }

    printf("ok   %-58s %s\n", basename($files[$file]).":{$number}", $text);
}

// And the negative control for the one range whose HEAD text matched two live lines: the old number must
// now land on a blank, which is exactly what the sweep reported, so the rewrite is a repair not a guess.
$oldAdmissionHead = trim($lines['admission'][523] ?? '');

if ($oldAdmissionHead !== '') {
    fwrite(STDERR, "REFUSED: AdmissionController.php:524 is not blank (it reads '{$oldAdmissionHead}'), so the sweep's EMPTY LINE is describing a different layout than this pass reads.\n");

    exit(1);
}

$edits = [
    // AdmissionController::approve() — three copies of the range, one per section, all claiming the
    // enrollment-creating approval. Head moves to the transaction so the range also carries site 361's
    // "sets admissionStatus = approved" claim; the tail is unchanged at 570.
    ['524-570', '518-570', null, 3],
    ['seat guard at :524', 'seat guard at :530', null, 1],
    ['the create at :537', 'the create at :543', null, 1],
    // RegistrarController::checklist() — the assignment moved up one line and the key down one.
    ['167-182', '166-183', null, 1],
    // RegistrarController::approve() — the atomic write, the BR31 derivation, and its column.
    ['RegistrarController.php:272', 'RegistrarController.php:286', null, 1],
    ['260-265', '273-276', null, 2],
    ['written at :266', 'written at :296', null, 2],
    // AuthServiceProvider — the assessment.compute row: the delegation block, no longer a blank line.
    ['160-162', '168-170', null, 1],
];

echo "\n";

docxApply($docx, $backup, $edits, [], $apply);

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

// Post-write: each new token occurs exactly the number of copies this pass intended, and each old needle
// is gone from the byte stream the engine actually wrote. Read the PART, not the archive: a `substr_count`
// over the .docx bytes measures compressed data and "finds" 0 copies of a plaintext needle.
$zip = new ZipArchive;

if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED (post-write): cannot open the written archive for reading.\n");

    exit(1);
}

$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$after = [
    '524-570' => 0,
    '167-182' => 0,
    '260-265' => 0,
    '160-162' => 0,
    'RegistrarController.php:272' => 0,
    '518-570' => 3,
    'seat guard at :530' => 1,
    'the create at :543' => 1,
    '166-183' => 1,
    'RegistrarController.php:286' => 1,
    '273-276' => 2,
    'written at :296' => 2,
    '168-170' => 1,
];

foreach ($after as $needle => $expected) {
    $seen = substr_count($xml, $needle);

    if ($seen !== $expected) {
        fwrite(STDERR, "REFUSED (post-write): '{$needle}' occurs {$seen} time(s) in the written document.xml, expected {$expected}.\n");

        exit(1);
    }

    printf("after %-30s occurs %d as intended\n", $needle, $seen);
}

echo "\npass 258 applied: seven citation problems re-pointed by reading.\n";
