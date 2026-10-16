<?php

/**
 * One-shot refresh of Documentation/EMS Complete Documentation.docx against the build
 * as of 2026-10-01.
 *
 * The document is the project's authority (owner ruling, 2026-09-29), so a sentence it
 * still asserts about a removed route or a dead notification channel becomes a false
 * requirement the moment the code changes. Every edit here is text inside an existing
 * <w:t> run or an existing table cell: no element is added or removed, so the file
 * keeps its styles, tables and bookmarks.
 *
 *   rawEdit   — whole-run needle, unique in the XML (or an expected count)
 *   paraEdits — one paragraph (found by an anchor); each edit names the run by its
 *               exact current text, so a wrong assumption fails instead of overwriting
 *   rowEdits  — one table row (found by an anchor); each edit names the cell by the
 *               start of its text and rewrites it as a single run in the cell's style
 *
 * The dry run prints what every target currently holds. Nothing is written while any
 * edit fails, and only --apply moves the file.
 */

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$tmp = sys_get_temp_dir().'/ems-docx-'.uniqid().'.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open source\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$failures = [];
$applied = 0;

function esc(string $t): string
{
    return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $t);
}

function show(string $s): string
{
    return str_replace(["\n", "\t"], ['\\n', '\\t'], $s);
}

function report(string $kind, bool $ok, string $label, string $note = ''): void
{
    global $applied, $failures;
    if ($ok) {
        $applied++;
        echo str_pad($kind, 5).' ok   '.substr($label, 0, 44)."\n        ".substr($note, 0, 150)."\n";
    } else {
        $failures[] = $label;
        echo str_pad($kind, 5).' FAIL '.substr($label, 0, 44)."\n        !! ".$note."\n";
    }
}

function rawEdit(string &$xml, string $needle, string $replacement, int $expected, string $label): void
{
    $count = substr_count($xml, $needle);
    if ($count !== $expected) {
        report('raw', false, $label, "found {$count}x, expected {$expected}x: ".substr(show($needle), 0, 90));
        return;
    }
    $xml = str_replace($needle, $replacement, $xml);
    report('raw', true, $label, "{$count}x  ".substr(show($needle), 0, 80));
}

/** Enclosing element span holding the anchor, or [false, false]. */
function spanFor(string $xml, string $anchor, string $open, string $close): array
{
    // An anchor phrase can occur in prose before it occurs in a table. Take the first
    // occurrence that is actually inside the element, not the first occurrence.
    $from = 0;
    while (($pos = strpos($xml, $anchor, $from)) !== false) {
        $start = strrpos(substr($xml, 0, $pos), $open);
        $end = strpos($xml, $close, $pos);
        if ($start !== false && $end !== false
            // Containment: nothing may close the element between its opening tag and
            // the anchor, or the anchor belongs to a different element than the span.
            && strpos(substr($xml, $start, $pos - $start), $close) === false) {
            return [$start, $end + strlen($close)];
        }
        $from = $pos + strlen($anchor);
    }
    return [false, false];
}

/** Rewrite the cells of one row's XML; each edit is [start of cell text, new text]. */
function rewriteCells(string $row, array $edits, ?string &$error): ?string
{
    foreach ($edits as [$prefix, $to]) {
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm, PREG_OFFSET_CAPTURE);
        $hits = [];
        foreach ($cm[0] as $i => $cell) {
            preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell[0], $tm);
            if (strncmp(implode('', $tm[1]), $prefix, min(strlen($prefix), 50)) === 0) {
                $hits[] = $i;
            }
        }
        if (count($hits) !== 1) {
            $cells = [];
            foreach ($cm[0] as $c) {
                preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $c[0], $tm);
                $cells[] = show(substr(implode('', $tm[1]), 0, 26));
            }
            $error = 'cell matches for ['.substr(show($prefix), 0, 50).']: '.count($hits)
                .' | row cells: '.implode(' ~ ', $cells);
            return null;
        }
        $cell = $cm[0][$hits[0]][0];
        if (substr_count($cell, '<w:p>') !== 1) {
            $error = 'cell ['.substr(show($prefix), 0, 40).'] holds '.substr_count($cell, '<w:p>').' paragraphs';
            return null;
        }
        preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
        preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
        $newRun = '<w:r>'.$pr[0].'<w:t xml:space="preserve">'.esc($to).'</w:t></w:r>';
        $newCell = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
        $newCell = str_replace('</w:p>', $newRun.'</w:p>', $newCell);
        $row = substr_replace($row, $newCell, $cm[0][$hits[0]][1], strlen($cell));
    }
    return $row;
}

