<?php

/**
 * Pass 175 — the enrollment form was recorded as verified on paper, and the paper says otherwise.
 *
 * §28.6's row 5 states that the clearance slip and the enrollment form "both print the schoolIdNumber they already
 * hold, verified by reprinting and reading the paper". Measured 2026-10-21 by extracting the text of the PDFs
 * `ems:print-fidelity` renders from live `ems` (lane62-print-paper-probe.php):
 *
 *   enrollment-certificate.pdf   DEMO-2026-017   yes   yes   yes     ← prints ID, program, year level
 *   subject-load.pdf             DEMO-2026-017   yes   yes   yes
 *   clearance-slip.pdf           DEMO-2026-018   —     —     —       ← its own subject, ID present
 *   enrollment-form.pdf          none            no    no    no      ← no ID token at all, no school year
 *
 * So the slip half of that sentence holds and the form half is false — and P-2, which the same document described as
 * a limitation, is confirmed by the artefact: the record for that paper (student 97, enrollment 92) holds the ID, the
 * program, the year level and the term, and none of them reaches the form.
 *
 * P-2 is also the register's only live engineering item, and until now it stated no verdict word, so no sweep could
 * see it at all. It gets one here, with the reason it cannot be closed by code: the physical blank form it must
 * match has never been supplied — Print-Fidelity §5 and the Registrar's 2026-10-11 decision sheet both still carry
 * that ask unchecked.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$probe = $root.'/storage/app/scratch/lane62-print-paper-probe.php';
if (! is_file($probe)) {
    exit("REFUSED: the paper probe this pass cites does not exist.\n");
}
$fidelity = glob($root.'/storage/app/prints/fidelity/*.pdf') ?: [];
if (count($fidelity) !== 8) {
    printf("REFUSED: %d rendered PDFs, not 8 — re-run php artisan ems:print-fidelity before quoting the set.\n",
        count($fidelity));
    exit(1);
}
if (str_contains((string) file_get_contents($root.'/resources/views/prints/enrollment-form.blade.php'), 'courseName')) {
    exit("REFUSED: the form template now prints a program — P-2 may be closed after all.\n");
}

$flat = (string) file_get_contents($root.'/storage/app/scratch/docx-flat.txt');

$edits = [
    // P-2's finding cell: a verdict word at last, and the blocker named.
    [
        'The enrollment form PDF carries no Student ID, program, year level, school year or semester anywhere in its '
            .'body, ; the desk reaches it as a PDF only, which is a decision rather than a gap, because the blade is '
            .'the paper.',
        'Open, and it is a paper question rather than a code one. The enrollment form PDF carries no Student ID, '
            .'program, year level, school year or semester anywhere in its body — read off the rendered file on '
            .'2026-10-21, where not one identity token appears although the record behind it holds all four. The desk '
            .'reaches it as a PDF only, which is a decision rather than a gap, because the blade is the paper — and '
            .'the paper it must match has never been supplied: §5 of the print-fidelity comparison and item 10 of the '
            .'Registrar’s 2026-10-11 decision sheet both still record the blank enrollment form as not provided.',
    ],
    // P-2's consequence cell.
    [
        'what the paper still cannot do is say which program or term the enrollment belongs to.',
        'what the paper still cannot do is say who the student is, which program or term the enrollment belongs to, '
            .'or which school year it was issued in — four identity facts the record holds and the form omits, so '
            .'nothing on the page ties it to the file it belongs in.',
    ],
    // G-29's own account of what reading found that no sweep could.
    [
        'One row was found by reading rather than by the checker: P-2 states no verdict word at all, and the '
            .'enrollment form still carries no course, term or year level, so it is the register’s only live '
            .'engineering item that the open-claims sweep cannot see.',
        'Two things were found only by reading, because no sweep can see either: P-2 carried no verdict word at all, '
            .'so the register’s single live engineering item was invisible to a status check until this pass named it; '
            .'and §28.6’s row 5 claimed the form prints the ID it holds, “verified by reprinting and reading the '
            .'paper”, which the extracted text of the reprint contradicts. Both are claims about an artefact, not about '
            .'a line of code, so a citation can land on a live line and the sentence still be false.',
    ],
    [
        'Four sections still asked questions the registers had answered, and §25.5 described a letterhead bridge that '
            .'had been built twenty days earlier',
        'Five sections still asked questions the registers had answered, or claimed a reading of the paper the paper '
            .'denies; §25.5 described a letterhead bridge that had been built twenty days earlier',
    ],
    // §28.8's closing sentence, which pointed at P-2 as the verdict-less row.
    [
        'and P-2, the one print row that states no verdict word at all while the enrollment form it describes still '
            .'cannot print a program or a term.',
        'and P-2, which now states its verdict: the enrollment form carries no student identity, program, year level '
            .'or school year on the paper at all, and is waiting on the blank physical form the Registrar has not yet '
            .'supplied.',
    ],
];

foreach ($edits as $i => [$from, $to]) {
    $n = substr_count($flat, $from);
    if ($n !== 1) {
        printf("REFUSED: edit %d's sentence appears %d time(s) in the dump: [%s…]\n", $i, $n, mb_substr($from, 0, 60));
        exit(1);
    }
}

// §28.6 row 5's claimed verification. That sentence is split across runs in the XML, so a text needle
// cannot reach it; the cell edit addresses it by the cell's own opening words inside the row the label
// selects.
$cellNeedle = 'Both print the schoolIdNumber they already hold, verified by reprinting and reading the paper.';
if (substr_count($flat, $cellNeedle) !== 1) {
    printf("REFUSED: §28.6's sentence appears %d time(s) in the dump.\n", substr_count($flat, $cellNeedle));
    exit(1);
}
$cellEdits = [
    [
        'The student ID is not on the paper (P-1, P-2)',
        'Both print the schoolIdNumber',
        'Re-read on the paper itself on 2026-10-21, by extracting the text of the set `php artisan ems:print-fidelity` '
            .'renders from live `ems` (storage/app/scratch/lane62-print-paper-probe.php). The slip holds up: its face '
            .'carries its own subject’s School ID (DEMO-2026-018 in today’s set), as do the certificate '
            .'(DEMO-2026-017) and the subject load. The enrollment form does not — no ID token of any kind appears in '
            .'its text, and no school year either, although the record behind that paper holds both. So the half of '
            .'this sentence about the form was written without reading it, and P-2 remains open.',
        1,
    ],
];

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass175.docx', $edits, $cellEdits, $apply);
