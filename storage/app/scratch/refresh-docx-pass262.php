<?php

/**
 * Two claims the build had already overtaken, both inside §7.1 and §27 — and both invisible to every
 * line-number checker because every cited range still lands on live code.
 *
 * 1. §27's E-4 paragraph states in the present tense that a paid record which was returned "cannot get
 *    back to paid", and cites `RegistrarController.php:304-326` for `returnToEvaluation()` — a method this
 *    batch retired when the return became one act in `App\Services\ReturnRecorder`. The round trip the
 *    paragraph says is impossible is now proved by a test: `EnrollmentReturnTest >
 *    a_paid_record_sent_back_comes_home_through_the_ordinary_desk_acts` walks
 *    paid → returnedToEvaluation → evaluated → assessed → paid and asserts the four history rows and the
 *    three re-signed boxes. (lane 70's N-1 shape: a mechanism sentence the code left behind.)
 * 2. §7.1's `enrolled → dropped` row says "no call site … Not implemented". `RegistrarController::drop()`
 *    at :333-347 has been the call site since the drop act was built; the row now names it and its reason
 *    rule.
 *
 * §7.1's return rows also widen to what the machine actually allows: the return is legal from every status
 * but `dropped`, and `returnedToAdmission → pending` had no row of its own.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass262.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass262-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$state = file_get_contents($root.'/app/Services/EnrollmentStateMachine.php');
$registrar = explode("\n", file_get_contents($root.'/app/Http/Controllers/Registrar/RegistrarController.php'));
$recorder = $root.'/app/Services/ReturnRecorder.php';
$test = $root.'/tests/Feature/Enrollment/EnrollmentReturnTest.php';

foreach ([$recorder, $test] as $f) {
    if (! is_file($f)) {
        fwrite(STDERR, "REFUSED: {$f} is not on disk, and the rewrite cites it.\n");

        exit(1);
    }
}

if (! str_contains($state, "'returnedToAdmission' => ['pending']")) {
    fwrite(STDERR, "REFUSED: the machine no longer declares returnedToAdmission => pending, so the new row would be false.\n");

    exit(1);
}

if (! str_contains($state, "'dropped' => [],")) {
    fwrite(STDERR, "REFUSED: `dropped` no longer reads as terminal in the machine; the widened return row would be wrong.\n");

    exit(1);
}

if (str_contains($state, 'returnToEvaluation')) {
    fwrite(STDERR, "REFUSED: the state machine still names returnToEvaluation, so §27's sentence may still be its own claim.\n");

    exit(1);
}

// The drop row's new call site, asserted against the lines it names.
foreach ([
    [333, 'public function drop(', 'the action'],
    [338, "'dropReason' => 'required|string|min:10|max:500'", 'the reason rule the row states'],
    [344, 'EnrollmentStatus::Dropped', 'the status it moves to'],
] as [$number, $mustContain, $why]) {
    $text = trim($registrar[$number - 1] ?? '');

    if (! str_contains($text, $mustContain)) {
        fwrite(STDERR, "REFUSED: RegistrarController.php:{$number} reads '{$text}', expected {$mustContain} — {$why}.\n");

        exit(1);
    }

    printf("ok   RegistrarController.php:%-5d %s\n", $number, $text);
}

$registrarSrc = implode("\n", $registrar);

if (str_contains($registrarSrc, 'function returnToEvaluation')) {
    fwrite(STDERR, "REFUSED: RegistrarController still DEFINES returnToEvaluation, so the citation §27 carries may still be live.\n");

    exit(1);
}

// What the file does say about it — the retirement note, which is the record the paragraph points to.
$line = 1 + (int) array_search(true, array_map(static fn (string $l): bool => str_contains($l, 'returnToEvaluation'), $registrar), true);

if (! str_contains($registrarSrc, 'returnToEvaluation')) {
    fwrite(STDERR, "REFUSED: RegistrarController no longer mentions returnToEvaluation at all, so the retirement note this paragraph cites is gone.\n");

    exit(1);
}

printf("ok   RegistrarController.php:%-5d the method is gone; only the retirement note names it\n", $line);

$zip = new ZipArchive;

if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the document for probing.\n");

    exit(1);
}

$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

// The E-4 paragraph's own words are split across styled runs — `cannot get back to paid` reads
// continuously on screen and exists nowhere in the file — so the correction goes through the engine's
// `para` kind: one anchor that IS contiguous, and the paragraph rebuilt from it. The cost is that the
// paragraph becomes a single run, which is stated here rather than left for a reader to notice.
foreach (['Second trigger (E-4). An enrollment that', 'no call site', 'BR31 return-for-rework'] as $needle) {
    if (($seen = substr_count($xml, $needle)) !== 1) {
        fwrite(STDERR, "REFUSED: the anchor '{$needle}' occurs {$seen} time(s); this pass cannot address it uniquely.\n");

        exit(1);
    }

    printf("probe %-40s occurs 1\n", $needle);
}

$edits = [];

$paraEdits = [
    [
        'Second trigger (E-4). An enrollment that',
        'Second trigger (E-4), closed by measurement rather than by argument. An enrollment that did reach paid, was returned to Department Evaluation, and is then '
            .'re-costed does come back, and it comes back through the ordinary desk acts: the return leaves the money where it is (the receipts keep counting through '
            .'Payments::held(), so the account is not re-billed for pesos already handed over), the Evaluation desk proposes the load again with '
            .'evaluation.subjects.propose, which moves returnedToEvaluation to evaluated, Assessment finalizes it to assessed, and Accounting settles it to paid. '
            .'finalize fires only from evaluated ( AssessmentController.php:424-430 ) and record only from assessed ( AccountingController.php:180-183 ), and that '
            .'sequence is exactly the path a returned record walks. Pinned end to end by '
            .'tests/Feature/Enrollment/EnrollmentReturnTest.php , whose case a_paid_record_sent_back_comes_home_through_the_ordinary_desk_acts asserts the four '
            .'history rows in order, the three boxes re-signed, and one enrollmentreturns row written. The act itself is App\Services\ReturnRecorder ( '
            .'app/Services/ReturnRecorder.php:87-170 ); the method this paragraph used to cite, RegistrarController::returnToEvaluation, no longer exists — which is '
            .'why every checker agreed with the sentence while it described a retired construct.',
    ],
];

$cells = [
    // §7.1's drop row: the call site exists.
    ['no call site', 'no call site', 'RegistrarController.php:333-347 , the reason rule at :338'],
    ['no call site', 'Declared', 'The Registrar drops a record with a required reason of at least ten characters, releases its proposed and confirmed subjects and frees the seat, so the student can be enrolled in the term again. Status: Implemented, and the seat release is what makes a dropped student eligible for the term.'],
    // §7.1's return row: the act is not confined to `paid`.
    ['BR31 return-for-rework', 'paid', 'pending, evaluated, assessed, paid or enrolled'],
    ['BR31 return-for-rework', 'returnedToEvaluation', 'returnedToEvaluation or returnedToAdmission'],
];

echo "\n";

docxApply($docx, $backup, $edits, $cells, $apply, $paraEdits);

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$again = new ZipArchive;
$again->open($docx);
$after = (string) $again->getFromName('word/document.xml');
$again->close();

foreach ([
    'closed by measurement rather than by argument' => 1,
    'a_paid_record_sent_back_comes_home_through_the_ordinary_desk_acts' => 1,
    'no call site' => 0,
    'RegistrarController.php:333-347' => 1,
    'Status: Implemented' => 1,
    'pending, evaluated, assessed, paid or enrolled' => 1,
    'returnedToEvaluation or returnedToAdmission' => 1,
] as $needle => $expected) {
    $seen = substr_count($after, $needle);

    if ($seen !== $expected) {
        fwrite(STDERR, "REFUSED (post-write): '{$needle}' occurs {$seen}, expected {$expected}.\n");

        exit(1);
    }

    printf("after %-58s %d\n", substr($needle, 0, 56), $seen);
}

echo "\npass 262 applied: §7.1's drop and return rows and §27's E-4 paragraph state the build as it is.\n";
