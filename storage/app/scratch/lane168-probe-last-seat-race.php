<?php

/**
 * lane168 — the last-seat race, measured with two writers on one MySQL server.
 *
 * ADS guide problems 2, 4, 7 and 10 ask for the race to be *shown*, not described. The Pest suite cannot show it:
 * `enrollmentIds.*` is validated `exists:` so no bad id reaches the loop, SQLite has no row locks, and one
 * connection cannot contend with itself. What SQLite can and does pin is the shape (the count inside the
 * transaction, after the lock) — `tests/Feature/Blocking/BlockCapacityAtomicityTest.php`.
 *
 * This probe does the other half on a scratch clone of live `ems`:
 *
 *   pass 1, working bytes  — two children, one seat, same start instant → exactly one assigns, one is refused
 *                            on `capacity`, and the block never holds more than its own `maxStudents`.
 *   pass 2, HEAD's bytes   — the same pair on the file as it stood before this batch → BOTH assign, and the block
 *                            holds two seats against a declared capacity of one. That is the defect, measured.
 *   pass 3, working bytes  — the file is restored by hash and pass 1 is re-run, so the tree the probe leaves
 *                            behind is the tree it found.
 *
 * A pass 2 that also refuses is reported BAD, not as a clean run: an instrument that cannot produce the defect it
 * names is decoration (this file's rule for a leg that cannot fail), and it would mean the lock is not what closed
 * the race.
 *
 * The children call `assignStudents()` directly rather than going through the HTTP stack, and say so: PHP's
 * built-in server handles one request at a time, so an "HTTP race" would serialise and read as no race.
 *
 * Usage: php storage/app/scratch/lane168-probe-last-seat-race.php [--schema=ems_l168_a] [--keep]
 * Export needed first: git show HEAD:app/Http/Controllers/Blocking/BlockingController.php \
 *   > storage/app/scratch/lane168-head-BlockingController.php
 */
$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';

$argvMap = [];
foreach (array_slice($GLOBALS['argv'], 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) {
        $argvMap[$m[1]] = $m[2] ?? true;
    } else {
        fwrite(STDERR, 'REFUSED: this tool does not understand '.escapeshellarg($a).".\n");
        exit(1);
    }
}

$schema = $argvMap['schema'] ?? 'ems_l168_a';
$keep = isset($argvMap['keep']);
$pairs = (int) ($argvMap['pairs'] ?? 3);
$source = 'ems';

if ($pairs < 1 || $pairs > 20) {
    fwrite(STDERR, 'REFUSED: --pairs must be 1 to 20 (got '.$pairs."). A pair is two live writers; this is not a load test.\n");
    exit(1);
}

foreach (['schema', 'keep', 'pairs'] as $known) {
    unset($argvMap[$known]);
}
if ($argvMap !== []) {
    fwrite(STDERR, 'REFUSED: flag(s) not read by this tool: '.implode(', ', array_map(fn ($k) => '--'.$k, array_keys($argvMap))).".\n");
    exit(1);
}

if (preg_match('/^ems_l168_[a-z]$/', (string) $schema) !== 1) {
    fwrite(STDERR, 'REFUSED: --schema must match ems_l168_[a-z] (got '.var_export($schema, true)
        ."). A concurrent write may only be exercised on a scratch schema a later run retires by name.\n");
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

$auth = '-u root';
$env = [];
foreach (file($root.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $m)) {
        $env[$m[1]] = trim($m[2], '"');
    }
}
if (($env['DB_PASSWORD'] ?? '') !== '') {
    $auth .= ' --password='.$env['DB_PASSWORD'];
}
$user = $env['DB_USERNAME'] ?? 'root';
$pass = $env['DB_PASSWORD'] ?? null;

