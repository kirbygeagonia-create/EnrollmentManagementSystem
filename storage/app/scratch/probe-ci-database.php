<?php
/**
 * Rehearsal probe: what does a CI-shaped MySQL database actually contain?
 *
 * CI creates an empty schema, migrates it, runs DatabaseSeeder then DevReferenceDataSeeder, and the
 * walkthrough inserts a student with a hardcoded religionId. The first run of that step failed on the
 * religions foreign key, so this prints the ids the seeders really produce rather than the ids assumed.
 */
$database = $argv[1] ?? 'ems_ci_dry';

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname='.$database, 'root', '');

$tables = ['religions', 'roles', 'courses', 'subjects', 'rooms', 'academicterms', 'curriculums'];

foreach ($tables as $table) {
    $count = (int) $pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
    echo str_pad($table, 16).' rows='.$count."\n";
}

echo "\nreligions (id => name) as the seeders left them:\n";
foreach ($pdo->query('SELECT religionId, religionName FROM religions ORDER BY religionId') as $row) {
    echo '  '.$row['religionId'].' => '.$row['religionName']."\n";
}

echo "\ncolumns of students that are NOT NULL without a default (the walkthrough must supply each):\n";
$stmt = $pdo->query(
    "SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT, EXTRA
       FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = '".$database."' AND TABLE_NAME = 'students'
        AND IS_NULLABLE = 'NO' AND COLUMN_DEFAULT IS NULL AND EXTRA NOT LIKE '%auto_increment%'
      ORDER BY ORDINAL_POSITION"
);
foreach ($stmt as $row) {
    echo '  '.$row['COLUMN_NAME'].' ('.$row['DATA_TYPE'].")\n";
}

echo "\nforeign keys out of students, with the table each points at:\n";
$stmt = $pdo->query(
    "SELECT k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME
       FROM information_schema.KEY_COLUMN_USAGE k
      WHERE k.TABLE_SCHEMA = '".$database."' AND k.TABLE_NAME = 'students'
        AND k.REFERENCED_TABLE_NAME IS NOT NULL
      ORDER BY k.ORDINAL_POSITION"
);
foreach ($stmt as $row) {
    echo '  '.$row['COLUMN_NAME'].' -> '.$row['REFERENCED_TABLE_NAME'].'.'.$row['REFERENCED_COLUMN_NAME']."\n";
}
