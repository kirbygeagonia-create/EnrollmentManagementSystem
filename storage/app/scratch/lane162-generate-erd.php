<?php

/**
 * ADS presentation item 1 asks for an ERD. A hand-drawn one is a second definition of the schema and rots the
 * moment a migration lands, so this generates it from the server: relationships come from
 * `information_schema.KEY_COLUMN_USAGE`, cardinality from whether the referencing column set is UNIQUE, and the
 * domain/framework split is DERIVED from the bundled `0001_` migrations plus `migrations` — lane 60's rule that a
 * partition is never recalled.
 *
 * It writes nothing to the database and refuses when the server answers no foreign keys, because an empty diagram
 * drawn confidently is exactly the fake-clean verdict this repository keeps meeting.
 *
 * Usage: php storage/app/scratch/lane162-generate-erd.php [--schema=ems] [--out=<file>] [--mermaid-only]
 */
$root = dirname(__DIR__, 3);
$known = ['schema', 'out', 'mermaid-only'];
$schema = null;
$out = null;
$mermaidOnly = false;

for ($i = 1; $i < $argc; $i++) {
    if (! preg_match('/^--([a-z0-9-]+)(=(.*))?$/', $argv[$i], $m)) {
        fwrite(STDERR, "REFUSED: unreadable argument {$argv[$i]}\n");
        exit(1);
    }
    if (! in_array($m[1], $known, true)) {
        fwrite(STDERR, "REFUSED: unknown flag --{$m[1]} — this generator does not read it\n");
        exit(1);
    }
    if ($m[1] === 'schema') {
        $schema = (string) ($m[3] ?? '');
    } elseif ($m[1] === 'out') {
        $out = (string) ($m[3] ?? '');
    } elseif ($m[1] === 'mermaid-only') {
        $mermaidOnly = true;
    }
}

$env = [];
foreach (file($root.'/.env') as $l) {
    if (preg_match('/^([A-Z_]+)=(.*)$/', trim($l), $m)) {
        $env[$m[1]] = $m[2];
    }
}
$schema = $schema !== null && $schema !== '' ? $schema : ($env['DB_DATABASE'] ?? 'ems');

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname='.$schema, $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? null);
} catch (Throwable $e) {
    fwrite(STDERR, 'REFUSED: cannot open schema '.$schema." — ".$e->getMessage()."\n");
    exit(1);
}

// The framework half is read off the migrations Laravel ships, not off a remembered list.
$framework = ['migrations'];
foreach (glob($root.'/database/migrations/0001_*.php') as $f) {
    preg_match_all('/Schema::create\(\s*[\'"](\w+)[\'"]/', (string) file_get_contents($f), $m);
    foreach ($m[1] as $t) {
        $framework[] = $t;
    }
}
$framework = array_values(array_unique($framework));

$tables = $pdo->query('select TABLE_NAME from information_schema.TABLES where TABLE_SCHEMA = '
    .$pdo->quote($schema).' and TABLE_TYPE = "BASE TABLE" order by TABLE_NAME')->fetchAll(PDO::FETCH_COLUMN);
if ($tables === []) {
    fwrite(STDERR, "REFUSED: schema {$schema} answers no base tables — the diagram would be empty by reading nothing.\n");
    exit(1);
}
$domain = array_values(array_diff($tables, $framework));

$fks = $pdo->query('select CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    from information_schema.KEY_COLUMN_USAGE where TABLE_SCHEMA = '.$pdo->quote($schema).'
    and REFERENCED_TABLE_NAME is not null order by TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION')
    ->fetchAll(PDO::FETCH_ASSOC);
if ($fks === []) {
    fwrite(STDERR, "REFUSED: schema {$schema} answers no foreign keys — a relationship diagram of zero edges is not a measurement.\n");
    exit(1);
}

// Which column sets are unique, so an edge can be drawn 1:1 instead of 1:N by guess.
$uniqRows = $pdo->query('select TABLE_NAME, COLUMN_NAME, NON_UNIQUE from information_schema.STATISTICS
    where TABLE_SCHEMA = '.$pdo->quote($schema).' and NON_UNIQUE = 0')->fetchAll(PDO::FETCH_ASSOC);
$uniqueSets = [];
foreach ($uniqRows as $r) {
    $uniqueSets[$r['TABLE_NAME'].'/'.$r['COLUMN_NAME']] = true;
}

$edges = [];
foreach ($fks as $f) {
    if (! in_array($f['TABLE_NAME'], $domain, true) || ! in_array($f['REFERENCED_TABLE_NAME'], $domain, true)) {
        continue;
    }
    $card = isset($uniqueSets[$f['TABLE_NAME'].'/'.$f['COLUMN_NAME']]) ? '||--||' : '||--o{';
    $edges[] = sprintf('    %s %s %s : "%s"',
        $f['REFERENCED_TABLE_NAME'], $card, $f['TABLE_NAME'], $f['COLUMN_NAME']);
}
$edges = array_values(array_unique($edges));

$mermaid = "```mermaid\nerDiagram\n".implode("\n", $mermaidOnly ? $edges : $edges)."\n```";

$counts = $pdo->query('select TABLE_NAME, count(*) c from information_schema.COLUMNS where TABLE_SCHEMA = '
    .$pdo->quote($schema).' group by TABLE_NAME')->fetchAll(PDO::FETCH_KEY_PAIR);

$body = '';
if (! $mermaidOnly) {
    $body = "\n\n## Partition read from the server\n\n"
        ."- tables in `{$schema}`: **".count($tables)."** — domain **".count($domain)."**, framework **".count($framework)."** "
        ."(the `0001_` migrations plus `migrations`, derived from the migration files rather than recalled)\n"
        ."- foreign keys: **".count($fks)."**; edges drawn between domain tables: **".count($edges)."**\n"
        ."- domain columns: **".array_sum(array_intersect_key($counts, array_flip($domain)))."**\n\n"
        ."## Domain tables and their keys\n\n| table | columns | primary key | unique keys |\n|---|---|---|---|\n";

    $pk = $pdo->query('select TABLE_NAME, COLUMN_NAME from information_schema.KEY_COLUMN_USAGE where TABLE_SCHEMA = '
        .$pdo->quote($schema)." and CONSTRAINT_NAME = 'PRIMARY' order by TABLE_NAME, ORDINAL_POSITION")->fetchAll(PDO::FETCH_GROUP);
    $uq = [];
    foreach ($uniqRows as $r) {
        $uq[$r['TABLE_NAME']][] = $r['COLUMN_NAME'];
    }
    foreach ($domain as $t) {
        $body .= sprintf("| %s | %d | %s | %s |\n", $t, (int) ($counts[$t] ?? 0),
            implode('+', array_column($pk[$t] ?? [], 'COLUMN_NAME')) ?: '-',
            (string) count($uq[$t] ?? []));
    }
}

$text = $mermaid.$body."\n";
if ($out !== null && $out !== '') {
    if (file_put_contents($out, $text) === false) {
        fwrite(STDERR, "REFUSED: could not write {$out}\n");
        exit(1);
    }
    printf("wrote %s (%d bytes)\n", $out, strlen($text));
}

printf("schema %s: %d tables (%d domain, %d framework), %d foreign keys, %d domain edges drawn\n",
    $schema, count($tables), count($domain), count($framework), count($fks), count($edges));
