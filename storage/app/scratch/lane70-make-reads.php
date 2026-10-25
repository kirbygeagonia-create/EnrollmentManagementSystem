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
$byFile = [
    // The keys carry the file EXACTLY as the document spells it, so these prefixes are spelled that way too: the
    // attribution hands back `Preflight.php` for a sentence that named no directory, and
    // `app/Http/Controllers/StudentController.php` for one that named the path.
    'RegistrarController.php' => 'all 15 rows opened one by one earlier in the lane; each cites the action whose line it is',
    'EvaluationController.php' => 'read this session: :93-95 the returned-to-evaluation queue filter and :631-641 the mandatory-block completeness rule; the rest of the cluster was asserted by passes 232-234 before those writes',
    'BlockingController.php' => 'read this session: :616 is downloadBlockSchedule\'s signature, :483-493 and :495-507 are the block and room caps, :286-287 the filing warn-and-proceed return, :502 the room-cap line inside it; :327-333 was asserted by passes 229-230',
    'Preflight.php' => 'lane70-read-span: the four spans are applicationKeyIsReal, servedFolderStaysPrivate, uploadDiskIsPrivate, hostZoneMatchesTheSchools',
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
    'RbacSeeder.php' => 'direct read: the row says the contradictory pair is GONE — the "CREATE ALL 13 DESK ROLES" comment is not in the file, and :474 (Role::updateOrCreate) and :483 (the `Created N desk and faculty roles` console line) are the two counts that remain',
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
foreach ($keys as $k) {
    $head = (string) preg_replace('/#[0-9a-f]{8}$/u', '', $k);
    $why = null;
    foreach ($exact as $p => $t) {
        if (str_starts_with($head, $p)) {
            $why = $t;
            break;
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
        $records[$k] = ['read' => $READ, 'verdict' => 'true', 'by' => $why];
    }
}
printf("%d of %d queue key(s) carry a recorded read.\n", count($records), count($keys));
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
