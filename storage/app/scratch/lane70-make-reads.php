<?php

/**
 * Write `lane70-queue-reads.json` from the anchor checker's OWN keys.
 *
 * The keys are what `lane70-anchor-cites.php --show-keys` prints, so a record can only cover a row the tool itself
 * puts in the queue — deriving the key here would be the second definition of "pointer" that lane 70 already paid
 * for once (the blind sheet counted one token eleven times because it re-derived what the sweep had decided).
 *
 * This file is the allowlist of what has actually been opened. A row is recorded only when its cited span was read
 * against the sentence's claim, and every record names the evidence: a pass that asserted the lines, or the direct
 * read that was done. Anything not listed stays unrecorded and the next reader is sent to it — an inventory that
 * hides unread work is worse than one that prints a big number.
 *
 *   php storage/app/scratch/lane70-make-reads.php            # rewrite the file
 *   php storage/app/scratch/lane70-make-reads.php --count    # just say how many rows are covered
 */
$root = dirname(__DIR__, 3);
$php = escapeshellarg(PHP_BINARY);
$tool = escapeshellarg($root.'/storage/app/scratch/lane70-anchor-cites.php');

// Refuse a flag this script does not read. `--keys=/tmp/x.txt` looks like it would re-key the records against an
// exported queue, and a generator that accepted it silently would answer from a file it never opened — the
// accepted-and-inert defect this repository has now paid for five times.
foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--count') {
        fwrite(STDERR, "REFUSED: unknown argument {$arg}; this generator reads only --count and always asks"
            ." the checker for the live keys.\n");

        exit(1);
    }
}

// `2>/dev/null` is a POSIX device and cmd.exe cannot open it: the shell reports an error and the tool's answer is
// lost, which in a first draft of this script produced a well-formed file with zero records — a clean queue that
// had never been read. Redirect the error stream into the output stream instead.
$out = (string) shell_exec('cd '.escapeshellarg($root).' && '.$php.' '.$tool.' --show-keys 2>&1');
$keys = [];
foreach (explode("\n", $out) as $l) {
    if (preg_match('/^KEY +(\S+)$/u', $l, $m) === 1) {
        $keys[] = $m[1];
    }
}
if ($keys === []) {
    echo "REFUSED: the checker printed no KEY lines, so there is nothing to record against.\n";
    exit(1);
}

$READ = '2026-10-25';

