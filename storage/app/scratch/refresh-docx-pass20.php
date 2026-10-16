<?php

/**
 * Pass 20 — §13.7, the section the shift module never had.
 *
 * The document described the shift flow only as a row in the §28 register. The new paragraphs
 * are CLONED from the chapter's own styles — the §13.6 subsection heading and a §13.3 body
 * paragraph — so the section cannot arrive in a font of its own, and the engine's container
 * guard lets <w:p> move by exactly the ten paragraphs this block adds and nothing else.
 */

require __DIR__.'/docx-edit-engine.php';

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

/** Flatten the paragraph that owns a unique anchor into [start, end, body]. */
$paragraph = function (string $xml, string $anchor): array {
    if (substr_count($xml, $anchor) !== 1) {
        fwrite(STDERR, "template anchor not unique: {$anchor}\n");
        exit(1);
    }
    $p = strpos($xml, $anchor);
    $start = max(strrpos(substr($xml, 0, $p), '<w:p>'), strrpos(substr($xml, 0, $p), '<w:p '));
    $end = strpos($xml, '</w:p>', $p) + 6;

    return [$start, $end, substr($xml, $start, $end - $start)];
};

/** Rewrite a paragraph body to a single run carrying new text, keeping the first run's properties. */
$retext = function (string $body, string $text): string {
    preg_match('/<w:r\b.*?<\/w:r>/s', $body, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $stripped = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $body);
    $escaped = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);

    return str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>', $stripped);
};

[, , $headingTemplate] = $paragraph($xml, '13.6 Retention Examination');
[, , $bodyTemplate] = $paragraph($xml, 'The subject list the department may propose from is the curriculum');

$heading = '13.7 Program Change — the Shift Request';

$paragraphs = [
    'A shift is a student already enrolled at SEAIT moving to a different program, and the paper that authorises it starts at this stage. Ruling 11 (2026-10-02) placed it here rather than at Admission because the student is not a new applicant: the department already holds the record, the load and the credit evaluation, and the Guidance councillor’s signature is the final call. This closes gap G-7, which recorded that a program change existed nowhere in the application as an act — only as a courseId that differed from the previous term’s.',
    'The paper is its own table, shiftingrequests (migration 2026_10_03_1000000008_create_shiftingrequests_table.php): the student, the program being left and the one being entered, the enrollment the paper retires (currentEnrollmentId) and the one the grant opens (grantedEnrollmentId), the student’s own willStatement — required, 20 to 2,000 characters, captured on the form rather than summarised by staff — then three signature pairs: the filing hand (requestedBy, requestedAt), the department’s endorsement (departmentSignedBy, departmentSignedAt, which is also where the receiving termId and yearLevel are stated), and the decision (decisionBy, decidedAt, decisionRemarks). The row carrying both enrollment ids IS the history of the change (§21.4); no separate history table was added, and AuditLogObserver is attached to the model, so later edits to the paper are recorded too.',
    'The order is enforced by the status, not by trusting the desk. ShiftRequestStatus runs pending → endorsed → granted | rejected. ShiftRequestPolicy::endorse() (app/Policies/ShiftRequestPolicy.php:57) requires shift.sign.department AND that the paper is still pending; ShiftRequestPolicy::decide() (:71) requires shift.grant AND that it is endorsed. So the councillor cannot decide a paper the department has not signed, the department cannot re-sign a closed paper, and nothing is decided twice. Shiftingrequests::waitingOn() (app/Models/Shiftingrequests.php:47) is the single place that names whose desk each open paper is waiting on, and the docket’s four counters read from it.',
    'Who may act, live after the reseed (94 permissions across 15 modules): shift.request.create is held by DeptEvaluator, Dean and ProgramHead; shift.sign.department by Dean and ProgramHead; shift.grant by GuidanceStaff alone — the councillor’s signature is the final call, which is why it is not shared with the department that endorsed the paper. The routes are registered under /evaluation/shift-requests BEFORE /evaluation/{enrollment} (routes/web.php:81-84) or the docket would be bound as an enrollment id; the screen is resources/js/Pages/Evaluation/ShiftRequests.jsx, reached from the Evaluation desk sub-navigation and tabbed on the Exam desk, because the two desks examine the same students.',
    'Filing refuses three things (ShiftRequestController::store(), app/Http/Controllers/Evaluation/ShiftRequestController.php:109): a student with no non-dropped enrollment — a person coming to SEAIT for the first time is admitted, not shifted, and the refusal says so; a target program identical to the one the student is already in, which is not a change; and a second open paper for the same student, because two requests in flight would ask Guidance for opposite answers. The enrollment named as currentEnrollmentId is the one the grant retires, so the change is never left to be reconstructed later from a differing courseId.',
    'Granting is where the seat rules meet the paper (ShiftRequestController::decide(), :200). A paper that reached the councillor without a term and a level is refused, because those are the endorsement’s job. Then every active enrollment the student holds in the receiving term OTHER than the one this paper names makes the grant refuse and point at the Registrar’s drop — a shift is not a way around ruling 17’s one seat per student per term. Where the record being left sits in the same term as the receiving enrollment, the state machine moves it to dropped with remarks naming the shift request, and its proposed and confirmed subject rows are retired, which is what releases the block seat (Blocking weighs distinct students who are not dropped). Where it sits in an earlier term the rows are history and are left alone. The receiving enrollment is issued through EnrollmentIssuer with studentType = shifter, enrollmentType = old, no admissionId, and the curriculum version pinned at issue.',
    'Examinations do not gate a shift. Ruling 11 states the proof of readiness as the form plus the credit evaluation, so EvaluationController::retentionBlocker() (:788) returns null for an enrollment that a granted shift request names as its grantedEnrollmentId. ShiftRequestFlowTest pins both halves, because an exemption that was never tested is indistinguishable from a missing rule: the same course and term with no shift behind it still meets the retention blocker.',
    'The detection rule is what keeps the exemption honest. Because a program change is now a named act with two signatures, an ordinary issue that quietly moves a student to another program is refused: evaluation.store rejects a courseId differing from the student’s last enrollment and names the shift flow in the message (:876). A shifter is therefore only ever a shifter because a councillor signed for it.',
    'Downstream the receiving load behaves like a transferee’s, not a continuing student’s: the department proposes and credit-evaluates it, the mandatory-block completeness rule is relaxed for shifter and transferee types (they may carry a partial load, EvaluationController:574), and the Registrar’s checklist reads clearance for them the way it does for any returning type. Where a shift moves a student mid-term, the retired record keeps its own history row, so the ladder in §6 still shows what the student actually did.',
];

$block = $retext($headingTemplate, $heading);
foreach ($paragraphs as $t) {
    $block .= $retext($bodyTemplate, $t);
}

$inserts = [
    ['anchor' => '14. Stage 7', 'xml' => $block, 'pos' => 'before'],
];

$cellEdits = [
    // §28's G-7 row now points at the section that describes the flow.
    ['There was no program-change action anywhere', '§8.4',
        '§8.4, §13.7, §16.3, §17.3, §19.5, §20.8, §21.4'],
];

printf("built %d paragraphs (%d chars) for insertion\n", 1 + count($paragraphs), strlen($block));

docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-04-pass20.docx', [], $cellEdits, $apply, [], $inserts);
