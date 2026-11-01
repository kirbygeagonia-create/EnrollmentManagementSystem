<?php

/**
 * ADS Problems 8, 9 and 23 are "measure, do not assume". This weighs the enrollment queries on the synthetic scale
 * schema `lane163` builds, and prints the plan as well as the time, because a timing without a plan cannot say
 * which index the server used.
 *
 * Three families are measured, each with the shape the application uses today and the shape the fix would use:
 *   P08  the student directory's contains-search against a prefix search against a full-text search
 *   P09  one student's enrollment history, and the Registrar queue's per-row checklist against one batched read
 *   P23  the enrollment-write queries (seat lookup, capacity count) that the locking change touches
 *
 * It is read-only: it runs SELECT and EXPLAIN, and any index it adds is added to the SCRATCH schema only and
 * reported, never to a live schema. Pass --no-ddl to measure without touching the schema at all.
 *
 * Usage: php storage/app/scratch/lane164-measure-scale-queries.php [--schema=ems_l163_a] [--limit=20] [--no-ddl]
 */
$root = dirname(__DIR__, 3);
$known = ['schema', 'limit', 'no-ddl'];
$schema = 'ems_l163_a';
$limit = 20;
$noDdl = false;

for ($i = 1; $i < $argc; $i++) {
    if (! preg_match('/^--([a-z0-9-]+)(=(.*))?$/', $argv[$i], $m)) {
        fwrite(STDERR, "REFUSED: unreadable argument {$argv[$i]}\n");
        exit(1);
    }
    if (! in_array($m[1], $known, true)) {
        fwrite(STDERR, "REFUSED: unknown flag --{$m[1]} — this probe does not read it\n");
        exit(1);
    }
    if ($m[1] === 'schema') {
        $schema = (string) ($m[3] ?? $schema);
    } elseif ($m[1] === 'limit') {
        $limit = max(1, (int) ($m[3] ?? $limit));
    } elseif ($m[1] === 'no-ddl') {
        $noDdl = true;
    }
}

if (! preg_match('/^ems_l163_[a-z]$/', $schema)) {
    fwrite(STDERR, "REFUSED: this probe writes DDL and only runs against a schema named ems_l163_[a-z]; got '{$schema}'.\n");
    exit(1);
}

$env = [];
foreach (file($root.'/.env') as $l) {
    if (preg_match('/^([A-Z_]+)=(.*)$/', trim($l), $m)) {
        $env[$m[1]] = $m[2];
    }
}
$pdo = new PDO('mysql:host=127.0.0.1;dbname='.$schema, $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? null);

$population = [];
foreach (['students', 'enrollments', 'enrolledsubjects'] as $t) {
    $population[$t] = (int) $pdo->query("select count(*) from `{$t}`")->fetchColumn();
}
if ($population['students'] < 10000) {
    fwrite(STDERR, "REFUSED: {$schema} holds only ".number_format($population['students']).
        " students — build the scale schema first (lane163), or this measures a rounding error.\n");
    exit(1);
}
printf("scale %s: %s students, %s enrollments, %s subject lines\n\n", $schema,
    number_format($population['students']), number_format($population['enrollments']), number_format($population['enrolledsubjects']));

$weigh = static function (string $label, string $sql) use ($pdo): array {
    $t0 = microtime(true);
    $st = $pdo->query($sql);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $ms = (microtime(true) - $t0) * 1000;
    $plan = $pdo->query('explain '.$sql)->fetchAll(PDO::FETCH_ASSOC);
    $used = [];
    foreach ($plan as $p) {
        $used[] = ($p['table'] ?? '?').':'.($p['type'] ?? '?').':'.($p['key'] ?? 'NULL')
            .':rows='.($p['rows'] ?? '?');
    }
    printf("%-46s %8.2f ms  rows=%s\n    plan %s\n", $label, $ms, number_format(count($rows)), implode(' | ', $used));

    return ['ms' => $ms, 'rows' => count($rows), 'plan' => $used];
};

