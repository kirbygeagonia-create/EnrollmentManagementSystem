<?php

// Lane 108 helper: list the scratch MySQL schemas and whether each is migrated, so the rehearsal command names a
// schema that really holds the tables. Written to a file because `php -r` with quoted SQL dies in this shell.
$b = [];
foreach (file(dirname(__DIR__, 3).'/.env') as $l) {
    $l = trim($l);
    if ($l === '' || $l[0] === '#') {
        continue;
    }
    $p = explode('=', $l, 2);
    if (count($p) === 2) {
        $b[trim($p[0])] = trim($p[1], " \x22\x27");
    }
}
$dsn = 'mysql:host='.($b['DB_HOST'] ?? '127.0.0.1').';port='.($b['DB_PORT'] ?? '3306');
try {
    $pdo = new PDO($dsn, $b['DB_USERNAME'] ?? 'root', $b['DB_PASSWORD'] ?? '');
} catch (Throwable $e) {
    fwrite(STDERR, 'CONNECT FAILED: '.$e->getMessage()."\n");

    exit(1);
}

$names = $pdo->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE 'ems_l%' OR SCHEMA_NAME IN ('ems','ems_test') ORDER BY SCHEMA_NAME")
    ->fetchAll(PDO::FETCH_COLUMN);
if ($names === []) {
    fwrite(STDERR, "REFUSED: the server answered no schema at all, which is not the same as an empty list.\n");

    exit(1);
}
foreach ($names as $s) {
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
    $st->execute([$s]);
    $tables = (int) $st->fetchColumn();
    $st2 = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'admissions' AND COLUMN_NAME = 'studentId'");
    $st2->execute([$s]);
    $marker = (int) $st2->fetchColumn();
    $mode = $pdo->prepare("SELECT @@sql_mode");
    $mode->execute();
    printf("  %-12s tables=%-4s admissions.studentId=%d %s\n", $s, $tables, $marker, $marker === 1 ? 'MIGRATED' : 'not the app schema');
    if (in_array($s, ['ems_l108_a', 'ems'], true)) {
        $m = (string) $mode->fetchColumn();
        echo '      sql_mode contains ONLY_FULL_GROUP_BY: ', str_contains($m, 'ONLY_FULL_GROUP_BY') ? 'yes' : 'no', "\n";
    }
}
