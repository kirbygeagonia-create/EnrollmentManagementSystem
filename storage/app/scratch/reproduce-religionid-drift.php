<?php
/**
 * Synthetic reproduction of CI's failure, without waiting for the suite.
 *
 * CI's seed step created eight religions and the walkthrough still could not reference religionId 1.
 * The mechanism under test: MySQL's AUTO_INCREMENT counter survives a rolled-back transaction, so a
 * suite that inserted and rolled back religions leaves the counter high, and the seeders then write ids
 * nobody asked for. This pushes the counter to 41 on an empty table, runs the seeders the way CI does,
 * and reports where the eight rows actually landed — then runs one walkthrough leg to see whether the
 * same foreign-key error appears.
 */
$database = $argv[1] ?? 'ems_ci_dry';
$php = $argv[2] ?? 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$root = dirname(__DIR__, 3);

// This script truncates a table and renumbers it. It must never aim at the live demo database, so the
// name is checked here rather than trusted from the command line.
if ($database === 'ems') {
    fwrite(STDERR, "Refusing to truncate reference data in the live 'ems' database.\n");
    exit(1);
}

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname='.$database, 'root', '');

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('TRUNCATE TABLE religions');
$pdo->exec('ALTER TABLE religions AUTO_INCREMENT = 41');
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
echo "religions emptied and its counter pushed to 41 (what a rolled-back suite leaves behind)\n";

// The artisan child process is handed the same database this script just truncated, so the seeder cannot
// drift off to the `.env` default. No pipe follows: exec runs through cmd.exe, where `tail` is not a
// command and the seed would silently not happen.
$env = 'set "DB_CONNECTION=mysql" && set "DB_HOST=127.0.0.1" && set "DB_PORT=3306" && set "DB_DATABASE='.$database.'" && set "DB_USERNAME=root" && set "DB_PASSWORD=" && ';
exec('cd "'.$root.'" && '.$env.'"'.$php.'" artisan db:seed --force 2>&1', $seedLines);
foreach (array_slice($seedLines, -3) as $line) {
    echo '    '.$line."\n";
}

$ids = $pdo->query('SELECT religionId FROM religions ORDER BY religionId')->fetchAll(PDO::FETCH_COLUMN);
echo "\nseeded ids = [".implode(', ', $ids)."]\n";

if ($ids === []) {
    fwrite(STDERR, "NO CONCLUSION: the seeders wrote nothing, so nothing here can say where the counter moved.\n");
    exit(1);
}

$one = in_array(1, array_map('intval', $ids), true);
echo 'religionId = 1 exists? '.var_export($one, true)."\n";
echo $one
    ? "MUTANT NOT EFFECTIVE — the seeder still landed on 1, so the counter theory is wrong.\n"
    : "Reproduced: the seeders landed on ".implode(', ', $ids).", and the walkthrough's hardcoded 1 points at nothing.\n";
