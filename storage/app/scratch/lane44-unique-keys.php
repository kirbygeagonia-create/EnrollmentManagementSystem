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
// The count sentence is a claim about THIS register, so it is read from the window that holds the register:
// from the table's `Key | Columns` header to the next `23.6.2` heading. Searching the whole document made the
// FIRST `[number] keys in [word] tables.` win, and a §1.1 revision row that quotes the figure shadowed the
// register it quotes — the checker weighed a sentence in another section and stayed silent while §23.6.1's own
// claim rotted. A window it cannot find is a refusal, not a guess.
$headAt = strpos($dump, 'Key | Columns | The rule it enforces');
if ($headAt === false) {
    fwrite(STDERR, "REFUSED: the §23.6.1 table header (`Key | Columns | The rule it enforces`) is not in the dump, so the\n"
        ."          count sentence has no section to be read from. Re-dump, or name the heading this checker anchors on.\n");
    exit(1);
}
$windowEnd = strlen($dump);
if (preg_match('/P \d+: 23\.6\.2/u', $dump, $mh, PREG_OFFSET_CAPTURE) === 1) {
    $windowEnd = (int) $mh[0][1];
}
$sentenceWindow = substr($dump, $headAt, $windowEnd - $headAt);
// The word list used to stop at `Ten`, so the checker went UNBOUND the first day the register grew past it — the
// right behaviour (an unread claim refuses), but a boundary claim has to survive its own getting truer. Spelled
// out through twenty and digits accepted, so the next key is a document edit rather than a code edit.
if (preg_match(
    '/(Six|Seven|Eight|Nine|Ten|Eleven|Twelve|Thirteen|Fourteen|Fifteen|Sixteen|Seventeen|Eighteen|Nineteen|Twenty|\d+)\s+keys?\s+in\s+(six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|twenty|\d+)\s+tables?/i',
    $sentenceWindow,
    $c
)) {
    $claimed = [strtolower($c[1]), strtolower($c[2])];
}
$word = ['six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12,
    'thirteen' => 13, 'fourteen' => 14, 'fifteen' => 15, 'sixteen' => 16, 'seventeen' => 17, 'eighteen' => 18,
    'nineteen' => 19, 'twenty' => 20];
$asNumber = static fn (string $w): ?int => is_numeric($w) ? (int) $w : ($word[$w] ?? null);
$tables = count(array_unique(array_column($domain, 'table')));

if ($claimed === null) {
    printf("  UNBOUND the sentence stating the key and table count could not be read — a claim the checker\n");
    printf("          cannot find has not been checked, which is not the same as a claim that passed\n");
    $problems++;
} else {
    $okKeys = $asNumber($claimed[0]) === count($domain);
    $okTables = $asNumber($claimed[1]) === $tables;
    printf("  %s the sentence says %s keys in %s tables; the server holds %d keys across %d tables "
            ."(read from the §23.6.1 window, %d bytes)\n",
        $okKeys && $okTables ? 'ok  ' : 'MISS',
        $claimed[0], $claimed[1], count($domain), $tables, strlen($sentenceWindow));
    if (! ($okKeys && $okTables)) {
        $problems++;
    }
}

printf("\n  %d problem(s).\n", $problems);
echo "  Uniqueness is the one integrity rule MySQL defends for this schema, so §23.6.1 is read as a boundary:\n";
echo "  a key the list omits is a rule a future reader will assume the database does not hold.\n";

exit($problems === 0 ? 0 : 1);