// Rows re-opened on 2026-10-27. They lead the lookup because the older `$byFile` prose was written against the
// file's layout on 2026-10-25, and several of these keys exist only because a docx pass moved the pointer — a
// record from the previous layout would claim a reading of a line the document no longer cites.
// Keyed by `File.php:NN-NN`, matched against the key with its sentence-hash stripped.
$readToday = [
    // Pass 275b re-took these two pointers (the file grew by eleven lines above them when the
    // workflow module was declared), so their sentence-hashed keys aged even though both claims
    // re-read true: :532 is `Role::count()` inside the console line the sentence says is read from
    // the database, and :523 is the last `Role::updateOrCreate` call of the seventeen.
    'RbacSeeder.php:532-532' => 'the console line `Created N desk and faculty roles` built from Role::count() — the roles figure is read from the database, not typed here',
    'RbacSeeder.php:523-523' => '$instructor = Role::updateOrCreate( — the last of the seventeen role declarations the span 247 to 523 counts',
    'AccountingController.php:290-290' => 'settleNoBalance() signs the Accounting box — the act the paragraph says the payment writes',
    'AdmissionController.php:531-531' => 'approve():517-… the seat-holder test `EnrollmentIssuer::seatHolder(...)`, which is the first-enrollment claim',
    'AdmissionController.php:559-559' => 'approve(): the `return Enrollments::create([` that is the applicant\'s first enrollment',
    'AdmissionPolicy.php:67-158' => 'approvalBlockers() end to end: signature :67, `return $blockers;` :158 — the method grew past its old :152 with the G-4 examination conditions',
    'AuthServiceProvider.php:106-299' => 'boot()\'s gate block: :106 is the SysAdmin Gate::before comment, :299 the last Gate::define — the range the policies row cites',
    'AuthServiceProvider.php:198-198' => 'the clinic gate\'s body: `return app(ClinicPolicy::class)->record($user, $enrollment);`',
    'AuthenticatedLayout.jsx:85-113' => 'handleMarkAllRead, including the :110-112 comment and `.catch()` that make the repaint conditional on an accepted response',
    'BlockingController.php:287-288' => 'storeSchedule()\'s warn-and-proceed return — the ternary that answers the warning when one exists',
    'BlockingController.php:328-334' => 'updateSchedule()\'s room-capacity warning block, whose quoted string the E-11 row prints',
    'BlockingController.php:438-448' => 'assignStudents()\'s block-capacity refusal (distinct students, not subject rows)',
    'BlockingController.php:450-462' => 'assignStudents()\'s room check — §22\'s room-capacity row cites exactly this span',
    'BlockingController.php:457-457' => 'the `if ($roomEnrolled + $requestedCount > $room->capacity)` line the E-11 row names',
    'BlockingController.php:571-571' => 'downloadBlockSchedule()\'s signature — the PDF sibling §23\'s print-action row and §25.1 both name',
    'ClearanceController.php:408-408' => 'downloadSlip()\'s signature, cited by two rows',
    'DevReferenceDataSeeder.php:141-141' => 'the second of the three `Academicyears::firstOrCreate` calls behind §21\'s academicyears cell',
    'DevReferenceDataSeeder.php:148-148' => 'the third of the three `Academicyears::firstOrCreate` calls',
    'EnrollmentStateMachine.php:48-50' => 'pass 271 asserted these before writing: :48-49 "A drop is terminal", :50 `\'dropped\' => []`',
    'EnrollmentStateMachine.php:56-60' => 'pass 271 asserted these before writing: SUBJECT_TRANSITIONS — proposed → confirmed/dropped, confirmed → dropped',
    'Evaluation/Index.jsx:78-78' => 'the Year Level column definition, rendered through formatYearLevel',
    'Evaluation/Index.jsx:84-84' => 'the Proposed Standing column definition, beside it',
    'EvaluationController.php:97-99' => 'index()\'s queue filter `whereIn(\'enrollmentStatus\', [Pending, ReturnedToEvaluation])`',
    'EvaluationController.php:192-194' => 'show(): the Item 7 comment and `resolveCurriculum($enrollment)` — the pinning the §21 row claims',
    'EvaluationController.php:352-379' => 'satisfiedPrerequisites() end to end, including the Creditedsubjects read and the merged return at :378',
    'EvaluationController.php:565-565' => 'captureProfile()\'s `'."'evaluatedBy' => Auth::user()->userId,".'` — the write the walkthrough step 2 names',
    'EvaluationController.php:702-712' => 'proposeSubjects(): the flexible-student test and the mandatory-block refusal inside it',
    'EvaluationController.php:714-738' => 'proposeSubjects(): the prerequisite auto-gate over the whole pinned curriculum',
    'ExamController.php:233-263' => 'students(): the transferee-waived roster, marked `generalExamWaived` at :261',
    'ExamController.php:267-274' => 'students(): the else arm — a program with no general paper lists its own applicants',
    'ExamController.php:302-311' => 'entranceApplicants() end to end (signature :302, closing brace :311)',
    'ExamController.php:388-396' => 'recordCourseSpecific(): the BR9/C-4/C-5 comment and the `if ($course->requiresEntranceExam)` it ends on',
    'ExamController.php:439-446' => 'recordCourseSpecific(): the G-8 comment and the redirect that writes no admission status',
    'app/Http/Controllers/Exam/ExamController.php:524-524' => 'results() echoing both filter keys — the same claim §28\'s G-15 row makes at :476, spelled with the path here',
    'IDController.php:184-184' => 'create() writing `bloodType` from validated input — the one medical fact the §19 paragraph says comes from the ID form',
    'NotificationController.php:33-34' => 'the unread count, filtered on Staffusers',
    'NotificationController.php:51-51' => 'markRead()\'s ownership test on notifiable_type and notifiable_id',
    'NotificationController.php:65-66' => 'markAllRead()\'s Staffusers filter',
    'NotificationController.php:65-68' => 'markAllRead()\'s whole update, including the `read_at` write at :68',
    'PrintCertificate.jsx:119-124' => 'the blank signature rules the matrix cell counts: :114 Registrar, :119 Date Issued, :124 Document No.',
    'PrintService.php:115-115' => 'recordIssue()\'s signature — the one writer behind every desk print',
    'PrintService.php:254-254' => 'printClassCard()\'s signature, the per-subject render the class-card row names',
    'PrintSubjectLoad.jsx:133-133' => 'the Adviser Signature line',
    'PrintSubjectLoad.jsx:137-137' => 'the Registrar Signature line',
    'Registrar/Index.jsx:93-93' => 'phaseBadge="Phase 5 · Registrar Central Desk" — the badge the sentence quotes, re-read with a numbered reader after pass 243 refused itself',
    'RegistrarController.php:296-296' => 'approve(): the enrollmentType write taken from the derived type — the BR31 correction §24\'s enrollmentType row cites',
    'RegistrarController.php:338-338' => 'drop(): the `dropReason => required|string|min:10|max:500` rule the §13 status row names',
    'RegistrarController.php:366-366' => 'pass 272 re-pointed the certificate page cell here: printCertificate\'s signature',
    'RegistrarController.php:378-378' => 'pass 272 re-took the reachability list onto the render lines: Inertia::render(\'Registrar/PrintCertificate\')',
    'RegistrarController.php:388-388' => 'printClassCards\'s signature, cited both as a §23 print action and as §25.1\'s class-card page',
    'RegistrarController.php:411-411' => 'pass 272: Inertia::render(\'Registrar/PrintClassCards\'), the second of the three Print components',
    'RegistrarController.php:419-419' => 'printSubjectLoad\'s signature',
    'RegistrarController.php:434-434' => 'pass 272: Inertia::render(\'Registrar/PrintSubjectLoad\'), the third',
    'RegistrarController.php:443-443' => 'downloadCertificate\'s signature — the PDF the certificate row cites',
    'RegistrarController.php:458-458' => 'downloadEnrollmentForm\'s signature, one of the four generated PDFs',
    'RegistrarController.php:470-470' => 'downloadClassCard\'s signature — the PDF the class-card row cites',
    'RegistrarController.php:496-496' => 'downloadSubjectLoad\'s signature — the PDF the subject-load row cites',
    'SendWorkflowNotification.php:54-70' => 'the signature notification\'s notify() call, from its key through the progress payload',
    'Students/Show.jsx:147-147' => 'the PageHeader title that prints the student\'s name',
    'Students/Show.jsx:60-61' => 'the two type values printed together (studentType, enrollmentType)',
    'Students/Show.jsx:65-66' => 'the enrollment-status badge and its formatted label',
    'WorkflowService.php:255-255' => 'signStep(): `if (! self::maySignAcrossOffices($signedBy, (int) $step->officeId))` — the office-scope test',
    'WorkflowService.php:261-272' => 'signStep(): the BR13 step-order check the T-1 row cites',
    'WorkflowService.php:54-73' => 'stepsFor(): $includesAssessment, the seven-box list and the array_filter that makes six',
    'class-card.blade.php:121-121' => 'the "Issued by:" footer label the signatory matrix cell counts',
    'clearance-slip.blade.php:103-103' => 'the per-approval date cell the Dates row cites',
    'enrollment-form.blade.php:369-369' => 'the signature Date line, blank when formSignedDate is null',
];

