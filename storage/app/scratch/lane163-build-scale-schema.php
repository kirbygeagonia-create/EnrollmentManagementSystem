<?php

/**
 * ADS Problems 8, 9 and 23 need a measured before-and-after, and live `ems` holds 24 students and 37 enrollments —
 * a schema too small for a plan to mean anything. This builds an isolated scale schema by cloning the LIVE
 * STRUCTURE (never its data) and seeding synthetic rows, so the queries can be weighed at the volume the guide
 * describes without touching a real record.
 *
 * It writes only to the schema named by --target, refuses any name it was not told to own, and refuses to run
 * against `ems`: no load rehearsal, no generated data, ever touches the live schema (§38.H of the guide and this
 * repository's own rule that a new write path is never exercised on live data).
 *
 * Usage:
 *   php storage/app/scratch/lane163-build-scale-schema.php --target=ems_l163_a [--students=200000] [--batch=5000]
 */
$root = dirname(__DIR__, 3);
$known = ['target', 'students', 'batch', 'verify-only'];
$target = '';
$students = 200000;
$batch = 5000;
$verifyOnly = false;

for ($i = 1; $i < $argc; $i++) {
    if (! preg_match('/^--([a-z0-9-]+)(=(.*))?$/', $argv[$i], $m)) {
        fwrite(STDERR, "REFUSED: unreadable argument {$argv[$i]}\n");
        exit(1);
    }
    if (! in_array($m[1], $known, true)) {
        fwrite(STDERR, "REFUSED: unknown flag --{$m[1]} — this builder does not read it\n");
        exit(1);
    }
    if ($m[1] === 'target') {
        $target = (string) ($m[3] ?? '');
    } elseif ($m[1] === 'students') {
        $students = (int) ($m[3] ?? $students);
    } elseif ($m[1] === 'batch') {
        $batch = max(100, (int) ($m[3] ?? $batch));
    } elseif ($m[1] === 'verify-only') {
        $verifyOnly = true;
    }
}

$env = [];
foreach (file($root.'/.env') as $l) {
    if (preg_match('/^([A-Z_]+)=(.*)$/', trim($l), $m)) {
        $env[$m[1]] = $m[2];
    }
}
$source = $env['DB_DATABASE'] ?? 'ems';
$user = $env['DB_USERNAME'] ?? 'root';
$pass = $env['DB_PASSWORD'] ?? '';

if ($target === '' || ! preg_match('/^ems_l163_[a-z]$/', $target)) {
    fwrite(STDERR, "REFUSED: --target must match ems_l163_[a-z] (got '".($target === '' ? '(empty)' : $target)."'). "
        ."A generated dataset belongs in a scratch schema a later run can retire by name.\n");
    exit(1);
}
if ($target === $source || in_array($source, ['ems'], true) && $target === 'ems') {
    fwrite(STDERR, "REFUSED: the target is the live schema.\n");
    exit(1);
}
if ($students < 10000) {
    fwrite(STDERR, 'REFUSED: --students='.$students.' is not a scale the guide asks about; it wants thousands.\n');
    exit(1);
}

$mysql = 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
$mysqldump = 'C:/laragon/bin/mysql/mysql-8.4.3-win32-vs16-x64/bin/mysqldump.exe';
$mysqldump = is_file($mysqldump) ? $mysqldump : 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysqldump.exe';
foreach (['mysql' => $mysql, 'mysqldump' => $mysqldump] as $label => $bin) {
    if (! is_file($bin)) {
        fwrite(STDERR, "REFUSED: {$label} is not at {$bin} — cmd.exe cannot see a Git-Bash path.\n");
        exit(1);
    }
}

$auth = '-u '.$user.($pass === '' ? '' : ' -p'.$pass);

$src = new PDO('mysql:host=127.0.0.1;dbname='.$source, $user, $pass);