/**
 * @param array<int,array{0:string,1:string}> $edits each [exact current run text, new text]
 */
function paraEdits(string &$xml, string $anchor, array $edits, string $label): void
{
    [$s, $e] = spanFor($xml, $anchor, '<w:p>', '</w:p>');
    if ($s === false) {
        report('para', false, $label, 'anchor or containing paragraph not found: '.substr(show($anchor), 0, 70));
        return;
    }
    $region = substr($xml, $s, $e - $s);
    $seen = [];
    foreach ($edits as [$expect, $to]) {
        preg_match_all('/(<w:t(?: [^>]*)?>)(.*?)(<\/w:t>)/s', $region, $m, PREG_OFFSET_CAPTURE);
        $hits = [];
        foreach ($m[2] as $i => $inner) {
            if ($inner[0] === $expect) {
                $hits[] = $i;
            }
        }
        if (count($hits) !== 1) {
            report('para', false, $label, 'run matches for ['.substr(show($expect), 0, 50).']: '.count($hits));
            return;
        }
        $i = $hits[0];
        $seen[] = '['.substr(show($expect), 0, 34).']';
        $region = substr($region, 0, $m[2][$i][1]).esc($to).substr($region, $m[2][$i][1] + strlen($expect));
    }
    $xml = substr($xml, 0, $s).$region.substr($xml, $e);
    report('para', true, $label, $anchor.' <- '.implode(', ', $seen));
}

/**
 * @param array<int,array{0:string,1:string}> $edits each [start of cell text, new text]
 */
function rowEdits(string &$xml, string $anchor, array $edits, string $label): void
{
    [$s, $e] = spanFor($xml, $anchor, '<w:tr', '</w:tr>');
    if ($s === false) {
        report('row', false, $label, 'anchor or containing row not found: '.substr(show($anchor), 0, 70));
        return;
    }
    $error = null;
    $row = rewriteCells(substr($xml, $s, $e - $s), $edits, $error);
    if ($row === null) {
        report('row', false, $label, $error);
        return;
    }
    $xml = substr($xml, 0, $s).$row.substr($xml, $e);
    $seen = array_map(fn (array $e2) => '['.show(substr($e2[0], 0, 26)).']', $edits);
    report('row', true, $label, show($anchor).' <- '.implode(', ', $seen));
}

/**
 * Insert a copy of the row holding $anchor, with its cells rewritten, immediately
 * after it. Used to extend a table the build outgrew, keeping the table's own styling.
 */
function rowInsertAfter(string &$xml, string $anchor, array $edits, string $label): void
{
    [$s, $e] = spanFor($xml, $anchor, '<w:tr', '</w:tr>');
    if ($s === false) {
        report('ins', false, $label, 'anchor or containing row not found: '.substr(show($anchor), 0, 70));
        return;
    }
    $error = null;
    $row = rewriteCells(substr($xml, $s, $e - $s), $edits, $error);
    if ($row === null) {
        report('ins', false, $label, $error);
        return;
    }
    $xml = substr($xml, 0, $e).$row.substr($xml, $e);
    $seen = array_map(fn (array $e2) => '['.show(substr($e2[0], 0, 26)).']', $edits);
    report('ins', true, $label, show($anchor).' + '.implode(', ', $seen));
}

// ---------------------------------------------------------------- raw whole-run edits

rawEdit($xml,
    '<w:t xml:space="preserve">Revised 2026-09-28 · 54 application tables · schema read from the current migration set</w:t>',
    '<w:t xml:space="preserve">Revised 2026-10-01 (the 2026-09-28 description, re-read against the build) · 54 application tables · schema read from the current migration set</w:t>',
    1, 'header revision line');

rawEdit($xml, 'id.validate, id.release, id.sign', 'id.validate, id.sign', 3,
    'retire id.release in the role permission lists');