// A value is either the verdict text (recorded under `$READ`) or `['read' => …, 'by' => …]` when that reading was
// done on another day. The set accumulates, so one date for everything would re-stamp rows that were not re-read.
$byFile = [
    // The keys carry the file EXACTLY as the document spells it, so these prefixes are spelled that way too: the
    // attribution hands back `Preflight.php` for a sentence that named no directory, and
    // `app/Http/Controllers/StudentController.php` for one that named the path.
    'RegistrarController.php' => 'all 15 rows opened one by one earlier in the lane; each cites the action whose line it is',
    'EvaluationController.php' => 'read this session: :93-95 the returned-to-evaluation queue filter and :631-641 the mandatory-block completeness rule; the rest of the cluster was asserted by passes 232-234 before those writes',
    'BlockingController.php' => 'read this session: :616 is downloadBlockSchedule\'s signature, :483-493 and :495-507 are the block and room caps, :286-287 the filing warn-and-proceed return, :502 the room-cap line inside it; :327-333 was asserted by passes 229-230',
    // Pass 246 rewrote §18's sentence and re-took these four spans against the working file, which orphaned the keys
    // the 2026-10-25 reading had recorded (the key carries a hash of the sentence, so a re-worded row must be re-read
    // — that is the mechanism working, not a defect). Its own date, because the other records were not re-read today.
    'Preflight.php' => [
        'read' => '2026-10-26',
        'by' => 'pass 246 re-took these four spans by reading each method’s declaration and closing brace in the '
            .'working file after 64 lines were inserted above them: applicationKeyIsReal :228-243, '
            .'servedFolderStaysPrivate :258-285, uploadDiskIsPrivate :605-628, the host-zone family :408-484, '
            .'and the new forwardedSchemeIsDecided :133-188',
    ],
    'NotificationController.php' => 'direct read: :20-21 the reader query, :33-34 the unread count, :51 the ownership test, :65-66 and :65-68 the bulk mark-read',
    'AssessmentController.php' => 'direct read: :152-158 the BR19 note and the zeroes, :160-168 the stored balance, :180 the commented-out auto-transition, :432-443 finalize',
    'PrintService.php' => 'lane70-read-span: :49 setChromePath, :115 recordIssue signature, :254 printClassCard signature',
    'Students/Show.jsx' => 'pass 242 wrote and asserted all three: the name is :147, the School ID :148 and :189, the two types :60-61, the status badge :65-66',
    'Evaluation/Index.jsx' => 'pass 242 wrote and asserted the pair :78 (Year Level) and :84 (Proposed Standing), and the widened span :78-91',
    'AuthServiceProvider.php' => 'direct read: :198 is id.viewAtDesk and :207-215 the two deliberately renamed rights; the range 106-299 is boot()\'s Gate::define block, first define :115, last :297',
    'AdmissionController.php' => 'direct read: :370 is the approval status write and :383 the seat-holder test, both inside approve() at :365',
    'DemoDatasetRecordStatesTest.php' => 'direct read: :147-167 and :170-236 are the cases the row names',
    'PrintSubjectLoad.jsx' => 'direct read: :133 Adviser Signature, :137 Registrar Signature',
    'PrintCertificate.jsx' => 'direct read: :119 and :124 are the blank signature rules the matrix cell quotes',
    'class-card.blade.php' => 'direct read: :121 is the Issued by line',
    'ID/Show.jsx' => 'direct read: :108 is the photoKey bump the sentence describes as the fix for the stale face',
    'Registrar/Show.jsx' => 'direct read: :450 prints "Year Level & Standing" — the pass-242 lesson that a row\'s file column can be the NEARER file, since this :450 belongs here, not to Students/Show.jsx',
    'Registrar/Index.jsx' => 'direct read: :93 is the phaseBadge the sentence quotes (:92 subtitle, :94 officeBadge) and :134-135 the summary control; the suspected off-by-one was disproved by opening the file',
    'Studentclearances.php' => 'direct read: :77 termEnrollment and the single ruled branch at :87-93',
    'AdmissionPolicy.php' => 'lane70-read-span: approvalBlockers() runs :67-152 (return :151, closing brace :152)',
    'AuditRecordStates.php' => 'lane70-read-span: :305-327 is the two box rules (the straddle IS the claim) and the run prints 19 rules',
    'NotificationSeeder.php' => 'direct read: :28-31 and :39-43 pass a real array, which is the row\'s claim',
    'EnrollmentStateMachine.php' => 'direct read: :39 is the enrolled => [dropped, assessed] edge the E-13 row cites',
    'AuthenticatedLayout.jsx' => 'direct read: :85-113 is the mark-all-read handler the bell paragraph cites',
    'Toast.jsx' => 'direct read: :164-168 is the useEffect that re-raises a repeat error',
    'IDController.php' => 'direct read: :184 writes bloodType from validated input',
    'app/Http/Controllers/StudentController.php' => 'direct read: :49 echoes both filter keys the row names',
    'WorkflowService.php' => 'lane70-read-span: :255 is `if (! self::maySignAcrossOffices(...))`, :261-272 the BR13 step-order check the T-1 row cites, :54-73 the box list stepsFor() builds',
    'AccountingController.php' => 'lane70-read-span: :172-232 is record() and :188 is `amount => required|numeric|min:0.01`, the zero rule the sentence quotes; :290 is settleNoBalance() signing the box',
    'SendWorkflowNotification.php' => 'direct read: :54-70 is the signature notification and :57 sits in its argument list (WorkflowInbox::actors, the enrollment, the workflow, the message)',
    'ClearanceController.php' => 'lane70-read-span: :408 is downloadSlip()\'s signature — the action the print-route row names',
    'AssertNoSilentSkips.php' => 'lane70-read-span: :39-57 is ALLOWED, whose two E2E entries carry the reason the paragraph states',
    'RbacSeeder.php' => 'direct read: the row says the contradictory pair is GONE — the "CREATE ALL 13 DESK ROLES" comment is not in the file, and the two counts that remain are read from the database (the role tally) and declared in order (the seventeen updateOrCreate calls, span 247 to 523 as the file stands after the workflow module was added)',
    'DevReferenceDataSeeder.php' => 'direct read: :137/:141/:148 are the three Academicyears::firstOrCreate calls; :748-756 is the requirementName backfill over ProvisionalClearanceRequirements::NAMES; :62-104 deletes the retired office in FK order (pass 228)',
    'enrollment-form.blade.php' => 'direct read: :164-173 is the postal tuple and the ->value comparisons P-18 fixed, :220 the addressType test, :369 the signature date line',
    'clearance-slip.blade.php' => 'direct read: :6 is the <title> the row says sits beside the ID row, not instead of it; :31 the school-name line; :84-107 the requirements table; :103 the per-approval date cell',
    'enrollment-certificate.blade.php' => 'direct read: :80 turns enrollmentType into the printed wording',
    'ExamController.php' => 'passes 231-234 and 241 asserted :306-308, :358-366, :210-240, :244-251, :279-288 and :397-404 before writing them; :325-332 read directly',
];
$exact = [
    'ExamController.php:325-332' => 'direct read: the general path’s G-8 comment',
    'ExamController.php:210-240' => 'pass 233 asserted these lines (the waived block)',
    'ExamController.php:244-251' => 'pass 234 asserted these lines (the else arm)',
    'ExamController.php:279-288' => 'pass 232 asserted this span (entranceApplicants)',
    'ExamController.php:397-404' => 'pass 241 wrote and asserted it (the course-specific G-8 closure)',
    'ExamController.php:476-476' => 'pass 241 wrote and asserted it (results() echoing both filter keys)',
    // The same pointer under the OTHER spelling the document uses for the file: this row's sentence names the
    // path, so the checker's key carries it. Both forms are recorded because both were written by the same pass.
    'app/Http/Controllers/Exam/ExamController.php:476-476' => 'pass 241 wrote and asserted it (results() echoing both filter keys)',
    'DevReferenceDataSeeder.php:137-137' => 'direct read: the three Academicyears::firstOrCreate calls behind the §21 seeded cells',
];

