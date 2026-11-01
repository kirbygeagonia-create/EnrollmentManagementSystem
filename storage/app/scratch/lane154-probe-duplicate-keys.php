<?php

/**
 * ADS Problems 3, 4, 11 and 18 need a database-level rule, and §38.D of the guide says an
 * additive unique key may not be proposed until the existing rows are weighed against it.
 * Read-only: it asks three questions of whichever schema .env names and prints the duplicate
 * groups it finds. It writes nothing.
 */
$env = [];
foreach (file(getcwd().'/.env') as $l) {
    if (preg_match('/^([A-Z_]+)=(.*)$/', trim($l), $m)) {
        $env[$m[1]] = $m[2];
    }
}

$schema = $argv[1] ?? ($env['DB_DATABASE'] ?? 'ems');
$password = $env['DB_PASSWORD'] ?? null;

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname='.$schema, $env['DB_USERNAME'] ?? 'root', $password);
} catch (Throwable $e) {
    fwrite(STDERR, 'REFUSED: cannot open schema '.$schema.' — '.$e->getMessage()."\n");
    exit(1);
}

$questions = [
    'one fee sheet per enrollment (ADS P3/P11)' =>
        'select enrollmentId, count(*) c from studentassessments group by enrollmentId having c > 1',
    'one ACTIVE enrollment per student in a term (ADS P3/P18)' =>
        'select studentId, termId, count(*) c from enrollments where enrollmentStatus <> \'dropped\' group by studentId, termId having c > 1',
    'one subject line per enrollment+subject (ADS P3)' =>
        'select enrollmentId, subjectId, count(*) c from enrolledsubjects group by enrollmentId, subjectId having c > 1',
    'one student per schoolIdNumber (ADS P18)' =>
        'select schoolIdNumber, count(*) c from students group by schoolIdNumber having c > 1',
];

foreach ($questions as $label => $sql) {
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    printf("%-56s duplicate groups: %d\n", $label, count($rows));
    foreach (array_slice($rows, 0, 5) as $r) {
        echo '    ', json_encode($r, JSON_UNESCAPED_UNICODE), "\n";
    }
}

echo "\ncensus of the tables this probe read:\n";
foreach (['students', 'enrollments', 'enrolledsubjects', 'studentassessments', 'blocks', 'schedules', 'rooms'] as $t) {
    printf("  %-22s %s rows\n", $t, $pdo->query("select count(*) from `{$t}`")->fetchColumn());
}

// `information_schema.STATISTICS` names its key column INDEX_NAME; CONSTRAINT_NAME belongs to
// TABLE_CONSTRAINTS. Asking the first for the second's column is a 1054, not an empty answer.
printf("\nkeys the server holds in %s: %s index rows, %s unique constraints, %s total table constraints\n", $schema,
    $pdo->query('select count(*) from information_schema.STATISTICS where TABLE_SCHEMA = '.$pdo->quote($schema))->fetchColumn(),
    $pdo->query('select count(*) from information_schema.TABLE_CONSTRAINTS where TABLE_SCHEMA = '.$pdo->quote($schema)." and CONSTRAINT_TYPE = 'UNIQUE'")->fetchColumn(),
    $pdo->query('select count(*) from information_schema.TABLE_CONSTRAINTS where TABLE_SCHEMA = '.$pdo->quote($schema))->fetchColumn());
