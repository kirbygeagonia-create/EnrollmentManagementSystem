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
    echo "source counts (never written by this tool): ".json_encode($counts)."\n";
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
$blockIds = $dst->query('select blockId from blocks order by blockId')->fetchAll(PDO::FETCH_COLUMN);
if ($term === 0 || $course === 0 || $subjectIds === []) {
    fwrite(STDERR, "REFUSED: the scale schema names no term, course or subject — the seed would invent parents.\n");
    exit(1);
}

$started = microtime(true);
$dst->beginTransaction();
$inserted = 0;
$st = $dst->prepare('insert into students (schoolIdNumber, lastName, firstName, middleName, suffix, gender,
    birthdate, birthplace, citizenship, civilStatus, religionId, contactNumber, email, username, passwordHash,
    admissionType, status, semestersCompleted, yearsInInstitution, created_at, updated_at) values
    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');

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
        'regular',
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
// `enrolledsubjects` carries no units column and none of the eight record-timestamp tables, so the insert names
// only the columns the table really holds — a guessed column is a 1054 mid-seed that proves nothing about the query.
$lines = 0;
$enSt = $dst->prepare('insert into enrolledsubjects (enrollmentId, subjectId, blockId, status) values (?,?,?,?)');

for ($sid = 1; $sid <= $maxStudent; $sid++) {
    $en->execute([$sid, $course, $term, ($sid % 4) + 1, 'firstYear', 'new', 'enrolled', $signer, date('Y-m-d')]);
    $enrollmentId = (int) $dst->lastInsertId();
    foreach (array_slice($subjectIds, 0, 5) as $sub) {
        $enSt->execute([$enrollmentId, $sub, $blockIds === [] ? null : $blockIds[$lines % count($blockIds)],
            'confirmed']);
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
echo "\nnow weigh the queries: php storage/app/scratch/lane164-measure-scale-queries.php --schema={$target}\n";
