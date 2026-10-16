<?php

/** The five rows the precondition pass put in doubt, read as facts rather than verdicts. */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'ems',
    'database.connections.mysql.username' => 'root', 'database.connections.mysql.password' => '']);
DB::purge('mysql');

function dump(string $label, array $rows): void
{
    echo "\n=== {$label}\n";
    foreach ($rows as $r) {
        $p = [];
        foreach ((array) $r as $k => $v) {
            $p[] = $k.'='.($v === null ? 'NULL' : $v);
        }
        echo '  '.implode('  ', $p)."\n";
    }
    if ($rows === []) {
        echo "  (no rows)\n";
    }
}

dump('every ID request on the two named students and the demo set', DB::select(
    "SELECT ir.idRequestId, ir.enrollmentId, s.schoolIdNumber, CONCAT(s.lastName,', ',s.firstName) student,
            ir.status, ir.requestReason, ir.requestDate, ir.validatedBy, ir.validatedDate,
            IF(ir.cardPhotoPath IS NULL,'no photo','photo on file') photo, e.enrollmentStatus
       FROM idrequests ir
       JOIN enrollments e ON e.enrollmentId = ir.enrollmentId
       JOIN students s ON s.studentId = e.studentId
      WHERE ir.enrollmentId IN (876, 92, 53, 91)
      ORDER BY ir.enrollmentId"));

dump('enrolled records on term 11 whose next unsigned box is ID', DB::select(
    "SELECT e.enrollmentId, s.schoolIdNumber, CONCAT(s.lastName,', ',s.firstName) student,
            (SELECT COUNT(*) FROM idrequests ir WHERE ir.enrollmentId = e.enrollmentId) requests,
            (SELECT COUNT(*) FROM workflowsteps w JOIN enrollmentworkflow wf ON wf.workflowId = w.workflowId
              WHERE wf.enrollmentId = e.enrollmentId AND w.stepStatus <> 'completed') unsignedBoxes,
            (SELECT GROUP_CONCAT(CONCAT(w.officeId,':',w.stepStatus) ORDER BY w.stepOrder)
              FROM enrollmentworkflow wf2 JOIN workflowsteps w ON w.workflowId = wf2.workflowId
              WHERE wf2.enrollmentId = e.enrollmentId) boxes
      FROM enrollments e JOIN students s ON s.studentId = e.studentId
      WHERE e.enrollmentStatus = 'enrolled' AND e.termId = 11
      ORDER BY e.enrollmentId"));

dump('block seats on Patricia Diaz (89) and Gabriel Ramos (90)', DB::select(
    "SELECT es.enrolledSubjectId, es.enrollmentId, es.subjectId, es.blockId, es.status, sub.subjectCode
       FROM enrolledsubjects es JOIN subjects sub ON sub.subjectId = es.subjectId
      WHERE es.enrollmentId IN (89, 90) ORDER BY es.enrollmentId, es.enrolledSubjectId"));

dump('the three blocks and who sits on them', DB::select(
    "SELECT b.blockId, b.blockName, b.yearLevel, b.maxStudents, b.scheduleStatus,
            COUNT(DISTINCT es.enrollmentId) studentsOnBlock
      FROM blocks b LEFT JOIN enrolledsubjects es ON es.blockId = b.blockId AND es.status <> 'dropped'
      WHERE b.termId = 11 GROUP BY b.blockId"));

dump('Maria Reyes 596 clearance confirmation', DB::select(
    "SELECT e.enrollmentId, e.enrollmentStatus, e.clearanceConfirmedBy, e.clearanceConfirmedAt,
            e.studentType, (SELECT COUNT(*) FROM studentclearances sc WHERE sc.studentId = e.studentId) slips
      FROM enrollments e WHERE e.enrollmentId IN (596, 264, 287)"));

dump('Bea Alonzo 88 receipts', DB::select(
    "SELECT paymentId, orNumber, amount, paymentStatus, paymentDate, refundedAt IS NOT NULL refunded
      FROM payments WHERE enrollmentId = 88 ORDER BY paymentId"));

dump('audit trail: what touched these tables last', DB::select(
    "SELECT tableName, COUNT(*) events, MAX(createdAt) lastWrite
      FROM auditlogs
      WHERE tableName IN ('idrequests','enrolledsubjects','enrollmentworkflow','workflowsteps','payments')
      GROUP BY tableName ORDER BY lastWrite DESC"));
