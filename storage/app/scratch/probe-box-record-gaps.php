<?php

/**
 * Blast radius of the two box/record contradictions the record-state audit does not look at:
 * a Clinic box signed with no clinic record behind it, and an ID box signed with no validated
 * request behind it. Counted per enrollment, joined to what the office actually wrote.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'ems',
    'database.connections.mysql.username' => 'root', 'database.connections.mysql.password' => '']);
DB::purge('mysql');

const TERM = 11;

echo "=== Clinic box COMPLETED with no clinicrecords row on that enrollment\n";
$clinic = DB::select(
    "SELECT e.enrollmentId, s.schoolIdNumber, CONCAT(s.lastName,', ',s.firstName) student, e.termId,
            w.signedBy, w.signedDate,
            (SELECT COUNT(*) FROM clinicrecords cr WHERE cr.enrollmentId = e.enrollmentId) records
      FROM workflowsteps w
      JOIN enrollmentworkflow wf ON wf.workflowId = w.workflowId
      JOIN enrollments e ON e.enrollmentId = wf.enrollmentId
      JOIN students s ON s.studentId = e.studentId
      WHERE w.officeId = 11 AND w.stepStatus = 'completed'
        AND NOT EXISTS (SELECT 1 FROM clinicrecords cr2 WHERE cr2.enrollmentId = e.enrollmentId)
      ORDER BY e.termId DESC, e.enrollmentId");
foreach ($clinic as $r) {
    echo '  '.implode(' | ', array_map(fn ($k, $v) => "$k=".($v ?? 'NULL'), array_keys((array) $r), array_values((array) $r)))."\n";
}
echo '  total: '.count($clinic)."\n";

echo "\n=== ID box COMPLETED with no VALIDATED request on that enrollment\n";
$id = DB::select(
    "SELECT e.enrollmentId, s.schoolIdNumber, CONCAT(s.lastName,', ',s.firstName) student, e.termId,
            w.signedBy, w.signedDate,
            (SELECT COUNT(*) FROM idrequests ir WHERE ir.enrollmentId = e.enrollmentId) anyRequest,
            (SELECT GROUP_CONCAT(ir2.status) FROM idrequests ir2 WHERE ir2.enrollmentId = e.enrollmentId) statuses
      FROM workflowsteps w
      JOIN enrollmentworkflow wf ON wf.workflowId = w.workflowId
      JOIN enrollments e ON e.enrollmentId = wf.enrollmentId
      JOIN students s ON s.studentId = e.studentId
      WHERE w.officeId = 22 AND w.stepStatus = 'completed'
        AND NOT EXISTS (SELECT 1 FROM idrequests ir3 WHERE ir3.enrollmentId = e.enrollmentId AND ir3.status = 'validated')
      ORDER BY e.termId DESC, e.enrollmentId");
foreach ($id as $r) {
    echo '  '.implode(' | ', array_map(fn ($k, $v) => "$k=".($v ?? 'NULL'), array_keys((array) $r), array_values((array) $r)))."\n";
}
echo '  total: '.count($id)."\n";

echo "\n=== the reverse: a clinic record whose box is NOT completed (allowed? measured anyway)\n";
echo '  '.DB::select(
    "SELECT COUNT(*) c FROM clinicrecords cr JOIN enrollments e ON e.enrollmentId = cr.enrollmentId
      JOIN enrollmentworkflow wf ON wf.enrollmentId = e.enrollmentId
      JOIN workflowsteps w ON w.workflowId = wf.workflowId AND w.officeId = 11
      WHERE w.stepStatus <> 'completed'")[0]->c." clinic records sit on enrollments whose Clinic box is still open\n";

echo "\n=== what the term-11 demo students are missing, desk by desk\n";
foreach (DB::select(
    "SELECT e.enrollmentId, s.schoolIdNumber, CONCAT(s.lastName,', ',s.firstName) student, e.enrollmentStatus,
            (SELECT COUNT(*) FROM clinicrecords cr WHERE cr.enrollmentId = e.enrollmentId) clinic,
            (SELECT COUNT(*) FROM idrequests ir WHERE ir.enrollmentId = e.enrollmentId) idreq,
            (SELECT GROUP_CONCAT(CONCAT(w.officeId,':',LEFT(w.stepStatus,4)) ORDER BY w.stepOrder)
              FROM enrollmentworkflow wf2 JOIN workflowsteps w ON w.workflowId = wf2.workflowId
              WHERE wf2.enrollmentId = e.enrollmentId) boxes
      FROM enrollments e JOIN students s ON s.studentId = e.studentId
      WHERE e.termId = ".TERM." AND e.enrollmentStatus = 'enrolled' ORDER BY e.enrollmentId") as $r) {
    printf("  %-5s %-14s %-22s clinic=%s idreq=%s  %s\n", $r->enrollmentId, $r->schoolIdNumber, $r->student, $r->clinic, $r->idreq, $r->boxes ?? '');
}
