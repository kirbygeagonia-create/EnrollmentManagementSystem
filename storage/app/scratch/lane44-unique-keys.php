<?php

/**
 * Lane 44 — is §23.6.1's "complete list" of enforced uniqueness actually complete?
 *
 * §23.6.1 says: "Every unique index in the EMS domain tables, with the business rule each one stands in
 * for… Eight keys in seven tables. permissions and roles also carry unique keys ((name, guard_name)), but
 * those belong to the Spatie package." That is a claim about the server, stated as a closed set, so it can be
 * checked by asking the server.
 *
 * The document's scope is honoured rather than guessed: Spatie's own tables and the Laravel framework tables
 * are excluded because the sentence excludes them, and the exclusion is printed so a reader can disagree with
 * the scope instead of with the arithmetic.
 *
 * The list is read from the regenerated flat dump (storage/app/scratch/docx-flat.txt), which is what
 * dump-docx-flat.php writes from the docx — re-dump before trusting a green run.
 *
 * Usage: php storage/app/scratch/lane44-unique-keys.php
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

set_exception_handler(function (\Throwable $e): void {
    fwrite(STDERR, 'FATAL: '.$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n");
    exit(1);
});

$root = dirname(__DIR__, 3);
$dumpPath = $root.'/storage/app/scratch/docx-flat.txt';
$database = DB::connection()->getDatabaseName();

// An overridable dump, read rather than accepted: pass 41's harness learned that a flag the code ignores makes
// a mutant run look clean.
foreach ($argv ?? [] as $a) {
    if (str_starts_with($a, '--dump=')) {
        $dumpPath = substr($a, 7);
    }
}
if (! str_starts_with($dumpPath, '/') && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $dumpPath)) {
    $dumpPath = $root.'/'.$dumpPath;
}

if (! is_file($dumpPath)) {
    fwrite(STDERR, "no flat dump at {$dumpPath} — run dump-docx-flat.php first\n");
    exit(1);
}

/** Excluded by §23.6.1's own sentence, or because Laravel owns the table. */
$notDomain = [
    'permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions',
    'sessions', 'jobs', 'job_batches', 'failed_jobs', 'cache', 'cache_lines', 'cache_locks',
    'migrations', 'password_reset_tokens', 'users',
];

$rows = DB::select(
    "SELECT TABLE_NAME tbl, INDEX_NAME idx, NON_UNIQUE nu, SEQ_IN_INDEX seq, COLUMN_NAME col
     FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND INDEX_NAME <> 'PRIMARY'",
    [$database]
);

$unique = [];
foreach ($rows as $r) {
    if ((int) $r->nu !== 0) {
        continue;
    }
    $key = $r->tbl.'.'.$r->idx;
    $unique[$key]['table'] = $r->tbl;
    $unique[$key]['name'] = $r->idx;
    $unique[$key]['cols'][$r->seq] = $r->col;
}
foreach ($unique as $k => $u) {
    ksort($unique[$k]['cols']);
    $unique[$k]['cols'] = array_values($unique[$k]['cols']);
}

$domain = array_filter($unique, fn (array $u): bool => ! in_array($u['table'], $notDomain, true));
$excluded = array_filter($unique, fn (array $u): bool => in_array($u['table'], $notDomain, true));

printf("Database: %s\n", $database);
printf("Unique indexes on the server: %d  |  in scope for §23.6.1: %d  |  excluded by its own sentence: %d\n",
    count($unique), count($domain), count($excluded));
foreach ($excluded as $u) {
    printf("  excluded  %-46s (%s)\n", $u['table'].'.'.$u['name'], implode(', ', $u['cols']));
}

// What the document lists, read off the regenerated dump
$dump = (string) file_get_contents($dumpPath);
$listed = [];
foreach (explode("\n", $dump) as $line) {
    if (! str_starts_with($line, 'ROW ')) {
        continue;
    }
    $cells = explode(' | ', $line);
    $first = trim(preg_replace('/^ROW \d+: /', '', $cells[0]));
    if (preg_match('/^(uq_[a-z0-9_]+|[a-z0-9_]+_unique)$/', $first)) {
        $listed[$first] = true;
    }
}

printf("\nKeys §23.6.1 lists: %d\n", count($listed));

$problems = 0;
foreach ($domain as $u) {
    if (isset($listed[$u['name']])) {
        printf("  ok    %-34s %s\n", $u['name'], implode(', ', $u['cols']));

        continue;
    }
    printf("  MISS  %-34s %s  — on the server, absent from the table that calls itself complete\n",
        $u['name'], implode(', ', $u['cols']));
    $problems++;
}

foreach (array_keys($listed) as $name) {
    $onServer = false;
    foreach ($domain as $u) {
        if ($u['name'] === $name) {
            $onServer = true;

            break;
        }
    }
    if (! $onServer) {
        printf("  PHANTOM %-32s listed in §23.6.1 but no such unique index exists on %s\n", $name, $database);
        $problems++;
    }
}

$claimed = null;
if (preg_match('/(Eight|Nine|Ten|Seven|Six)\s+keys?\s+in\s+(seven|eight|nine|six)\s+tables?/i', $dump, $c)) {
    $claimed = [strtolower($c[1]), strtolower($c[2])];
}
$word = ['six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10];
$tables = count(array_unique(array_column($domain, 'table')));

if ($claimed === null) {
    printf("  UNBOUND the sentence stating the key and table count could not be read — a claim the checker\n");
    printf("          cannot find has not been checked, which is not the same as a claim that passed\n");
    $problems++;
} else {
    $okKeys = $word[$claimed[0]] === count($domain);
    $okTables = $word[$claimed[1]] === $tables;
    printf("  %s the sentence says %s keys in %s tables; the server holds %d keys across %d tables\n",
        $okKeys && $okTables ? 'ok  ' : 'MISS',
        $claimed[0], $claimed[1], count($domain), $tables);
    if (! ($okKeys && $okTables)) {
        $problems++;
    }
}

printf("\n  %d problem(s).\n", $problems);
echo "  Uniqueness is the one integrity rule MySQL defends for this schema, so §23.6.1 is read as a boundary:\n";
echo "  a key the list omits is a rule a future reader will assume the database does not hold.\n";

exit($problems === 0 ? 0 : 1);
