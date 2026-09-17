<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=ems', 'root', '');
$tables = [
    'students', 'admissions', 'enrollments', 'studentassessments',
    'payments', 'enrolledsubjects', 'blocks', 'schedules',
    'clinicrecords', 'idrequests', 'studentids', 'studentclearances',
    'staffusers', 'examresults'
];
foreach ($tables as $t) {
    echo str_pad($t, 22) . ': ' . $pdo->query("SELECT count(*) FROM $t")->fetchColumn() . PHP_EOL;
}