rawEdit($xml,
    '<w:t xml:space="preserve">1st Year (Freshman) · 2nd Year (Sophomore) · 3rd Year (Junior) · 4th Year (Senior)</w:t>',
    '<w:t xml:space="preserve">Year 1 (Freshman) · Year 2 (Sophomore) · Year 3 (Junior) · Year 4 (Senior), rendered through one shared formatter (statusLabel.js formatYearLevel())</w:t>',
    1, 'year-level vocabulary (concern #2)');

rawEdit($xml, 'Year Level: 1st Year', 'Year Level: Year 1', 1, 'print sample year-level wording');

rawEdit($xml, '<w:t xml:space="preserve"> or released.</w:t>',
    '<w:t xml:space="preserve"> or validated.</w:t>', 1, 'ID desk: pending or validated, no cancel action');

rawEdit($xml, '<w:t xml:space="preserve">releasedDate</w:t>',
    '<w:t xml:space="preserve">mismatchRemark</w:t>', 1, 'idrequests column list (releasedDate never existed)');

rawEdit($xml,
    '<w:t xml:space="preserve">Confirmed absent: any read path for Student-addressed rows; any delete or retention mechanism (</w:t>',
    '<w:t xml:space="preserve">Confirmed present after the 2026-10-01 fix: the bell reads the Staff-addressed rows both listeners now write. Confirmed absent: any delete or retention mechanism (</w:t>',
    1, '19.6 confirmed-absent line');

rawEdit($xml,
    '<w:t xml:space="preserve">Certification: all five gates passed, proposed subjects confirmed.</w:t>',
    '<w:t xml:space="preserve">Certification: all seven gates passed, proposed subjects confirmed.</w:t>',
    1, 'Registrar certification sentence');

rawEdit($xml, ' — five gates, including the clearance check; ',
    ' — seven gates, including the clearance check, admission-document verification and prerequisite validity; ',
    1, 'Registrar gate count in the approval sentence');

// ---------------------------------------------------------------- paragraph run edits

// §18.3 — the note that warned about a card left unreleased.
paraEdits($xml, 'A completed workflow does not mean a released card.', [
    ['A completed workflow does not mean a released card.', 'There is no card-release step to wait for.'],
    [' The last box is signed at *validation*, so an enrollment whose workflow reads ',
        ' Card production and hand-over were removed from the system by owner ruling on 2026-09-30, so the release route, the '],
    ['completed', 'id.release'],
    [' may still have a card sitting unclaimed at ', ' permission and any action that could write a '],
    ['validated', 'released'],
    ['. Report card production and card hand-over from ', ' status are gone. What the desk performs is validation: it stamps '],
    ['idrequests.status', 'validatedBy'],
    [', never from ', ' and '],
    ['workflowStatus', 'validatedDate'],
    ['.', ', signs the ID Office box, and is the last act this system records. Report what the desk did from idrequests.status, never from workflowStatus.'],
], '18.3 note: release step removed');

// §18.5 — the desk’s output still ended at a released request.
paraEdits($xml, ', and the final workflow signature. When the last box completes,', [
    ['released', 'validated'],
], '18.5 output: request status');

// §10.6 — the course-specific examination is now a per-course requirement.
paraEdits($xml, 'The course-specific examination is ', [
    ['The course-specific examination is ',
        'An absent course-specific result blocks approval only where the program asks for that examination. The switch is '],
    ['not', 'requiresCourseSpecificExam'],
    [' required for approval; a failed one blocks it, but an absent one does not (',
        ' on the course, added 2026-10-01 by the additive migration 2026_10_01_1000000001 and default false, so no program is obliged to hold a paper it never administered. Where the flag is off the earlier reading still holds: a failed result blocks approval, an absent one does not ('],
    ['AdmissionPolicy.php:83-90', 'AdmissionPolicy.php:96-130'],
    [', &quot;only enforce course-specific exam if one was administered&quot;). If board programs are meant to require it before admission, this is a hole (§28, G-6, C-5).',
        '). Which programs set the flag is the department’s answer to C-5, entered in Reference Data → Courses; the flag closes the mechanism (G-6), not the policy question.'],
], '10.6: course-specific examination flag');

