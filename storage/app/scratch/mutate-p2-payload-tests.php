<?php

/**
 * Proof over the P-2 and P-18 tests (tests/Feature/Print/PrintEnrollmentFormPayloadTest.php).
 *
 * P-2 was opened because the enrollment form printed no identity at all, and it was blocked for weeks on a blank
 * physical form that never arrived; the owner supplied the reference designs on 2026-10-23 and the identification
 * block is now on the paper. The review that followed found three more things the sheet stated wrongly — P-18:
 * Part 2 counted proposed and dropped subjects, the home/current box could never print because `addressType` is an
 * enum cast and the template compared it with `===` to a string, and the print's own number was minted after the
 * render and printed nowhere. Two halves of the earlier work are the kind that pass when they read nothing:
 *
 *  - the drift check compares a list in the test against lists in two source files, so a regex that matches nothing
 *    would have to refuse, not shrug;
 *  - the query counter measures lazily-loaded relations, so if the model never lazy-loads it reports 0 forever;
 *  - the block test reads the rendered HTML, and the document's `<title>` has carried the student number the whole
 *    time — an assertion that matches the head element would have "proved" P-2 closed on a form that still prints
 *    nothing, which is exactly how the register's "verified on paper" sentence came to be false (lane 62).
 *
 * Legs A–E are P-2's; F–K are P-18's, each reverted to the shape it had before the fix. Those matter most, because
 * every one of them survived a green suite: a filter dropped from a blade still renders, a dead `@if` still renders
 * (it just renders nothing), and a number minted after the page still lands in the log. Each leg must turn the named
 * case red. Bytes are restored unconditionally, and the run refuses when a needle matches nothing, when the control
 * is not green, or when artisan prints no "Tests:" line.
 *
 * Usage: php storage/app/scratch/mutate-p2-payload-tests.php
 */

$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$suite = 'tests/Feature/Print/PrintEnrollmentFormPayloadTest.php';
$service = 'app/Services/PrintService.php';
$fidelity = 'app/Console/Commands/PrintFidelitySamples.php';
$form = 'resources/views/prints/enrollment-form.blade.php';

$DRIFT = 'test_the_desk_loads_the_relations_the_form_would_need';
$NPLUS = 'test_reading_those_facts_issues_no_further_query';
$BLOCK = 'test_the_form_prints_the_identification_block_on_its_face';

$IDENTIFICATION_BLOCK = <<<'BLADE'
    <div class="form-grid">
        <div class="field">
            <span class="label">Student ID:</span>
            <span class="value">{{ $enrollment->student->schoolIdNumber }}</span>
        </div>
        <div class="field">
            <span class="label">School Year:</span>
            <span class="value">{{ $enrollment->term?->academicYear?->yearLabel }}</span>
        </div>
        @isset($documentNumber)
        <div class="field">
            <span class="label">Document No.:</span>
            <span class="value">{{ $documentNumber }}</span>
        </div>
        @endisset
        <div class="field full-width">
            <span class="label">Course:</span>
            <span class="value">{{ $enrollment->course?->courseName }}{{ $enrollment->major ? ' - '.$enrollment->major->majorName : '' }}</span>
        </div>
        <div class="field">
            <span class="label">Year Level:</span>
            <span class="value">{{ $enrollment->yearLevel ? 'Year '.$enrollment->yearLevel : 'Year N/A' }}</span>
        </div>
        <div class="field">
            <span class="label">Semester:</span>
            <span class="value">{{ $enrollment->term?->semester?->value }}</span>
        </div>
    </div>
BLADE;

$STUDENT_ID_FIELD = <<<'BLADE'
        <div class="field">
            <span class="label">Student ID:</span>
            <span class="value">{{ $enrollment->student->schoolIdNumber }}</span>
        </div>
BLADE;

// P-18's three paper defects, each reverted to the shape it had before this batch.
$CONFIRMED_FILTER = <<<'BLADE'
                $confirmed = $enrollment->enrolledSubjects->filter(
                    fn ($es) => $es->status === \App\Enums\EnrolledSubjectStatus::Confirmed
                )->values();
BLADE;

$EMPTY_ROW = <<<'BLADE'
                <tr>
                    <td colspan="6" style="font-style: italic;">No confirmed subjects for this term.</td>
                </tr>
BLADE;

