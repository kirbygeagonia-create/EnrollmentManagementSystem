<?php
$pdo = new PDO('sqlite:'.$argv[1]);
foreach ($pdo->query('PRAGMA table_info("'.$argv[2].'")') as $r) {
    echo $r['name'].' '.$r['type'].' notnull='.$r['notnull']."\n";
}