// §19.6 — the notification recipient, answered in code.
paraEdits($xml, 'For human confirmation (C-9):', [
    ['For human confirmation (C-9):', 'Answered in code on 2026-10-01 (C-9):'],
    [' the intended recipient. The data model points at students, the interface points at staff, and there is no student portal. Until SEAIT decides, the honest documentation position is: notifications are recorded, none are delivered.',
        ' the staff member who must act next. Both writers addressed every row at App\Models\Students while the only reader, NotificationController, filtered on App\Models\Staffusers, so the channel was dead at both ends and the students who owned the rows have no login. App\Support\WorkflowInbox now resolves the office owning the first still-pending box — or, before a workflow form exists, the desk the enrollment status hands the record to — and a completed form notifies the Registrar as records custodian. SEAIT can still reverse the ruling; until then the honest position is that notices are recorded and addressed to the desk, and are delivered only where a queue worker runs (C-10).'],
], '19.6: C-9 answered');

// §22.6 — the renamed-abilities sentence cited a gate that no longer exists.
paraEdits($xml, 'deliberately renamed', [['id.releaseRequest', 'id.remarkRequest']], '22.6: renamed ability list');

// ---------------------------------------------------------------- table cell edits

// §18.3 action table: the fourth step is now the mismatch remark.
rowEdits($xml, 'the physical card is handed to the student', [
    ['4 Release', '4 Remark'],
    ['id.releaseRequest', 'id.remarkRequest — the officer records, against the request, what did not match on the card or at the window.'],
    ['status = released', 'idrequests.mismatchRemark. No status change, no box signed (IDController.php:202-213).'],
], '18.3 row 4: release became remark');

// §27 findings register — the rows this workstream closed or narrowed.
rowEdits($xml, 'No code path creates an enrollment for a returning student', [
    ['AdmissionController.php:322-333',
        'Still open. AdmissionController is the only place in app/ that creates an Enrollments row, so stages 1-3 — intake, general examination, admission decision — remain the only way an enrollment comes into existence, and a continuing or shifting student cannot be enrolled for a new term inside the system. The 2026-10-01 retention work makes a returning student prove the previous term was learned, but it assumes a row that was created by hand.'],
], '27 G-1');

rowEdits($xml, ' is written as 1 and never promoted', [
    ['Every enrollment this system creates carries yearLevel = 1',
        'Nothing promotes the level from semesters completed or from the curriculum, and a transferee still carries whatever the evaluator recorded. What changed on 2026-10-01 is that the level is captured rather than assumed: the Evaluation desk sets it when it signs, the screen offers the level the previous term implies as a suggestion beside the picker, and every queue, record screen and print view renders it through one formatter (statusLabel.js formatYearLevel()), which replaced six copies of a helper that drew four different vocabularies from the same number.'],
    ['Developer with the Registrar', 'Developer (automatic promotion); Registrar (the level itself)'],
    ['yearLevel is written as 1 and never promoted', 'yearLevel is captured by hand and still never promoted'],
], '27 G-2');

rowEdits($xml, 'The approval dialog promises a student-number assignment', [
    ['Admission/Show.jsx:427 says the approval',
        'Closed 2026-10-01. No screen promises an assigned student number: the wording is gone from the approval dialog and schoolIdNumber remains what intake types, so the dialog states only what the approval actually does.'],
    ['Front-end developer (wording)', 'Closed in code'],
    ['The approval dialog promises a student-number assignment that does not exist',
        'The approval dialog promised a student-number assignment that does not exist'],
], '27 G-5');

rowEdits($xml, 'An absent Course-Specific Examination result does not block', [
    ['AdmissionPolicy.php:83-90',
        'Closed 2026-10-01 by giving the two examinations two independent flags: courses.requiresEntranceExam is the school-general paper every applicant to an examining program must pass, and courses.requiresCourseSpecificExam (additive migration 2026_10_01_1000000001, default false) makes the departmental paper a requirement of its own, named in the desk’s approval blockers. Where the flag is off, a recorded non-pass still blocks and an absent result does not.'],
    ['Developer, after C-5 is answered', 'Closed in code; which programs set the flag is C-5'],
    ['An absent Course-Specific Examination result does not block admission approval',
        'An absent Course-Specific Examination result did not block admission approval'],
], '27 G-6');

rowEdits($xml, 'Religion values are seeded as one row', [
    ['The seeder inserts Roman Catholic only',
        'Closed 2026-10-01. StarterReferenceDataSeeder writes eight religions and keeps Roman Catholic at id 1 so the historical row is updated rather than duplicated; the live database holds eight, so neither intake nor profile capture is blocked on a single permitted value. The list itself is school policy and is maintained in Reference Data.'],
    ['SysAdmin (reference data)', 'Closed in data; SysAdmin maintains the list'],
    ['Religion values are seeded as one row', 'Religion values were seeded as one row'],
], '27 D-2');