$target = 'app/Http/Controllers/Blocking/BlockingController.php';
$live = $root.'/app/Http/Controllers/Blocking/BlockingController.php';
$headExport = $root.'/storage/app/scratch/lane168-head-BlockingController.php';
if (! is_file($headExport)) {
    fwrite(STDERR, "REFUSED: no HEAD export at {$headExport}.\n"
        ."  take it first: git show HEAD:{$target} > storage/app/scratch/lane168-head-BlockingController.php\n");
    exit(1);
}
$headBytes = (string) file_get_contents($headExport);
$workingBytes = (string) file_get_contents($live);
if ($headBytes === $workingBytes) {
    fwrite(STDERR, 'REFUSED: the HEAD export is byte-identical to the working file ('.strlen($workingBytes)
        .' bytes). Either this batch is already committed — re-take the export from the commit BEFORE it — or the'
        ." race was never fixed, and this probe cannot tell the two apart.\n");
    exit(1);
}

echo "clone {$source} -> {$schema}\n";
// The source is read before and after, and the run refuses if the two differ. A clone that quietly damaged the
// database it was copied from is the defect this repository already paid for once with a sed-edited dump.
$src = new PDO('mysql:host=127.0.0.1;dbname='.$source, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sourceRowsBefore = (int) $src->query('select count(*) from students')->fetchColumn();
$dumpFile = sys_get_temp_dir().'/lane168-clone-'.getmypid().'.sql';
// Structure AND data: the first cut passed --no-create-info, so the target held no tables at all and the clone
// died on "Table ems_l168_a.academicterms doesn't exist" — a refusal, not a false green, but a whole run lost.
// No --databases, so the dump carries neither CREATE DATABASE nor USE and cannot act on the source.
exec(escapeshellarg($mysqldump)." {$auth} --no-create-db --single-transaction ".escapeshellarg($source)
    .' > '.escapeshellarg($dumpFile).' 2>&1', $o, $code);
if ($code !== 0 || ! is_file($dumpFile) || filesize($dumpFile) < 2048) {
    fwrite(STDERR, "REFUSED: the data dump did not land (exit {$code}).\n");
    exit(1);
}
$restoreDone = false;
// Captured by reference: this file has already met a shutdown hook whose `use ($copies)` saw the array as it was
// when the closure was built — an empty one — and left every planted mutant in the tree after a clean-looking run.
register_shutdown_function(static function () use (&$restoreDone, $live, $workingBytes, $schema, $mysql, $auth, $keep, $dumpFile) {
    @unlink($dumpFile);
    // Only write when the tree actually carries something else: the first run printed "shutdown restored" on an
    // aborted clone that had never swapped the file, which is a claim about a change that did not happen.
    if ($restoreDone !== true && sha1((string) file_get_contents($live)) !== sha1($workingBytes)) {
        file_put_contents($live, $workingBytes);
        echo 'shutdown restored '.basename($live)." (the run exited with HEAD's bytes in the tree)\n";
    }
    if (! $keep) {
        exec(escapeshellarg($mysql)." {$auth} -e ".escapeshellarg("DROP DATABASE IF EXISTS `{$schema}`").' 2>&1');
    }
});
$dumpText = (string) file_get_contents($dumpFile);
if (stripos($dumpText, 'DROP DATABASE') !== false || stripos($dumpText, 'CREATE DATABASE') !== false) {
    fwrite(STDERR, "REFUSED: the clone dump carries DROP DATABASE or CREATE DATABASE — it would act on {$source} itself.\n");
    exit(1);
}
if (! str_contains($dumpText, 'INSERT INTO `enrollments`')) {
    fwrite(STDERR, "REFUSED: the clone dump carries no enrollments rows, so there is nothing to race over.\n");
    exit(1);
}

exec(escapeshellarg($mysql)." {$auth} -e ".escapeshellarg("DROP DATABASE IF EXISTS `{$schema}`").' 2>&1', $d, $code);
if ($code !== 0) {
    fwrite(STDERR, "REFUSED: could not drop the previous scratch schema (exit {$code})\n  ".implode("\n  ", $d)."\n");
    exit(1);
}
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

$pdo = new PDO('mysql:host=127.0.0.1;dbname='.$schema, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sourceRowsAfter = (int) $src->query('select count(*) from students')->fetchColumn();
if ($sourceRowsAfter !== $sourceRowsBefore) {
    fwrite(STDERR, "REFUSED: live `{$source}` went from {$sourceRowsBefore} to {$sourceRowsAfter} students across the clone."
        ." Stop and read the source before anything else runs.\n");
    exit(1);
}
printf("cloned: %s students, %s enrollments, %s blocks  (source `%s` verified unchanged at %s students)\n",
    number_format((int) $pdo->query('select count(*) from students')->fetchColumn()),
    number_format((int) $pdo->query('select count(*) from enrollments')->fetchColumn()),
    number_format((int) $pdo->query('select count(*) from blocks')->fetchColumn()),
    $source, number_format($sourceRowsAfter));

// ---- the stage the two writers share -----------------------------------------------------------------
// One block with one seat, one schedule under it, and two enrollments whose next pending box is Blocking.
// `officeId` 5 is read through the enum's value rather than typed: the id belongs to App\Enums\OfficeId.
$blocking = 5;
$block = $pdo->query('select b.blockId, b.courseId, b.termId, b.yearLevel, s.scheduleId, s.subjectId, s.roomId
    from blocks b join schedules s on s.blockId = b.blockId
    order by b.blockId limit 1')->fetch(PDO::FETCH_ASSOC);
if (! $block) {
    fwrite(STDERR, "REFUSED: the clone holds no block with a schedule — the desk cannot assign without one.\n");
    exit(1);
}
$desk = $pdo->query("select userId from staffusers where officeId = {$blocking} and status = 'active' limit 1")->fetchColumn();
if ($desk === false) {
    fwrite(STDERR, "REFUSED: no active blocking-desk account in the clone, so authorize() would 403 before the race.\n");
    exit(1);
}

// Two enrollments in the block's own course, term and year level, each with a live subject line whose subject is
// the schedule's — the row the scheduleId stamp is allowed to touch.
$candidates = $pdo->query("select e.enrollmentId from enrollments e
    where e.courseId = {$block['courseId']} and e.termId = {$block['termId']} and e.yearLevel = {$block['yearLevel']}
      and e.enrollmentStatus <> 'dropped'
      and exists (select 1 from enrolledsubjects es where es.enrollmentId = e.enrollmentId
                   and es.subjectId = {$block['subjectId']} and es.status <> 'dropped')
    order by e.enrollmentId limit 2")->fetchAll(PDO::FETCH_COLUMN);
if (count($candidates) < 2) {
    fwrite(STDERR, 'REFUSED: only '.count($candidates)." enrollment(s) in the clone could take this seat; the race needs two writers.\n");
    exit(1);
}

/** Put the block back to exactly one free seat and both candidates back to a pending, unsigned Blocking box.
 * `enrollmentworkflow` is singular and neither it nor `workflowsteps` carries timestamps or remarks — read from
 * information_schema above, not recalled, because a guessed column is a 1054 in a child whose stderr the parent
 * would only see as "no verdict". */
$stage = static function () use ($pdo, $block, $candidates): void {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('update blocks set maxStudents = 1 where blockId = '.(int) $block['blockId']);
    // Every row holding this block is released, not only the two candidates: the clone carries a demo block that
    // already had a student in it, and a stage that leaves a seat taken measures a block with zero seats left
    // rather than one with one — both children would then be refused and the run would read as a clean fix.
    $pdo->exec('update enrolledsubjects set blockId = null, scheduleId = null where blockId = '.(int) $block['blockId']
        .' or enrollmentId in ('.implode(',', array_map('intval', $candidates)).')');

    foreach ($candidates as $enrollmentId) {
        $workflow = $pdo->query('select workflowId from enrollmentworkflow where enrollmentId = '.(int) $enrollmentId
            .' limit 1')->fetchColumn();
        if ($workflow === false) {
            $pdo->exec('insert into enrollmentworkflow (enrollmentId, currentStep, workflowStatus) values ('
                .(int) $enrollmentId.", 4, 'in_progress')");
            $workflow = $pdo->lastInsertId();
        }
        $pdo->exec('delete from workflowsteps where workflowId = '.(int) $workflow);
        // Steps in the first-year order; every box ahead of Blocking is closed, Blocking itself is pending.
        foreach ([[4, 1], [3, 2], [2, 3], [1, 4], [5, 5], [11, 6], [22, 7]] as [$officeId, $order]) {
            $pdo->exec('insert into workflowsteps (workflowId, officeId, stepOrder, stepStatus, signedBy, signedDate) values ('
                .(int) $workflow.', '.(int) $officeId.', '.$order.', '.($order <= 4 ? "'completed'" : "'pending'")
                .($order <= 4 ? ', (select userId from staffusers where officeId = 4 limit 1), NOW()' : ', null, null').')');
        }
        $pdo->exec("update enrollments set enrollmentStatus = 'enrolled' where enrollmentId = ".(int) $enrollmentId);
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
};

$seats = static fn (): int => (int) $pdo->query('select count(distinct enrollmentId) from enrolledsubjects where blockId = '
    .(int) $block['blockId']." and status <> 'dropped'")->fetchColumn();

/** Run the two writers as one aligned pair and return each child's own JSON verdict. */
$runPair = static function (string $label) use ($php, $root, $schema, $block, $candidates, $desk): array {
    $startAt = microtime(true) + 2.0;
    $procs = [];
    $pipes = [];
    foreach ($candidates as $i => $enrollmentId) {
        $cmd = escapeshellarg($php).' '.escapeshellarg($root.'/storage/app/scratch/lane168-assign-child.php')
            .' --schema='.$schema.' --block='.((int) $block['blockId'])
            .' --schedule='.((int) $block['scheduleId']).' --enrollment='.((int) $enrollmentId)
            .' --desk='.((int) $desk).' --start-at='.sprintf('%.6f', $startAt);
        $procs[$i] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $p, $root);
        if (! is_resource($procs[$i])) {
            fwrite(STDERR, "REFUSED: child {$i} would not start ({$label}).\n");
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
            fwrite(STDERR, "REFUSED: child {$i} printed no verdict (exit {$code}). stdout="
                .substr((string) $stdout, 0, 300).' stderr='.substr((string) $stderr, 0, 300)."\n");
            exit(1);
        }
        $decoded['exit'] = $code;
        $out[] = $decoded;
    }

    return $out;
};

/**
 * Judge a pass. HEAD was first expected to overrun the seat; measured, its failure is a 1213 deadlock instead —
 * a writer died with a 500 rather than double-booking. Both are the same defect seen from two sides: the act is
 * not serialised. So an unsafe HEAD outcome is either one, and the CLEAN outcome (exactly one assigned, the other
 * refused on `capacity`, nobody erroring) is what HEAD must NOT produce — if it does, the lock is not what closed
 * this and the probe's pass verdict would mean nothing.
 */
$verdicts = [];
$evaluate = static function (string $label, array $pairsRun, int $capacity, int $maxHeld) use (&$verdicts): void {
    $assigned = $refused = $deadlock = $other = $clean = 0;
    foreach ($pairsRun as $pair) {
        foreach ($pair as $r) {
            if ($r['status'] === 'assigned') {
                $assigned++;
            } elseif ($r['status'] === 'refused' && $r['detail'] === 'capacity') {
                $refused++;
            } elseif ($r['status'] === 'error' && preg_match('/1213|1205/', (string) $r['detail'])) {
                $deadlock++;
            } else {
                $other++;
            }
        }
        printf("  %-18s pair: %s\n", $label, implode('   |   ', array_map(
            fn (array $r) => '#'.$r['enrollmentId'].' '.$r['status']
                .($r['detail'] !== null ? '['.substr(preg_replace('/\s+/', ' ', (string) $r['detail']), 0, 52).']' : '')
                .' seats='.$r['seatCountAfter'].' '.$r['ms'].'ms',
            $pair)));
    }
    $clean = 0;
    foreach ($pairsRun as $pair) {
        $a = count(array_filter($pair, fn (array $r) => $r['status'] === 'assigned'));
        $c = count(array_filter($pair, fn (array $r) => $r['status'] === 'refused' && $r['detail'] === 'capacity'));
        $e = count(array_filter($pair, fn (array $r) => $r['status'] === 'error'));
        if ($a === 1 && $c === 1 && $e === 0) {
            $clean++;
        }
    }
    $over = $maxHeld > $capacity;
    printf("  %-18s => assigned %d, refused %d, deadlock %d, other %d, clean pairs %d/%d, max seats %d of %d%s\n\n",
        $label, $assigned, $refused, $deadlock, $other, $clean, count($pairsRun), $maxHeld, $capacity,
        $over ? '   *** OVER CAPACITY ***' : '');

    $verdicts[$label] = ['assigned' => $assigned, 'refused' => $refused, 'deadlock' => $deadlock,
        'other' => $other, 'clean' => $clean, 'pairs' => count($pairsRun), 'held' => $maxHeld,
        'capacity' => $capacity, 'over' => $over];
};

$passes = ['working' => $workingBytes, 'HEAD' => $headBytes, 'working-restored' => $workingBytes];
printf("\n=== %d pairs of concurrent assigns, run three times (once per pass) ===\n", $pairs);
foreach ($passes as $label => $bytes) {
    file_put_contents($live, $bytes);
    if (sha1_file($live) !== sha1($bytes)) {
        fwrite(STDERR, "REFUSED: the bytes for pass `{$label}` did not land in the live file.\n");
        exit(1);
    }
    if ($label === 'HEAD') {
        echo "\n  (HEAD's BlockingController.php is now the live file — ".strlen($bytes)
            ." bytes against the batch's ".strlen($workingBytes).")\n\n";
    }
    $pairsRun = [];
    $max = 0;
    for ($i = 0; $i < $pairs; $i++) {
        $stage();
        if ($seats() !== 0) {
            fwrite(STDERR, 'REFUSED: the staged block held a seat before pair '.($i + 1)." of `{$label}`;"
                ." a refusal there would not be the fix working.\n");
            exit(1);
        }
        $pairsRun[] = $runPair($label);
        $max = max($max, $seats());
    }
    $evaluate($label, $pairsRun, 1, $max);
}
file_put_contents($live, $workingBytes);
if (sha1_file($live) !== sha1($workingBytes)) {
    fwrite(STDERR, "REFUSED: the working bytes were not restored.\n");
    exit(1);
}
$restoreDone = true;

echo "=== verdict ===\n";
$fail = [];
$w = $verdicts['working'];
$r = $verdicts['working-restored'];
$h = $verdicts['HEAD'];

if ($w['clean'] !== $w['pairs'] || $w['other'] > 0 || $w['over']) {
    $fail[] = 'the locked act did not serialise every pair: clean '.$w['clean'].'/'.$w['pairs']
        .' other-error '.$w['other'].' max seats '.$w['held'].' of '.$w['capacity'];
}
if ($w['deadlock'] > 0) {
    $fail[] = 'the locked act still met a 1213/1205 that reached the desk — the retry is not absorbing it';
}
$headUnsafe = $h['over'] || $h['deadlock'] > 0;
if (! $headUnsafe) {
    $fail[] = 'HEAD produced the clean outcome (clean '.$h['clean'].'/'.$h['pairs'].', no deadlock, no overrun).'
        .' This probe cannot show the defect it was written for, so its pass verdict would mean nothing —'
        .' widen the race (more pairs, a slower seat read) before believing any green here.';
}
if ($r['clean'] !== $w['clean'] || $r['held'] !== $w['held']) {
    $fail[] = 'the restored file does not behave like the working file it was copied from: clean '
        .$r['clean'].' vs '.$w['clean'].', max seats '.$r['held'].' vs '.$w['held'];
}

foreach ($verdicts as $label => $v) {
    printf("  %-18s clean %d/%d  assigned %d  refused %d  deadlock %d  max seats %d of %d\n",
        $label, $v['clean'], $v['pairs'], $v['assigned'], $v['refused'], $v['deadlock'], $v['held'], $v['capacity']);
}
printf("  HEAD failed by: %s\n", $h['over'] ? 'overbooking the last seat' : 'a 1213 deadlock that reached the desk as a 500');

echo $keep
    ? "\n  --keep: {$schema} left in place for reading.\n"
    : "\n  {$schema} will be dropped as this run exits.\n";

if ($fail !== []) {
    echo "\n";
    foreach ($fail as $f) {
        echo 'BAD: '.$f."\n";
    }
    exit(1);
}
echo "\nRACE MEASURED AND CLOSED: HEAD lost the pair (seat overrun or a 1213 at the desk), the locked act"
    ." refused exactly one writer per pair and never overran, twice.\n";
