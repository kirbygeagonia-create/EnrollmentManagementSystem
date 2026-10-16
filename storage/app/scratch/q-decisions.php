<?php
$p = new PDO('mysql:host=127.0.0.1;dbname=ems', 'root', '');
$one = fn ($q) => $p->query($q)->fetch();

echo "== X-4: what one OfficeHead account can sign ==\n";
$r = $one("SELECT id FROM roles WHERE name='OfficeHead'");
$cnt = $one("SELECT COUNT(*) c FROM role_permissions WHERE roleId={$r['id']}")['c'];
echo "  OfficeHead holds {$cnt} permissions, incl.: ";
foreach ($p->query("SELECT name FROM permissions JOIN role_permissions ON permissionId=permissions.id WHERE roleId={$r['id']} AND (name LIKE '%approve%' OR name LIKE '%sign%' OR name LIKE '%validate%' OR name LIKE 'record%') ORDER BY name") as $x) { echo $x['name'].' '; }

echo "\n\n== C-11: enum values the columns allow vs the values rows hold ==\n";
$cols = [
    ['payments', 'paymentStatus'], ['idrequests', 'status'], ['clearanceperiods', 'periodStatus'],
    ['studentscholarships', 'status'], ['enrollments', 'enrollmentStatus'], ['studentclearances', 'overallStatus'],
    ['clinicrecords', 'status'], ['admissions', 'admissionStatus'], ['examresults', 'examResult'],
    ['enrolledsubjects', 'status'], ['documents', 'submissionStatus'], ['schedules', 'scheduleStatus'],
];
foreach ($cols as [$t, $c]) {
    $col = $one("SELECT COLUMN_TYPE ty FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c'");
    if (! $col) { echo "  (no $t.$c)\n"; continue; }
    preg_match_all("/'([^']+)'/", $col['ty'], $m);
    $allowed = $m[1];
    $used = [];
    foreach ($p->query("SELECT DISTINCT `$c` AS v FROM `$t`") as $x) { if ($x["v"] !== null) { $used[] = $x["v"]; } }
    $dead = array_values(array_diff($allowed, $used));
    printf("  %-14s %-17s allows %2d, holds %2d -> unused: %s\n", $t, $c, count($allowed), count($used), $dead ? implode(', ', $dead) : 'none');
}

echo "\n== D-1: the seeded office with no OfficeId case ==\n";
foreach ($p->query("SELECT officeId, officeName FROM offices WHERE officeId NOT IN (1,2,3,4,5,6,11,22) ORDER BY officeId") as $o) {
    printf("  office %s \"%s\": staff=%s, clearance requirements=%s\n", $o['officeId'], $o['officeName'],
        $one("SELECT COUNT(*) c FROM staffusers WHERE officeId={$o['officeId']}")['c'],
        $one("SELECT COUNT(*) c FROM clearancerequirements WHERE officeId={$o['officeId']}")['c']);
}

echo "\n== print-log attribution (documentNumber) ==\n";
foreach ($p->query("SELECT documentType, SUM(enrollmentId IS NULL) nulls, COUNT(*) rows_, COUNT(DISTINCT documentNumber) nums FROM documentprintlog GROUP BY documentType") as $r2) {
    printf("  %-16s rows=%-3s NULL enrollment=%-3s distinct numbers=%s\n", $r2['documentType'], $r2['rows_'], $r2['nulls'], $r2['nums']);
}

echo "\n== G-4: one active enrollment per student+term ==\n";
printf("  active rows=%s, duplicate (studentId,termId) groups=%s\n",
    $one("SELECT COUNT(*) c FROM enrollments WHERE enrollmentStatus NOT IN ('dropped','cancelled')")['c'],
    $one("SELECT COUNT(*) c FROM (SELECT studentId,termId FROM enrollments WHERE enrollmentStatus NOT IN ('dropped','cancelled') GROUP BY 1,2 HAVING COUNT(*)>1) x")['c']);
