<?php
/**
 * What the demo pipeline's hardcoded ids actually point at, in each database.
 *
 * A foreign key only proves the number exists. This prints the row behind each id the tool assumes, so a
 * value that is valid but semantically wrong — an enrollment "evaluated" by the wrong desk — is visible
 * rather than implied. Run against the developer's live `ems` and against a freshly seeded database and
 * compare.
 */
$database = $argv[1] ?? 'ems';
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname='.$database, 'root', '');

echo "database: {$database}\n\n";

$assumptions = [
    ['subjects', 'subjectId', 5, 'the tool comments it as IT101'],
    ['rooms', 'roomId', 1, 'the schedule line names no room'],
    ['feetypes', 'feeTypeId', 1, 'tuition on the fee sheet'],
    ['feetypes', 'feeTypeId', 2, 'miscellaneous'],
    ['feetypes', 'feeTypeId', 3, 'laboratory'],
    ['feetypes', 'feeTypeId', 4, 'library'],
    ['staffusers', 'userId', 5, 'written into enrollments.evaluatedBy'],
];

foreach ($assumptions as [$table, $column, $id, $meaning]) {
    $cols = $table === 'subjects' ? 'subjectCode, subjectName'
        : ($table === 'rooms' ? 'roomName'
        : ($table === 'feetypes' ? 'feeName, defaultAmount'
        : 'username, officeId'));

    $row = $pdo->query("SELECT {$cols} FROM {$table} WHERE {$column} = ".(int) $id.' LIMIT 1')->fetch(PDO::FETCH_ASSOC);

    printf(
        "%-12s %-6s → %s   [%s]\n",
        $table,
        $id,
        $row ? json_encode($row, JSON_UNESCAPED_UNICODE) : 'ABSENT — the insert dies on the foreign key',
        $meaning
    );
}

echo "\nthe desks that could own an evaluation, by office:\n";
$staff = $pdo->query(
    'SELECT userId, username, officeId FROM staffusers WHERE officeId IN (4,7) ORDER BY userId'
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($staff as $s) {
    printf("  userId %-5s office %-3s %s\n", $s['userId'], $s['officeId'], $s['username']);
}

echo "\nwho the live enrollments actually name as evaluator:\n";
$evals = $pdo->query(
    'SELECT evaluatedBy, COUNT(*) AS n FROM enrollments WHERE evaluatedBy IS NOT NULL GROUP BY evaluatedBy ORDER BY n DESC'
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($evals as $e) {
    printf("  evaluatedBy %-5s on %s enrollment(s)\n", $e['evaluatedBy'], $e['n']);
}