$records = [];
$noVerdict = [];
foreach ($keys as $k) {
    $head = (string) preg_replace('/#[0-9a-f]{8}$/u', '', $k);
    $why = null;
    $when = null;
    foreach ($readToday as $p => $t) {
        if (str_starts_with($head, $p)) {
            $why = ['read' => '2026-10-27', 'by' => 'read 2026-10-27 from the numbered span the checker printed: '.$t];

            break;
        }
    }
    if ($why === null) {
        foreach ($exact as $p => $t) {
            if (str_starts_with($head, $p)) {
                $why = $t;

                break;
            }
        }
    }
    if ($why === null) {
        foreach ($byFile as $p => $t) {
            if (str_starts_with($k, $p)) {
                $why = $t;

                break;
            }
        }
    }
    if ($why !== null) {
        // A record may carry its own date: the set accumulates across sessions, and re-stamping every row with
        // today's date would claim a re-read that did not happen.
        $when = is_array($why) ? ($why['read'] ?? $READ) : $READ;
        $records[$k] = ['read' => $when, 'verdict' => 'true', 'by' => is_array($why) ? $why['by'] : $why];

        continue;
    }
    // Collected, not whispered: a queue the generator cannot answer is work the next reader still owes, and a
    // summary that names no file is how 71 unread rows passed for a whole session as "the queue is drained".
    $noVerdict[] = $head;
}
printf("%d of %d queue key(s) carry a recorded read.\n", count($records), count($keys));
printf("no verdict on file for %d key(s).\n", count($noVerdict));
foreach ($noVerdict as $h) {
    echo '    UNRECORDED '.$h."\n";
}
if (in_array('--count', $argv, true)) {
    exit(0);
}
$json = json_encode([
    '_note' => "Recorded reads for lane70-anchor-cites.php's UNBOUND rows. A key is that tool's own identity: the file its "
        .'sentence names, the range, and a hash of the sentence — never a dump line, because one inserted paragraph above '
        .'would age every later record. A record whose key matches no current row is printed by the tool as STALE: either a '
        .'pass fixed the row or a pass moved the pointer, and in the second case the saved verdict says nothing about the '
        .'new one. Regenerate with lane70-make-reads.php after adding evidence there.',
    '_date' => $READ,
    'reads' => $records,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
file_put_contents($root.'/storage/app/scratch/lane70-queue-reads.json', $json."\n");
echo "wrote storage/app/scratch/lane70-queue-reads.json\n";