echo "=== P08 student search ===\n";
$contains = $weigh("contains '%Scale12%'", "select studentId, lastName, firstName from students where lastName like '%Scale12%' limit {$limit}");
$prefix = $weigh("prefix 'Scale12%'", "select studentId, lastName, firstName from students where lastName like 'Scale12%' limit {$limit}");
$idExact = $weigh('schoolIdNumber exact', "select studentId from students where schoolIdNumber = 'SCALE-00000123'");

if (! $noDdl) {
    $hasFt = (bool) $pdo->query("select 1 from information_schema.STATISTICS where TABLE_SCHEMA = ".$pdo->quote($schema)
        ." and TABLE_NAME = 'students' and INDEX_TYPE = 'FULLTEXT'")->fetchColumn();
    if (! $hasFt) {
        $pdo->exec('alter table students add fulltext index `ft_students_names` (lastName, firstName)');
        echo "  added ft_students_names (scratch schema only)\n";
    }
    $fulltext = $weigh('full-text MATCH AGAINST', "select studentId, lastName from students where match(lastName, firstName) against ('Scale12*' in boolean mode) limit {$limit}");
    printf("\n  ratio contains/prefix     %.1fx\n", $contains['ms'] / max(0.01, $prefix['ms']));
    printf("  ratio contains/fulltext   %.1fx\n", $contains['ms'] / max(0.01, $fulltext['ms']));
}

echo "\n=== P09 enrollment history ===\n";
$sid = (int) $pdo->query('select min(studentId) from students')->fetchColumn();
$weigh('one student, joined history', "select e.enrollmentId, e.yearLevel, e.enrollmentStatus, at.semester, ay.yearLabel, c.courseCode
    from enrollments e join academicterms at on at.termId = e.termId join academicyears ay on ay.academicYearId = at.academicYearId
    join courses c on c.courseId = e.courseId where e.studentId = {$sid} order by at.startDate desc");
$weigh('one student, subject lines', "select es.enrolledSubjectId, es.subjectId, es.status, s.subjectCode, s.subjectName
    from enrolledsubjects es join subjects s on s.subjectId = es.subjectId
    join enrollments e on e.enrollmentId = es.enrollmentId where e.studentId = {$sid}");

// The queue's per-row cost, measured as the loop actually runs it rather than described.
$ids = $pdo->query("select enrollmentId from enrollments order by enrollmentId limit {$limit}")->fetchAll(PDO::FETCH_COLUMN);
$t0 = microtime(true);
foreach ($ids as $eid) {
    $pdo->query("select count(*) from enrolledsubjects where enrollmentId = ".(int) $eid." and status <> 'dropped'")->fetchColumn();
    $pdo->query("select count(*) from studentassessments where enrollmentId = ".(int) $eid)->fetchColumn();
}
$perRowMs = (microtime(true) - $t0) * 1000;
$t0 = microtime(true);
$in = implode(',', array_map('intval', $ids));
$pdo->query("select enrollmentId, count(*) c from enrolledsubjects where enrollmentId in ({$in}) and status <> 'dropped' group by enrollmentId")->fetchAll();
$pdo->query("select enrollmentId, count(*) c from studentassessments where enrollmentId in ({$in}) group by enrollmentId")->fetchAll();
$batchedMs = (microtime(true) - $t0) * 1000;
printf("per-row loop for %d rows                    %8.2f ms  (%d queries)\n", count($ids), $perRowMs, count($ids) * 2);
printf("same two reads batched for %d rows          %8.2f ms  (2 queries)\n", count($ids), $batchedMs);

echo "\n=== P23 enrollment write path ===\n";
$weigh('seat lookup (what the guard asks)', "select enrollmentId from enrollments where studentId = {$sid} and enrollmentStatus <> 'dropped'");
$block = $pdo->query('select blockId, maxStudents from blocks order by blockId limit 1')->fetch(PDO::FETCH_ASSOC);
if ($block) {
    $weigh('capacity count for one block', 'select count(distinct enrollmentId) c from enrolledsubjects where blockId = '
        .(int) $block['blockId']." and status <> 'dropped'");
    printf("    block %s maxStudents=%s (the invariant the lock must protect)\n", $block['blockId'], $block['maxStudents']);
}

echo "\nVERDICT is the table above: every figure is a measured millisecond against a plan the server printed.\n";