$MINT_BEFORE_RENDER = <<<'PHP'
        // Minted before the page is built, the way the slip does it: the number is printed on the
        // form's own face, and a sheet that carries no number cannot be traced back to the print
        // that produced it even though the log row exists (P-18).
        $printLog = $this->recordIssue(
            $enrollment->enrollmentId,
            DocumentType::EnrollmentForm,
            $printedBy,
            $enrollment->studentId
        );
PHP;

$DOCUMENT_NUMBER_FIELD = <<<'BLADE'
        @isset($documentNumber)
        <div class="field">
            <span class="label">Document No.:</span>
            <span class="value">{{ $documentNumber }}</span>
        </div>
        @endisset
BLADE;

$cases = [
    ['desk path drops term.academicYear', $service, $DRIFT,
        "                'major',\n                'term.academicYear',", "                'major',"],
    ['fidelity command drops term.academicYear', $fidelity, $DRIFT,
        // The trio `'course', 'major', 'term.academicYear',` appears in seven of the command's render
        // closures, so the needle has to name the enrollment-form line it belongs to.
        "'enrollment-form' => fn () => view('prints.enrollment-form', ['enrollment' => \$enrollment->load(['student.addresses', 'student.guardians', 'course', 'major', 'term.academicYear',",
        "'enrollment-form' => fn () => view('prints.enrollment-form', ['enrollment' => \$enrollment->load(['student.addresses', 'student.guardians', 'course', 'major',"],
    ['the N+1 counter with no eager load at all', $suite, $NPLUS,
        '$enrollment = $this->issuedEnrollment()->load(self::DESK_RELATIONS);'."\n\n        ".'DB::flushQueryLog();',
        '$enrollment = $this->issuedEnrollment()->load([]);'."\n\n        ".'DB::flushQueryLog();'],
    ['the identification block is off the paper', $form, $BLOCK, $IDENTIFICATION_BLOCK."\n", ''],
    ['only the <title> carries the student number', $form, $BLOCK, $STUDENT_ID_FIELD."\n", ''],
    ['P-18: Part 2 counts proposed and dropped subjects again', $form, 'test_part_2_prints_only_the_subjects_the_term_confirmed',
        $CONFIRMED_FILTER, '                $confirmed = $enrollment->enrolledSubjects;'],
    ['P-18: an empty load prints a bare TOTAL 0', $form, 'test_the_form_and_the_subject_load_state_the_same_load',
        '            @if($confirmed->isEmpty())'."\n".$EMPTY_ROW."\n            @endif\n", ''],
    ['P-18: the address type compared with a string, so the box never prints', $form,
        'test_the_home_address_says_whether_the_current_address_is_the_same',
        "@if(\$address->addressType?->value === 'home')", "@if(\$address->addressType === 'home')"],
    ['P-18: the issuance number never reaches the sheet', $form,
        'test_the_form_prints_the_number_of_the_print_that_issued_it',
        $DOCUMENT_NUMBER_FIELD."\n", ''],
    ['P-18: the number minted after the render, as it was', $service,
        'test_the_form_prints_the_number_of_the_print_that_issued_it',
        $MINT_BEFORE_RENDER."\n\n        \$path = \$this->generatePdf('prints.enrollment-form', [\n"
            ."            'enrollment' => \$enrollment->load([\n"
            ."                'student.addresses',\n                'student.guardians',\n                'course',\n"
            ."                'major',\n                'term.academicYear',\n                'enrolledSubjects.subject',\n"
            ."                'evaluatedByUser',\n                'registrarProcessedByUser',\n            ]),\n"
            ."            'documentNumber' => \$printLog->documentNumber,\n        ], \$filename);\n\n"
            ."        return new PrintedDocument(\$printLog, \$path);",
        "        \$path = \$this->generatePdf('prints.enrollment-form', [\n"
            ."            'enrollment' => \$enrollment->load([\n"
            ."                'student.addresses',\n                'student.guardians',\n                'course',\n"
            ."                'major',\n                'term.academicYear',\n                'enrolledSubjects.subject',\n"
            ."                'evaluatedByUser',\n                'registrarProcessedByUser',\n            ]),\n"
            ."        ], \$filename);\n\n"
            ."        return new PrintedDocument(\n"
            ."            \$this->recordIssue(\$enrollment->enrollmentId, DocumentType::EnrollmentForm, \$printedBy, \$enrollment->studentId),\n"
            ."            \$path\n"
            ."        );",
    ],
    ['P-18: student type spelled by ucfirst, so the paper reads FirstYear', $form,
        'test_student_type_is_spelled_the_way_the_other_papers_spell_it',
        '{{ \Illuminate\Support\Str::headline($enrollment->studentType->value) }}',
        '{{ ucfirst($enrollment->studentType->value) }}'],
];

