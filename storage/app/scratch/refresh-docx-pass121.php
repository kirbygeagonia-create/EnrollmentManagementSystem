<?php

/**
 * Pass 121 — two role-permission claims the negative-claim sweep found, both measured against live `ems`.
 *
 * Lane 34 asks a question the line sweep cannot: the document says something is ABSENT, and the commit that
 * adds it never comes back to edit the sentence. Two answers were stale, both about what `OfficeHead` holds:
 *
 *   ROW 945 (§22 role matrix, the Shifter row) — "OfficeHead is no longer granted admission.approve,
 *     enrollment.approve, evaluation.sign or id.validate (ruling 7)". Three of those four are true. The fourth
 *     is not: the role HOLDS `id.validate` (live query: 42 permissions, `id.validate` among them; and
 *     `RbacSeeder.php` grants it in the OfficeHead list). It also omits the two the ruling really did move —
 *     `clinic.sign` and `id.sign`. So the sentence was wrong in both directions at once, and the seeder's own
 *     comment is the authority: ruling 7 split five signature acts out — enrollment.approve, admission.approve,
 *     evaluation.sign, clinic.sign, id.sign — and `id.validate` was never one of them.
 *
 *   P 952 (§28 X-4, an OPEN question awaiting a human answer) — "Today an OfficeHead of any office holds
 *     enrollment.approve, admission.approve, evaluation.sign, clinic.record, id.validate, payment.record and
 *     assessment.compute … and two of those policies are bypassed by X-1." Three of the seven named rights
 *     have not been held since ruling 7 was built on 2026-10-03, and the X-1 bypass was closed by the ability
 *     renames (`*AtDesk`, `*Request`, `clinic.recordAssessment`) that `AbilityNameCollisionTest` pins. This one
 *     matters more than a stale matrix cell: it is a question still on the register, describing an exploitable
 *     condition that the code no longer has, so the owner could answer a risk that does not exist.
 *
 * The question itself is kept open — whether the role is meant to be a generalist is a real design question,
 * and 42 permissions across nine modules is the evidence for asking it. What changes is the fact pattern under
 * it, and the sentence now says what is left to decide is custody, not exploitability.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';

$textEdits = [
    [
        'or id.validate (ruling 7); it keeps the counter acts',
        'or the five signature acts ruling 7 split out — enrollment.approve, admission.approve, evaluation.sign, clinic.sign and id.sign (built 2026-10-03). It does still hold id.validate, which authorises nothing on its own: IDPolicy.php:117-125 asks for id.sign beside it and office-22 custody. It keeps the counter acts',
    ],
];

$paraEdits = [
    [
        'For human confirmation (X-4)',
        'For human confirmation (X-4): whether OfficeHead is meant to be a generalist. Measured on live ems today the role holds 42 permissions spanning nine modules — admission, assessment, blocking, clearance, clinic, evaluation, exam, ID, payment and print (print.certificate, print.classCard, print.enrollmentForm, print.subjectLoad) — among them clinic.record, id.validate, payment.record and assessment.compute. The five signature acts are NOT among them: ruling 7 split enrollment.approve, admission.approve, evaluation.sign, clinic.sign and id.sign out to the desks that own each box when it was built on 2026-10-03. And the bypass this question used to carry is closed: the desk abilities are named away from the permissions the roles hold (clinic.viewAtDesk, id.viewAtDesk, id.validateRequest, id.remarkRequest, clinic.recordAssessment, payment.recordAtDesk, assessment.computeAtDesk) so Spatie’s Gate::before cannot grant one by permission, which AbilityNameCollisionTest now pins. What remains to decide is therefore custody — whether one head should reach nine modules at all — and not exploitability.',
    ],
];

require __DIR__.'/docx-edit-engine.php';
docxApply($src, $root.'/storage/app/scratch/docx-backup-2026-10-15-pass121.docx',
    $textEdits, [], in_array('--apply', $argv, true), $paraEdits);
