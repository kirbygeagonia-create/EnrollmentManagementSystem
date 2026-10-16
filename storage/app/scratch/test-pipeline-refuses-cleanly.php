<?php
/**
 * Does the new precondition refuse before writing anything?
 *
 * The claim being tested is the one printed in the refusal itself — "Nothing was written." A count of every
 * table before the run and after is the only honest way to check it, because the tool previously wrote Juan's
 * payment, a block and several schedules before dying on Liza.
 */
$database = $argv[1] ?? 'ems_fresh';
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$root = dirname(__DIR__, 3);

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname='.$database, 'root', '');

$snapshot = function () use ($pdo): array {
    $tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    $out = [];
    foreach ($tables as $t) {
        $out[$t] = (int) $pdo->query('SELECT COUNT(*) FROM `'.$t.'`')->fetchColumn();
    }

    return $out;
};

$before = $snapshot();
printf("tables counted before: %d, total rows: %d\n", count($before), array_sum($before));
printf("presumed students present: 001=%s 004=%s\n",
    $pdo->query("SELECT COUNT(*) FROM students WHERE schoolIdNumber='DEMO-2026-001'")->fetchColumn(),
    $pdo->query("SELECT COUNT(*) FROM students WHERE schoolIdNumber='DEMO-2026-004'")->fetchColumn());

$cmd = 'cd "'.$root.'" && set "DB_CONNECTION=mysql" && set "DB_HOST=127.0.0.1" && set "DB_PORT=3306"'
    .' && set "DB_DATABASE='.$database.'" && set "DB_USERNAME=root" && set "DB_PASSWORD=" && "'.$php.'" tools/seed_full_demo_pipeline.php 2>&1';

exec($cmd, $lines, $code);
echo "--- tool output (last 12 lines) ---\n";
foreach (array_slice($lines, -12) as $l) {
    echo '  '.$l."\n";
}
echo "exit code: {$code}\n";

$after = $snapshot();
printf("tables counted after: %d, total rows: %d\n", count($after), array_sum($after));

$changed = [];
foreach ($after as $t => $n) {
    $b = $before[$t] ?? 0;
    if ($n !== $b) {
        $changed[] = $t.": {$b} -> {$n}";
    }
}

if ($changed === []) {
    echo "no table changed size — the refusal really wrote nothing\n";
} else {
    echo "WROTE DESPITE REFUSING:\n";
    foreach ($changed as $c) {
        echo '  '.$c."\n";
    }
}

exit($code === 1 && $changed === [] ? 0 : 1);
