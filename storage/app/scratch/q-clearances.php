<?php
$p = new PDO('mysql:host=127.0.0.1;dbname=ems', 'root', '');
echo "clearances on file:\n";
foreach ($p->query('SELECT sc.studentClearanceId, sc.studentId, sc.clearancePeriodId, cp.termId, sc.overallStatus,
   (SELECT GROUP_CONCAT(CONCAT(e.termId,":",e.enrollmentStatus)) FROM enrollments e WHERE e.studentId = sc.studentId) enrols
   FROM studentclearances sc JOIN clearanceperiods cp ON cp.clearancePeriodId = sc.clearancePeriodId ORDER BY sc.studentClearanceId') as $r) {
    printf("  #%s student %-4s period %s(term %s) %-9s enrollments: %s\n", $r['studentClearanceId'], $r['studentId'], $r['clearancePeriodId'], $r['termId'], $r['overallStatus'], $r['enrols'] ?? 'NONE');
}
echo "\nopen periods:\n";
foreach ($p->query('SELECT clearancePeriodId, termId, periodStatus FROM clearanceperiods') as $r) {
    printf("  period %s term %s %s\n", $r['clearancePeriodId'], $r['termId'], $r['periodStatus']);
}
