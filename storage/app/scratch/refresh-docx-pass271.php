<?php

/**
 * Pass 271: three register pointers that were false by meaning, re-taken by reading.
 *
 * This is the docx half of draining the lane-70 human queue. Seventy-one UNBOUND rows were opened against their
 * files; sixty-eight carried their sentence's claim, and three did not. Each of the three had been moved by a
 * build and left pointing at a live line of the right file — which is the shape no bounds check can see:
 *
 *   §23's `documentprintlog` row named the print actions at RegistrarController lines 376/382/413 (desk pages) and
 *     437/452/464/490 (PDFs). Those seven numbers now land on a `recordIssue()` call, two closing `]);`, a `}` and
 *     the docblock of the next method: the seven actions themselves begin at 366, 388, 419, 443, 458, 470, 496.
 *     The row also listed Blocking's print page but not its PDF download, so :571 is added beside :542.
 *   §28's E-13 row cited EnrollmentStateMachine 30-31 for "enrolled → dropped" and 37-40 for "dropped is terminal".
 *     G-10 inserted the two return states between: 30-31 is now ruling 17's comment and 37-40 is the
 *     `returnedToEvaluation` block. The enrolled edge is :47, the terminal drop is :48-50, and the row's own second
 *     claim — "subject status has its own proposed → confirmed → dropped line" — had no pointer at all, so :56-60
 *     is added for `SUBJECT_TRANSITIONS`.
 *   §6's Registrar/Show row cited 407-418 and :613 for "the year level as an ordinal with the standing beside it".
 *     407-418 is the standing report headline and its radio group; :613 is a closing `</div>`. The label and value
 *     that carry the claim are :615-618.
 *
 * Every NEW number is asserted against the line it names, in the working file, before a byte is written — the
 * assertion loop is the only thing that distinguishes a re-point from a re-guess. A needle that is not unique
 * refuses the run, and all four travel with their run boundaries so a short number cannot be another citation's
 * tail. See refresh-docx-pass196/224/246 for the same discipline.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass271.php [--apply]
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

/** @return string[] the file split on \n — never /\R/, which matches 0x85 inside a UTF-8 emoji */
function lines(string $path): array
{
    if (! is_file($path)) {
        fwrite(STDERR, "REFUSED: cannot read {$path}, so no assertion over it can be trusted.\n");

        exit(1);
    }

    return explode("\n", (string) file_get_contents($path));
}

$failures = [];
$checks = 0;

/** Require line $n (1-based) of $file to contain $needle. */
function assertLine(string $file, int $n, string $needle, string $claim): void
{
    global $failures, $checks;
    $checks++;
    $at = lines($file)[$n - 1] ?? null;
    if ($at === null) {
        $failures[] = "{$file}:{$n} does not exist ({$claim}).";

        return;
    }
    if (! str_contains($at, $needle)) {
        $failures[] = "{$file}:{$n} reads [".trim($at)."] and was offered for \"{$claim}\", which needs [{$needle}].";
    }
}

$registrar = $root.'/app/Http/Controllers/Registrar/RegistrarController.php';
$clearance = $root.'/app/Http/Controllers/Clearance/ClearanceController.php';
$blocking = $root.'/app/Http/Controllers/Blocking/BlockingController.php';
$printSvc = $root.'/app/Services/PrintService.php';
$machine = $root.'/app/Services/EnrollmentStateMachine.php';
$showJsx = $root.'/resources/js/Pages/Registrar/Show.jsx';

// §23's documentprintlog row: seven actions, each named by the signature that opens it.
assertLine($registrar, 366, 'function printCertificate', 'the three desk pages');
assertLine($registrar, 388, 'function printClassCards', 'the three desk pages');
assertLine($registrar, 419, 'function printSubjectLoad', 'the three desk pages');
assertLine($registrar, 443, 'function downloadCertificate', 'the generated PDFs');
assertLine($registrar, 458, 'function downloadEnrollmentForm', 'the generated PDFs');
assertLine($registrar, 470, 'function downloadClassCard', 'the generated PDFs');
assertLine($registrar, 496, 'function downloadSubjectLoad', 'the generated PDFs');
assertLine($clearance, 380, 'function printSlip', 'the clearance slip print page');
assertLine($clearance, 408, 'function downloadSlip', 'the clearance slip PDF');
assertLine($blocking, 542, 'function printBlockSchedule', 'the block schedule print page');
assertLine($blocking, 571, 'function downloadBlockSchedule', 'the block schedule PDF');
assertLine($printSvc, 115, 'public function recordIssue(', 'the one writer behind all of them');

// §28's E-13 row: the enrolled edge, the terminal drop, and the subject's own line.
assertLine($machine, 47, "'enrolled' => ['dropped'", 'enrolled → dropped');
assertLine($machine, 48, 'A drop is terminal', 'the drop is terminal');
assertLine($machine, 50, "'dropped' => []", 'the drop is terminal');
assertLine($machine, 56, 'SUBJECT_TRANSITIONS', 'subject status has its own line');
assertLine($machine, 57, "'proposed' => ['confirmed', 'dropped']", 'proposed → confirmed → dropped');
assertLine($machine, 58, "'confirmed' => ['dropped']", 'proposed → confirmed → dropped');

