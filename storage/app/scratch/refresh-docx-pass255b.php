<?php

/**
 * Docx pass 255b — the sentences and citations that still describe the Registrar's private return.
 *
 * Ruling 14's `RegistrarController::returnToEvaluation()` was deleted by the one return act (gap G-10,
 * development prompt §6.4), which leaves eleven citations to `RegistrarController.php:304-326` / `-328`
 * and four sentences that state the act as the Registrar's alone over a `paid` record. The bounds sweep
 * cannot see any of it: `:304` is now a live line of the comment that records the retirement, so every
 * one of those pointers lands inside the right file and says something the file no longer does. Each
 * edit here was probed with `lane70-probe-needles.php` first and carries the count the probe measured.
 *
 * The four cells are taken through the engine's cell kind rather than as text needles because each
 * citation sits in a different `<w:t>` run from the prose it belongs to — a partial text rewrite would
 * leave the new mechanism's words beside the old method's line numbers, which is the half-applied
 * register this file refuses elsewhere. Two of the four are mirror rows (§28.5 asks the question, §30
 * repeats it), so their needles carry expect=2 and both copies move together.
 *
 * NOT here, on purpose:
 *   - §7.1's transition table needs NEW rows (a return is legal from every state but `dropped`, and
 *     `returnedToAdmission` is a state a record can be in), which means `<w:tr>` clones the engine
 *     refuses — pass 255c;
 *   - §24's dictionary entry for `enrollmentreturns`, §28.3's G-32 register row and §1.1's record row
 *     are clones too — 255c;
 *   - the §28.7 E-4 walkthrough row, whose claim ("the record cannot reach `paid` again") has to be
 *     decided by driving the round trip, not by re-wording it.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass255b.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass255b-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

$recorder = 'app/Services/ReturnRecorder.php:87-170';
$refusals = 'app/Services/ReturnRecorder.php:176-205';

docxApply(
    $docx,
    $backup,
    [
        // §16.4's hold paragraph. Its citation is replaced separately below because the same pointer
        // appears in §24's timeline paragraph, and both mean the same write.
        ['The Registrar may send a paid enrollment back to Department Evaluation with a mandatory returnReason',
            'Any desk holding the one return right may send a record back to Department Evaluation with a mandatory reason, a category and the correction owed',
            null, 1],
        ['RegistrarController.php:304-328', $recorder, null, 2],
        ['The Registrar hold writes the reason twice', 'The return writes the reason twice', null, 1],
    ],
    [
        // §7.1's transition table, the row this record moves through when it is sent back.
        ['BR31 return-for-rework', 'Registrar Office',
            'Any of the three desks holding enrollment.return'],
        ['BR31 return-for-rework', 'RegistrarController.php:304-326', $recorder],
        ['BR31 return-for-rework', 'BR31 return-for-rework',
            'BR31 return-for-rework. A return is legal from every state but dropped, the reason is required '
            .'(returnReason), and the category, destination and expected correction are stored in their own '
            .'columns — see §24 enrollmentreturns.'],

        // §28's B-9 row: the enforcement pointer, not the office's own name.
        ['Registrar finds a defect after payment', 'Enforced',
            'Enforced — '.$refusals],

        // §28.3's E-3 desk step: it promised the signed boxes stay untouched, and what the act really
        // does is re-open the destination and everything after it while the history keeps the act.
        ['Ruled 2026-10-23 that this is the policy', 'Status moves to returnedToEvaluation',
            'Status moves to returnedToEvaluation and the reason is stored, and the payments stay exactly as '
            .'they are. The destination box and every box after it go back to pending with their signer and '
            .'date cleared so the desk can sign again; boxes before the destination keep theirs. Clearing is '
            .'not erasing: the status-history row, the enrollmentreturns row and the audit row all keep who '
            .'did what, which is the rule the payment void follows.'],

        // The §28.5 / §30 mirror pair: the ruling is unchanged, the method name and its line numbers are not.
        ['Is a returned enrollment’s payment kept, or must Accounting re-collect?',
            'Answered 2026-10-23 by the owner: kept.',
            'Answered 2026-10-23 by the owner: kept. The return moves the status and stores the reason and '
            .'touches no payments row ('.$recorder.'), and that is now policy rather than a gap — a return '
            .'voids nothing and refunds nothing, the receipts keep counting through Payments::held(), and '
            .'Accounting neither re-collects nor reverses (E-3, §17).', 2],
        // Row anchor is a RUN-contiguous fragment, not the full question: the question is split across
        // `<w:t>` elements by a styled word, and a phrase needle cannot match that. Measured with
        // storage/app/scratch/lane70-probe-needles.php — this fragment occurs exactly twice, once per
        // mirror row, and nothing else in the document carries it.
        ['must the payments be voided or refunded first?',
            'Answered 2026-10-23 by the owner: neither.',
            'Answered 2026-10-23 by the owner: neither. The return stores a reason and moves the status and '
            .'nothing else ('.$recorder.'), and the money stays counted: outstandingBalance() reads the '
            .'receipts on the enrollment ( app/Models/Studentassessments.php:81-86 ), so an account whose '
            .'paper went back to Evaluation is not re-billed for pesos already handed over (E-3).', 2],
    ],
    $apply
);
