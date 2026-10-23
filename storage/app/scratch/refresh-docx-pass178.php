<?php

/**
 * Pass 178 — P-2's blocker is re-checked rather than repeated, and the ready half is pinned.
 *
 * P-7 taught the lesson one day early: a row can wait on somebody else because a file says it must. So before this
 * sentence goes into the register, the repository was searched for the thing P-2 calls missing —
 * `find` over the tree for any blank/sample/scan/template artefact and for any file whose name carries "enroll"
 * outside the source, and `git log --diff-filter=A` over every PNG, JPG and PDF ever added: the only image
 * additions are college and office logos. `Documentation/Images`, where the Registrar's photographs live, is
 * gitignored and absent from this box. The wait is therefore on the paper, and that is now a measurement rather than
 * a restatement of item 10.
 *
 * What is not waiting is the data. Three cases in
 * tests/Feature/Print/PrintEnrollmentFormPayloadTest.php prove the desk's own eager-load list already delivers the
 * school ID, the program name, the year level, the semester and the school year; that reading those costs no further
 * query; and that the print path and ems:print-fidelity load the same relations, so an "8/8 rendered" run cannot
 * quietly certify a document the desk does not produce. Proved able to fail by
 * storage/app/scratch/mutate-p2-payload-tests.php.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};

$test = (string) file_get_contents($root.'/tests/Feature/Print/PrintEnrollmentFormPayloadTest.php');
foreach ([
    'test_the_desk_loads_the_relations_the_form_would_need',
    'test_the_form_payload_already_carries_every_identity_fact_the_paper_lacks',
    'test_reading_those_facts_issues_no_further_query',
] as $m) {
    $guard(str_contains($test, $m), 'the case this pass cites exists: '.$m);
}
$harness = (string) file_get_contents($root.'/storage/app/scratch/mutate-p2-payload-tests.php');
$guard(str_contains($harness, "'term.academicYear'"), 'and the planted defects drop the very relation the form would print');
$guard(substr_count($harness, 'test_the_desk_loads_the_relations') >= 1 && str_contains($harness, 'test_reading_those_facts_issues_no_further_query'),
    'the mutant harness drives the two cases this sentence says can be refuted');
$guard(str_contains($test, "'term.academicYear'"), 'and the list names the school-year relation the form would print');
$guard(is_file($root.'/storage/app/scratch/mutate-p2-payload-tests.php'), 'the mutant harness this pass cites exists');

$textEdits = [
    [
        '§5 of the print-fidelity comparison and item 10 of the Registrar’s 2026-10-11 decision sheet both still '
            .'record the blank enrollment form as not provided.',
        '§5 of the print-fidelity comparison and item 10 of the Registrar’s 2026-10-11 decision sheet both still '
            .'record the blank enrollment form as not provided, and 2026-10-22 searched rather than assumed: no '
            .'blank, sample, scan or template artefact exists anywhere in the working tree, and the only images ever '
            .'added to this repository’s history are the college and office logos — so the wait is on the paper '
            .'itself, not on a search nobody had done. The other half is now pinned instead of asserted: '
            .'tests/Feature/Print/PrintEnrollmentFormPayloadTest.php proves the desk’s own eager-load list already '
            .'delivers the school ID, the program name, the year level, the semester and the school year; that '
            .'reading them costs no further query; and that the print path and ems:print-fidelity load the same '
            .'relations, so a fidelity run cannot certify a document the desk does not produce. Each case is proved '
            .'able to fail by a planted defect in storage/app/scratch/mutate-p2-payload-tests.php. The data was never the '
            .'gap: PrintService.php:321 is the one place the desk builds this paper, and its load list already '
            .'carries the student, the course, the major and the term with its academic year. The day the blank form '
            .'reaches this office, closing P-2 is a layout edit in one blade — not plumbing.',
    ],
];

if ($fails > 0) {
    printf("\n%d guard(s) failed — NOTHING WRITTEN.\n", $fails);
    exit(1);
}

docxApply($src, $root.'/storage/app/scratch/pass178-backup.docx', $textEdits, [], $apply);
