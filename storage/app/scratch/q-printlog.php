<?php
$p = new PDO('mysql:host=127.0.0.1;dbname=ems', 'root', '');
echo "12 most recent issue rows:\n";
foreach ($p->query('SELECT printLogId,documentType,enrollmentId,documentNumber,printedDate,printedBy FROM documentprintlog ORDER BY printLogId DESC LIMIT 12') as $r) {
    printf("  #%-4s %-14s enroll=%-6s n=%-3s %s by %s\n", $r['printLogId'], $r['documentType'], var_export($r['enrollmentId'], true), $r['documentNumber'], $r['printedDate'], $r['printedBy']);
}
echo "\nslips whose student has no enrollment in the clearance period's term (the NULL case today):\n";
$q = $p->query('SELECT sc.studentClearanceId, sc.clearancePeriodId, cp.termId, sc.studentId,
   (SELECT COUNT(*) FROM enrollments e WHERE e.studentId = sc.studentId AND e.termId = cp.termId) hits
   FROM studentclearances sc JOIN clearanceperiods cp ON cp.clearancePeriodId = sc.clearancePeriodId');
$total = 0; $zero = 0;
foreach ($q as $r) { $total++; if ((int) $r['hits'] === 0) { $zero++; echo "  clearance {$r['studentClearanceId']} student {$r['studentId']} term {$r['termId']}: no enrollment row\n"; } }
echo "  {$zero} of {$total} clearances have no enrollment for their own period's term\n";
