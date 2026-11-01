<?php

/**
 * T23 / T24 — the enrollment-day challenge, executed rather than designed.
 *
 * The defence question is "if 1,000 enrollment requests are submitted at the same time, how can you prove the
 * database keeps records accurate, consistent, secure and reliable, when a registrar — not the student — is the one
 * processing them?" A design does not answer that. This tool answers it by running the act the application actually
 * uses to create a record — `App\Services\EnrollmentIssuer::issue()`, the one writer that puts the enrollment and
 * its workflow form in a single transaction — from several independent PHP processes against a scratch clone, and
 * then asking the server what the rows say.
 *
 * Three phases, because they prove different things:
 *
 *   VOLUME      N students, one issue each, spread over W workers. Proves throughput and that nothing is
 *               half-written: every created enrollment carries exactly one workflow form, and no student ends up
 *               holding two active seats.
 *   CONTENTION  the SAME student issued by W workers at one start instant. Proves the seat rule is the server's and
 *               not the caller's: exactly one row survives, the rest are refused, and — the part a desk never sees —
 *               the losers leave no orphan `enrollmentworkflow` row behind, because the failed insert rolls the
 *               whole transaction back.
 *   CANCEL      one child drops a student's record through the state machine while another issues for the same seat.
 *               Either order is legal — drop first and the re-enrollment wins, issue first and it is refused — so
 *               what is asserted is not who won but that nobody ever holds two active seats.
 *
 * The children call the service rather than the HTTP stack and say so, for the reason lane168 records: PHP's
 * built-in server handles one request at a time, so an "HTTP race" leg would serialise and read as no race.
 *
 * It never touches live `ems`: the target schema is matched, a data-only clone is taken, and the source's own row
 * counts are read before and after the clone and compared. A child that prints no verdict, a tally that does not
 * reconcile with the rows, or a population the clone cannot stage is a REFUSED run with exit 1, never a clean one.
 *
 * `--verify-only` runs the SAME integrity queries against a schema that already exists, without cloning, staging or
 * writing. That mode exists so the queries can be falsified: `mutate-lane172-load-integrity.php` damages a copy of
 * a kept schema (drops the seat key and plants a duplicate, deletes a form, adds an orphan) and requires this tool
 * to name each defect. A checker that has never printed a failure is not trusted, and one definition of the queries
 * serves both paths.
 *
 * Usage: php storage/app/scratch/lane172-enrollment-day-load.php
 *            [--requests=1000] [--workers=10] [--schema=ems_l172_a] [--keep] [--skip-contention]
 *            [--verify-only --schema=<name> --term=<id> [--contended=<studentId>]]
 */

// Declared before the shutdown hook that reads it by reference: a hook that closes over an unset variable reports
// "no verdict" about a run that reached one.
$reported = false;

$argvMap = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z0-9-]+)(?:=(.*))?$/', $a, $m) === 1) {
        $argvMap[$m[1]] = $m[2] ?? true;

        continue;
    }
    fwrite(STDERR, 'REFUSED: this tool does not understand '.escapeshellarg($a).".\n");

    exit(1);
}

$requests = (int) ($argvMap['requests'] ?? 1000);
$workers = (int) ($argvMap['workers'] ?? 10);
$schema = (string) ($argvMap['schema'] ?? 'ems_l172_a');
$keep = isset($argvMap['keep']);
$skipContention = isset($argvMap['skip-contention']);
$verifyOnly = isset($argvMap['verify-only']);
$contended = isset($argvMap['contended']) ? (int) $argvMap['contended'] : 0;
$forcedTerm = isset($argvMap['term']) ? (int) $argvMap['term'] : 0;
// Each pair is two processes, and a process is a full framework boot, so the ceiling here is the box's memory as
// much as the test's: 10 pairs is 20 live connections, which is already more contention than the seat can absorb.
$pairs = isset($argvMap['pairs']) ? (int) $argvMap['pairs'] : 5;

