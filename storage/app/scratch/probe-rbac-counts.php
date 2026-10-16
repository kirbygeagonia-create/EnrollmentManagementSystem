<?php

/**
 * Counts what RbacSeeder declares and what the live ems tables actually hold, so the
 * permission figures quoted in the authority document are re-read rather than remembered.
 * The seeder prunes permissions it no longer declares, so a difference between the two is a
 * deployment that has not been reseeded — not something to reconcile by editing numbers.
 */

$root = dirname(__DIR__, 3);
$seeder = file_get_contents($root.'/database/seeders/RbacSeeder.php');

// The declared permission map: 'module' => ['name', ...] inside $permissions = [ ... ];
if (! preg_match('/\$permissions = \[(.*?)\n        \];/s', $seeder, $block)) {
    fwrite(STDERR, "cannot find the \$permissions array\n");
    exit(1);
}

$body = $block[1];
preg_match_all("/^\s+'([a-z]+)' => \[/m", $body, $modules);
preg_match_all("/'([a-z]+\.[A-Za-z]+)'/", $body, $names);

$declaredNames = array_values(array_unique($names[1]));
sort($declaredNames);

printf("first \$permissions block: %d module keys, %d quoted names\n", count(array_unique($modules[1])), count($declaredNames));

// Every permission-looking literal in the whole file, because later passes appended rights in
// more than one place and a count read from one block understates the catalogue.
preg_match_all("/'([a-z][a-z]*\.[A-Za-z][A-Za-z]*(?:\.[A-Za-z][A-Za-z]*)*)'/", $seeder, $all);
$everyName = array_values(array_unique($all[1]));
sort($everyName);
$prefixes = array_values(array_unique(array_map(fn (string $n): string => explode('.', $n)[0], $everyName)));
sort($prefixes);

printf("whole file: %d distinct permission literals across %d prefixes\n", count($everyName), count($prefixes));
echo "prefixes: ", implode(', ', $prefixes), "\n";

$dsn = 'mysql:host=127.0.0.1;dbname=ems;charset=utf8mb4';
try {
    $pdo = new PDO($dsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    echo "live ems not reachable: ", $e->getMessage(), "\n";
    exit(0);
}

$live = $pdo->query('SELECT name FROM permissions ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
$roles = (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn();
$grants = (int) $pdo->query('SELECT COUNT(*) FROM role_permissions')->fetchColumn();
$modulesLive = $pdo->query('SELECT DISTINCT SUBSTRING_INDEX(name, ".", 1) FROM permissions')->fetchAll(PDO::FETCH_COLUMN);
sort($modulesLive);

printf("live ems holds %d permissions in %d modules, %d roles, %d grants\n", count($live), count($modulesLive), $roles, $grants);

$missing = array_diff($declaredNames, $live);
$extra = array_diff($live, $declaredNames);

printf("declared but absent from ems: %d%s", count($missing), $missing ? "\n  - ".implode("\n  - ", $missing) : "\n");
printf("\nlive but not declared (the seeder would prune these): %d%s", count($extra), $extra ? "\n  - ".implode("\n  - ", $extra) : "\n");