if ($verifyOnly) {
    $counts = [];
    foreach (['students', 'enrollments', 'enrolledsubjects', 'auditlogs'] as $t) {
        $counts[$t] = (int) $src->query("select count(*) from `{$source}`.`{$t}`")->fetchColumn();
    }
    echo 'source counts (never written by this tool): '.json_encode($counts)."\n";
    $dst = new PDO('mysql:host=127.0.0.1;dbname='.$target, $user, $pass);
    foreach (['students', 'enrollments', 'enrolledsubjects'] as $t) {
        printf("  %-20s scale=%s\n", $t, number_format((int) $dst->query("select count(*) from `{$t}`")->fetchColumn()));
    }
    exit(0);
}

// Structure only: --no-data, --no-create-db, so no DROP DATABASE and no rows travel.
$dumpFile = sys_get_temp_dir().'/ems-structure-'.getmypid().'.sql';
exec(escapeshellarg($mysqldump)." {$auth} --no-data --no-create-db --skip-add-drop-table ".escapeshellarg($source).' > '.escapeshellarg($dumpFile).' 2>&1', $o, $code);
if ($code !== 0 || ! is_file($dumpFile) || filesize($dumpFile) < 2048) {
    fwrite(STDERR, "REFUSED: the structure dump did not land (exit {$code})\n");
    exit(1);
}
register_shutdown_function(static fn () => @unlink($dumpFile));
if (stripos((string) file_get_contents($dumpFile), 'DROP DATABASE') !== false) {
    fwrite(STDERR, "REFUSED: the structure dump carries DROP DATABASE — refuse before it can be applied.\n");
    exit(1);
}

exec(escapeshellarg($mysql)." {$auth} -e ".escapeshellarg("DROP DATABASE IF EXISTS `{$target}`").' 2>&1', $d, $code);
if ($code !== 0) {
    fwrite(STDERR, "REFUSED: could not drop the previous scratch schema (exit {$code})\n  ".implode("\n  ", $d)."\n");
    exit(1);
}
exec(escapeshellarg($mysql)." {$auth} -e ".escapeshellarg("CREATE DATABASE `{$target}` CHARACTER SET utf8mb4").' 2>&1', $c, $code);
if ($code !== 0) {
    fwrite(STDERR, "REFUSED: could not create {$target}\n  ".implode("\n  ", $c)."\n");
    exit(1);
}
exec(escapeshellarg($mysql)." {$auth} ".escapeshellarg($target).' < '.escapeshellarg($dumpFile).' 2>&1', $r, $code);
if ($code !== 0) {
    fwrite(STDERR, "REFUSED: the structure restore into {$target} exited {$code}\n  ".implode("\n  ", array_slice($r, 0, 8))."\n");
    exit(1);
}

$dst = new PDO('mysql:host=127.0.0.1;dbname='.$target, $user, $pass);
$dst->exec('SET FOREIGN_KEY_CHECKS = 0');

// Carry over the small reference tables the synthetic rows must name, copied by select-insert (data of a
// reference catalogue is not sensitive and the FK targets have to exist).
foreach (['offices', 'academicunits', 'academicyears', 'academicterms', 'courses', 'religions', 'subjects',
    'curriculums', 'curriculumsubjects', 'blocks', 'schedules', 'rooms', 'feetypes'] as $t) {
    $n = (int) $src->query("select count(*) from `{$source}`.`{$t}`")->fetchColumn();
    if ($n === 0) {
        continue;
    }
    $rows = $src->query("select * from `{$source}`.`{$t}`")->fetchAll(PDO::FETCH_ASSOC);
    $cols = array_keys($rows[0]);
    $ins = $dst->prepare('insert into `'.$t.'` (`'.implode('`,`', $cols).'`) values ('
        .implode(',', array_fill(0, count($cols), '?')).')');
    foreach ($rows as $row) {
        $ins->execute(array_values($row));
    }
    printf("reference %-22s %d row(s) carried\n", $t, count($rows));
}

$term = (int) $dst->query('select termId from academicterms order by termId limit 1')->fetchColumn();
$course = (int) $dst->query('select courseId from courses order by courseId limit 1')->fetchColumn();
$subjectIds = $dst->query('select subjectId from subjects order by subjectId')->fetchAll(PDO::FETCH_COLUMN);
if ($term === 0 || $course === 0 || $subjectIds === []) {
    fwrite(STDERR, "REFUSED: the scale schema names no term, course or subject — the seed would invent parents.\n");
    exit(1);
}