rowEdits($xml, 'Scholarship types are not seeded', [
    ['The Scholarship desk can record aid only',
        'Closed 2026-10-01. StarterReferenceDataSeeder seeds provisional scholarship types (the live table holds two) so the desk has something to grant against on a fresh install; SEAIT’s real aid categories replace the provisional rows.'],
    ['SysAdmin', 'Closed in data; SysAdmin replaces the provisional rows'],
    ['Scholarship types are not seeded', 'Scholarship types were not seeded'],
], '27 D-3');

rowEdits($xml, 'Elective configuration cannot be entered', [
    ['The reference-data screens save subjectId',
        'Closed 2026-10-01. curriculumsubjects carries is_elective, elective_group, elective_min_choices and elective_max_choices; the reference-data screen edits all four, and ReferenceDataController::normalizeElective clears the band when a subject is not an elective — so elective bounds can be configured and can actually fire at Evaluation.'],
    ['Developer', 'Closed in code'],
    ['Elective configuration cannot be entered', 'Elective configuration could not be entered'],
], '27 D-4');

rowEdits($xml, 'The seeder labels its own role count two different ways', [
    ['RbacSeeder.php:184 comments',
        'Closed 2026-10-01: the contradictory console line and the stale desk-role comment are gone, so the seeder prints no role figure anyone could audit against. The live roles table holds 17 rows and the permission set 87.'],
    ['Developer', 'Closed in code'],
    ['The seeder labels its own role count two different ways', 'The seeder labelled its own role count two different ways'],
], '27 D-5');

rowEdits($xml, 'A clearance requirement has no text', [
    ['clearancerequirements holds clearanceRequirementId',
        'Closed 2026-10-01. clearancerequirements.requirementName (additive migration 2026_09_30_1000000002, backfilled only where the row is still empty so a school’s own wording survives the upgrade) puts the obligation on the line: the printed slip says what the student owes instead of an office name, and an approving officer signs against text.'],
    ['Developer, after the Registrar lists', 'Closed in code; the Registrar supplies the wording'],
    ['A clearance requirement has no text', 'A clearance requirement had no text'],
], '27 D-6');

rowEdits($xml, 'Desks cannot see the student type they are processing', [
    ['The Clearance queue lists name and ID only',
        'Closed 2026-10-01. The queues that act on a student carry the type and, where the desk judges it, the standing: the Clearance list, the Blocking roster and the Evaluation queue read from one shared label map, so a Transferee or Shifter is distinguishable in the list and not only on the record.'],
    ['Front-end developer', 'Closed in code'],
    ['Desks cannot see the student type', 'Desks could not see the student type they were processing'],
], '27 U-1');

rowEdits($xml, 'Eight desk queues carry neither type nor standing', [
    ['Registrar, Assessment, Accounting, Clinic',
        'Closed 2026-10-01 for the queues where the distinction changes what the desk does — Registrar, Evaluation, Clearance, Blocking and Admission — and the Evaluation summary counts transferees and shifters separately rather than as one figure. Columns were added where an officer can act on the difference, not as decoration on every table.'],
    ['Front-end developer', 'Closed in code'],
    ['Eight desk queues carry neither type nor standing', 'Desk queues carried neither type nor standing'],
], '27 U-2');

rowEdits($xml, ' flash key never reach the screen', [
    ['HandleInertiaRequests.php:58-61',
        'Closed 2026-10-01: HandleInertiaRequests shares the info key beside success, warning and error, so the Scholarship duplicate guard and the other two guards that explain themselves under info put the reason on the screen.'],
    ['Developer', 'Closed in code'],
    ['Explanations sent under the', 'Explanations sent under the info flash key never reached the screen'],
], '27 U-3');

rowEdits($xml, 'Two labels for the same desk on one screen', [
    ['Registrar/Index.jsx:106 calls the summary',
        'Closed 2026-10-01: the summary and the page badge both read Phase 5, so the screen no longer contradicts itself about which phase the Registrar sits in.'],
    ['Front-end developer', 'Closed in code'],
], '27 U-4');

