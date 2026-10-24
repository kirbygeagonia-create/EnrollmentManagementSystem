<?php

/**
 * Drop the scratch schemas no harness names any more — and refuse anything a harness still names.
 *
 * The box accumulates a schema per rehearsal lane. Nine of the sixteen here are load-bearing: a tracked
 * harness either refuses to run unless DB_DATABASE names them (`mutate-lane54-report.php` will not run on
 * any other name) or clones live `ems` into them. Dropping one of those does not free space, it breaks an
 * instrument — and the breakage is silent, because the harness that wanted it would report a red it
 * attributes to the code.
 *
 * So the allowlist is checked twice before any DDL: once against a literal never-drop list (live `ems` and
 * the four schemas belonging to other projects), and once against the repository's own text — every .php
 * under storage/, tools/ and .github/ is read and searched for the name. A name found anywhere there is
 * refused, which is the guard that survives a future harness starting to use a schema this pass considers
 * dead. `git grep` is not available: shell_exec cannot reach git from PHP on this box, so the scan walks
 * the working tree itself.
 *
 *   php storage/app/scratch/drop-retired-scratch-databases.php          # dry run
 *   php storage/app/scratch/drop-retired-scratch-databases.php --apply  # drop the named few
 */

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

/** Schemas this pass believes no harness names. A literal list, never a pattern. */
$candidates = [
    'ems_lane39_fresh', 'ems_lane39_fresh2', 'ems_lane39_raw', 'ems_lane39_restore',
    'ems_l53', 'ems_l54', 'ems_p17',
];

/** Must survive under every circumstance. */
$never = ['ems', 'enrollment', 'enroll_test', 'medicalregistrationdb', 'seaitenrollments'];

$apply = in_array('--apply', $argv, true);
$fails = 0;
$say = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD ', $what);
    if (! $ok) {
        $fails++;
    }
};

if (array_intersect($candidates, $never) !== []) {
    fwrite(STDERR, "REFUSE: the candidate list names a schema that must survive: "
        .implode(', ', array_intersect($candidates, $never))."\n");
    exit(1);
}

$exists = array_column(DB::select('SELECT schema_name AS s FROM information_schema.schemata'), 's');
foreach ($never as $mustLive) {
    if (! in_array($mustLive, $exists, true)) {
        fwrite(STDERR, "REFUSE: `{$mustLive}` is not present, so this is not the instance that was measured.\n");
        exit(1);
    }
}

// ---- the reference scan: read the repository, not a remembered list -------------------------------
$scanned = 0;
$refs = [];
foreach (['/storage/app/scratch', '/tools', '/.github', '/tests', '/app', '/database', '/Documentation'] as $dir) {
    if (! is_dir($root.$dir)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.$dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (! $file->isFile() || ! preg_match('/\.(php|md|yml|yaml|json|ps1)$/i', $file->getFilename())) {
            continue;
        }
        $path = $file->getPathname();
        if (str_replace('\\', '/', $path) === str_replace('\\', '/', __FILE__)) {
            continue;   // this pass's own literal list must not count as a reference
        }
        $bytes = (string) file_get_contents($path);
        $scanned++;
        foreach ($candidates as $s) {
            if (preg_match('/\b'.preg_quote($s, '/').'\b/', $bytes)) {
                $refs[$s][] = ltrim(str_replace($root, '', $path), '\\/');
            }
        }
    }
}

printf("%s: %d schema(s) considered, %d repository file(s) scanned.\n\n",
    $apply ? 'APPLY' : 'DRY RUN', count($candidates), $scanned);

$present = [];
foreach ($candidates as $s) {
    if (! in_array($s, $exists, true)) {
        printf("  gone   %s (nothing to drop)\n", $s);

        continue;
    }
    if (($refs[$s] ?? []) !== []) {
        printf("  KEEP   %-22s named by %d file(s): %s\n", $s, count($refs[$s]),
            implode(', ', array_slice($refs[$s], 0, 3)));

        continue;
    }
    $tables = (int) DB::selectOne(
        'SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema = ? AND table_type = \'BASE TABLE\'', [$s])->c;
    $rows = 0;
    foreach (DB::select("SELECT table_name t FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE'", [$s]) as $t) {
        $rows += (int) DB::selectOne("SELECT COUNT(*) c FROM `{$s}`.`{$t->t}`")->c;
    }
    printf("  drop   %-22s %2d tables, %d rows — named by no scanned file\n", $s, $tables, $rows);
    $present[] = [$s, $tables];
}

$say($present !== [], 'at least one schema is actually retired (otherwise there is nothing to do)');
if ($fails > 0 && ! $apply) {
    echo "\nNothing to drop, or a guard failed — see above.\n";
    exit($present === [] ? 0 : 1);
}
if ($fails > 0) {
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN — pass --apply to drop exactly the lines above marked \"drop\". Nothing else is named.\n";
    exit(0);
}

$before = DB::selectOne('SELECT (SELECT COUNT(*) FROM ems.enrollments) e, (SELECT COUNT(*) FROM ems.workflowsteps) w, (SELECT COUNT(*) FROM ems.staffusers) s, (SELECT COUNT(*) FROM ems.auditlogs) a');

foreach ($present as [$s, $tables]) {
    DB::statement('DROP DATABASE `'.str_replace('`', '``', $s).'`');
    echo "dropped {$s}\n";
}

$after = array_column(DB::select('SELECT schema_name AS s FROM information_schema.schemata'), 's');
foreach ($present as [$s, $tables]) {
    if (in_array($s, $after, true)) {
        fwrite(STDERR, "REFUSE: {$s} survived the drop\n");
        exit(1);
    }
}
foreach ($never as $mustLive) {
    if (! in_array($mustLive, $after, true)) {
        fwrite(STDERR, "REFUSE: `{$mustLive}` is GONE — it was never on the drop list\n");
        exit(1);
    }
}
foreach (array_keys($refs) as $kept) {
    if (! in_array($kept, $after, true)) {
        fwrite(STDERR, "REFUSE: {$kept} was named by a repository file and must not have been dropped\n");
        exit(1);
    }
}

$now = DB::selectOne('SELECT (SELECT COUNT(*) FROM ems.enrollments) e, (SELECT COUNT(*) FROM ems.workflowsteps) w, (SELECT COUNT(*) FROM ems.staffusers) s, (SELECT COUNT(*) FROM ems.auditlogs) a');
if ((array) $now !== (array) $before) {
    fwrite(STDERR, "REFUSE: live ems moved across the batch — before ".json_encode($before)." after ".json_encode($now)."\n");
    exit(1);
}

printf("\nDROPPED %d retired schema(s). live ems unchanged: %d enrollments, %d boxes, %d staff, %d audit rows.\n",
    count($present), $now->e, $now->w, $now->s, $now->a);
echo "Every schema a scanned file names is still present, and the four foreign schemas and ems are intact.\n";
