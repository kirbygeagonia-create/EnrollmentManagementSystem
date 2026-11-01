<?php

/**
 * Proof that lane172's integrity questions can fail.
 *
 * `lane172-enrollment-day-load.php` answers "LOAD INTEGRITY HELD" over a thousand concurrent acts. A clean verdict
 * from a query nobody has seen return a row is the defect this repository has met from every direction — the
 * checker that read the wrong construct, the mutant that slept, the census that opened no file. So each question
 * the load tool asks is damaged here, one at a time, in a throwaway copy of a KEPT load schema, and the same tool
 * run in `--verify-only` mode must name it and exit 1.
 *
 * Every plant is built from the table's own columns, read with SHOW COLUMNS from the copy, and not from a typed
 * column list: `enrolledsubjects` and `enrollmentworkflow` carry no timestamps, so a hand-written list would fail
 * on a schema that is perfectly capable of holding the defect. That is the lane108 rule, applied to a harness.
 *
 * Nothing is invented and nothing compounds: each leg re-clones from the schema lane172 left behind with --keep,
 * the source is weighed before and after every copy, and the copy is dropped at the end. A plant whose SQL affects
 * no row prints BAD rather than passing.
 *
 * Usage: php storage/app/scratch/mutate-lane172-load-integrity.php --source=ems_l172_a --term=11 [--contended=<studentId>]
 */

$argvMap = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z0-9-]+)(?:=(.*))?$/', $a, $m) === 1) {
        $argvMap[$m[1]] = $m[2] ?? true;

        continue;
    }
    fwrite(STDERR, 'REFUSED: this harness does not understand '.escapeshellarg($a).".\n");

    exit(1);
}
$known = ['source', 'term', 'contended'];
if ($unknown = array_values(array_diff(array_keys($argvMap), $known))) {
    fwrite(STDERR, 'REFUSED: flag(s) not read by this harness: '.implode(', ', array_map(static fn ($k) => '--'.$k, $unknown)).".\n");

    exit(1);
}
$source = (string) ($argvMap['source'] ?? '');
$term = (int) ($argvMap['term'] ?? 0);
$contended = isset($argvMap['contended']) ? (int) $argvMap['contended'] : 0;
if (preg_match('/^ems_l172_[a-z]$/', $source) !== 1) {
    fwrite(STDERR, 'REFUSED: --source must name the schema lane172 kept (ems_l172_[a-z]); got '
        .var_export($source, true).".\n");

    exit(1);
}
if ($term <= 0) {
    fwrite(STDERR, "REFUSED: --term must name the term the kept schema was loaded against.\n");

    exit(1);
}
$copy = $source === 'ems_l172_a' ? 'ems_l172_b' : 'ems_l172_a';