$fails = 0;
$touched = [];

$run = function (string $method) use ($php, $suite, $root): array {
    chdir($root);
    $out = preg_replace('/\e\[[0-9;]*m/', '', (string) shell_exec(
        escapeshellarg($php).' artisan test '.escapeshellarg($suite).' --filter '.escapeshellarg($method).' 2>&1'
    ));
    if (! preg_match('/Tests:\s+([^\n]*)/u', $out, $m)) {
        return ['unknown', $out];
    }

    return [str_contains($m[1], 'failed') ? 'red' : 'green', $out];
};

try {
    echo "control — every case on the unmutated tree:\n";
    $control = [
        ['drift check', $DRIFT],
        ['identity facts', 'test_the_form_payload_carries_every_identity_fact_the_block_prints'],
        ['n+1 counter', $NPLUS],
        ['identification block', $BLOCK],
        ['confirmed-only table', 'test_part_2_prints_only_the_subjects_the_term_confirmed'],
        ['two papers, one load', 'test_the_form_and_the_subject_load_state_the_same_load'],
        ['home/current box', 'test_the_home_address_says_whether_the_current_address_is_the_same'],
        ['issuance number', 'test_the_form_prints_the_number_of_the_print_that_issued_it'],
        ['student type words', 'test_student_type_is_spelled_the_way_the_other_papers_spell_it'],
    ];
    foreach ($control as [$label, $method]) {
        [$verdict, $out] = $run($method);
        printf("  %-16s %s\n", $label, $verdict);
        if ($verdict !== 'green') {
            echo '       '.(preg_match('/Tests:.*$/mu', $out, $mm) ? trim($mm[0]) : substr(trim($out), -300))."\n";
            $fails++;
        }
    }
    if ($fails > 0) {
        echo "\nREFUSED: the control is not green, so a red mutant would prove nothing.\n";
        exit(1);
    }

    foreach ($cases as [$label, $file, $method, $from, $to]) {
        printf("\ncase: %s\n", $label);
        $bytes = (string) file_get_contents($root.'/'.$file);
        $n = substr_count($bytes, $from);
        if ($n !== 1) {
            printf("  BAD  the needle matched %d times in %s — a mutant that cannot apply cannot fail\n", $n, $file);
            $fails++;

            continue;
        }
        $touched[$root.'/'.$file] ??= $bytes;
        file_put_contents($root.'/'.$file, str_replace($from, $to, $bytes));

        [$verdict, $out] = $run($method);
        $says = preg_match('/drifted from|different relations|query\(ies\) after|no longer holds an eager-load|no longer renders|prints no|cannot be tied back|did not add up|proposed subject printed|dropped subject printed|never printed at all|unticked|not printed beside|renders before it mints|the way the certificate|instead of saying so|withholds a subject/u', $out)
            ? 'it named the drift in its own message'
            : 'FAILED OPEN — red, but without saying which contract broke';
        printf("  %-4s the named test on the mutant  (%s)\n", $verdict === 'red' ? 'ok  ' : 'FAIL', $says);
        if ($verdict !== 'red') {
            printf("       %s\n", preg_match('/Tests:.*$/mu', $out, $mm) ? trim($mm[0]) : substr(trim($out), -300));
            $fails++;
        }

        file_put_contents($root.'/'.$file, $bytes);
        clearstatcache(true, $root.'/'.$file);
    }
} finally {
    foreach ($touched as $path => $bytes) {
        if ((string) file_get_contents($path) !== $bytes) {
            file_put_contents($path, $bytes);
            printf("\nrestored %s after an aborted run\n", basename($path));
        }
    }
}

if ($fails > 0) {
    printf("\n%d case(s) did not behave.\n", $fails);
    exit(1);
}
echo "\nAll P-2 payload cases behaved: green on the tree, red on the defect, each naming the contract it broke.\n";
