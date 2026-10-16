<?php

/**
 * Pass 122 — the number pass 121 invented, now measured.
 *
 * Pass 121 rewrote the X-4 open question and, in doing so, wrote "the role holds 42 permissions spanning nine
 * modules". Neither figure was measured: the live count is 43, and the permission names sort into fifteen
 * prefixes (admission 6, evaluation 5, clearance 5, block 4, clinic 4, print 4, assessment 3, id 3, payment 3,
 * and one each of audit, enrollment, exam, refdata, students, user). The sentence was written to replace an
 * unverified claim and introduced a second one, which is precisely the failure this whole streak is about —
 * a number that reads like a measurement and is not one.
 *
 * Caught here rather than at the defense because the standing rule says every figure a pass introduces gets
 * measured before the pass is applied, and this time that check was skipped. Counting it:
 *   SELECT COUNT(*) FROM role_permissions WHERE roleId = (OfficeHead)            -> 43
 *   SELECT SUBSTRING_INDEX(name,'.',1), COUNT(*) ... GROUP BY the prefix         -> 15 rows
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    [
        '42 permissions spanning nine modules — admission, assessment, blocking, clearance, clinic, evaluation, exam, ID, payment and print (print.certificate, print.classCard, print.enrollmentForm, print.subjectLoad) — among them',
        '43 permissions across fifteen name-spaces — admission 6, evaluation 5, clearance 5, block 4, clinic 4 and print 4 (print.certificate, print.classCard, print.enrollmentForm, print.subjectLoad), assessment 3, id 3 and payment 3, and one each of audit, enrollment, exam, refdata, students and user — among them',
    ],
];

require __DIR__.'/docx-edit-engine.php';
docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-15-pass122.docx',
    $textEdits, [], in_array('--apply', $argv, true));
