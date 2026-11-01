<?php

/**
 * lane190 — the subject-line race, measured with two writers on one MySQL server (ADS guide problem 10, ¶131).
 *
 * P10 has been recorded as "Partly — the race itself is undriven" since the matrix was written. The pieces the
 * guide names already exist: `uq_enrollment_subject_attempt` on enrolledsubjects (enrollmentId, subjectId,
 * attempt_number) is a real server key, and `proposeSubjects()` runs a delete-then-insert inside a transaction.
 * What has never existed is a measurement of two of those acts overlapping, which is the only thing that says
 * whether the key and the transaction are enough or whether the desk meets a 500.
 *
 *   pass 1 — N pairs of children, each calling the REAL `EvaluationController::proposeSubjects()` for ONE
 *            enrollment with ONE identical payload, released at the same instant.
 *
 * A pair is SAFE when every child ends either proposed or refused or deadlock-retried, and the enrollment is left
 * holding exactly the payload's own row count in `evaluated` status. It is UNSAFE when a row set vanishes (one
 * writer deleted the other's lines), when the count disagrees with the payload, or when a driver error reaches the
 * caller as an uncaught exception — that last one is a 500 at the desk, not a refusal.
 *
 * Everything is written on a scratch clone; live `ems` is only ever read, and its student count is re-taken after
 * the clone so a stray write to the source is named rather than assumed.
 *
 * Usage: php storage/app/scratch/lane190-probe-subject-line-race.php [--schema=ems_l190_a] [--pairs=3] [--keep]
 */
$root = dirname(__DIR__, 3);

$argvMap = [];
foreach (array_slice($GLOBALS['argv'], 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) {
        $argvMap[$m[1]] = $m[2] ?? true;
    } else {
        fwrite(STDERR, 'REFUSED: this tool does not understand '.escapeshellarg($a).".\n");
        exit(1);
    }
}

$schema = $argvMap['schema'] ?? 'ems_l190_a';
$keep = isset($argvMap['keep']);
$recreate = isset($argvMap['recreate']);
$pairs = (int) ($argvMap['pairs'] ?? 3);

if ($pairs < 1 || $pairs > 20) {
    fwrite(STDERR, 'REFUSED: --pairs must be 1 to 20 (got '.$pairs."). A pair is two live writers; this is not a load test.\n");
    exit(1);
}
foreach (['schema', 'keep', 'recreate', 'pairs'] as $known) {
    unset($argvMap[$known]);
}
if ($argvMap !== []) {
    fwrite(STDERR, 'REFUSED: flag(s) not read by this tool: '.implode(', ', array_map(fn ($k) => '--'.$k, array_keys($argvMap))).".\n");
    exit(1);
}
if (preg_match('/^ems_l190_[a-z]$/', (string) $schema) !== 1) {
    fwrite(STDERR, 'REFUSED: --schema must match ems_l190_[a-z] (got '.var_export($schema, true)
        ."). A concurrent write may only be exercised on a scratch schema a later run retires by name.\n");
    exit(1);
}

$bin = 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin';
[$mysqldump, $mysql] = [$bin.'/mysqldump.exe', $bin.'/mysql.exe'];
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
foreach ([$mysqldump, $mysql, $php] as $exe) {
    if (! is_file($exe)) {
        fwrite(STDERR, "REFUSED: {$exe} is not on this box.\n");
        exit(1);
    }
}

