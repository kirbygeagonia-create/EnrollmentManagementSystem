<?php

/**
 * ADS problem 8/23: the capacity count is the query the enrollment-day lock has to sit behind, and at 100,000
 * synthetic students it read 212,940 index rows and took 410 ms. A lock held across a 400 ms scan is a deadlock
 * and lock-wait generator, so the index comes before the lock. This adds the two composite indexes the desk's own
 * counts ask for to ONE scratch schema and prints the before/after of the same two queries — it never touches a
 * live schema, and it refuses if either measurement produced no rows.
 *
 * Usage: php storage/app/scratch/lane167-probe-capacity-index.php --schema=ems_l163_a
 */
$root = dirname(__DIR__, 3);
$schema = 'ems_l163_a';
for ($i = 1; $i < $argc; $i++) {
    if (preg_match('/^--schema=(.+)$/', $argv[$i], $m)) {
        $schema = $m[1];
    } else {
        fwrite(STDERR, "REFUSED: unreadable argument {$argv[$i]} — this probe reads only --schema=\n");
        exit(1);
    }
}
if (! preg_match('/^ems_l163_[a-z]$/', $schema)) {
    fwrite(STDERR, "REFUSED: this probe writes DDL and only runs against ems_l163_[a-z] (got {$schema}).\n");
    exit(1);
}

$env = [];
foreach (file($root.'/.env') as $l) {
    if (preg_match('/^([A-Z_]+)=(.*)$/', trim($l), $m)) {
        $env[$m[1]] = $m[2];
    }
}
$pdo = new PDO('mysql:host=127.0.0.1;dbname='.$schema, $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? null);

// The busiest block, not the first one. `order by blockId limit 1` picked a carried demo block that the seed
// never wrote a line to, and a count over an empty partition measures the plumbing, not the query: the first run
// of this probe reported 300 ms for a block holding 101,484 students against maxStudents 40 — a fixture no school
// runs — and 0.5 ms for a block holding nobody. Neither is the desk's question.
$block = $pdo->query("select b.blockId, b.blockName, b.maxStudents, count(distinct e.enrollmentId) held
    from blocks b left join enrolledsubjects e on e.blockId = b.blockId and e.status <> 'dropped'
    group by b.blockId, b.blockName, b.maxStudents order by held desc, b.blockId limit 1")->fetch(PDO::FETCH_ASSOC);
// The schedule of THAT block, not the first schedule in the table: the two counts are then the two guards one
// assign act really runs, over the same partition the desk is filling.
$schedule = $pdo->query('select scheduleId from schedules where blockId = '.(int) $block['blockId'].' limit 1')->fetchColumn();
if (! $block || $schedule === false) {
    fwrite(STDERR, "REFUSED: the scale schema holds no block, or the busiest block names no schedule to count against.\n");
    exit(1);
}
printf("busiest block %s (%s): %s students against maxStudents=%s; the block count is taken for THAT partition\n\n",
    $block['blockId'], $block['blockName'], number_format((int) $block['held']), $block['maxStudents']);

$queries = [
    'block capacity count' => 'select count(distinct enrollmentId) c from enrolledsubjects where blockId = '
        .(int) $block['blockId']." and status <> 'dropped'",
    'room capacity count' => 'select count(distinct enrollmentId) c from enrolledsubjects where scheduleId = '
        .(int) $schedule." and status <> 'dropped'",
];

$weigh = static function (string $label) use ($pdo, $queries): array {
    $out = [];
    foreach ($queries as $name => $sql) {
        // The seed stamps no scheduleId, so a zero here means the fixture never asked the question — printing it
        // as a fast measurement would be the two-units defect again (lane 90), just in milliseconds.
        $stamp = str_starts_with($name, 'room')
            ? (int) $pdo->query('select count(*) from enrolledsubjects where scheduleId is not null')->fetchColumn()
            : -1;
        if ($stamp === 0) {
            printf("  %-22s NOT MEASURED — the seed names no scheduleId\n", $name);
            $out[] = 'not measured';

            continue;
        }
        $t0 = microtime(true);
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $ms = (microtime(true) - $t0) * 1000;
        if ($rows === []) {
            fwrite(STDERR, "REFUSED: {$name} answered no row at all ({$label}) — that is a broken measurement, not a fast one.\n");
            exit(1);
        }
        $plan = $pdo->query('explain '.$sql)->fetchAll(PDO::FETCH_ASSOC);
        $bits = [];
        foreach ($plan as $p) {
            $bits[] = ($p['key'] ?? 'NULL').':'.($p['type'] ?? '?').':rows='.($p['rows'] ?? '?');
        }
        $out[] = sprintf('  %-22s %9.2f ms  count=%-8s plan %s', $name, $ms, number_format((int) $rows[0]['c']), implode('|', $bits));
    }
    printf("%s\n", $label);
    foreach ($out as $line) {
        echo $line."\n";
    }

    return $out;
};

$before = $weigh('BEFORE (the shape live `ems` has today)');

foreach ([
    'idx_enrolledsubjects_block_status' => 'alter table enrolledsubjects add index `idx_enrolledsubjects_block_status` (blockId, status, enrollmentId)',
    'idx_enrolledsubjects_schedule_status' => 'alter table enrolledsubjects add index `idx_enrolledsubjects_schedule_status` (scheduleId, status, enrollmentId)',
] as $name => $ddl) {
    $has = (bool) $pdo->query('select 1 from information_schema.STATISTICS where TABLE_SCHEMA = '
        .$pdo->quote($schema).' and INDEX_NAME = '.$pdo->quote($name).' limit 1')->fetchColumn();
    if ($has) {
        printf("index %s already present\n", $name);

        continue;
    }
    $pdo->exec($ddl);
    printf("added %s\n", $name);
}

$after = $weigh('AFTER the two composite indexes');

echo "\nCompare the two blocks line by line: the counts must be identical and the plan must stop reading the whole\n";
echo "index. This prints no verdict of its own — the reading is the point.\n";
