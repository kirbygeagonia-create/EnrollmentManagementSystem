<?php
/**
 * Do the two clocks show up in real data?
 *
 * auditlogs.createdAt and enrollmentstatushistory.changedAt are written by MySQL (`useCurrent()`), while
 * every Eloquent timestamp is written by PHP. If the two clocks disagree, the same action carries two times
 * eleven hours apart — which matters because the audit trail is the thing the school is asked to trust.
 * This looks for an audit row and the record it describes, taken in the same request, and prints the gap.
 */
$database = $argv[1] ?? 'ems';
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname='.$database, 'root', '');

echo "database: {$database}\n";
printf("mysql NOW() = %s   php date() = %s\n\n", $pdo->query('SELECT NOW()')->fetchColumn(), date('Y-m-d H:i:s'));

echo "columns the database itself stamps (useCurrent in a migration):\n";
foreach ([['auditlogs', 'createdAt'], ['enrollmentstatushistory', 'changedAt']] as [$t, $c]) {
    $row = $pdo->query("SELECT COLUMN_DEFAULT, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t}' AND COLUMN_NAME = '{$c}'")->fetch(PDO::FETCH_ASSOC);
    printf("  %-26s %-12s default=%s extra=%s\n", $t.'.'.$c, '', (string) $row['COLUMN_DEFAULT'], $row['EXTRA']);
}

echo "\naudit row beside the record it describes (same request, two clocks):\n";
$sql = <<<'SQL'
    SELECT a.auditId, a.action, a.entityTable, a.entityId, a.createdAt AS auditTime,
           e.updated_at AS recordTime, TIMESTAMPDIFF(SECOND, e.updated_at, a.createdAt) AS gapSeconds
      FROM auditlogs a
      JOIN enrollments e ON e.enrollmentId = a.entityId
     WHERE a.entityTable = 'enrollments' AND a.updated_at IS NOT NULL
     ORDER BY a.auditId DESC
     LIMIT 8
SQL;

try {
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo '  query failed: '.trim($e->getMessage())."\n";
    $rows = [];
}

if ($rows === []) {
    echo "  no comparable pair found — the claim stays unproven on this database\n";
}

foreach ($rows as $r) {
    printf(
        "  audit %-6s %-8s enroll %-5s audit=%s record=%s gap=%ss (%.1f h)\n",
        $r['auditId'],
        $r['action'],
        $r['entityId'],
        $r['auditTime'],
        $r['recordTime'],
        $r['gapSeconds'],
        ((int) $r['gapSeconds']) / 3600
    );
}

$gaps = array_map(fn ($r) => abs((int) $r['gapSeconds']), $rows);
echo "\n";
if ($gaps !== []) {
    printf("largest gap seen in the last %d pairs: %d seconds (%.1f hours)\n", count($gaps), max($gaps), max($gaps) / 3600);
}