if ($verifyOnly) {
    // Verify mode reads a schema the caller names, so the load-shaped floor on --requests and the worker count are
    // not its questions; the schema pattern still is, because a damaged copy is still a scratch copy.
    if (preg_match('/^ems_l172_[a-z]$/', $schema) !== 1) {
        fwrite(STDERR, 'REFUSED: --verify-only needs --schema matching ems_l172_[a-z] (got '
            .var_export($schema, true).").\n");

        exit(1);
    }
    if ($forcedTerm <= 0) {
        fwrite(STDERR, "REFUSED: --verify-only needs --term, because it does not stage one and must not guess the"
            ." term whose seats it is counting.\n");

        exit(1);
    }
} else {
    if ($requests < 50 || $requests > 20000) {
        fwrite(STDERR, "REFUSED: --requests must be between 50 and 20000 (got {$requests}). Below 50 the run is a demo, not a load.\n");

        exit(1);
    }
    if ($workers < 2 || $workers > 32) {
        fwrite(STDERR, "REFUSED: --workers must be between 2 and 32 (got {$workers}).\n");

        exit(1);
    }
    if ($pairs < 0 || $pairs > 10) {
        fwrite(STDERR, "REFUSED: --pairs must be between 0 and 10 (got {$pairs}); each pair is two framework boots.\n");

        exit(1);
    }
    if (preg_match('/^ems_l172_[a-z]$/', $schema) !== 1) {
        fwrite(STDERR, 'REFUSED: --schema must match ems_l172_[a-z] (got '.var_export($schema, true)
            ."). This tool stages a load population and must never be pointed at a database it was not built for.\n");

        exit(1);
    }
}
$known = ['requests', 'workers', 'schema', 'keep', 'skip-contention', 'verify-only', 'contended', 'term', 'pairs'];
if ($unknown = array_values(array_diff(array_keys($argvMap), $known))) {
    fwrite(STDERR, 'REFUSED: flag(s) not read by this tool: '.implode(', ', array_map(static fn ($k) => '--'.$k, $unknown)).".\n");

    exit(1);
}

