<?php
/**
 * Pass 52 — the four policies, re-pointed at the methods the sentences name.
 *
 * These are mostly small offsets (a signature line moves when a comment is added above it), but
 * three were genuinely mis-pointed: RegistrarPolicy.php:37 was cited for a comment that lives at
 * :63-69, PaymentPolicy.php:32 was cited for a database rule no policy holds, and the two files’
 * public spans shifted when the print helpers were folded into one private mayPrint().
 * Current maps — RegistrarPolicy: validatePrerequisites :36-91 (assessed-or-paid :47-50, the
 * clearance clause :63-69), approve :92-113, drop :114-174, mayPrint :146, the four print methods
 * :175-211. AdmissionPolicy: approve :48-66, approvalBlockers :67-136. PaymentPolicy: record
 * :34-51, void :56-88, refund :89-114, settle :115-131. ClearancePolicy: generateSlip :44-62,
 * recordDeskReceipt :68-94, approveRequirement :100-113, replaceLostSlip :120-142.
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$textEdits = [
    ['RegistrarPolicy.php:50-53', 'RegistrarPolicy.php:47-50', null, 2],
    ['RegistrarPolicy.php:39-86', 'RegistrarPolicy.php:36-91'],
    ['RegistrarPolicy.php:37,66', 'RegistrarPolicy.php:63-69'],
    ['RegistrarPolicy.php:37', 'RegistrarPolicy.php:63-69', 'Continuing students must have cleared obligations'],
    ['RegistrarPolicy.php:66-83', 'RegistrarPolicy.php:63-69'],

    ['PaymentPolicy.php:33-48', 'PaymentPolicy.php:34-51'],
    ['PaymentPolicy.php:33-51', 'PaymentPolicy.php:34-51'],
    ['PaymentPolicy.php:53-69', 'PaymentPolicy.php:56-88', null, 2],

    ['ClearancePolicy.php:69-88', 'ClearancePolicy.php:68-94', null, 4],
    ['ClearancePolicy.php:101-114', 'ClearancePolicy.php:100-113', null, 2],
    ['ClearancePolicy.php:45-62', 'ClearancePolicy.php:44-62'],
    ['ClearancePolicy.php:42-62', 'ClearancePolicy.php:44-62'],
    ['ClearancePolicy.php:120-131', 'ClearancePolicy.php:120-142'],

    ['AdmissionPolicy.php:47-94', 'AdmissionPolicy.php:48-66 , approvalBlockers() at :67-136', null, 2],
    ['AdmissionPolicy.php:45-70', 'AdmissionPolicy.php:39-47 , approvalBlockers() at :67-136'],
    ['AdmissionPolicy.php:45', 'AdmissionPolicy.php:39-47', 'need an admission record before enrollment'],
];

$cellEdits = [
    // §26.2 BR5 — the collision is real, but the second citation named no such rule.
    ['100% aid cap', 'OR number must be unique',
     'OR number must be unique ( AccountingController.php:186 and the database key payments.uq_payments_ornumber ); the aid cap is BR19 in code', 1],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass52.docx', $textEdits, $cellEdits, $apply);
