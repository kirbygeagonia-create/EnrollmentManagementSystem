<?php

/**
 * Pass 199 — the §25 identity matrix: every bare number re-taken against the file its column owns, and named.
 *
 * The matrix has one row per required context fact and one column per printed document, and its cells say what
 * the desk screen and the PDF each do — `desk ✓ ( :87-90 ) / PDF ✗`. The numbers are bare because the file is a
 * COLUMN property, stated nowhere in the cell, so the bounds sweep's attribution rule (the file named within 160
 * characters) cannot bind any of them and no instrument had ever read one. `lane70-matrix-cites.php` resolves each
 * cell to its column's two files and prints the line it lands on; reading those seventeen lines against what the
 * cells claim found that most of them were stale in the way only a template edit causes:
 *
 *   Student type            :207      -> enrollment-form.blade.php:265-266   (:207 is a `<div class="field">`)
 *   Academic standing       :210-221  -> enrollment-form.blade.php:273-278   (the two radio rows; :210 is `</div>`)
 *   Enrollment status, desk :87-90    -> PrintCertificate.jsx:93-95          (:87 is the ENROLLMENT DATE row)
 *   Signatory, form         :273-290  -> enrollment-form.blade.php:355-368   (the evaluator line + three blanks)
 *   Signatory, certificate  :106-119 / :117-124
 *                           -> PrintCertificate.jsx:114,:119,:124 and enrollment-certificate.blade.php:131-136
 *                              (:117-124 in the certificate is the TOTAL UNITS row)
 *   Signatory, subject load :125-137  -> PrintSubjectLoad.jsx:129-137        (:125 is blank — the tool's MOVED row)
 *   Dates, form             :227,:289 -> enrollment-form.blade.php:292,:369  (Form Issue Date; formSignedDate)
 *   Dates, certificate      :113      -> PrintCertificate.jsx:120-121 desk, enrollment-certificate.blade.php:127 PDF
 *   Dates, class card       :124      -> class-card.blade.php:126            (:124 is a footer-field div)
 *   Dates, slip             :54 / :76 / :89 -> clearance-slip.blade.php:80 / :103 / PrintSlip.jsx:101
 *
 * The fix names the file inside the cell rather than teaching a checker to guess a column: once the citation
 * carries its basename, the existing sweep judges it on every future run, which is the rule this repository
 * learned from §21.2's column. Two cells that were already correct (the class card's signature box, the block
 * schedule's year level) are kept and merely named.
 *
 *   php storage/app/scratch/refresh-docx-pass199.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass199.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$files = [
    'form' => 'resources/views/prints/enrollment-form.blade.php',
    'cert-jsx' => 'resources/js/Pages/Registrar/PrintCertificate.jsx',
    'cert-blade' => 'resources/views/prints/enrollment-certificate.blade.php',
    'card' => 'resources/views/prints/class-card.blade.php',
    'load' => 'resources/js/Pages/Registrar/PrintSubjectLoad.jsx',
    'slip' => 'resources/views/prints/clearance-slip.blade.php',
    'slip-jsx' => 'resources/js/Pages/Clearance/PrintSlip.jsx',
    'block' => 'resources/views/prints/block-schedule.blade.php',
];
$lines = [];
foreach ($files as $k => $rel) {
    $lines[$k] = (array) file($root.'/'.$rel);
}

/** Each target the rewrite claims: [file key, line, a token that must be on it]. */
$claims = [
    ['form', 265, 'Student Type'], ['form', 266, 'studentType'],
    ['form', 273, 'regular'], ['form', 277, 'irregular'],
    ['form', 292, 'formIssuedDate'], ['form', 369, 'formSignedDate'],
    ['form', 355, 'Evaluator'], ['form', 360, '____'],
    ['cert-jsx', 93, 'Enrollment Status'], ['cert-jsx', 95, 'formatStatus'],
    ['cert-jsx', 114, 'border-t'], ['cert-jsx', 119, 'border-t'], ['cert-jsx', 124, 'border-t'],
    ['cert-jsx', 120, 'Date Issued'], ['cert-jsx', 121, 'issuedDate'],
    ['cert-blade', 127, 'Date Enrolled'], ['cert-blade', 131, 'Evaluated by'], ['cert-blade', 135, 'Processed by'],
    ['card', 110, 'Signature of Instructor'], ['card', 121, 'Issued by'], ['card', 126, 'now()'],
    ['load', 129, 'Student Signature'], ['load', 133, 'Adviser Signature'], ['load', 137, 'Registrar Signature'],
    ['slip', 80, 'Date to be Signed'], ['slip', 103, 'approvalDate'],
    ['slip-jsx', 101, 'Received on'],
    ['block', 48, 'Year Level'], ['block', 44, 'Course:'],
];
$bad = 0;
foreach ($claims as [$k, $n, $needle]) {
    $t = trim($lines[$k][$n - 1] ?? '');
    if (! str_contains($t, $needle)) {
        printf("  BAD  %s :%d is [%s], which does not carry [%s]\n", $k, $n, mb_substr($t, 0, 60), $needle);
        $bad++;
    }
}
if ($bad > 0) {
    printf("\nREFUSED: %d claimed line(s) do not carry the field the cell names.\n", $bad);
    exit(1);
}
echo "  ok   all ".count($claims)." claimed lines carry the field the cell names\n";