rowEdits($xml, 'A permission alone can bypass an office-scoped policy', [
    ['Where an ability name is registered twice',
        'Closed 2026-10-01 for the office-scoped actions. The gates are named away from the permissions the roles hold — assessment.computeAtDesk, payment.recordAtDesk, clinic.viewAtDesk, id.viewAtDesk, id.validateRequest, id.remarkRequest, clinic.recordAssessment — so Spatie’s Gate::before no longer resolves the permission and skips the policy body: the office check runs. The same-name permissions listed in §22.6 scope nothing by office, so their consequence is unchanged.'],
    ['Developer (two-ability minimum fix)', 'Closed in code'],
    ['A permission alone can bypass', 'A permission alone could bypass an office-scoped policy'],
], '27 X-1');

rowEdits($xml, ' must be kept in step by hand', [
    ['The enum is the authority the code reads',
        'Contained 2026-10-01. The enum is still what the code reads and the table is still what an admin edits, but the reference-data screen now says which side each row is on and refuses the edit that would break the application in silence: deleting an office whose id OfficeId compares against, or one that staff, clearance requirements or workflow steps still point at (tests/Feature/Admin/OfficeAuthorityTest.php).'],
    ['Developer, then SysAdmin discipline', 'Closed in code; SysAdmin discipline still applies'],
], '27 X-3');

rowEdits($xml, 'No real notification is ever displayed', [
    ['The writers address Students; the only reader filters on Staffusers',
        'Closed 2026-10-01. Both listeners write Staff-addressed rows now, through App\Support\WorkflowInbox, so the bell shows the notices the workflow actually produced and names the desk that must act next.'],
    ['Developer', 'Closed in code'],
    ['No real notification is ever displayed', 'No real notification was ever displayed'],
], '27 N-1');

rowEdits($xml, 'Workflow step labels are off by one', [
    ['SendWorkflowNotification.php:21-30',
        'Closed 2026-10-01. WorkflowService::stepLabels() is the one definition, keyed by OfficeId rather than by box number, and a notice reads the signed step’s own office — so the signer is told which box closed, in the name the desk uses.'],
    ['Developer', 'Closed in code'],
    ['Workflow step labels are off by one', 'Workflow step labels were off by one'],
], '27 N-2');

rowEdits($xml, 'A returned enrollment has no user-facing sentence', [
    ['The status returnedToEvaluation falls through',
        'Closed 2026-10-01. The shared status map carries returnedToEvaluation with its label and tone, the desk copy names the office it came back to, and WorkflowInbox routes a returned enrollment onward instead of the screen reading the raw value.'],
    ['Developer', 'Closed in code'],
    ['A returned enrollment has no user-facing sentence', 'A returned enrollment had no user-facing sentence'],
], '27 N-3');

rowEdits($xml, 'The notification payload is encoded twice', [
    ['Notifications::$casts declares data as an array',
        'Closed 2026-10-01. The model still casts data to array and the listeners now pass a map rather than a pre-encoded string, so the reader gets the structure the cast declares.'],
    ['Developer', 'Closed in code'],
    ['The notification payload is encoded twice', 'The notification payload was encoded twice'],
], '27 N-4');

// §28 institutional questions — the two the code has answered.
rowEdits($xml, 'Must a board program’s Course-Specific Examination be passed', [
    ['A failed result blocks approval; an absent result does not',
        'A failed result blocks approval, and an absent one blocks only where the program sets courses.requiresCourseSpecificExam — so the answer has become a per-course setting the department owns in Reference Data → Courses, entered 2026-10-01. Which programs set it is still the department’s call.'],
], '28 C-5');

rowEdits($xml, 'Is a notification meant for the student', [
    ['The writers address Students and the only reader filters on Staffusers',
        'Both writers addressed every row at Students while the only reader filtered on Staffusers, so the channel was dead at both ends. Resolved in code 2026-10-01: App\Support\WorkflowInbox addresses each notice to the office owning the first still-pending box, and a completed enrollment form to the Registrar as records custodian.'],
    ['Project owner', 'Ruled by the developer on 2026-10-01; SEAIT may reverse it'],
    ['Is a notification meant for the student or for the staff member?',
        'Is a notification meant for the student or for the staff member? (answered 2026-10-01)'],
], '28 C-9');

// Citations that the 2026-10-01 edits moved.
rawEdit($xml, 'RegistrarController.php:110-128', 'RegistrarController.php:170-190', 4,
    'checkClearance moved out of the approve() body');
