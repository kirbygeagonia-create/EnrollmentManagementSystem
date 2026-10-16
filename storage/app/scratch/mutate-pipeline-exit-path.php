<?php
/**
 * Prove the new exception handler changes the exit status, without leaving a write behind.
 *
 * Before this batch the tool printed "Attempt to read property studentId on null" and exited 0. The throw is
 * planted immediately AFTER the precondition check, so the run reaches the handler before any section has
 * written — and the row counts around the run are checked to prove that. The file is held in memory and
 * restored byte-for-byte afterwards, because leaving a planted throw in a seeder would be worse than the bug.
 */
$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$tool = $root.'/tools/seed_full_demo_pipeline.php';
$database = 'ems'; // the install where the preconditions pass, so execution reaches the planted throw

$original = file_get_contents($tool);
$anchor = "if (\$absent !== []) {";
$mutant = "throw new RuntimeException('MUTANT: a failure must not exit 0');\nif (\$absent !== []) {";

if (substr_count($original, $anchor) !== 1) {
    fwrite(STDERR, "ABORT: the anchor for the planted throw is not unique\n");
    exit(1);
}

$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname='.$database, 'root', '');
$count = function () use ($pdo): int {
    $tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    $sum = 0;
    foreach ($tables as $t) {
        $sum += (int) $pdo->query('SELECT COUNT(*) FROM `'.$t.'`')->fetchColumn();
    }

    return $sum;
};

file_put_contents($tool, str_replace($anchor, $mutant, $original));
if (file_get_contents($tool) === $original) {
    fwrite(STDERR, "ABORT: the mutation did not land\n");
    file_put_contents($tool, $original);
    exit(1);
}
echo "mutant planted after the precondition check\n";

$before = $count();
exec('cd "'.$root.'" && "'.$php.'" tools/seed_full_demo_pipeline.php 2>&1', $lines, $code);
$after = $count();

foreach (array_slice($lines, -4) as $l) {
    echo '  '.$l."\n";
}

file_put_contents($tool, $original);
$restored = file_get_contents($tool) === $original;

printf("exit code with the planted throw: %d\n", $code);
printf("rows before=%d after=%d (%s)\n", $before, $after, $before === $after ? 'no write reached' : 'WROTE SOMETHING');
echo 'file restored byte-identical: '.var_export($restored, true)."\n";

$ok = $code === 1 && $before === $after && $restored;
echo $ok ? "VERDICT: a thrown failure now exits 1\n" : "VERDICT: something did not hold — check the three lines above\n";
exit($ok ? 0 : 1);