$auth = '-u root';
$env = [];
foreach (file($root.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (preg_match('/^([A-Z0-9_]+)=(.*)$/', trim($line), $m)) {
        $env[$m[1]] = $m[2];
    }
}
$user = $env['DB_USERNAME'] ?? 'root';
$pass = $env['DB_PASSWORD'] ?? '';
$source = 'ems';

$src = new PDO('mysql:host=127.0.0.1;dbname='.$source, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sourceRowsBefore = (int) $src->query('select count(*) from students')->fetchColumn();

// The key this probe measures must actually exist on the source, or the run would be describing a schema the
// project does not have. Read it from the server rather than recalling it.
$keyCols = $src->query("select GROUP_CONCAT(COLUMN_NAME order by SEQ_IN_INDEX) c from information_schema.STATISTICS
    where TABLE_SCHEMA='{$source}' and TABLE_NAME='enrolledsubjects' and INDEX_NAME='uq_enrollment_subject_attempt'")->fetchColumn();
if (! is_string($keyCols) || $keyCols === '') {
    fwrite(STDERR, "REFUSED: `{$source}` holds no uq_enrollment_subject_attempt, so there is no server-side rule to drive two writers against.\n");
    exit(1);
}
printf("server: enrolledsubjects holds uq_enrollment_subject_attempt (%s)\n", $keyCols);

$dumpFile = sys_get_temp_dir().'/ems-l190-'.getmypid().'.sql';
exec(escapeshellarg($mysqldump)." {$auth} --single-transaction --no-create-db --routines --triggers {$source} > ".escapeshellarg($dumpFile).' 2>&1', $d, $code);
if ($code !== 0 || ! is_file($dumpFile) || filesize($dumpFile) < 100000) {
    fwrite(STDERR, "REFUSED: the dump of `{$source}` did not land (exit {$code}).\n  ".implode("\n  ", array_slice($d, 0, 4))."\n");
    exit(1);
}
$dumpHead = (string) file_get_contents($dumpFile, false, null, 0, 2000000);
if (stripos($dumpHead, 'DROP DATABASE') !== false) {
    @unlink($dumpFile);
    fwrite(STDERR, "REFUSED: the dump carries a DROP DATABASE. Stop — that is the defect that destroyed live `ems` once.\n");
    exit(1);
}
if (stripos($dumpHead, 'CREATE TABLE `enrolledsubjects`') === false) {
    @unlink($dumpFile);
    fwrite(STDERR, "REFUSED: the file the tool just wrote holds no CREATE TABLE for enrolledsubjects, so it is not a copy of the schema it claims to clone.\n");
    exit(1);
}

exec(escapeshellarg($mysql)." {$auth} -e ".escapeshellarg("CREATE DATABASE `{$schema}` CHARACTER SET utf8mb4").' 2>&1', $c, $code);
if ($code !== 0) {
    // A scratch name this tool owns by pattern is the only thing it will ever drop, and --recreate is the only
    // way to ask: a schema left over from an earlier run is dropped by name, never silently overwritten.
    $exists = (int) $src->query("select count(*) from information_schema.SCHEMATA where SCHEMA_NAME='{$schema}'")->fetchColumn();
    if ($exists === 1 && ! $recreate) {
        fwrite(STDERR, "REFUSED: `{$schema}` already exists — it is the residue of an earlier run. Re-run with --recreate to drop"
            ." and re-clone that one name, or pass --schema=ems_l190_b for a fresh one.\n");
        exit(1);
    }
    if ($exists === 1) {
        exec(escapeshellarg($mysql)." {$auth} -e ".escapeshellarg("DROP DATABASE `{$schema}`").' 2>&1', $dd, $ddc);
        if ($ddc !== 0) {
            fwrite(STDERR, "REFUSED: --recreate could not drop `{$schema}` (exit {\$ddc}).\n");
            exit(1);
        }
        echo "recreated: dropped the scratch schema `{$schema}` this tool owns by name, then re-cloned it.\n";
        exec(escapeshellarg($mysql)." {$auth} -e ".escapeshellarg("CREATE DATABASE `{$schema}` CHARACTER SET utf8mb4").' 2>&1', $c2, $code);
    }
}
if ($code !== 0) {
    fwrite(STDERR, "REFUSED: could not create {$schema} (exit {$code}).\n  ".implode("\n  ", array_slice($c, 0, 3))."\n");
    exit(1);
}
exec(escapeshellarg($mysql)." {$auth} ".escapeshellarg($schema).' < '.escapeshellarg($dumpFile).' 2>&1', $r, $code);
if ($code !== 0) {
    fwrite(STDERR, "REFUSED: the clone into {$schema} exited {$code}\n  ".implode("\n  ", array_slice($r, 0, 6))."\n");
    exit(1);
}
@unlink($dumpFile);

$pdo = new PDO('mysql:host=127.0.0.1;dbname='.$schema, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sourceRowsAfter = (int) $src->query('select count(*) from students')->fetchColumn();
if ($sourceRowsBefore !== $sourceRowsAfter) {
    fwrite(STDERR, "REFUSED: live `{$source}` went from {$sourceRowsBefore} to {$sourceRowsAfter} students across the clone."
        ." Stop and read the source before anything else runs.\n");
    exit(1);
}
printf("cloned: %s students, %s enrollments  (source `%s` verified unchanged at %s students)\n",
    number_format((int) $pdo->query('select count(*) from students')->fetchColumn()),
    number_format((int) $pdo->query('select count(*) from enrollments')->fetchColumn()),
    $source, number_format($sourceRowsAfter));

$desk = $pdo->query("select userId from staffusers where username = 'staff8' and status = 'active' limit 1")->fetchColumn();
if ($desk === false) {
    fwrite(STDERR, "REFUSED: the clone holds no active staff8, so the run would meet a 403 before the race and measure nothing.\n");
    exit(1);
}

/**
 * A payload the controller's own rules will accept, read out of the enrollment's pinned curriculum: every
 * non-elective offered at that year level and semester whose curriculum row names no prerequisite, plus each
 * elective group's minimum. A flexible student (irregular, transferee, shifter) is required, because the
 * mandatory-block rule otherwise insists on the whole non-elective set including subjects behind a prerequisite.
 *
 * Returns [enrollmentId, payload] or null when this enrollment cannot carry the act.
 */
$build = static function (array $e) use ($pdo): ?array {
    $term = $pdo->query('select semester from academicterms where termId = '.(int) $e['termId'])->fetchColumn();
    if (! is_string($term) || $term === '') {
        return null;
    }
    $where = 'curriculumId = '.(int) $e['curriculumId'].' and yearLevel = '.(int) $e['yearLevel']
        ." and semesterOffered = ".$pdo->quote($term);

    $core = $pdo->query("select subjectId from curriculumsubjects where {$where} and is_elective = 0
        and prerequisiteSubjectId is null order by subjectId")->fetchAll(PDO::FETCH_COLUMN);
    if ($core === []) {
        return null;
    }
    $subjects = array_map(static fn ($id) => ['subjectId' => (int) $id], $core);

    $groups = $pdo->query("select elective_group, min(coalesce(elective_min_choices,0)) mn,
            count(*) n from curriculumsubjects where {$where} and is_elective = 1
            and elective_group is not null and elective_group <> ''
            group by elective_group")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($groups as $g) {
        $need = max(0, (int) $g['mn']);
        if ($need === 0) {
            continue;
        }
        $pick = $pdo->query("select subjectId from curriculumsubjects where {$where} and is_elective = 1
            and elective_group = ".$pdo->quote($g['elective_group']).' and prerequisiteSubjectId is null
            order by subjectId limit '.$need)->fetchAll(PDO::FETCH_COLUMN);
        if (count($pick) < $need) {
            return null;
        }
        foreach ($pick as $id) {
            $subjects[] = ['subjectId' => (int) $id];
        }
    }

    return [(int) $e['enrollmentId'], ['subjects' => $subjects]];
};

/**
 * Stage one enrollment for the act: pinned curriculum, flexible student type, `pending` status (the policy's only
 * door besides a return), the desk named as its evaluator, and no proposed rows already on the line.
 */
$stage = static function (int $enrollmentId, int $desk) use ($pdo): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('delete from enrolledsubjects where enrollmentId = '.$enrollmentId." and status = 'proposed'");
    $pdo->exec("update enrollments set enrollmentStatus = 'pending', evaluatedBy = {$desk} where enrollmentId = {$enrollmentId}");
    $pdo->exec('delete from enrollmentstatushistory where enrollmentId = '.$enrollmentId." and toStatus = 'evaluated'");
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    $held = (int) $pdo->query('select count(*) from enrolledsubjects where enrollmentId = '.$enrollmentId
        ." and status = 'proposed'")->fetchColumn();
    if ($held !== 0) {
        fwrite(STDERR, "REFUSED: staging left {$held} proposed row(s) on enrollment {$enrollmentId}; the pair would measure a carry-over.\n");
        exit(1);
    }
};

$candidates = $pdo->query("select e.enrollmentId, e.curriculumId, e.yearLevel, e.termId from enrollments e
    where e.curriculumId is not null
      and (e.academicStanding = 'irregular' or e.studentType in ('transferee','shifter'))
    order by e.enrollmentId")->fetchAll(PDO::FETCH_ASSOC);
if ($candidates === []) {
    fwrite(STDERR, "REFUSED: the clone holds no flexible enrollment with a pinned curriculum, so no payload can be built that the controller's rules accept.\n");
    exit(1);
}

$staged = null;
foreach ($candidates as $c2) {
    $built = $build($c2);
    if ($built === null) {
        continue;
    }
    [$enrollmentId, $payload] = $built;
    $stage($enrollmentId, (int) $desk);
    $staged = [$enrollmentId, $payload];
    break;
}
if ($staged === null) {
    fwrite(STDERR, 'REFUSED: none of the '.count($candidates)." flexible enrollment(s) in the clone could carry an accepted payload"
        ." (every curriculum either has no prerequisite-free subject at its own year level and semester, or an elective group whose minimum cannot be met).\n");
    exit(1);
}
[$enrollmentId, $payload] = $staged;
$n = count($payload['subjects']);
printf("staged: enrollment %s, %d subject line(s) — %s\n", $enrollmentId, $n,
    implode(', ', array_map(static fn ($s) => (string) $s['subjectId'], $payload['subjects'])));

$runPair = static function (string $label) use ($php, $root, $schema, $enrollmentId, $payload, $desk): array {
    $startAt = microtime(true) + 2.0;
    $procs = [];
    $pipes = [];
    foreach (['A', 'B'] as $i => $tag) {
        $cmd = escapeshellarg($php).' '.escapeshellarg($root.'/storage/app/scratch/lane190-propose-child.php')
            .' --schema='.$schema.' --enrollment='.((int) $enrollmentId)
            .' --desk='.((int) $desk)
        // base64, not raw JSON: cmd.exe does not honour the single quotes escapeshellarg wraps in, so a payload
        // full of double quotes arrives mangled (this box's documented trap).
            .' --subjects='.base64_encode(json_encode($payload, JSON_THROW_ON_ERROR))
            .' --tag='.$tag.' --start-at='.sprintf('%.6f', $startAt);
        $procs[$i] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $p, $root);
        if (! is_resource($procs[$i])) {
            fwrite(STDERR, "REFUSED: child {$tag} would not start ({$label}).\n");
            exit(1);
        }
        $pipes[$i] = $p;
    }
    $out = [];
    foreach ($pipes as $i => $p) {
        $stdout = stream_get_contents($p[1]);
        $stderr = stream_get_contents($p[2]);
        fclose($p[1]);
        fclose($p[2]);
        $code = proc_close($procs[$i]);
        $decoded = json_decode(trim((string) $stdout), true);
        if (! is_array($decoded) || ! isset($decoded['status'])) {
            fwrite(STDERR, "REFUSED: child printed no verdict (exit {$code}). stdout=".substr((string) $stdout, 0, 300)
                .' stderr='.substr((string) $stderr, 0, 300)."\n");
            exit(1);
        }
        $decoded['exit'] = $code;
        $out[] = $decoded;
    }
    foreach ($out as $o) {
        printf("  %s pair %s: %s%s  (%s ms, %d proposed rows after, enrollment reads %s)\n",
            $label, $o['tag'], $o['status'], $o['detail'] === null ? '' : ' — '.substr((string) $o['detail'], 0, 160),
            $o['ms'], $o['proposedAfter'], $o['statusAfter']);
    }

    return $out;
};

$counts = ['proposed' => 0, 'refused' => 0, 'deadlock' => 0, 'other_error' => 0, 'forbidden' => 0];
$rowsAfterPair = [];
$unsafe = [];
for ($i = 1; $i <= $pairs; $i++) {
    $stage($enrollmentId, (int) $desk);
    $pair = $runPair('pair '.$i);
    foreach ($pair as $o) {
        if ($o['status'] === 'proposed') {
            $counts['proposed']++;
        } elseif ($o['status'] === 'refused') {
            $counts['refused']++;
        } elseif ($o['status'] === 'forbidden') {
            $counts['forbidden']++;
        } elseif ($o['status'] === 'error' && preg_match('/1213|1205/', (string) $o['detail'])) {
            $counts['deadlock']++;
        } else {
            $counts['other_error']++;
        }
    }
    $rows = (int) $pdo->query('select count(*) from enrolledsubjects where enrollmentId = '.$enrollmentId
        ." and status = 'proposed'")->fetchColumn();
    $status = $pdo->query('select enrollmentStatus from enrollments where enrollmentId = '.$enrollmentId)->fetchColumn();
    $rowsAfterPair[] = $rows;
    printf("  pair %d settles: %d proposed row(s) on the enrollment, status %s\n", $i, $rows, $status);
    if ($counts['forbidden'] > 0) {
        $unsafe[] = 'a child was refused by the POLICY, so the act never ran and the pair measured nothing';
    }
    if ($rows !== $n) {
        $unsafe[] = "pair {$i} left {$rows} proposed row(s) where the payload holds {$n}";
    }
    if ($counts['other_error'] > 0) {
        $unsafe[] = "pair {$i} reached the caller as an uncaught driver error (a 500 at the desk)";
    }
}

$dupes = (int) $pdo->query('select count(*) from (select enrollmentId, subjectId, attempt_number from enrolledsubjects
    where enrollmentId = '.$enrollmentId." and status = 'proposed'
    group by enrollmentId, subjectId, attempt_number having count(*) > 1) t")->fetchColumn();

echo "\n";
printf("children run: %d  proposed: %d  refused: %d  deadlock or lock-timeout: %d  other driver error: %d  policy-refused: %d\n",
    $pairs * 2, $counts['proposed'], $counts['refused'], $counts['deadlock'], $counts['other_error'], $counts['forbidden']);
printf("row counts after each pair: %s   the payload holds %d   duplicate (enrollment,subject,attempt) groups: %d\n",
    implode(', ', $rowsAfterPair), $n, $dupes);

$unsafe = array_values(array_unique($unsafe));
if ($dupes > 0) {
    $unsafe[] = "the server allowed {$dupes} duplicate subject line(s)";
}
// A deadlock is not a refusal. The caller sees a QueryException, which bootstrap/app.php renders as the generic
// "conflicts with information already on file" page — a 500 at the desk, not a message that names the act. So a
// child that ends in 1213/1205 is the defect, exactly as lane168 records for the seat act: the data may still come
// out right while the person who clicked twice gets no answer.
if ($counts['deadlock'] > 0) {
    $unsafe[] = $counts['deadlock'].' of '.($pairs * 2).' writers died on a lock (1213/1205) that reached the caller unretried';
}
if ($unsafe !== []) {
    echo "\nUNSAFE — the subject-line race is not closed at the layer this probe can reach:\n";
    foreach ($unsafe as $u) {
        echo '  - '.$u."\n";
    }
    echo "\nThe scratch schema `{$schema}` is left in place so the pair can be re-read — retire it by name with"
        ." `mysql -e \"DROP DATABASE `{$schema}`\"` or let drop-retired-scratch-databases.php do it once no tracked"
        ." file names it.\n";
    exit(1);
}
echo "\nSAFE: every pair left exactly the payload's own row count, no duplicate line, and no driver error reached the caller.\n";
if (! $keep) {
    exec(escapeshellarg($mysql)." {$auth} -e ".escapeshellarg("DROP DATABASE `{$schema}`").' 2>&1', $dr, $code2);
    echo $code2 === 0 ? "retired: {$schema} dropped.\n" : "NOT RETIRED: {$schema} is still on the server (exit {$code2}).\n";
} else {
    echo "kept: {$schema} — it was asked for by --keep.\n";
}
exit(0);
