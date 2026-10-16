<?php
$p = new PDO('mysql:host=127.0.0.1;dbname=ems', 'root', '');
foreach ([285, 318, 91] as $eid) {
    $codes = $p->query("SELECT GROUP_CONCAT(s.subjectCode ORDER BY s.subjectCode) c FROM enrolledsubjects es JOIN subjects s ON s.subjectId=es.subjectId WHERE es.enrollmentId=$eid")->fetch();
    echo "  enrollment #$eid load: ".($codes['c'] ?? 'NONE')."\n";
}