$root = dirname(__DIR__, 3);
$env = [];
foreach (file($root.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    if (preg_match('/^([A-Z_][A-Z0-9_]*)=(.*)$/', $line, $m) === 1) {
        $env[$m[1]] = trim($m[2], " \"'");
    }
}
$bin = 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin';
[$mysqldump, $mysql, $php] = [$bin.'/mysqldump.exe', $bin.'/mysql.exe', 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe'];
$auth = '-u '.($env['DB_USERNAME'] ?? 'root').(($env['DB_PASSWORD'] ?? '') !== '' ? ' -p'.$env['DB_PASSWORD'] : '');
$pdo = new PDO('mysql:host='.($env['DB_HOST'] ?? '127.0.0.1').';port='.($env['DB_PORT'] ?? '3306').';charset=utf8mb4',
    $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$dumpFile = $root.'/storage/app/scratch/lane172-damage-clone.sql';
register_shutdown_function(static function () use ($copy, $dumpFile, $pdo): void {
    if (is_file($dumpFile)) {
        @unlink($dumpFile);
    }
    try {
        $pdo->exec("DROP DATABASE IF EXISTS `{$copy}`");
    } catch (Throwable) {
        // A dead connection cannot be talked to from a hook; the next run drops the copy at the top of its own clone.
    }
});

$legs = [];
$bad = 0;
$say = static function (string $name, bool $ok, string $detail = '') use (&$legs, &$bad): void {
    $legs[] = ($ok ? 'ok   ' : 'BAD  ').$name.($detail !== '' ? ' — '.$detail : '');
    if (! $ok) {
        $bad++;
    }
};

$flat = static fn (string $s): string => str_replace("\n", ' | ', substr($s, -240));

// A plant whose SQL throws is this harness's own failure, and it is printed as one. Silence here would let a leg
// that never landed its defect report as a leg that killed the checker.
set_exception_handler(static function (Throwable $e) use (&$legs): void {
    $legs[] = 'BAD  an exception stopped the run: '.get_class($e).' — '.$e->getMessage();
    echo "\n".implode("\n", $legs)."\n  (the damage copy was dropped by the shutdown hook; the source schema was not written to)\n";
    exit(1);
});

$reclone = static function () use ($mysqldump, $mysql, $auth, $source, $copy, $dumpFile, $pdo): void {
    $before = (int) $pdo->query("SELECT COUNT(*) FROM `{$source}`.enrollments")->fetchColumn();
    exec(escapeshellarg($mysqldump)." {$auth} --no-create-db --single-transaction ".escapeshellarg($source)
        .' > '.escapeshellarg($dumpFile).' 2>&1', $o, $code);
    if ($code !== 0 || filesize($dumpFile) < 1024) {
        fwrite(STDERR, "REFUSED: the kept schema would not dump (exit {$code}).\n");

        exit(1);
    }
    $text = (string) file_get_contents($dumpFile);
    if (str_contains($text, 'DROP DATABASE') || str_contains($text, 'CREATE DATABASE')) {
        fwrite(STDERR, "REFUSED: the damage copy carries DROP/CREATE DATABASE — it would act on {$source}.\n");

        exit(1);
    }
    $pdo->exec("DROP DATABASE IF EXISTS `{$copy}`");
    $pdo->exec("CREATE DATABASE `{$copy}` CHARACTER SET utf8mb4");
    exec(escapeshellarg($mysql)." {$auth} ".escapeshellarg($copy).' < '.escapeshellarg($dumpFile).' 2>&1', $r, $code);
    if ($code !== 0) {
        fwrite(STDERR, "REFUSED: the copy into {$copy} exited {$code}: ".implode(' ', array_slice($r, 0, 4))."\n");

        exit(1);
    }
    $after = (int) $pdo->query("SELECT COUNT(*) FROM `{$source}`.enrollments")->fetchColumn();
    if ($after !== $before) {
        fwrite(STDERR, "REFUSED: the SOURCE schema moved while copying ({$before} -> {$after} enrollments). Stopping.\n");

        exit(1);
    }
};

/**
 * The storable columns of a table, as the server holds them right now. Generated columns are left out: MySQL
 * answers `3105 The value specified for generated column 'seatKey' is not allowed` to an INSERT that names one,
 * which is the database being correct and this harness being wrong to ask.
 */
$columns = static function (string $table) use ($pdo, $copy): array {
    $out = [];
    foreach ($pdo->query("SHOW COLUMNS FROM `{$copy}`.`{$table}`") as $row) {
        if (str_contains((string) ($row['Extra'] ?? ''), 'GENERATED')) {
            continue;
        }
        $out[] = $row['Field'];
    }
    if ($out === []) {
        fwrite(STDERR, "REFUSED: `{$copy}`.`{$table}` answers no storable columns.\n");

        exit(1);
    }

    return $out;
};

/**
 * Duplicate one existing row into the same table with a few fields overridden, naming no column the table does not
 * hold. Returns false when the template row does not exist, which the caller reports as BAD.
 */
$plant = static function (string $table, string $where, array $overrides, string $pk) use ($pdo, $copy, $columns): bool {
    $cols = $columns($table);
    $row = $pdo->query("SELECT * FROM `{$copy}`.`{$table}` WHERE {$where} LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        return false;
    }
    $names = [];
    $values = [];
    foreach ($cols as $c) {
        if ($c === $pk) {
            continue; // let the auto-increment mint a fresh key
        }
        $v = array_key_exists($c, $overrides) ? $overrides[$c] : ($row[$c] ?? null);
        $names[] = '`'.$c.'`';
        $values[] = $v === null ? 'NULL' : (is_numeric($v) ? (string) $v : $pdo->quote((string) $v));
    }
    $pdo->exec('INSERT INTO `'.$copy.'`.`'.$table.'` ('.implode(', ', $names).') VALUES ('.implode(', ', $values).')');

    return true;
};

$runVerify = static function (string $schema) use ($php, $root, $term, $contended): array {
    $cmd = escapeshellarg($php).' '.escapeshellarg($root.'/storage/app/scratch/lane172-enrollment-day-load.php')
        .' --verify-only --schema='.escapeshellarg($schema).' --term='.(string) $term;
    if ($contended > 0) {
        $cmd .= ' --contended='.(string) $contended;
    }
    exec($cmd.' 2>&1', $out, $code);

    return [$code, implode("\n", $out)];
};

// ── 0. the control: the kept schema, undamaged, must read clean, or every leg below measures a broken tool ──
[$rc, $out] = $runVerify($source);
$clean = $rc === 0 && str_contains($out, 'INTEGRITY CLEAN');
$say('control-clean', $clean, 'rc='.$rc.' '.$flat($out));
if (! $clean) {
    echo "\n".implode("\n", $legs)."\n  the kept schema does not read clean, so nothing below can be attributed to its plant.\n";

    exit(1);
}

// ── 1. dup-seats: drop the seat key in the copy, then give one LOAD student a second active seat ──────────
$reclone();
$seat = $pdo->query("SELECT e.studentId FROM `{$copy}`.enrollments e"
    ." JOIN `{$copy}`.students s ON s.studentId = e.studentId"
    ." WHERE s.schoolIdNumber LIKE 'LOAD-%' AND e.termId = {$term} LIMIT 1")->fetchColumn();
if ($seat === false) {
    $say('dup-seats', false, "the copy holds no LOAD enrollment in term {$term}");
} else {
    $pdo->exec("ALTER TABLE `{$copy}`.enrollments DROP INDEX uq_enrollments_active_seat");
    $ok = $plant('enrollments', "studentId = ".(int) $seat." AND termId = {$term}",
        ['enrollmentStatus' => 'enrolled'], 'enrollmentId');
    [$rc, $out] = $runVerify($copy);
    $say('dup-seats-named', $ok && $rc === 1 && str_contains($out, 'dup-seats = '), 'planted='.var_export($ok, true).' rc='.$rc.' '.$flat($out));
}

// ── 2. orphan-forms: a workflow form that names no enrollment — the shape a half-committed act leaves ─────
$reclone();
$wfCols = $columns('enrollmentworkflow');
$ghost = (int) $pdo->query("SELECT MAX(enrollmentId) FROM `{$copy}`.enrollments")->fetchColumn() + 424242;
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$ok = $plant('enrollmentworkflow', '1=1', ['enrollmentId' => $ghost], 'workflowId');
$pdo->exec('DELETE FROM `'.$copy.'`.`enrollments` WHERE enrollmentId = '.$ghost);
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
if (! $ok) {
    $say('orphan-forms', false, 'the copy holds no workflow form to clone');
} else {
    [$rc, $out] = $runVerify($copy);
    $say('orphan-forms-named', $rc === 1 && str_contains($out, 'orphan-forms = '), 'rc='.$rc.' '.$flat($out).' (columns: '
        .implode(',', $wfCols).')');
}

// ── 3. acts-without-form: delete a committed load enrollment's form ───────────────────────────────────────
$reclone();
$victim = $pdo->query("SELECT w.enrollmentId FROM `{$copy}`.enrollmentworkflow w"
    ." JOIN `{$copy}`.enrollments e ON e.enrollmentId = w.enrollmentId"
    ." JOIN `{$copy}`.students s ON s.studentId = e.studentId"
    ." WHERE s.schoolIdNumber LIKE 'LOAD-%' LIMIT 1")->fetchColumn();
if ($victim === false) {
    $say('acts-without-form', false, 'the copy holds no load enrollment carrying a form');
} else {
    // Dependency order, because `workflowsteps` points at the form with a RESTRICT key and a plant that dies on
    // 1451 is this harness asking the schema a question it had not read the answer to.
    $wid = (int) $pdo->query('SELECT workflowId FROM `'.$copy.'`.enrollmentworkflow WHERE enrollmentId = '
        .(int) $victim)->fetchColumn();
    $pdo->exec('DELETE FROM `'.$copy.'`.`workflowsteps` WHERE workflowId = '.$wid);
    $pdo->exec('DELETE FROM `'.$copy.'`.`enrollmentworkflow` WHERE enrollmentId = '.(int) $victim);
    [$rc, $out] = $runVerify($copy);
    $say('acts-without-form-named', $rc === 1 && str_contains($out, 'acts-without-form = '), 'rc='.$rc.' '.$flat($out));
}

// ── 4. records-with-two-forms: one enrollment, two forms ──────────────────────────────────────────────────
$reclone();
// `SHOW INDEX` answers the question the first cut of this leg asked wrongly: `enrollmentworkflow` is guarded only
// by PRIMARY on `workflowId` plus a NON-UNIQUE FK index on `enrollmentId`. So "one workflow form per enrollment"
// is held by the code that creates it, not by the schema — which is exactly why this leg is plantable at all, and
// why the tool's `records-with-two-forms` question is worth keeping.
$dup = $pdo->query("SELECT w.enrollmentId, w.currentStep, w.workflowStatus FROM `{$copy}`.enrollmentworkflow w"
    ." JOIN `{$copy}`.enrollments e ON e.enrollmentId = w.enrollmentId"
    ." JOIN `{$copy}`.students s ON s.studentId = e.studentId WHERE s.schoolIdNumber LIKE 'LOAD-%' LIMIT 1")
    ->fetch(PDO::FETCH_ASSOC);
if ($dup === false) {
    $say('records-with-two-forms', false, 'no load form exists to duplicate');
} else {
    $pdo->exec('INSERT INTO `'.$copy.'`.enrollmentworkflow (enrollmentId, currentStep, workflowStatus) VALUES ('
        .(int) $dup['enrollmentId'].', '.$pdo->quote((string) $dup['currentStep']).', '
        .$pdo->quote((string) $dup['workflowStatus']).')');
    [$rc, $out] = $runVerify($copy);
    $say('records-with-two-forms-named', $rc === 1 && str_contains($out, 'records-with-two-forms = '),
        'rc='.$rc.' '.$flat($out));
}

// ── 5. blocks-over-capacity: push one block past its own maxStudents with confirmed subject lines ────────
// Attachments are planted through `enrolledsubjects`, not by minting enrollments: the seat key is still live in
// this copy, so a second enrollment for any student would be refused for a reason that has nothing to do with
// capacity, and the leg would report the instrument's own plant as a defect.
$reclone();
$esCols = $columns('enrolledsubjects');
// The first cut took the smallest block on the schema and asked it for maxStudents+1 new holders, then reported
// "stamped 12 of the 38 needed" and called the checker asleep when the checker was right. A block can only be
// overrun by enrollments its own term actually holds, so the leg now walks the six smallest blocks and takes the
// first one whose term can supply what the ceiling needs.
$block = false;
$need = 0;
$candidates = [];
foreach ($pdo->query("SELECT b.blockId, b.maxStudents, b.termId FROM `{$copy}`.blocks b"
    .' ORDER BY b.maxStudents ASC LIMIT 6')->fetchAll(PDO::FETCH_ASSOC) as $try) {
    $held = (int) $pdo->query('SELECT COUNT(DISTINCT es.enrollmentId) FROM `'.$copy.'`.`enrolledsubjects` es'
        ." WHERE es.blockId = ".(int) $try['blockId']." AND es.status <> 'dropped'")->fetchColumn();
    $wanted = (int) $try['maxStudents'] + 1 - $held;
    if ($wanted < 1) {
        continue;
    }
    $free = $pdo->query('SELECT e.enrollmentId FROM `'.$copy.'`.enrollments e'
        .' WHERE e.termId = '.(int) $try['termId']
        .' AND NOT EXISTS (SELECT 1 FROM `'.$copy.'`.`enrolledsubjects` es'
        .' WHERE es.enrollmentId = e.enrollmentId AND es.blockId = '.(int) $try['blockId'].')'
        .' ORDER BY e.enrollmentId LIMIT '.$wanted)->fetchAll(PDO::FETCH_COLUMN);
    if (count($free) >= $wanted) {
        $block = $try;
        $need = $wanted;
        $candidates = $free;
        break;
    }
}
if ($block === false) {
    $say('blocks-over-capacity', false, 'no block among the six smallest can be pushed past its own ceiling by real holder rows');
} else {
    $room = $need;
    $stamped = 0;
    foreach ($candidates as $enrollmentId) {
        $subject = $pdo->query("SELECT s.subjectId FROM `{$copy}`.subjects s WHERE s.subjectId NOT IN ("
            .'SELECT es.subjectId FROM `'.$copy.'`.`enrolledsubjects` es WHERE es.enrollmentId = '
            .(int) $enrollmentId.') LIMIT 1')->fetchColumn();
        if ($subject === false) {
            continue;
        }
        $set = '`enrollmentId` = '.(int) $enrollmentId.', `subjectId` = '.(int) $subject
            .', `blockId` = '.(int) $block['blockId'];
        if (in_array('status', $esCols, true)) {
            $set .= ', `status` = '.$pdo->quote('confirmed');
        }
        if (in_array('attempt_number', $esCols, true)) {
            $set .= ', `attempt_number` = 1';
        }
        $pdo->exec('INSERT INTO `'.$copy.'`.`enrolledsubjects` SET '.$set);
        $stamped++;
    }
    [$rc, $out] = $runVerify($copy);
    $say('blocks-over-capacity-named', $stamped === $room && $rc === 1 && str_contains($out, 'blocks-over-capacity = '),
        'stamped '.$stamped.' of the '.$room.' needed for maxStudents '.$block['maxStudents'].'; rc='.$rc.' '.$flat($out));
}

// ── 6. contended-seat-rows: the contended student given a second seat, key dropped first ─────────────────
if ($contended > 0) {
    $reclone();
    $pdo->exec("ALTER TABLE `{$copy}`.enrollments DROP INDEX uq_enrollments_active_seat");
    $ok = $plant('enrollments', "studentId = {$contended} AND termId = {$term}", ['enrollmentStatus' => 'enrolled'], 'enrollmentId');
    [$rc, $out] = $runVerify($copy);
    $say('contended-seat-rows-named', $ok && $rc === 1 && str_contains($out, 'contended-seat-rows = '),
        'planted='.var_export($ok, true).' rc='.$rc.' '.$flat($out));
}

// ── 7. refusals: a flag this tool does not read, and a schema outside the scratch pattern ────────────────
$cmd = escapeshellarg($php).' '.escapeshellarg($root.'/storage/app/scratch/lane172-enrollment-day-load.php')
    .' --verify-only --schema=ems_l172_a --term='.$term.' --no-such-flag-at-all';
exec($cmd.' 2>&1', $o7, $rc7);
$say('unknown-flag-refused', $rc7 === 1 && str_contains(implode("\n", $o7), '--no-such-flag-at-all'), 'rc='.$rc7);

$cmd = escapeshellarg($php).' '.escapeshellarg($root.'/storage/app/scratch/lane172-enrollment-day-load.php')
    .' --verify-only --schema=ems --term='.$term;
exec($cmd.' 2>&1', $o8, $rc8);
$say('live-schema-refused', $rc8 === 1 && str_contains(implode("\n", $o8), 'REFUSED'), 'rc='.$rc8);

// ── 8. the control again: the source was never written to, so it must still read clean ───────────────────
[$rc9, $out9] = $runVerify($source);
$say('control-clean-after', $rc9 === 0 && str_contains($out9, 'INTEGRITY CLEAN'), 'rc='.$rc9.' '.$flat($out9));

echo "\n".implode("\n", $legs)."\n";
printf("\n%d leg(s), %d bad\n", count($legs), $bad);

exit($bad === 0 ? 0 : 1);
