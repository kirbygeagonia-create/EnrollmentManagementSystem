<?php
/**
 * Which of the demo pipeline's hardcoded ids are enforced, and which are free integers?
 *
 * A column with a foreign key fails loudly when an id does not exist; a column without one accepts any
 * number and the desk then reads a fee sheet whose rows are labelled by whatever the reference table
 * happened to hold at those ids. The distinction decides how urgent the finding is, so it is measured in
 * information_schema rather than assumed from the model.
 */
$database = $argv[1] ?? 'ems_ci_dry';
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname='.$database, 'root', '');

$wants = [
    ['charges', 'feeTypeId'],
    ['schedules', 'roomId'],
    ['schedules', 'subjectId'],
    ['enrollments', 'evaluatedBy'],
    ['enrollments', 'courseId'],
    ['enrollments', 'termId'],
    ['blocks', 'courseId'],
    ['blocks', 'termId'],
];

foreach ($wants as [$table, $column]) {
    $fk = $pdo->query(
        "SELECT REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = '{$column}'
            AND REFERENCED_TABLE_NAME IS NOT NULL"
    )->fetchColumn();

    $nullable = $pdo->query(
        "SELECT IS_NULLABLE FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = '{$column}'"
    )->fetchColumn();

    printf(
        "%-14s %-14s %s\n",
        $table.'.'.$column,
        $fk ? 'FK -> '.$fk : ($nullable === 'NO' ? 'NO FK, NOT NULL' : 'NO FK, nullable'),
        $fk ? 'loud' : 'SILENT'
    );
}

echo "\nwhat the reference tables actually hold in this database:\n";
foreach ([['feetypes', 'feeTypeId', 'feeName'], ['rooms', 'roomId', 'roomName'], ['academicunits', 'unitId', 'unitName']] as [$t, $id, $name]) {
    try {
        $rows = $pdo->query("SELECT {$id}, {$name} FROM {$t} ORDER BY {$id} LIMIT 8")->fetchAll(PDO::FETCH_KEY_PAIR);
        $out = [];
        foreach ($rows as $k => $v) {
            $out[] = $k.'='.$v;
        }
        printf("  %-14s %s\n", $t, implode(', ', $out));
    } catch (Throwable $e) {
        printf("  %-14s unreadable: %s\n", $t, trim($e->getMessage()));
    }
}

$staff = $pdo->query('SELECT userId, username FROM staffusers ORDER BY userId LIMIT 8')->fetchAll(PDO::FETCH_KEY_PAIR);
echo "  staffusers     ".implode(', ', array_map(fn ($k, $v) => $k.'='.$v, array_keys($staff), $staff))."\n";
