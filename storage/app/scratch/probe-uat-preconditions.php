<?php

/**
 * UAT precondition pre-flight — read-only.
 *
 * The ID-desk walk found row 15.1 unperformable by eye. This measures every record the walkthrough
 * names against live `ems` in one pass, so a row an office cannot perform is found here rather than
 * at the desk. Every expectation is quoted from Documentation/Per-Office-UAT-Walkthrough.md; nothing
 * here writes, and a FAIL means either the dataset moved or the script describes an older build.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'ems',
    'database.connections.mysql.username' => 'root', 'database.connections.mysql.password' => '']);
DB::purge('mysql');

const TERM = 11;

$fails = 0;
$notes = 0;

function one(string $sql, array $bind = []): ?object
{
    $rows = DB::select($sql, $bind);

    return $rows[0] ?? null;
}

function val(string $sql, array $bind = []): string
{
    $r = one($sql, $bind);
    if ($r === null) {
        return 'NO ROW';
    }
    $v = array_values((array) $r)[0];

    return $v === null ? 'NULL' : (string) $v;
}

function check(string $row, string $expect, string $actual): void
{
    global $fails, $notes;
    // The call sites append a human-readable note to the value, so compare the expectation as a
    // delimited prefix: "0" matches "0 clinic record(s) — ..." but never "10 seated rows". Exact
    // equality turned all 84 annotated rows into false FAILs on 2026-10-12.
    $pattern = '/^'.preg_quote(trim($expect), '/').'(\s|$|—)/u';
    if (preg_match($pattern, trim($actual))) {
        printf("  PASS  %-10s %s\n", $row, $actual);

        return;
    }
    if (str_starts_with($expect, '~')) {          // a note, not a rule
        $notes++;
        printf("  NOTE  %-10s %s  (%s)\n", $row, $actual, ltrim($expect));

        return;
    }
    $fails++;
    printf("  FAIL  %-10s expected [%s] got [%s]\n", $row, $expect, $actual);
}

function section(string $name): void
{
    echo "\n=== {$name}\n";
}

// ---------------------------------------------------------------- §3 accounts
section('§3 the accounts, and the roles each actually holds');

$accounts = [
    'office6_head' => ['6', 'AdmissionOfficer+OfficeHead'],
    'office4_head' => ['4', 'GuidanceStaff+OfficeHead'],
    'office7_head' => ['7', 'DeptEvaluator+OfficeHead'],
    'dean_academic' => ['7', 'Dean+DeptEvaluator'],
    'instr.alvarez' => ['7', 'DeptEvaluator+Instructor'],
    'office3_head' => ['3', 'OfficeHead+ScholarshipOfficer'], // GROUP_CONCAT orders by role name
    'office2_head' => ['2', 'AccountingStaff+OfficeHead'],
    'office1_head' => ['1', 'OfficeHead+RegistrarApprover'],
    'office5_head' => ['5', 'BlockingCoordinator+OfficeHead'],
    'office11_head' => ['11', 'ClinicStaff+OfficeHead'],
    'office22_head' => ['22', 'IdOfficer+OfficeHead'],
    'staff8' => ['1', 'Admin+SysAdmin'],
    'office8_head' => ['1', 'OfficeHead+RegistrarApprover'],
];

$rolesOf = fn (string $u) => val(
    'SELECT IFNULL(GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR \'+\'),\'none\')
       FROM staffusers s JOIN staff_roles sr ON sr.model_id = s.userId
       JOIN roles r ON r.id = sr.roleId
      WHERE s.username = ?', [$u]);

foreach ($accounts as $user => [$office, $expect]) {
    $actual = $rolesOf($user);
    check('§3', $expect, $actual);
    $actualOffice = val('SELECT officeId FROM staffusers WHERE username = ?', [$user]);
    check('§3', $office, $actualOffice.' (office of '.$user.')');
}

check('§2.8', '~created by the tester', val('SELECT COUNT(*) FROM staffusers WHERE username LIKE \'head%_only\'').' head-only account(s) exist; §12R.1, §14R.2, §15R.4 and §17.1 wait on them');

$bare = val(
    'SELECT COUNT(*) FROM (
       SELECT s.userId, COUNT(sr.roleId) c FROM staffusers s
       LEFT JOIN staff_roles sr ON sr.model_id = s.userId
       GROUP BY s.userId HAVING c = 1
     ) t JOIN roles r ON r.id = (
       SELECT sr2.roleId FROM staff_roles sr2 WHERE sr2.model_id = t.userId LIMIT 1)
     WHERE r.name = \'OfficeHead\'');
check('§3', '0 bare OfficeHead accounts on the demo set', $bare.' bare OfficeHead accounts on the demo set');

// ---------------------------------------------------------------- §4 the named records
section('§4 every record the dataset table names');

$rows = [
    ['53', 'enrolled', 'Juan Dela Cruz / DEMO-2026-001'],
    ['596', 'pending', 'Maria Reyes returning load'],
    ['595', 'enrolled', 'Maria Reyes term 10'],
    ['262', 'enrolled', 'Liza Bautista, irregular'],
    ['84', 'pending', 'Elena Ramos first-year'],
    ['85', 'pending', 'Rafael Cruz transferee'],
    ['86', 'paid', 'Grace Tan'],
    ['87', 'assessed', 'Christian Lim'],
    ['88', 'assessed', 'Bea Alonzo'],
    ['89', 'enrolled', 'Patricia Diaz'],
    ['90', 'enrolled', 'Gabriel Ramos'],
    ['91', 'enrolled', 'Danica Sotto'],
    ['92', 'enrolled', 'Kevin Santos'],
    ['264', 'paid', 'Marco Villanueva'],
    ['287', 'returnedToEvaluation', 'Isagani Torres'],
    ['285', 'evaluated', 'Angelica Reyes'],
    ['318', 'evaluated', 'Camille Custodio'],
    ['286', 'assessed', 'Marisol Domingo'],
    ['475', 'pending', 'Iñigo Barrameda'],
    ['476', 'pending', 'Relinda Sabla'],
    ['876', 'enrolled', 'Nena Alcantara, the ID seat'],
];

foreach ($rows as [$id, $status, $who]) {
    check("§4 {$id}", $status, val('SELECT enrollmentStatus FROM enrollments WHERE enrollmentId = ?', [$id])." — {$who}");
}

check('§4 876', '1', val('SELECT COUNT(*) FROM idrequests WHERE enrollmentId = 876').' request(s) — the desk opened it on 2026-10-12');
check('§4 876', 'DEMO-2026-026', val("SELECT schoolIdNumber FROM students s JOIN enrollments e ON e.studentId = s.studentId WHERE e.enrollmentId = 876"));
check('§4 92', '1', val('SELECT COUNT(*) FROM clinicrecords WHERE enrollmentId = 92').' clinic record(s) — Kevin is the finished example');
check('§4 92', '1', val('SELECT COUNT(*) FROM idrequests WHERE enrollmentId = 92 AND status = \'validated\'').' validated ID request(s)');
check('§4 89', '3', val('SELECT COUNT(*) FROM enrolledsubjects WHERE enrollmentId = 89 AND blockId IS NOT NULL AND status <> \'dropped\'').' seated rows — she is BSIT 1-A, and §14.1\'s clinic subject');
check('§4 90', '0', val('SELECT COUNT(*) FROM enrolledsubjects WHERE enrollmentId = 90 AND blockId IS NOT NULL AND status <> \'dropped\'').' seated rows — Blocking\'s queue');
check('§4 85', 'irregular', val('SELECT academicStanding FROM enrollments WHERE enrollmentId = 85').' standing (C-2 must already hold)');
check('§4 262', 'irregular', val('SELECT academicStanding FROM enrollments WHERE enrollmentId = 262').' standing');
check('§4 84', '1', val('SELECT yearLevel FROM enrollments WHERE enrollmentId = 84').' year level (derived, no history)');

// ---------------------------------------------------------------- §5-§7 admission and evaluation
section('§5-§7 what the admission and evaluation desks will meet');

check('§5.1', 'pending', val('SELECT admissionStatus FROM admissions WHERE admissionId = 41'));
echo "\n        admission 41's program: ".val('SELECT CONCAT(c.courseCode,\' — \',c.courseName) FROM admissions a JOIN courses c ON c.courseId = a.courseId WHERE a.admissionId = 41');
check('§5R.4', '1', val('SELECT c.requiresCourseSpecificExam FROM admissions a JOIN courses c ON c.courseId = a.courseId WHERE a.admissionId = 41').' course-specific flag on that program (5R.4 cannot name a blocker without it)');
check('§7.16', '1', val('SELECT c.requiresEntranceExam FROM admissions a JOIN courses c ON c.courseId = a.courseId WHERE a.admissionId = 41').' general flag (7.16 only offers School Entrance passers)');
check('§5.1', '3', val('SELECT COUNT(*) FROM studentrequirementsubmissions WHERE admissionId = 41').' requirement lines on Pedro\'s application');
check('§4', '1 submitted / 2 pending', val(
    "SELECT CONCAT(SUM(submissionStatus = 'submitted'), ' submitted / ', SUM(submissionStatus = 'pending'), ' pending')
       FROM studentrequirementsubmissions WHERE admissionId = 41"));
check('§5.7', 'rejected', val('SELECT admissionStatus FROM admissions WHERE admissionId = 44'));
check('§5.4', 'approved', val('SELECT admissionStatus FROM admissions WHERE admissionId = 45'));

$queue = val('SELECT GROUP_CONCAT(enrollmentId ORDER BY enrollmentId) FROM enrollments
               WHERE enrollmentStatus IN (\'pending\',\'returnedToEvaluation\') AND termId = '.TERM);
check('§7.1', '84,85,287,475,476,596,598', $queue.' — Department Evaluation queue on term '.TERM);

check('§7.6', '1', val("SELECT COUNT(*) FROM examresults er JOIN enrollments e ON e.studentId = er.studentId
                         WHERE e.enrollmentId = 475 AND er.examStage = 'retention' AND er.examResult = 'pass'").' retention pass on Iñigo (475)');
check('§7.7', '0', val("SELECT COUNT(*) FROM examresults er JOIN enrollments e ON e.studentId = er.studentId
                         WHERE e.enrollmentId = 476 AND er.examStage = 'retention'").' retention result on Relinda (476) — the block stands');
check('§7.12', '1', val('SELECT COUNT(*) FROM enrollments WHERE enrollmentId = 596 AND clearanceConfirmedBy IS NOT NULL').' confirmation on Maria 596 (§4 says confirmed)');
check('§7.17', '0', val("SELECT COUNT(*) FROM admissions a JOIN courses c ON c.courseId = a.courseId
                          WHERE c.requiresEntranceExam = 0 AND c.requiresCourseSpecificExam = 1 AND a.termId = ".TERM).' applicant(s) to a board-only program — the roster is empty by design');
echo '        board-only programs: '.val("SELECT GROUP_CONCAT(CONCAT(courseCode,'[',IF(entranceExamExemptsTransferee=1,'exempt','not exempt'),']') SEPARATOR ', ')
                          FROM courses WHERE requiresEntranceExam = 0 AND requiresCourseSpecificExam = 1").' (5.8 / 7.17 targets)';

// ---------------------------------------------------------------- §8 the shift docket
section('§8 the shift docket');

check('§8', '0', val('SELECT COUNT(*) FROM shiftingrequests').' shiftingrequests rows — §8.1 files the first');

// ---------------------------------------------------------------- §9 scholarship
section('§9 the Scholarship desk');

check('§9.1', '0', val('SELECT COUNT(*) FROM studentassessments WHERE enrollmentId = 285').' sheet on Angelica (285) — compute creates it');
check('§9.2', '1', val('SELECT COUNT(*) FROM studentassessments sa JOIN enrollments e ON e.enrollmentId = sa.enrollmentId
                         WHERE e.enrollmentStatus = \'evaluated\'').' sheet(s) on a load not yet decided (the pipeline counter)');
check('§9.5', '0.00', val('SELECT remainingBalance FROM studentassessments sa JOIN enrollments e ON e.enrollmentId = sa.enrollmentId
                         WHERE e.enrollmentId = 286').' owed by Marisol (286)');
check('§9.6', 'active', val("SELECT status FROM studentscholarships WHERE studentScholarshipId = 1").' — §9.6 names grant id 1 for withdrawal');
check('§9.6', 'Domingo', val("SELECT CONCAT(s.lastName) FROM studentscholarships ss JOIN students s ON s.studentId = ss.studentId
                         WHERE ss.studentScholarshipId = 1").' owns grant 1');
check('§9.5', '100.00', val('SELECT coveragePercent FROM scholarshiptypes t JOIN studentscholarships ss ON ss.scholarshipTypeId = t.scholarshipTypeId
                         WHERE ss.studentScholarshipId = 1').' percent of grant 1 (Full Scholarship)');

// ---------------------------------------------------------------- §10 accounting
section('§10 the Accounting desk');

check('§10.1', '17500.00', val('SELECT remainingBalance FROM studentassessments WHERE enrollmentId = 87').' owed by Christian (87)');
check('§10.1', '0.00', val("SELECT IFNULL(SUM(amount),0) FROM payments WHERE enrollmentId = 87 AND paymentStatus IN ('paid','partial')").' held on 87');
check('§10.2', '10500.00', val('SELECT remainingBalance FROM studentassessments WHERE enrollmentId = 88').' owed by Bea (88)');
check('§10.2', '8000.00', val("SELECT IFNULL(SUM(amount),0) FROM payments WHERE enrollmentId = 88 AND paymentStatus IN ('paid','partial')").' held on 88');
check('§10.2', 'paid', val("SELECT GROUP_CONCAT(paymentStatus ORDER BY paymentId) FROM payments WHERE enrollmentId = 88 AND paymentStatus <> 'refunded'").' of Bea\'s live receipts (10.2 makes it partial)');

// ---------------------------------------------------------------- §11 clearance
section('§11 clearance — the window, the lines, the pile');

check('§11.1', '1', val('SELECT COUNT(*) FROM clearanceperiods WHERE periodStatus IN (\'open\',\'extended\')').' accepting window(s) anywhere on the install');
check('§11.1', '11 / open', val('SELECT CONCAT(termId,\' / \',periodStatus) FROM clearanceperiods WHERE periodStatus IN (\'open\',\'extended\')').' — the accepting window: term and status');
check('§11.2', '0', val('SELECT COUNT(*) FROM studentclearances sc JOIN clearanceperiods p ON p.clearancePeriodId = sc.clearancePeriodId
                          WHERE NOT EXISTS (SELECT 1 FROM enrollments e WHERE e.studentId = sc.studentId AND e.termId = p.termId)').' slip(s) with no enrollment in the window term');
$lines = val('SELECT GROUP_CONCAT(n ORDER BY n) FROM (SELECT studentClearanceId, COUNT(*) n FROM clearanceapprovals GROUP BY studentClearanceId) t');
check('§11.4', '~n/a', $lines.' requirement lines per clearance on live (the script says 9 per slip)');
check('§11.4', '9', val('SELECT COUNT(*) FROM clearancerequirements').' rows in the requirement catalog');
check('§11.4', '9', val('SELECT COUNT(DISTINCT officeId) FROM clearancerequirements').' distinct offices owning them');
check('§11.6', 'pending', val("SELECT overallStatus FROM studentclearances WHERE studentClearanceId = 42").' — Rico\'s slip, the one that blocks a close');
check('§11.6', '1', val("SELECT COUNT(*) FROM studentclearances WHERE overallStatus = 'pending'").' pending clearance(s) that would refuse the close');
check('§2.4', '21', val('SELECT COUNT(*) FROM documentprintlog WHERE enrollmentId IS NULL AND studentId IS NULL AND blockId IS NULL').' print rows carrying no key at all (G-9)');

// ---------------------------------------------------------------- §12 registrar
section('§12 the Registrar');

check('§12.1', '86,264', val('SELECT GROUP_CONCAT(enrollmentId ORDER BY enrollmentId) FROM enrollments
                                WHERE enrollmentStatus = \'paid\' AND termId = '.TERM).' paid records awaiting approval');
check('§12.6', '0', val('SELECT COUNT(*) FROM enrollments WHERE enrollmentStatus = \'dropped\' AND termId = '.TERM).' dropped record(s) on term '.TERM.' (12.6 creates one)');

// ---------------------------------------------------------------- §13 blocking
section('§13 Blocking — the three open blocks');

foreach ([['53', 'BSCrim 1-A', '3', '6'], ['75', 'BSIT 1-A', '1', '6'], ['114', 'BSBA 2-A', '0', '6']] as [$id, $name, $seats, $meetings]) {
    check("§13 block {$id}", $seats, val(
        "SELECT COUNT(DISTINCT e.studentId) FROM enrolledsubjects es JOIN enrollments e ON e.enrollmentId = es.enrollmentId
          WHERE es.blockId = ? AND es.status <> 'dropped'", [$id])." students on {$name}");
    check("§13 block {$id}", $meetings, val(
        'SELECT COUNT(*) FROM schedulemeetings sm JOIN schedules s ON s.scheduleId = sm.scheduleId
          WHERE s.blockId = ?', [$id]).' class meetings on the block');
}
check('§13.1', '3', val('SELECT COUNT(*) FROM blocks WHERE termId = '.TERM).' blocks on term '.TERM);

// ---------------------------------------------------------------- §14-§15 clinic and ID
section('§14-§15 the clinic and the ID desk');

check('§15.1', '1', val(
    "SELECT COUNT(*) FROM enrollments e
      WHERE e.enrollmentStatus = 'enrolled' AND e.termId = ".TERM."
        AND NOT EXISTS (SELECT 1 FROM idrequests ir WHERE ir.enrollmentId = e.enrollmentId)
        AND NOT EXISTS (SELECT 1 FROM workflowsteps w JOIN enrollmentworkflow wf ON wf.workflowId = w.workflowId
                         WHERE wf.enrollmentId = e.enrollmentId AND w.stepStatus <> 'completed')
     ").' enrolled record(s) with no request and no unsigned box before ID');
check('§15.1', '1', val("SELECT COUNT(*) FROM idrequests WHERE enrollmentId = 876 AND status = 'pending'").' pending request on Nena (876) for 15.2');

// ---------------------------------------------------------------- §16 reference data
section('§16 reference data the script names');

check('§16.2', 'Clearance Slip Replacement / 100.00', val("SELECT CONCAT(feeName,' / ',defaultAmount) FROM feetypes WHERE feeTypeId = 5"));

echo "\n";
printf("%d mismatch(es), %d note(s).\n", $fails, $notes);
