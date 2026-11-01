<?php

/**
 * ADS Problem 21 and presentation item 3 (Durability): prove the recovery path, not the backup file.
 *
 * The guide says plainly that creating a backup file does not prove the system can recover, and this repository
 * had only ever written the commands down — the nightly job and the restore drill are unchecked boxes in
 * `Documentation/First_Build_Plan.md`. This tool dumps live `ems` and restores it into a scratch schema, then
 * compares the two table by table, so "committed information is protected" becomes a reading rather than a claim.
 *
 * It never writes to the source schema. The dump goes to `C:\Users\ADMIN\ems-backups` (a database dump never
 * enters the repository), the restore target must be a scratch name, and `mysqldump` is run WITHOUT
 * `--databases`, because with it mysqldump emits the drop as an executable version comment that a hand-edited
 * file still runs — that is how live `ems` was destroyed once already.
 *
 * Usage:
 *   php tools/ems-backup-drill.php [--target=ems_drill] [--keep] [--tables=20]
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "REFUSED: this is a command-line tool.\n");
    exit(1);
}

$root = dirname(__DIR__);
$known = ['target', 'keep', 'tables', 'help'];
$target = 'ems_drill';
$keep = false;
$tableLimit = 0;

for ($i = 1; $i < $argc; $i++) {
    if (! preg_match('/^--([a-z0-9-]+)(=(.*))?$/', $argv[$i], $m)) {
        fwrite(STDERR, "REFUSED: unreadable argument {$argv[$i]}\n");
        exit(1);
    }
    if (! in_array($m[1], $known, true)) {
        fwrite(STDERR, "REFUSED: unknown flag --{$m[1]} — this tool does not read it\n");
        exit(1);
    }
    if ($m[1] === 'target') {
        $target = (string) ($m[3] ?? '');
    } elseif ($m[1] === 'keep') {
        $keep = true;
    } elseif ($m[1] === 'tables') {
        $tableLimit = (int) ($m[3] ?? 0);
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
$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = $env['DB_PORT'] ?? '3306';

// The names a scratch tool may never touch: the live schema and the four foreign projects this box also hosts.
$forbidden = ['ems', 'information_schema', 'mysql', 'performance_schema', 'sys',
    'school_database', 'hospital_database', 'library_database', 'warehouse_database', 'inventory_database'];
if (in_array(strtolower($target), $forbidden, true) || $target === '') {
    fwrite(STDERR, "REFUSED: --target=".($target === '' ? '(empty)' : $target).
        " names a schema this drill may not write. Name a scratch schema.\n");
    exit(1);
}

$mysql = 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe';
$mysqldump = 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysqldump.exe';
foreach (['mysql' => $mysql, 'mysqldump' => $mysqldump] as $label => $bin) {
    if (! is_file($bin)) {
        fwrite(STDERR, "REFUSED: {$label} is not at {$bin} — exec() runs cmd.exe, so a Git-Bash path is invisible here.\n");
        exit(1);
    }
}

$backupDir = 'C:\\Users\\ADMIN\\ems-backups';
if (! is_dir($backupDir)) {
    fwrite(STDERR, "REFUSED: {$backupDir} does not exist — dumps live outside the repository by rule, so the folder must already be there.\n");
    exit(1);
}

$stamp = date('Ymd-His');
$dump = $backupDir.'\\ems-drill-'.$stamp.'.sql';
$auth = '-u '.$user.($pass === '' ? '' : ' -p'.$pass).' -h '.$host.' -P '.$port;

echo "ADS P21 recovery drill — source {$source} → scratch schema {$target}\n\n";

// Durability is a configuration claim, so it is read from the server rather than asserted.
$pdo = new PDO('mysql:host='.$host.';port='.$port.';dbname='.$source, $user, $pass);
$durability = [];
foreach ($pdo->query("select VARIABLE_NAME, VARIABLE_VALUE from performance_schema.session_variables
    where VARIABLE_NAME in ('innodb_flush_log_at_trx_commit','sync_binlog','autocommit')")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $durability[$r['VARIABLE_NAME']] = trim((string) $r['VARIABLE_VALUE']);
    printf("  %-32s %s\n", $r['VARIABLE_NAME'], $durability[$r['VARIABLE_NAME']]);
}

$cmd = escapeshellarg($mysqldump)." {$auth} --single-transaction --routines --triggers --no-create-db ".
    '--no-tablespaces '.escapeshellarg($source).' > '.escapeshellarg($dump);
exec($cmd.' 2>&1', $out, $code);
if ($code !== 0 || ! is_file($dump) || filesize($dump) < 1024) {
    fwrite(STDERR, 'REFUSED: the dump did not land (exit '.$code.', bytes '.
        (is_file($dump) ? filesize($dump) : 0).")\n  ".implode("\n  ", array_slice($out, 0, 6))."\n");
    exit(1);
}
$dumpBytes = filesize($dump);
$dumpText = (string) file_get_contents($dump);
if (stripos($dumpText, 'DROP DATABASE') !== false) {
    fwrite(STDERR, "REFUSED: the dump carries a DROP DATABASE — restoring it would destroy a schema. Check the mysqldump flags.\n");
    exit(1);
}
foreach (['CREATE TABLE `students`', 'CREATE TABLE `enrollments`'] as $must) {
    if (strpos($dumpText, $must) === false) {
        fwrite(STDERR, "REFUSED: the dump holds no {$must} — it is not a backup of the schema it names.\n");
        exit(1);
    }
}
printf("\ndump: %s bytes (%.1f MB), no DROP DATABASE, the domain tables are present\n",
    number_format($dumpBytes), $dumpBytes / 1048576);

exec(escapeshellarg($mysql)." {$auth} -e ".escapeshellarg("CREATE DATABASE IF NOT EXISTS `{$target}` CHARACTER SET utf8mb4").' 2>&1', $c, $code);
if ($code !== 0) {
    fwrite(STDERR, "REFUSED: could not create the scratch schema {$target}\n  ".implode("\n  ", $c)."\n");
    exit(1);
}

// The dump was taken without --databases, so it contains no USE statement and lands wherever the client is told.
$restore = escapeshellarg($mysql)." {$auth} ".escapeshellarg($target).' < '.escapeshellarg($dump).' 2>&1';
exec($restore, $r, $code);
if ($code !== 0) {
    fwrite(STDERR, "REFUSED: the restore exited {$code}\n  ".implode("\n  ", array_slice($r, 0, 8))."\n");
    exit(1);
}

$drill = new PDO('mysql:host='.$host.';port='.$port.';dbname='.$target, $user, $pass);

$tables = $pdo->query('select TABLE_NAME from information_schema.TABLES where TABLE_SCHEMA = '
    .$pdo->quote($source).' and TABLE_TYPE = "BASE TABLE" order by TABLE_NAME')->fetchAll(PDO::FETCH_COLUMN);
if ($tableLimit > 0) {
    $tables = array_slice($tables, 0, $tableLimit);
}

$missing = [];
$differing = [];
$checks = 0;
foreach ($tables as $t) {
    $from = (int) $pdo->query("select count(*) from `{$source}`.`{$t}`")->fetchColumn();
    $into = $drill->query("select count(*) from `{$t}`");
    if ($into === false) {
        $missing[] = $t;

        continue;
    }
    $to = (int) $into->fetchColumn();
    $checks++;
    if ($to !== $from) {
        $differing[] = $t.' source='.$from.' restored='.$to;
    }
}

printf("\nrestored into %s and compared %d table(s) row for row\n", $target, $checks);
if ($missing !== []) {
    echo '  MISSING: '.implode(', ', $missing)."\n";
}
if ($differing !== []) {
    echo '  ROW COUNT DIFFERS: '.implode('; ', $differing)."\n";
}

// A count that agrees can still be a different table, so weigh a few ordered selections as well.
$samples = [
    ['students', 'schoolIdNumber', 'lastName'],
    ['enrollments', 'enrollmentId', 'enrollmentStatus'],
    ['payments', 'paymentId', 'orNumber'],
];
// A GROUP_CONCAT checksum would have been the weaker instrument even when it parsed: `group_concat_max_len`
// defaults to 1024 bytes, so a table wider than that hashes its own prefix and calls the remainder identical.
// The rows are therefore read in key order and hashed here, with the hashed row count printed beside the digest.
$hashOf = static function (PDO $c, string $table, string $prefix, string $key, string $col): array {
    $rows = $c->query("select `{$key}`, `{$col}` from {$prefix}`{$table}` order by `{$key}`")->fetchAll(PDO::FETCH_ASSOC);
    $parts = [];
    foreach ($rows as $r) {
        $parts[] = $r[$key].':'.($r[$col] ?? 'NULL');
    }

    return [count($rows), md5(implode('|', $parts))];
};

foreach ($samples as [$t, $key, $col]) {
    if (! in_array($t, $tables, true)) {
        continue;
    }
    [$na, $ha] = $hashOf($pdo, $t, '`'.$source.'`.', $key, $col);
    [$nb, $hb] = $hashOf($drill, $t, '', $key, $col);
    $same = $na === $nb && $ha === $hb;
    printf("  ordered checksum %-14s rows %s/%s digest %s  %s\n", $t, number_format($na), number_format($nb),
        substr($ha, 0, 8), $same ? 'MATCHES' : 'DIFFERS');
    if (! $same) {
        $differing[] = $t.' ordered checksum differs';
    }
}

if (! $keep) {
    exec(escapeshellarg($mysql)." {$auth} -e ".escapeshellarg("DROP DATABASE `{$target}`").' 2>&1', $d, $code);
    printf("\nscratch schema dropped (pass --keep to leave %s in place): exit %d\n", $target, $code);
} else {
    printf("\n--keep: %s still holds the restored copy; retire it only through drop-retired-scratch-databases.php\n", $target);
}

$problems = count($missing) + count($differing);
printf("\nVERDICT: %d table(s) missing, %d row-count or checksum difference(s), dump %s bytes, restore exit 0.\n",
    count($missing), count($differing), number_format($dumpBytes));

exit($problems === 0 ? 0 : 1);