// §6's Registrar/Show row: the label and the value painted beside it.
assertLine($showJsx, 615, 'Year Level & Standing:', 'the year level with the standing beside it');
assertLine($showJsx, 617, 'formatYearLevel(enrollment.yearLevel)', 'the year level as an ordinal');
assertLine($showJsx, 617, 'academicStanding', 'with the standing beside it');

if ($failures !== []) {
    fwrite(STDERR, "REFUSED WRITE: ".count($failures)." assertion(s) over {$checks} failed — a new number that does"
        ." not carry the claim is a re-guess, not a re-point.\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  - {$f}\n");
    }

    exit(1);
}

printf("assertions: %d line(s) read, every one carries the claim it is offered for\n", $checks);

$zip = new ZipArchive;

if (! $zip->open($docx)) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");

    exit(1);
}

$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

// Four edits, each with its run boundaries so a short number cannot be another citation's tail.
$edits = [
    [
        'The print actions — RegistrarController.php:376 , :382 , :413 (the three desk pages) and :437 , :452 , :464 , :490 (the generated PDFs), ClearanceController.php:380 and :408 , BlockingController.php:542 , and the one writer behind all of them, PrintService::recordIssue() at PrintService.php:115',
        'The print actions — RegistrarController.php:366 , :388 , :419 (the three desk pages) and :443 , :458 , :470 , :496 (the generated PDFs), ClearanceController.php:380 and :408 , BlockingController.php:542 and :571 , and the one writer behind all of them, PrintService::recordIssue() at PrintService.php:115',
    ],
    ['>EnrollmentStateMachine.php:30-31</w:t>', '>EnrollmentStateMachine.php:47</w:t>'],
    ['>:37-40</w:t>', '>:48-50 and :56-60</w:t>'],
    ['>Registrar/Show.jsx:407-418 , :613</w:t>', '>Registrar/Show.jsx:615-618</w:t>'],
];

foreach ($edits as $i => [$from, $to]) {
    $n = substr_count($xml, $from);
    if ($n !== 1) {
        fwrite(STDERR, "REFUSED: edit {$i}'s needle matched {$n} place(s), expected exactly 1.\n");

        exit(1);
    }
    if ($from === $to) {
        fwrite(STDERR, "REFUSED: edit {$i}'s needle and replacement are the same text.\n");

        exit(1);
    }
    $already = substr_count($xml, $to);
    if ($already !== 0) {
        fwrite(STDERR, "REFUSED: edit {$i}'s replacement already occurs {$already} time(s) — either this pass ran"
            ." before or the new number is another citation's bytes.\n");

        exit(1);
    }
}

$updated = $xml;
foreach ($edits as [$from, $to]) {
    $updated = str_replace($from, $to, $updated);
}

// After-check scoped to the pair, not to the document: these numbers occur elsewhere for other claims, and a
// pass that demanded a bare number had vanished document-wide would refuse an edit that had landed correctly.
foreach ($edits as $i => [$from, $to]) {
    if (substr_count($updated, $from) !== 0) {
        fwrite(STDERR, "REFUSED: edit {$i}'s needle still occurs ".substr_count($updated, $from)." time(s).\n");

        exit(1);
    }
    if (substr_count($updated, $to) !== 1) {
        fwrite(STDERR, "REFUSED: edit {$i}'s replacement occurs ".substr_count($updated, $to)." times, expected 1.\n");

        exit(1);
    }
}

// Tags counted by their OPEN form: `<w:t xml:space="preserve">` is the same element as `<w:t>`.
foreach ([['<w:p', '</w:p>'], ['<w:t', '</w:t>'], ['<w:tc', '</w:tc>'], ['<w:tr', '</w:tr>'], ['<w:tbl', '</w:tbl>']] as [$open, $close]) {
    $a = preg_match_all('/'.preg_quote($open, '/').'(?=[\s>])/', $updated);
    $b = substr_count($updated, $close);
    if ($a !== $b) {
        fwrite(STDERR, "REFUSED: the edited document is unbalanced in {$open} ({$a} against {$b}).\n");

        exit(1);
    }
}

if (simplexml_load_string($updated) === false) {
    fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses.\n");

    exit(1);
}

printf("%sre-point(s) the print-action, E-13 and Registrar/Show pointers\n", $apply ? '' : 'DRY RUN — ');

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass271-backup-'.date('Ymd-His').'.docx';

if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup.\n");

    exit(1);
}

$zip = new ZipArchive;

if (! $zip->open($docx, ZipArchive::CREATE)) {
    fwrite(STDERR, "REFUSED: cannot reopen for writing.\n");

    exit(1);
}

$zip->addFromString('word/document.xml', $updated);
$zip->close();

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();

if ($read !== $updated) {
    fwrite(STDERR, "REFUSED: the archive did not read back the bytes it was handed. Restore from {$backup}.\n");

    exit(1);
}

echo "\npass 271 applied: the three false-by-meaning pointers now name the lines that carry their claims.\n";
