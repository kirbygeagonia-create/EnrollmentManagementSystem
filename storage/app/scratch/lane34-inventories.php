<?php

/**
 * Rebuild the three ground-truth inventories that `lane34-negative-claim-sweep.php` judges the document's
 * existential negatives against: routes, columns, permissions.
 *
 * Why this file exists: the sweep read `lane34-routes.json`, `lane34-columns.txt` and `lane34-permissions.txt`, and
 * nothing in the repository produced them — they were captured by hand on 2026-10-15 and never refreshed, while the
 * schema has moved since (`studentclearances.extendedDeadline` was dropped on 2026-10-23). A checker whose ground
 * truth is a ten-day-old manual note is not checking the build; it is checking a memory of it. So the inventories now
 * have one generator, and the sweep refuses to judge against a capture older than the newest migration it has to
 * describe.
 *
 * Read-only. It never writes to the database and it never touches the docx.
 *
 *   php storage/app/scratch/lane34-inventories.php [--allow-schema=<name>]
 */
$root = dirname(__DIR__, 3);
$scratch = $root.'/storage/app/scratch';

$opt = static function (string $name) use ($argv): ?string {
    foreach ($argv as $a) {
        if (str_starts_with($a, $name.'=')) {
            return substr($a, strlen($name) + 1);
        }
    }

    return null;
};

// `.env` is parsed line by line, as every other probe here does: `parse_ini_file()` throws a syntax warning on this
// repository's line 3 and hands back nothing, which a probe must never read as "the environment names no database".
$env = [];
foreach ((array) @file($root.'/.env') as $l) {
    if (preg_match('/^([A-Z0-9_]+)\s*=\s*(.*)$/u', trim((string) $l), $m) === 1) {
        $env[$m[1]] = trim(trim($m[2]), "\"'");
    }
}
$schema = $env['DB_DATABASE'] ?? '';
$allow = $opt('--allow-schema');
if ($allow !== null && $allow !== $schema) {
    printf("REFUSED: asked for schema %s but the environment answers %s.\n", $allow, $schema ?: '(nothing)');
    exit(1);
}
if ($allow === null && $schema !== 'ems') {
    printf("REFUSED: this writes inventories off live rows and the environment points at %s; say --allow-schema=%s.\n",
        $schema ?: '(nothing)', $schema);
    exit(1);
}

// --- routes, from the running application (not from the route files) ---------
$routes = (string) shell_exec('cd '.escapeshellarg($root).' && '.escapeshellarg(PHP_BINARY).' artisan route:list --json 2>&1');
$json = json_decode($routes, true);
if (! is_array($json) || $json === []) {
    printf("REFUSED: `artisan route:list --json` answered no routes (%s) — an empty inventory cannot refute a claim.\n",
        substr(str_replace("\n", ' ', $routes), 0, 160));
    exit(1);
}

// --- columns and permissions, from the server --------------------------------
try {
    $pdo = new PDO('mysql:host='.($env['DB_HOST'] ?? '127.0.0.1').';dbname='.$schema,
        $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '');
} catch (Throwable $e) {
    printf("REFUSED: cannot read %s (%s).\n", $schema, $e->getMessage());
    exit(1);
}
$cols = $pdo->query('select concat(TABLE_NAME, \'.\', COLUMN_NAME) c from information_schema.COLUMNS
    where TABLE_SCHEMA = '.quote($schema).' order by c')->fetchAll(PDO::FETCH_COLUMN);
if ($cols === []) {
    printf("REFUSED: information_schema answered no columns for %s.\n", $schema);
    exit(1);
}
try {
    $perms = $pdo->query('select name from permissions order by name')->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    exit("REFUSED: this schema has no readable `permissions` table, so a permission claim could not be weighed.\n");
}
if ($perms === []) {
    exit("REFUSED: `permissions` holds no rows — an empty permission inventory would clear every permission claim.\n");
}

file_put_contents($scratch.'/lane34-routes.json', json_encode($json, JSON_PRETTY_PRINT));
file_put_contents($scratch.'/lane34-columns.txt', implode("\n", $cols)."\n");
file_put_contents($scratch.'/lane34-permissions.txt', implode("\n", $perms)."\n");
file_put_contents($scratch.'/lane34-inventory-meta.json', json_encode([
    'generated_at' => gmdate('Y-m-d H:i:s\Z'),
    'schema' => $schema,
    'routes' => count($json),
    'columns' => count($cols),
    'permissions' => count($perms),
], JSON_PRETTY_PRINT)."\n");

printf("inventories rebuilt from %s: %d route(s), %d column(s), %d permission(s)\n",
    $schema, count($json), count($cols), count($perms));
printf("meta at storage/app/scratch/lane34-inventory-meta.json (the sweep refuses a capture older than the newest migration)\n");
exit(0);

/** Quote a literal for the server without an empty-string escape hazard. */
function quote(string $v): string
{
    return "'".str_replace("'", "''", $v)."'";
}
