<?php
$pdo = new PDO('sqlite:'.$argv[1]);
$tables = ['addresses', 'guardians', 'students', 'rooms', 'courses', 'admissionrequirements'];
foreach ($tables as $t) {
    foreach ($pdo->query('PRAGMA table_info("'.$t.'")') as $r) {
        printf("%-12s %-22s notnull=%d dflt=%s\n", $t, $r['name'], $r['notnull'], var_export($r['dflt_value'], true));
    }
    echo "\n";
}