// A block the size the school actually runs. The first cut reused the five demo blocks for every generated
// enrollment, which put 101,484 students in one block whose own `maxStudents` is 40 and made the capacity count
// measure a distribution no desk has — 300 ms and an unusable plan. Blocks are therefore created in proportion to
// the students seeded, at the capacity the guide's scenario describes.
$perBlock = 40;
$blockCount = (int) max(1, ceil($students / $perBlock));
$blk = $dst->prepare('insert into blocks (courseId, termId, yearLevel, blockName, maxStudents, scheduleStatus)
    values (?,?,?,?,?,?)');
$dst->beginTransaction();
for ($b = 0; $b < $blockCount; $b++) {
    $blk->execute([$course, $term, ($b % 4) + 1, 'SCALE-B'.str_pad((string) $b, 4, '0', STR_PAD_LEFT), $perBlock, 'draft']);
}
$dst->commit();
$blockIds = $dst->query('select blockId from blocks where blockName like \'SCALE-B%\' order by blockId')->fetchAll(PDO::FETCH_COLUMN);
if (count($blockIds) !== $blockCount) {
    fwrite(STDERR, 'REFUSED: asked for '.$blockCount.' synthetic blocks and the schema answers '
        .count($blockIds)." — the fixture is not what this tool believes it is.\n");
    exit(1);
}
printf("created %d synthetic blocks at maxStudents=%d each (a student joins ONE block, as on a real roster)\n",
    count($blockIds), $perBlock);

// The seed names columns, so the columns are read from the server rather than recalled: a guessed column is a
// 1054 that kills the run mid-loop after the reference tables have already been carried, and `admissionType`
// turned out to be one — it belongs to `admissions`, not `students`.
$required = [];
foreach ($dst->query('select COLUMN_NAME from information_schema.COLUMNS where TABLE_SCHEMA = '.$dst->quote($target)
    ." and TABLE_NAME = 'students' and IS_NULLABLE = 'NO' and COLUMN_DEFAULT is null and EXTRA <> 'auto_increment'")
    ->fetchAll(PDO::FETCH_COLUMN) as $c) {
    $required[] = (string) $c;
}

$started = microtime(true);
$dst->beginTransaction();
$inserted = 0;
$studentCols = ['schoolIdNumber', 'lastName', 'firstName', 'middleName', 'suffix', 'gender', 'birthdate',
    'birthplace', 'citizenship', 'civilStatus', 'religionId', 'contactNumber', 'email', 'username',
    'passwordHash', 'status', 'semestersCompleted', 'yearsInInstitution'];
// created_at and updated_at are supplied by NOW() in the statement itself, so they are deliberately NOT in the
// placeholder list — 18 columns bound to 18 values, then two literal timestamps.
$missing = array_values(array_diff($required, array_merge($studentCols, ['created_at', 'updated_at'])));
if ($missing !== []) {
    fwrite(STDERR, 'REFUSED: the student seed does not supply NOT NULL column(s) '.implode(', ', $missing)
        ." — read the schema before writing the insert, do not add the column and hope.\n");
    exit(1);
}
$st = $dst->prepare('insert into students (`'.implode('`,`', $studentCols).'`,`created_at`,`updated_at`) values ('
    .implode(',', array_fill(0, count($studentCols), '?')).', NOW(), NOW())');

for ($i = 0; $i < $students; $i++) {
    $st->execute([
        'SCALE-'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
        'Scale'.($i % 997),
        'Given'.($i % 883),
        'M',
        'N/A',
        $i % 2 === 0 ? 'male' : 'female',
        '2004-01-01',
        'Test City',
        'Filipino',
        'single',
        1,
        '09170000000',
        'scale'.$i.'@example.com',
        'scale'.$i,
        '$2y$04$notarealhashforageneratedrow',
        'active',
        0,
        0,
    ]);
    $inserted++;
    if ($inserted % $batch === 0) {
        $dst->commit();
        $dst->beginTransaction();
        printf("  %s students (%.1fs)\n", number_format($inserted), microtime(true) - $started);
    }
}
$dst->commit();
printf("seeded %s synthetic students in %.1fs\n", number_format($inserted), microtime(true) - $started);

// One enrollment and one subject line per student, so the history query has a shape to plan against.
$started2 = microtime(true);
$dst->beginTransaction();
$en = $dst->prepare('insert into enrollments (studentId, courseId, termId, yearLevel, studentType, enrollmentType,
    enrollmentStatus, evaluatedBy, enrolledDate, created_at, updated_at) values (?,?,?,?,?,?,?,?,?,NOW(),NOW())');
$maxStudent = (int) $dst->query('select max(studentId) from students')->fetchColumn();
$signer = (int) $dst->query('select userId from staffusers order by userId limit 1')->fetchColumn();

// The blocks were sized from `--students`, but the ids handed out include the demo cast's, so `$maxStudent` runs
// past that count. Top the set up before assigning rather than letting the remainder wrap into the first blocks:
// the first cut used `$sid % count($blockIds)`, which put 41 seats in a block whose own `maxStudents` is 40 — the
// fixture then violated the very invariant the probes below claim to measure, and the fullest partition was a
// defect of the seed rather than a state a desk could reach.
$neededBlocks = (int) ceil($maxStudent / $perBlock);
if (count($blockIds) < $neededBlocks) {
    for ($b = count($blockIds); $b < $neededBlocks; $b++) {
        $blk->execute([$course, $term, ($b % 4) + 1,
            'SCALE-B'.str_pad((string) $b, 4, '0', STR_PAD_LEFT), $perBlock, 'draft']);
    }
    $blockIds = $dst->query('select blockId from blocks where blockName like \'SCALE-B%\' order by blockId')
        ->fetchAll(PDO::FETCH_COLUMN);
    if (count($blockIds) < $neededBlocks) {
        fwrite(STDERR, 'REFUSED: asked for '.$neededBlocks.' blocks to cover '.$maxStudent.' seats and the schema '
            .'answers '.count($blockIds)." — the fixture would over-fill a block.\n");
        exit(1);
    }
}
printf("blocks sized to seats: %d blocks x %d seats covers studentId 1..%d\n",
    count($blockIds), $perBlock, $maxStudent);

// A schedule and a room per block, so the room-capacity branch of `assignStudents()` has a real partition to count.
// Without them `enrolledsubjects.scheduleId` is null on every generated row and the probe can only print NOT
// MEASURED for that half — which is the same silence that hid the block-side distribution bug. Ten blocks share a
// room, the way one lecture hall carries several sections; `schedules` carries no day or time (those live in
// `schedulemeetings`), so the insert names the five columns the table really holds.
$blocksPerRoom = 10;
$roomIds = [];
$roomStmt = $dst->prepare('insert into rooms (roomName, capacity, building) values (?,?,?)');
for ($r = 0; $r < (int) ceil(count($blockIds) / $blocksPerRoom); $r++) {
    $roomStmt->execute(['SCALE-R'.str_pad((string) $r, 4, '0', STR_PAD_LEFT), $blocksPerRoom * $perBlock, 'Scale Building']);
    $roomIds[] = (int) $dst->lastInsertId();
}
$schedStmt = $dst->prepare('insert into schedules (blockId, subjectId, instructorId, roomId) values (?,?,?,?)');
$scheduleByBlock = [];
foreach ($blockIds as $i => $bid) {
    $schedStmt->execute([(int) $bid, (int) $subjectIds[0], $signer, $roomIds[intdiv($i, $blocksPerRoom)]]);
    $scheduleByBlock[(int) $bid] = (int) $dst->lastInsertId();
}
printf("created %d rooms (capacity %d, %d blocks each) and %d schedules\n",
    count($roomIds), $blocksPerRoom * $perBlock, $blocksPerRoom, count($scheduleByBlock));
// `enrolledsubjects` carries no units column and none of the eight record-timestamp tables, so the insert names
// only the columns the table really holds — a guessed column is a 1054 mid-seed that proves nothing about the query.
$lines = 0;
$enSt = $dst->prepare('insert into enrolledsubjects (enrollmentId, subjectId, blockId, status, scheduleId)
    values (?,?,?,?,?)');

for ($sid = 1; $sid <= $maxStudent; $sid++) {
    $en->execute([$sid, $course, $term, ($sid % 4) + 1, 'firstYear', 'new', 'enrolled', $signer, date('Y-m-d')]);
    $enrollmentId = (int) $dst->lastInsertId();
    // Filled in order, `$perBlock` seats to a block, so no synthetic block can hold more seats than its own
    // declared capacity — the modulo form over-filled the first blocks by the demo cast's id remainder.
    $blockIdx = intdiv($sid - 1, $perBlock);
    if (! isset($blockIds[$blockIdx])) {
        fwrite(STDERR, 'REFUSED: seat '.$sid.' needs block #'.$blockIdx.' of '.count($blockIds)
            ." — the block set no longer covers the roster.\n");
        exit(1);
    }
    $blockFor = $blockIds[$blockIdx];
    foreach (array_slice($subjectIds, 0, 5) as $sub) {
        // scheduleId rides only on the row whose subject matches the block's schedule — the same rule
        // `assignStudents()` states in its own comment, because stamping every row would print one subject's
        // meeting time on every line of the printed load.
        $schedForLine = ((int) $sub === (int) $subjectIds[0]) ? $scheduleByBlock[(int) $blockFor] : null;
        $enSt->execute([$enrollmentId, $sub, $blockFor, 'confirmed', $schedForLine]);
        $lines++;
    }
    if ($sid % 2000 === 0) {
        $dst->commit();
        $dst->beginTransaction();
        printf("  %s enrollments, %s lines (%.1fs)\n", number_format($sid), number_format($lines), microtime(true) - $started2);
    }
}
$dst->commit();
$dst->exec('SET FOREIGN_KEY_CHECKS = 1');

printf("seeded %s enrollments with %s subject lines in %.1fs\n\n", number_format($maxStudent), number_format($lines), microtime(true) - $started2);
foreach (['students', 'enrollments', 'enrolledsubjects'] as $t) {
    printf("  %-20s %s rows\n", $t, number_format((int) $dst->query("select count(*) from `{$t}`")->fetchColumn()));
}

// The fixture is asked the same question the audit asks a real roster: does any block hold more seats than it
// declares? Measured on the seed's own rows, counted the way `assignStudents()` counts them (distinct
// enrollmentId, dropped rows excluded) — so a "fullest partition" reading downstream cannot be an artefact of
// this tool, which is exactly how the 41-of-40 block arrived the first time.
// Scoped to the synthetic blocks, because a block carried from live `ems` may legitimately sit over its own
// cap — data written before the rule is a real record state, not a seed bug — so that one is printed, not refused.
$held = "select b.blockId, b.blockName, b.maxStudents,
        (select count(distinct es.enrollmentId) from enrolledsubjects es
           join enrollments e on e.enrollmentId = es.enrollmentId
           where es.blockId = b.blockId and es.status <> 'dropped') as held
    from blocks b";
$overSynthetic = (int) $dst->query("select count(*) from ({$held}) x
    where x.held > x.maxStudents and x.blockName like 'SCALE-B%'")->fetchColumn();
if ($overSynthetic > 0) {
    fwrite(STDERR, 'REFUSED: '.$overSynthetic.' synthetic block(s) hold more seats than their own maxStudents — '
        ."no probe result on this schema describes a state a desk could reach.\n");
    exit(1);
}
$carried = $dst->query("select count(*), coalesce(max(x.held),0) from ({$held}) x
    where x.held > x.maxStudents and x.blockName not like 'SCALE-B%'")->fetch(PDO::FETCH_NUM);
$fullest = $dst->query("select * from ({$held}) x
    where x.blockName like 'SCALE-B%' order by x.held desc, x.blockId limit 1")->fetch(PDO::FETCH_ASSOC);
printf("  capacity invariant   0 synthetic blocks over their own maxStudents; fullest is block %d at %s of %s\n",
    (int) $fullest['blockId'], (int) $fullest['held'], (int) $fullest['maxStudents']);
printf("  carried from source  %s block(s) already hold more seats than they declare (real data, not seeded)\n",
    (int) $carried[0]);
echo "\nnow weigh the queries: php storage/app/scratch/lane164-measure-scale-queries.php --schema={$target}\n";
