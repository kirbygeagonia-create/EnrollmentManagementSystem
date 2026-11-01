<?php
/**
 * Rehearsal for the withdrawn active-seat key: does every fixture file that the key once refused now run clean on
 * the driver that can hold it?
 *
 * Why this exists as a file rather than a shell loop: the loop that first ran this question died after its first
 * file, and the log ended with a `DeadlockException` and no footer. A truncated log is not a pass, and a line that
 * names one file cannot say anything about the other seven. So this driver runs each file in its own child,
 * SEQUENTIALLY, requires each to print its own `Tests:` tally, and refuses the whole run rather than reporting a
 * verdict for a file it never measured.
 *
 * The connection is pinned on the child's environment, not only the database name: naming a MySQL schema without
 * `DB_CONNECTION=mysql` makes Laravel open a SQLite file named after it, which produces errors that look like code
 * regressions (lane 87's rule, met by this repository twice).
 *
 * SQLite cannot be the judge here at all: it compiles no `ALTER TABLE … ADD COLUMN … GENERATED`, so the seat key
 * never existed in the suite's `:memory:` schema and its absence there proves nothing. MySQL is the only surface
 * where the withdrawal is visible, and CI's MySQL job is where the eight files are actually weighed.
 *
 * Usage: php storage/app/scratch/lane174-rehearsal-seat-key-withdrawal.php [--schema=ems_l174_a] [--keep]
 */

$schema = 'ems_l174_a';
$keep = false;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--schema=')) {
        $schema = substr($arg, 7);
        continue;
    }
    if ($arg === '--keep') {
        $keep = true;
        continue;
    }
    fwrite(STDERR, "REFUSED: this tool does not read the flag {$arg}\n");
    exit(1);
}

if (! preg_match('/^ems_l174_[a-z]$/', $schema)) {
    fwrite(STDERR, "REFUSED: --schema={$schema} is not one of the ems_l174_[a-z] scratch schemas. This rehearsal "
        ."creates and drops a schema; it will not be pointed at live `ems`.\n");
    exit(1);
}

$env = parse_env();
$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$dsn = "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$schema}";
$pdo = new PDO($dsn, $env['DB_USERNAME'], $env['DB_PASSWORD'] ?? '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$schema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "schema `{$schema}` ready\n";

/**
 * The files the key refused, plus the two that assert the withdrawal itself. Read as a list because a rehearsal of
 * six of the seven is a rehearsal that missed one, and the seventh is the file that would silently skip.
 */
$files = [
    'tests/Feature/Evaluation/RetakeAttemptTest.php',
    'tests/Feature/Evaluation/ElectiveValidationTest.php',
    'tests/Feature/Evaluation/AcademicStandingOwnershipTest.php',
    'tests/Feature/Evaluation/ShiftRequestFlowTest.php',
    'tests/Feature/Registrar/ClearanceWindowGateTest.php',
    'tests/Feature/Registrar/EnrollmentDropTest.php',
    'tests/Feature/Blocking/BlockCapacityAtomicityTest.php',
    'tests/Feature/Enrollment/DatabaseLevelEnrollmentRulesTest.php',
];

$bad = 0;
$deadlocks = [];

foreach ($files as $file) {
    $cmd = sprintf(
        'cd /d %s && set "DB_CONNECTION=mysql" && set "DB_DATABASE=%s" && %s artisan test %s --colors=never 2>&1',
        escapeshellarg($root), $schema, escapeshellarg($php), escapeshellarg($file)
    );
    $out = shell_exec($cmd);
    $flat = preg_replace('/\e\[[0-9;]*m/', '', (string) $out);

    // A run that printed no tally executed nothing, whatever its exit code says. This is the refusal, not advice.
    if (! preg_match('/Tests:\s+([^\r\n]+)/', $flat, $t)) {
        echo '  FAIL no-tally  '.$file."\n";
        echo '       '.trim(str_replace("\n", ' ', substr($flat, 0, 400)))."\n";
        $bad++;

        continue;
    }

    $isDeadlock = str_contains($flat, 'DeadlockException') || str_contains($flat, '1213');
    if ($isDeadlock) {
        $deadlocks[] = $file;
    }

    $failed = preg_match('/(Failed|⨯)/u', $flat) || preg_match('/Errors:\s+[1-9]/', $flat);
    printf("  %s %-58s Tests: %s%s\n", $failed ? 'FAIL' : 'ok  ', $file, trim($t[1]),
        $isDeadlock ? '   <-- DEADLOCK SEEN' : '');

    if ($failed) {
        $bad++;
        foreach (preg_split('/\n/', $flat) as $line) {
            if (preg_match('/(⨯|Failed|Error:|Exception:)/u', $line)) {
                echo '       '.trim($line)."\n";
            }
        }
    }
}

// The schema the last child left behind is the proof the withdrawal is what the fresh migration builds.
$idx = (int) $pdo->query('select count(*) from information_schema.STATISTICS where TABLE_SCHEMA = '
    .$pdo->quote($schema).' and INDEX_NAME = \'uq_enrollments_active_seat\'')->fetchColumn();
$col = (int) $pdo->query('select count(*) from information_schema.COLUMNS where TABLE_SCHEMA = '
    .$pdo->quote($schema).' and TABLE_NAME = \'enrollments\' and COLUMN_NAME = \'seatKey\'')->fetchColumn();
$fee = (int) $pdo->query('select count(*) from information_schema.STATISTICS where TABLE_SCHEMA = '
    .$pdo->quote($schema).' and INDEX_NAME = \'uq_studentassessments_enrollment\'')->fetchColumn();

printf("schema after the last fresh migration: seat index %d, seatKey column %d, fee-sheet index %d "
    ."(want 0, 0, 1)\n", $idx, $col, $fee);
if ($idx !== 0 || $col !== 0 || $fee !== 1) {
    $bad++;
}

if ($deadlocks !== []) {
    echo "\nDEADLOCK NOTE: ".count($deadlocks).' file(s) met a DeadlockException: '.implode(', ', $deadlocks)."\n"
        ."  Each was run alone, so a deadlock here is not two suite processes colliding. Read `innodb_trx` and the\n"
        ."  retry surface in App\\Support\\RetryOnDeadlock before calling it the test's fault.\n";
}

if (! $keep) {
    $pdo->exec("DROP DATABASE `{$schema}`");
    echo "dropped `{$schema}` (--keep retains it)\n";
}

printf("\nVERDICT: %d file(s) rehearsed, %d problem(s).\n", count($files), $bad);
exit($bad === 0 ? 0 : 1);
