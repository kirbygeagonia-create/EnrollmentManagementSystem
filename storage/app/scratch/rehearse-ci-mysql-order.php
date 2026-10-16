<?php
/**
 * Rehearsal of the exact CI order, with the auto-increment counters printed at each hand-off.
 *
 * The first run of the MySQL walkthrough step failed on `students.religionid_foreign` even though the
 * seed step before it created eight religions. The suspected mechanism is that the suite step ran
 * RefreshDatabase against the same schema: rolled-back inserts still consume AUTO_INCREMENT values, so
 * the seeders land on ids nobody asked for and the walkthrough's `religionId => 1` points at a row that
 * does not exist. This script performs the three steps in CI's order and reports the counter and the
 * actual ids at each boundary, so the claim is measured rather than argued.
 *
 * Usage: php storage/app/scratch/rehearse-ci-mysql-order.php <database> [php-binary]
 */
$database = $argv[1] ?? 'ems_ci_dry';
$php = $argv[2] ?? 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$root = dirname(__DIR__, 3);

// Step 1 recreates every table in the named schema, so the name is checked rather than trusted.
if ($database === 'ems') {
    fwrite(STDERR, "Refusing to run migrate:fresh against the live 'ems' database.\n");
    exit(1);
}

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname='.$database, 'root', '');

function state(PDO $pdo, string $label): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM religions')->fetchColumn();
    $next = $pdo->query(
        "SELECT AUTO_INCREMENT FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'religions'"
    )->fetchColumn();
    $ids = $pdo->query('SELECT religionId FROM religions ORDER BY religionId')->fetchAll(PDO::FETCH_COLUMN);
    echo str_pad($label, 36).' rows='.$count
        .'  next_auto_inc='.($next === null ? 'null' : (string) $next)
        .'  ids=['.implode(',', $ids)."]\n";
}

/**
 * Run a command through the shell and print only its last lines.
 *
 * The truncation happens in PHP, not in the shell: `passthru` here goes through cmd.exe, where `tail` is
 * not a command, and a pipeline to a missing binary would let a step look like it ran when it never did.
 */
function step(string $label, string $command, int $tail = 6): void
{
    echo "\n==> ".$label."\n";
    exec($command.' 2>&1', $lines, $code);
    foreach (array_slice($lines, -$tail) as $line) {
        echo '    '.$line."\n";
    }
    if ($code !== 0) {
        fwrite(STDERR, $label.' failed with exit '.$code." — nothing after this point is proven.\n");
        exit($code);
    }
}

/**
 * A `NAME=VALUE` prefix is shell syntax this script does not run through.
 *
 * exec()/passthru() here go to cmd.exe, where the POSIX form is parsed as a program name and fails, so
 * the variables are set the way that shell expects. The `set "K=V"` quoting leaves an empty password
 * genuinely empty instead of swallowing the next token.
 */
function envPrefix(array $vars): string
{
    $out = '';
    foreach ($vars as $name => $value) {
        $out .= 'set "'.strtoupper($name).'='.$value.'" && ';
    }

    return $out;
}

$env = envPrefix([
    'db_connection' => 'mysql',
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_database' => $database,
    'db_username' => 'root',
    'db_password' => '',
    'e2e_db_database' => $database,
]);
$cmd = 'cd "'.$root.'" && '.$env.'"'.$php.'" ';

state($pdo, 'before anything');

step('CI step 1 — the whole suite against MySQL', $cmd.'vendor/bin/phpunit --log-junit storage/app/scratch/junit-mysql-rehearsal.xml', 8);
state($pdo, 'after the suite (rows rolled back)');

step('CI step 2a — migrate', $cmd.'artisan migrate --force', 3);
step('CI step 2b — db:seed', $cmd.'artisan db:seed --force', 3);
step('CI step 2c — DevReferenceDataSeeder', $cmd.'artisan db:seed --class=DevReferenceDataSeeder --force', 3);
state($pdo, 'after the seeders');

$rows = (int) $pdo->query('SELECT COUNT(*) FROM religions')->fetchColumn();
if ($rows === 0) {
    fwrite(STDERR, "NO CONCLUSION: the seeders wrote no religions, so the ids below prove nothing about the counter.\n");
    exit(1);
}
$one = (bool) $pdo->query('SELECT COUNT(*) FROM religions WHERE religionId = 1')->fetchColumn();
echo "\nreligionId = 1 exists after seeding?  ".var_export($one, true)."\n";

step('CI step 3 — the walkthrough', $cmd.'vendor/bin/phpunit tests/Feature/E2E --fail-on-skipped', 14);
state($pdo, 'after the walkthrough');