/**
 * Anchors are the bare NUMBER runs themselves (`:207`, `:87-90`, `:124`, `:48`) where a neighbouring phrase is
 * split across runs — the matrix cell "desk ✗ / PDF ✗ — both print enrollmentType instead" is three runs, so the
 * phrase is not contiguous in the XML and only the number run is. The engine's own guard refuses an anchor that
 * appears in more than the one row it expects.
 */
$cellEdits = [
    // [row needle, cell prefix, new cell text, expected rows]
    [':207</w:t>', 'PDF ✓ studentType',
        'PDF ✓ studentType ( enrollment-form.blade.php:265-266 )'],
    ['PDF ✓ regular/irregular', 'PDF ✓ regular/irregular',
        'PDF ✓ regular/irregular ( enrollment-form.blade.php:273-278 )'],
    [':87-90</w:t>', 'desk ✓ (',
        'desk ✓ ( PrintCertificate.jsx:93-95 ) / PDF ✗'],
    // the signatory row: four of its six cells carry numbers
    ['PDF: evaluator named', 'PDF: evaluator named',
        'PDF: evaluator named, 3 blank lines ( enrollment-form.blade.php:355-368 )'],
    ['desk: 3 blank lines', 'desk: 3 blank lines',
        'desk: 3 blank lines ( PrintCertificate.jsx:114 , :119 , :124 ); PDF: evaluated + processed by ( enrollment-certificate.blade.php:131-136 )'],
    ['PDF: instructor box', 'desk: student + instructor',
        'desk: student + instructor; PDF: instructor box + “Issued by” ( class-card.blade.php:110 , :121 )'],
    ['desk: student + adviser + registrar', 'desk: student + adviser + registrar',
        'desk: student + adviser + registrar ( PrintSubjectLoad.jsx:129 , :133 , :137 )'],
    // the dates row
    ['PDF: issue + signature', 'PDF: issue + signature',
        'PDF: issue + signature ( enrollment-form.blade.php:292 , :369 )'],
    ['desk: issued (blank)', 'desk: issued (blank)',
        'desk: issued (blank) ( PrintCertificate.jsx:120-121 ); PDF: enrolled ( enrollment-certificate.blade.php:127 )'],
    [':124</w:t>', 'desk: today; PDF: now()',
        'desk: today; PDF: now() ( class-card.blade.php:126 )'],
    ['PDF: date to be signed', 'PDF: date to be signed',
        'PDF: date to be signed ( clearance-slip.blade.php:80 ) + per-approval date ( :103 ); desk: received-on only ( PrintSlip.jsx:101 )'],
    // the one already-true bare number in the matrix, named so the sweep can judge it
    ['block’s own (</w:t>', 'block’s own',
        'block’s own ( block-schedule.blade.php:48 ); desk ✓'],
];

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass199.docx', [], $cellEdits, $apply);