rawEdit($xml, 'RegistrarController.php:143-158', 'RegistrarController.php:212-215', 1,
    'checklist refusal in approve()');
rawEdit($xml, 'AssessmentPolicy.php:32-57', 'AssessmentPolicy.php:34-56', 1, 'compute() whole-method citation');
rawEdit($xml, 'AssessmentPolicy.php:45-46', 'AssessmentPolicy.php:54-55', 1, 'compute() office citation');

// §16 — the checklist the Registrar reads. The two new gates are cloned from the last
// row of the table, so they take the table's own borders and fonts.
rawEdit($xml, 'a five-gate checklist', 'a seven-gate checklist', 1, '16.1 checklist length');

rowInsertAfter($xml, '5 registrar_ready', [
    ['5 registrar_ready', '6 documents_verified'],
    ['The next uncompleted workflow box belongs to the Registrar office.',
        'Every document the applicant’s own admission required has a submission in verified status. A record with no admission behind it — a continuing student readmitting, or one created directly at this desk — has nothing to re-open, so the gate reads true.'],
    ['Indirectly',
        'Yes — it starts from the admission row; with no admission there is no required document to check (EnrollmentReadiness::documentsVerified).'],
], '16 gate 6: documents_verified inserted');

rowInsertAfter($xml, '6 documents_verified', [
    ['6 documents_verified', '7 prerequisites_met'],
    ['Every document the applicant',
        'No subject on the confirmed load sits behind a curriculum prerequisite the student has not satisfied, judged against the curriculum the enrollment is pinned to and counting a subject satisfied by a passing grade or by a credit transfer.'],
    ['Yes — it starts from the admission row',
        'No — but a load decided before the curriculum moved, a retake, or a direct proposal can clear the Evaluation screen’s lock and still miss the rule, so this desk reads the record rather than the button (EnrollmentReadiness::prerequisitesMet).'],
], '16 gate 7: prerequisites_met inserted');

rowEdits($xml, '5 registrar_ready', [
    ['5 registrar_ready', '5 registrarApprovalPending'],
], '16 gate 5: key name as the code names it');

paraEdits($xml, 'both the queue filter', [
    ['AssessmentPolicy.php:40-43', 'AssessmentPolicy.php:41-43'],
    [').',
        '). Since 2026-10-01 the policy tests a second condition the queue does not: no fee sheet may be computed before the Department Evaluation signature (AssessmentPolicy.php:45-53), because a fee sheet prices the load that signature stands behind. A profile-less enrollment could reach Phase 3 with the desk’s own Sign button visibly disabled; it cannot any more.'],
], '14 entry conditions: compute now needs the signature');

// §13.6 — the retention examination now decides something.
paraEdits($xml, 'Board programs and any program flagged', [
    ['EvaluationController.php:493-510', 'EvaluationController.php:690-740'],
    [', BR10) and is never an entrance examination. It applies to Continuing-Regular, Continuing-Irregular, and — where the program requires it — a Shifter now in a board program; it does not apply to First-Year or Transferee students in their first term, who have no SEAIT standing to retain.',
        ', BR10) and is never an entrance examination. It applies to Continuing-Regular, Continuing-Irregular, and — where the program requires it — a Shifter now in a board program; it does not apply to First-Year or Transferee students in their first term, who have no SEAIT standing to retain. Since 2026-10-01 the examination is also what the desk signs against: signBlockers() refuses the Department Evaluation signature for a returning student on a program that flags requiresRetentionExam until a pass is recorded against that enrollment’s own term, which is the owner’s ruling on concern #14 — a continuing student proves the previous term was learned before advancing to a higher year level.'],
], '13.6: retention examination gates the sign');

echo "\nresolved {$applied}, failed ".count($failures)."\n";
if ($failures !== []) {
    fwrite(STDERR, "not writing: some edits did not resolve\n");
    exit(1);
}

if (! $apply) {
    echo "dry run — pass --apply to write\n";
    exit(0);
}

copy($src, $tmp);
$z2 = new ZipArchive();
if ($z2->open($tmp) !== true) {
    fwrite(STDERR, "cannot open temp copy\n");
    exit(1);
}
$z2->addFromString('word/document.xml', $xml);
$z2->close();
rename($tmp, $src);
echo "APPLIED to {$src}\n";