$root = dirname(__DIR__, 3);
$env = [];
foreach (file($root.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (preg_match('/^([A-Z_][A-Z0-9_]*)=(.*)$/', $line, $m) === 1) {
        $env[$m[1]] = trim($m[2], " \"'");
    }
}
$source = $env['DB_DATABASE'] ?? 'ems';
if ($source === $schema) {
    fwrite(STDERR, "REFUSED: .env names {$source}, which is the scratch pattern this tool writes into.\n");

    exit(1);
}
$bin = 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin';
[$mysqldump, $mysql, $php] = [$bin.'/mysqldump.exe', $bin.'/mysql.exe', 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe'];
foreach ([$mysqldump, $mysql, $php] as $exe) {
    if (! is_file($exe)) {
        fwrite(STDERR, "REFUSED: {$exe} is not on this box.\n");

        exit(1);
    }
}
$auth = '-u '.($env['DB_USERNAME'] ?? 'root').(($env['DB_PASSWORD'] ?? '') !== '' ? ' -p'.$env['DB_PASSWORD'] : '');

$pdo = new PDO('mysql:host='.($env['DB_HOST'] ?? '127.0.0.1').';port='.($env['DB_PORT'] ?? '3306').';charset=utf8mb4',
    $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '');

$scalar = static function (string $sql) use ($pdo): int {
    $row = $pdo->query($sql);
    if ($row === false) {
        fwrite(STDERR, "REFUSED: the server would not answer this query:\n  ".substr($sql, 0, 200)."\n");

        exit(1);
    }

    return (int) $row->fetchColumn();
};

/**
 * The integrity questions, in ONE place, so a run that loaded rows and a run that only reads a schema ask the
 * server exactly the same thing. Every metric must be able to leave zero: the harness damages a copy and requires
 * each of these to come back named.
 *
 * @return array<string, int>
 */
$weigh = static function (string $schema, int $term, int $contended) use ($scalar): array {
    return [
        'load-students' => $scalar("SELECT COUNT(*) FROM `{$schema}`.students WHERE schoolIdNumber LIKE 'LOAD-%'"),
        'load-enrollments' => $scalar("SELECT COUNT(*) FROM `{$schema}`.enrollments e"
            ." JOIN `{$schema}`.students s ON s.studentId = e.studentId WHERE s.schoolIdNumber LIKE 'LOAD-%'"),
        'dup-seats' => $scalar("SELECT COUNT(*) FROM (SELECT e.studentId, e.termId FROM `{$schema}`.enrollments e"
            ." JOIN `{$schema}`.students s ON s.studentId = e.studentId WHERE s.schoolIdNumber LIKE 'LOAD-%'"
            ." AND e.enrollmentStatus <> 'dropped' GROUP BY e.studentId, e.termId HAVING COUNT(*) > 1) d"),
        'orphan-forms' => $scalar("SELECT COUNT(*) FROM `{$schema}`.enrollmentworkflow w"
            ." LEFT JOIN `{$schema}`.enrollments e ON e.enrollmentId = w.enrollmentId WHERE e.enrollmentId IS NULL"),
        'acts-without-form' => $scalar("SELECT COUNT(*) FROM `{$schema}`.enrollments e"
            ." JOIN `{$schema}`.students s ON s.studentId = e.studentId WHERE s.schoolIdNumber LIKE 'LOAD-%'"
            ." AND NOT EXISTS (SELECT 1 FROM `{$schema}`.enrollmentworkflow w WHERE w.enrollmentId = e.enrollmentId)"),
        'records-with-two-forms' => $scalar("SELECT COUNT(*) FROM (SELECT w.enrollmentId FROM `{$schema}`.enrollmentworkflow w"
            ." JOIN `{$schema}`.enrollments e ON e.enrollmentId = w.enrollmentId"
            ." JOIN `{$schema}`.students s ON s.studentId = e.studentId WHERE s.schoolIdNumber LIKE 'LOAD-%'"
            .' GROUP BY w.enrollmentId HAVING COUNT(*) > 1) d'),
        // Mirrors AuditRecordStates::blockOverCapacity() on the clone. The creation act writes no subject line, so
        // the only honest reading of this column is "unchanged"; the capacity race is lane168's act and says so.
        'blocks-over-capacity' => $scalar("SELECT COUNT(*) FROM (SELECT es.blockId, b.maxStudents"
            ." FROM `{$schema}`.enrolledsubjects es JOIN `{$schema}`.blocks b ON b.blockId = es.blockId"
            ." WHERE es.status <> 'dropped' GROUP BY es.blockId, b.maxStudents"
            .' HAVING COUNT(DISTINCT es.enrollmentId) > b.maxStudents) x'),
        'contended-seat-rows' => $contended > 0 ? $scalar("SELECT COUNT(*) FROM `{$schema}`.enrollments"
            ." WHERE studentId = {$contended} AND termId = {$term} AND enrollmentStatus <> 'dropped'") : -1,
    ];
};

if ($verifyOnly) {
    $m = $weigh($schema, $forcedTerm, $contended);
    $problems = [];
    foreach (['dup-seats', 'orphan-forms', 'acts-without-form', 'records-with-two-forms', 'blocks-over-capacity'] as $k) {
        if ($m[$k] !== 0) {
            $problems[] = "{$k} = {$m[$k]}";
        }
    }
    if ($contended > 0 && $m['contended-seat-rows'] > 1) {
        $problems[] = "contended-seat-rows = {$m['contended-seat-rows']}";
    }
    $reported = true;
    echo "verify-only against {$schema} (term {$forcedTerm}".($contended > 0 ? ", contended student {$contended}" : '').")\n";
    foreach ($m as $k => $v) {
        printf("  %-28s %s\n", $k, $v < 0 ? 'not asked' : $v);
    }
    if ($problems !== []) {
        echo "\nINTEGRITY DEFECTS FOUND:\n  - ".implode("\n  - ", $problems)."\n";

        exit(1);
    }
    echo "\nINTEGRITY CLEAN on this schema for the questions asked.\n";

    exit(0);
}

// The source is weighed before and after the clone: a data-only dump must not quietly become an act of writing.
$sourceStudents = $scalar("SELECT COUNT(*) FROM `{$source}`.students");
$sourceEnrollments = $scalar("SELECT COUNT(*) FROM `{$source}`.enrollments");

$dumpFile = $root.'/storage/app/scratch/lane172-clone.sql';
$o = [];
exec(escapeshellarg($mysqldump)." {$auth} --no-create-db --single-transaction ".escapeshellarg($source)
    .' > '.escapeshellarg($dumpFile).' 2>&1', $o, $code);
if ($code !== 0 || ! is_file($dumpFile) || filesize($dumpFile) < 1024) {
    fwrite(STDERR, "REFUSED: the data dump did not land (exit {$code}).\n");

    exit(1);
}
$dumpText = (string) file_get_contents($dumpFile);
if (str_contains($dumpText, 'DROP DATABASE') || str_contains($dumpText, 'CREATE DATABASE')) {
    fwrite(STDERR, "REFUSED: the clone dump carries DROP/CREATE DATABASE — it would act on {$source} itself.\n");

    exit(1);
}
if (! str_contains($dumpText, 'INSERT INTO `students`')) {
    fwrite(STDERR, "REFUSED: the clone dump carries no students rows, so there is no population to stage.\n");

    exit(1);
}

register_shutdown_function(static function () use (&$reported, $keep, $schema, $mysql, $auth, $dumpFile): void {
    if (is_file($dumpFile)) {
        @unlink($dumpFile);
    }
    if (! $keep && $schema !== '') {
        exec(escapeshellarg($mysql)." {$auth} -e ".escapeshellarg("DROP DATABASE IF EXISTS `{$schema}`").' 2>&1');
    }
    if ($reported !== true) {
        fwrite(STDERR, "\nREFUSED: this run reached no verdict. The scratch schema was ".($keep ? 'left in place.' : 'dropped.'));
    }
});

exec(escapeshellarg($mysql)." {$auth} -e ".escapeshellarg("DROP DATABASE IF EXISTS `{$schema}`").' 2>&1');
exec(escapeshellarg($mysql)." {$auth} -e ".escapeshellarg("CREATE DATABASE `{$schema}` CHARACTER SET utf8mb4").' 2>&1', $c, $code);
if ($code !== 0) {
    fwrite(STDERR, "REFUSED: could not create {$schema} (exit {$code}).\n");

    exit(1);
}
exec(escapeshellarg($mysql)." {$auth} ".escapeshellarg($schema).' < '.escapeshellarg($dumpFile).' 2>&1', $r, $code);
if ($code !== 0) {
    fwrite(STDERR, "REFUSED: the clone into {$schema} exited {$code}\n  ".implode("\n  ", array_slice($r, 0, 6))."\n");

    exit(1);
}

$afterStudents = $scalar("SELECT COUNT(*) FROM `{$source}`.students");
$afterEnrollments = $scalar("SELECT COUNT(*) FROM `{$source}`.enrollments");
if ($afterStudents !== $sourceStudents || $afterEnrollments !== $sourceEnrollments) {
    fwrite(STDERR, "REFUSED: live `{$source}` moved across the clone (students {$sourceStudents} -> {$afterStudents},"
        ." enrollments {$sourceEnrollments} -> {$afterEnrollments}). The source was touched; stopping.\n");

    exit(1);
}
echo "clone {$schema} from {$source}: {$sourceStudents} students, {$sourceEnrollments} enrollments, source unchanged\n";

// One term and one program the clone really holds, and the desk account the issuer stamps as evaluator.
$term = $scalar("SELECT termId FROM `{$schema}`.enrollments GROUP BY termId ORDER BY COUNT(*) DESC LIMIT 1");
$course = $scalar("SELECT courseId FROM `{$schema}`.enrollments WHERE termId = {$term} GROUP BY courseId ORDER BY COUNT(*) DESC LIMIT 1");
$deskId = $scalar("SELECT userId FROM `{$schema}`.staffusers WHERE officeId = 4 AND status = 'active' ORDER BY userId LIMIT 1");
if ($term === 0 || $course === 0 || $deskId === 0) {
    fwrite(STDERR, "REFUSED: the clone names no busy term/program or no active office-4 account"
        ." (term {$term}, course {$course}, desk {$deskId}).\n");

    exit(1);
}
echo "staged against term {$term}, course {$course}, desk userId {$deskId}\n";

$spawn = static function (array $args) use ($php, $root, $schema): array {
    $cmd = escapeshellarg($php).' '.escapeshellarg($root.'/storage/app/scratch/lane172-issue-child.php');
    foreach ($args as $k => $v) {
        $cmd .= ' --'.$k.'='.escapeshellarg((string) $v);
    }
    $piped = [];
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $piped, $root);
    if (! is_resource($proc)) {
        fwrite(STDERR, "REFUSED: a child would not start ({$args['mode']}).\n");

        exit(1);
    }

    return [$proc, $piped, $cmd, $args];
};

$reap = static function (array $running, string $label): array {
    $rows = [];
    foreach ($running as [$proc, $piped, $cmd]) {
        $out = stream_get_contents($piped[1]);
        $err = stream_get_contents($piped[2]);
        fclose($piped[1]);
        fclose($piped[2]);
        $code = proc_close($proc);
        $flat = trim((string) $out);
        $json = json_decode($flat, true);
        if (! is_array($json) || ! array_key_exists('created', $json)) {
            fwrite(STDERR, "REFUSED: {$label} child printed no verdict (exit {$code}).\n  cmd: {$cmd}\n  stdout: "
                .substr($flat, 0, 400)."\n  stderr: ".substr((string) $err, 0, 400)."\n");

            exit(1);
        }
        $rows[] = $json;
    }

    return $rows;
};

$sum = static fn (array $rows, string $key): int => array_sum(array_map(static fn (array $r) => (int) ($r[$key] ?? 0), $rows));

// The alignment gate is a float microtime, the same shape lane168's children compare against. A child started at
// t+2s waits for the instant rather than beginning the moment its framework finished booting, so the acts overlap.
$startAt = microtime(true) + 2.0;
$per = (int) ceil($requests / $workers);
$running = [];
for ($i = 0; $i < $workers; $i++) {
    $offset = $i * $per;
    $limit = min($per, $requests - $offset);
    if ($limit <= 0) {
        break;
    }
    $running[] = $spawn([
        'schema' => $schema, 'mode' => 'volume', 'offset' => $offset, 'limit' => $limit,
        'term' => $term, 'course' => $course, 'desk' => $deskId, 'start-at' => sprintf('%.6F', $startAt),
    ]);
}
echo 'VOLUME  '.count($running).' workers, '.$requests.' issues, one student each … ';
$t0 = microtime(true);
$volumeRows = $reap($running, 'volume');
$volumeSeconds = round(microtime(true) - $t0, 2);
echo 'created '.$sum($volumeRows, 'created').', refused '.$sum($volumeRows, 'refused')
    , ', error '.$sum($volumeRows, 'errored')." in {$volumeSeconds}s\n";

$contentionRows = [];
$contendedStudent = 0;
if (! $skipContention) {
    // A student who holds no active seat in this term, so the workers really are contending for a seat that is
    // still free. Asked of the server, not assumed: if the clone seats everyone, the phase refuses rather than
    // reporting W refusals as "the key held" when nothing was ever in play.
    $contendedStudent = $scalar("SELECT s.studentId FROM `{$schema}`.students s"
        ." LEFT JOIN `{$schema}`.enrollments e ON e.studentId = s.studentId AND e.termId = {$term}"
        ." AND e.enrollmentStatus <> 'dropped' WHERE e.enrollmentId IS NULL ORDER BY s.studentId LIMIT 1");
    if ($contendedStudent === 0) {
        fwrite(STDERR, "REFUSED: every student in the clone already holds a seat in term {$term},"
            ." so contention mode has no free seat to contend for. Name another term or pass --skip-contention.\n");

        exit(1);
    }
    $cStart = microtime(true) + 2.0;
    $running = [];
    for ($i = 0; $i < $workers; $i++) {
        $running[] = $spawn([
            'schema' => $schema, 'mode' => 'contention', 'student' => $contendedStudent,
            'term' => $term, 'course' => $course, 'desk' => $deskId, 'start-at' => sprintf('%.6F', $cStart),
        ]);
    }
    echo 'CONTENTION '.count($running).' workers, student '.$contendedStudent.', one seat … ';
    $c0 = microtime(true);
    $contentionRows = $reap($running, 'contention');
    echo 'created '.$sum($contentionRows, 'created').', refused '.$sum($contentionRows, 'refused')
        , ', error '.$sum($contentionRows, 'errored')." in ".round(microtime(true) - $c0, 2)."s\n";
}

// ── phase 3: cancellation competing with enrollment (prompt §40's fourth simulation) ──────────────────────
// One child drops the student's record through the state machine while another issues a new one for the same seat,
// at the same instant. Both orders are legal — the drop first and the re-enrollment wins, the issue first and it is
// refused — and neither is the defect. The defect would be two active seats, or zero rows where a form still points
// at one, so that is what is asserted.
$cancelRows = [];
$pairedStudents = [];
$cancelOutcome = ['one' => 0, 'none' => 0, 'many' => 0];
if ($pairs > 0) {
    // Staging, not the act under test. The state machine refuses a drop of a `pending` record — that is the rule
    // that a desk may not cancel what the department still holds, and this tool met it on its first run, when the
    // paired children reported `InvalidStateTransitionException` because the issuer leaves every new record
    // pending. A Registrar drops from the assessed load upward, so the pair's row is moved to `enrolled` here by
    // direct UPDATE (no history row is written, because the point is the state, not the audit trail) and the race
    // that follows is the one the guide asks about.
    $rowset = $pdo->query("SELECT e.enrollmentId, e.studentId FROM `{$schema}`.enrollments e"
        ." JOIN `{$schema}`.students s ON s.studentId = e.studentId"
        ." WHERE s.schoolIdNumber LIKE 'LOAD-%' AND e.termId = {$term} AND e.enrollmentStatus = 'pending'"
        .' ORDER BY e.enrollmentId LIMIT '.(int) $pairs)->fetchAll(PDO::FETCH_ASSOC);
    if (count($rowset) < $pairs) {
        fwrite(STDERR, 'REFUSED: --pairs asked for '.(int) $pairs
            ." cancellation races but the clone holds only ".count($rowset)." load records to stage.\n");

        exit(1);
    }
    foreach ($rowset as $r) {
        $pdo->exec("UPDATE `{$schema}`.enrollments SET enrollmentStatus = 'enrolled'"
            .' WHERE enrollmentId = '.(int) $r['enrollmentId']);
    }
    $xStart = microtime(true) + 2.0;
    $running = [];
    foreach ($rowset as $r) {
        $pairedStudents[] = (int) $r['studentId'];
        $running[] = $spawn(['schema' => $schema, 'mode' => 'cancel', 'enrollment' => (int) $r['enrollmentId'],
            'limit' => 1, 'term' => $term, 'course' => $course, 'desk' => $deskId, 'start-at' => sprintf('%.6F', $xStart)]);
        $running[] = $spawn(['schema' => $schema, 'mode' => 'contention', 'student' => (int) $r['studentId'],
            'term' => $term, 'course' => $course, 'desk' => $deskId, 'start-at' => sprintf('%.6F', $xStart)]);
    }
    echo 'CANCEL-VS-ENROLL '.count($rowset).' pairs, one drop and one issue each … ';
    $x0 = microtime(true);
    $cancelRows = $reap($running, 'cancel-pair');
    echo 'cancelled '.$sum($cancelRows, 'cancelled').', created '.$sum($cancelRows, 'created')
        .', refused '.$sum($cancelRows, 'refused').', error '.$sum($cancelRows, 'errored')
        .' in '.round(microtime(true) - $x0, 2)."s\n";

    $inList = implode(',', array_map('intval', $pairedStudents));
    $perStudent = $pdo->query("SELECT studentId, COUNT(*) held FROM `{$schema}`.enrollments"
        ." WHERE studentId IN ({$inList}) AND termId = {$term} AND enrollmentStatus <> 'dropped'"
        .' GROUP BY studentId')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($perStudent as $p) {
        $held = (int) $p['held'];
        if ($held === 1) {
            $cancelOutcome['one']++;
        } else {
            $cancelOutcome['many']++;
        }
    }
    $cancelOutcome['none'] = count($pairedStudents) - count($perStudent);
}

// Everything below is asked of the SERVER, not of the children's arithmetic.
$m = $weigh($schema, $term, $contendedStudent);

$codes = [];
foreach (array_merge($volumeRows, $contentionRows, $cancelRows) as $r) {
    foreach ((array) ($r['codes'] ?? []) as $c => $n) {
        $codes[(string) $c] = ($codes[(string) $c] ?? 0) + (int) $n;
    }
}
ksort($codes);

// Prompt §40 asks for response times and for "database connection behaviour", so both are read rather than
// described: the per-request wall time each worker reports, and what the server says about its own session and
// thread counts at the moment the run finishes. These are measurements of this run, not statements of policy.
$perRequest = [];
foreach ($volumeRows as $r) {
    $n = max(1, (int) ($r['limit'] ?? 1));
    $perRequest[] = round(((float) ($r['ms'] ?? 0)) / $n, 1);
}
sort($perRequest);
$p50 = $perRequest === [] ? 0.0 : $perRequest[(int) floor(count($perRequest) / 2)];
$threads = [];
foreach (['Threads_connected', 'Threads_running', 'Max_used_connections'] as $v) {
    $row = $pdo->query('SHOW GLOBAL STATUS LIKE '.$pdo->quote($v))->fetch(PDO::FETCH_ASSOC);
    $threads[$v] = $row === false ? 'not reported' : (string) $row['Value'];
}
$isolation = (string) $pdo->query('SELECT @@transaction_isolation')->fetchColumn();
$lockWait = (string) $pdo->query('SELECT @@innodb_lock_wait_timeout')->fetchColumn();

$problems = [];
if ($m['load-students'] !== $requests) {
    $problems[] = "the clone holds {$m['load-students']} LOAD- students; the run was asked for {$requests}";
}
// The load-scoped rows are the volume phase's creations plus any re-enrollment the cancel phase won: a drop that
// lands before its paired issue releases the seat, and then the issue succeeds. The contention winner is a
// pre-existing student and is weighed by its own seat count, so it is deliberately not in this sum.
if ($sum($volumeRows, 'created') + $sum($cancelRows, 'created') !== $m['load-enrollments']) {
    $problems[] = 'the workers report '.$sum($volumeRows, 'created').' issues plus '
        .$sum($cancelRows, 'created')." re-enrollments while the server holds {$m['load-enrollments']}"
        .' LOAD-scoped enrollments';
}
if ($sum($volumeRows, 'errored') + $sum($contentionRows, 'errored') + $sum($cancelRows, 'errored') > 0) {
    $sick = array_values(array_filter(
        array_merge($volumeRows, $contentionRows, $cancelRows),
        static fn (array $r) => (int) ($r['errored'] ?? 0) > 0
    ));
    $problems[] = 'a child reported an error that was not a driver refusal ('.count($sick).' child(ren)): '
        .implode(' | ', array_slice(array_map(static fn (array $r) => (string) ($r['mode'] ?? '?').':'
            .($r['detail'] ?? 'no detail'), $sick), 0, 4));
}
if ($cancelOutcome['many'] !== 0) {
    $problems[] = "{$cancelOutcome['many']} student(s) hold more than one active seat after a drop raced an"
        .' issue — the invariant a cancellation must never break';
}
if ($cancelRows !== [] && $sum($cancelRows, 'cancelled') + $sum($cancelRows, 'refused') + $sum($cancelRows, 'created') === 0) {
    $problems[] = 'the cancel-vs-enroll phase reported no outcome at all on '.count($pairedStudents).' paired students';
}
foreach (['dup-seats' => 'student/term pair(s) hold more than one active seat — the seat key did not hold under load',
    'orphan-forms' => 'workflow form(s) name no enrollment — a rolled-back act left a child row behind',
    'acts-without-form' => 'load enrollment(s) have no workflow form — a committed act was incomplete',
    'records-with-two-forms' => 'load enrollment(s) carry more than one form — one record was built twice',
    'blocks-over-capacity' => 'block(s) hold more students than they declare'] as $k => $why) {
    if ($m[$k] !== 0) {
        $problems[] = "{$m[$k]} {$why}";
    }
}
if ($contendedStudent > 0 && $m['contended-seat-rows'] > 1) {
    $problems[] = "the contended student holds {$m['contended-seat-rows']} active seats; exactly one may exist";
}
if ($contentionRows !== [] && $sum($contentionRows, 'created') !== 1) {
    $problems[] = 'the contention phase created '.$sum($contentionRows, 'created')." rows for one student's one seat";
}
if ($contentionRows !== [] && $sum($contentionRows, 'refused') !== count($contentionRows) - 1) {
    $problems[] = 'the contention phase refused '.$sum($contentionRows, 'refused').' of '.count($contentionRows)
        .' workers; one winner implies '.(count($contentionRows) - 1).' refusals';
}

printf("  %-38s %s\n", 'students staged', $m['load-students']);
printf("  %-38s %s\n", 'enrollments the server holds', $m['load-enrollments']);
printf("  %-38s %s\n", 'duplicate active seats', $m['dup-seats']);
printf("  %-38s %s\n", 'orphan workflow forms', $m['orphan-forms']);
printf("  %-38s %s\n", 'committed acts without a form', $m['acts-without-form']);
printf("  %-38s %s\n", 'records with two forms', $m['records-with-two-forms']);
printf("  %-38s %s (this act places no subject line)\n", 'blocks over declared capacity', $m['blocks-over-capacity']);
printf("  %-38s %s\n", 'contended seat rows', $m['contended-seat-rows'] < 0 ? 'phase skipped' : $m['contended-seat-rows']);
if ($cancelRows !== []) {
    printf("  %-38s %d left one active, %d left none, %d left more than one\n",
        'drop raced an issue', $cancelOutcome['one'], $cancelOutcome['none'], $cancelOutcome['many']);
}
printf("  %-38s %.1f per second over {$volumeSeconds}s\n", 'throughput', $requests / max($volumeSeconds, 0.01));
printf("  %-38s median %s ms, slowest %s ms, per request\n", 'response time', $p50, ($perRequest === [] ? 0 : max($perRequest)));
printf("  %-38s %s\n", 'session isolation', $isolation);
printf("  %-38s %s s\n", 'innodb_lock_wait_timeout', $lockWait);
foreach ($threads as $v => $val) {
    printf("  %-38s %s\n", strtolower($v), $val);
}
echo '  '.str_repeat('-', 66)."\n";
foreach ($codes as $c => $n) {
    printf("  %-38s %d\n", 'driver '.$c, $n);
}

$reported = true;

if ($problems !== []) {
    echo "\nLOAD INTEGRITY FAILED:\n";
    foreach ($problems as $p) {
        echo '  - '.$p."\n";
    }
    echo $keep ? "schema {$schema} left in place for reading.\n" : "schema {$schema} dropped.\n";

    exit(1);
}

echo "\nLOAD INTEGRITY HELD: {$requests} issues through the act every desk routes through, across ".count($volumeRows)
    ." real processes; the server kept one active seat per student per term, every committed enrollment carries"
    ." exactly one workflow form, no form outlived a rolled-back act, and no block passed its own maxStudents."
    .($contentionRows === [] ? '' : ' Contention on one seat gave exactly one winner and '.(count($contentionRows) - 1).' refusals.')
    ."\nCapacity under concurrent writers is lane168's own act; this run weighs the creation path, and says so.\n";
echo $keep ? "schema {$schema} left in place for reading (--keep).\n" : "schema {$schema} dropped.\n";

exit(0);
