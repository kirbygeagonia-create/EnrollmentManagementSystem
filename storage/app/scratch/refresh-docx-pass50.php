<?php
/**
 * Pass 50 — Accounting, Clearance and the print-numbering paragraphs, re-pointed and re-read.
 *
 * Three of these rows are not citation repairs at all: they state behaviour the rulings changed.
 * The Accounting box is signed through OfficeId::Accounting now, in two acts rather than one; a
 * voided receipt sits in an enum that has grown a fourth value; and the desk that "cannot sign the
 * box by hand" gained exactly that action when ruling 9 settled zero-balance accounts. Line
 * numbers measured from the current files: record() :171-231 (assessed guard :179-182, OR rule
 * :186-188, paid transition and sign :220-228), settleNoBalance() :243-280 (sign at :273),
 * void() :327-371 (the pending write at :332), recordIssue() :105-154 with its scope key at :168-181.
 */
require __DIR__.'/docx-edit-engine.php';

$src = 'Documentation/EMS Complete Documentation.docx';

$textEdits = [
    // The transition into paid, and the balance recompute behind it.
    ['AccountingController.php:131', 'AccountingController.php:220-228'],
    ['AccountingController.php:86-143', 'AccountingController.php:196-215'],
    ['AccountingController.php:86-99', 'PaymentPolicy.php:34-51'],

    // The assessed guard every queue row cites.
    ['AccountingController.php:95-99', 'AccountingController.php:179-182'],
    ['AccountingController.php:95-98', 'AccountingController.php:179-182'],

    // OR-number uniqueness is a validation rule and a DB key, not a policy.
    ['AccountingController.php:84', 'AccountingController.php:186'],
    ['ClearanceController.php:249', 'AccountingController.php:186'],

    // The validator that refuses a zero receipt.
    ['AccountingController.php:102', 'AccountingController.php:187'],
    ['PaymentPolicy.php:46-49', 'PaymentPolicy.php:44-48'],

    // The print service’s own span, now that six document types live in it.
    ['PrintService.php:59-193', 'PrintService.php:58-330'],

    // Clearance: the receipt act, the requirement loop, the period actions.
    ['ClearanceController.php:185-195', 'ClearanceController.php:262-276'],
    ['ClearanceController.php:165-172', 'ClearanceController.php:240-248', null, 2],
    ['ClearanceController.php:157-172', 'ClearanceController.php:229-248'],
    ['ClearanceController.php:267-282', 'ClearanceController.php:354-372'],
    ['ClearanceController.php:53-60', 'ClearanceController.php:376-402'],
    ['ClearanceController.php:281', 'ClearanceController.php:367'],
    ['ClearanceController.php:125,148', 'ClearanceController.php:224'],
    ['ClearancePolicy.php:43-62', 'ClearancePolicy.php:44-62'],
];

$cellEdits = [
    // §26.6 — the void/pending collision is real, but the vocabulary it described is gone.
    ['AccountingController.php:180', 'PaymentStatus has paid',
     'PaymentStatus has paid , partial , pending and refunded ; voiding a receipt stores it as pending ( AccountingController.php:332 )', 1],
    ['AccountingController.php:180', 'The same word describes a receipt',
     'The same word still describes a receipt that has not been paid and a receipt that was cancelled, and a report that totals pending payments still double-counts reversals. What changed is the enum: partial and refunded arrived with rulings 13 and 14, so the column now carries four states while the void act uses the vaguest of them.', 1],

    // §27.3 step 8 — the desk gained the action this row said did not exist.
    ['No desk action signs it', 'No desk action signs it',
     'Closed 2026-10-04 by ruling 9: an account that owes nothing is signed out by settleNoBalance() ( AccountingController.php:243-280 , the signature at :273 ), and a collected payment signs the same box at :226. Neither is a hand-written signature — the box is the accounting act’s own.', 1],
];

$paraEdits = [
    // §15 — the bare-integer quirk, now the enum.
    ['AccountingController.php:138',
     'The Accounting box is signed through OfficeId::Accounting->value — at AccountingController.php:226 when a payment settles the account, and again at :273 when a zero-balance account is settled without money. The bare integer this paragraph recorded is gone, and the same enum decides which office a workflow notice is addressed to ( App\Support\WorkflowInbox ).'],

    // §25 — what documentNumber means, restated from the one helper that mints it.
    ['a subject ordinal for class cards',
     'documentNumber is one sequence, minted in one place — PrintService::recordIssue() ( PrintService.php:105-154 ) — but its scope depends on which column identifies the paper: the student’s own series for a clearance slip that carries a studentId, otherwise the enrollment’s series, the block’s series for a block schedule, and the enrollment’s series for everything else ( :168-181 ). Each new number is one above the highest already issued in that scope, taken under a row lock on the maximum rather than a row count ( :130 ), and it steps over the numbers the key-less legacy rows reserve ( :132-142 ). The class card is the exception in form rather than in numbering: one document is issued per subject, its number is only ever its own title line ( class-card.blade.php:6 ) and the card prints no Document No. row, while the slip and the certificate both print theirs ( clearance-slip.blade.php:40 , enrollment-certificate.blade.php:46 ). The unique index over ( enrollmentId , documentType , documentNumber ) was added with the print keys (§25, P-14), and it covers the rows that carry an enrollment — the 21 legacy rows with no key at all remain outside it, because MySQL treats their NULLs as distinct, which is why they are reserved rather than repaired (ruling 6).'],
];

$apply = in_array('--apply', $argv, true);
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass50.docx', $textEdits, $cellEdits, $apply, $paraEdits);
