<?php

/**
 * Drop the eight scratch schemas the owner ruled out on 2026-10-14 — and nothing else.
 *
 * The list is a literal allowlist, not a pattern: `like 'ems_%'` would also drop `ems_test` and, one typo or
 * one future naming convention away from `ems` itself, would drop the live database. This box has already lost
 * live `ems` once to a file whose dangerous statement did not look like a dangerous statement, so every guard
 * here is checked against information_schema before any DDL runs, `ems` is refused by name, and the four
 * schemas that turned out to belong to other projects (`enrollment`, `enroll_test`, `medicalregistrationdb`,
 * `seaitenrollments`) cannot be reached by this script at all.
 *
 * The two rehearsal schemas were dumped first to C:\Users\ADMIN\ems-backups (verified: 63 CREATE TABLEs each,
 * zero DROP DATABASE statements, because they were dumped without --databases).
 *
 * usage: php storage/app/scratch/drop-scratch-databases.php [--apply]
 */

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$allowlist = [
    'ems_lane89_rehearse', 'ems_lane30_rehearse', 'ems_fresh', 'ems_fresh_check',
    'ems_e2e_local', 'ems_ci_dry', 'ems_ci_test', 'ems_test',
];

$never = ['ems', 'enrollment', 'enroll_test', 'medicalregistrationdb', 'seaitenrollments'];
$apply = in_array('--apply', $argv, true);

$exists = array_column(DB::select('SELECT schema_name AS s FROM information_schema.schemata'), 's');

$fail = function (string $why): never {
    fwrite(STDERR, "REFUSE: {$why}\n");
    exit(1);
};

// Refuse before measuring anything else: if an allowlisted name ever collides with a schema that must survive,
// the whole batch stops rather than the one statement that would have done the damage.
if (array_intersect($allowlist, $never) !== []) {
    $fail('the allowlist and the never-drop list overlap: '.implode(', ', array_intersect($allowlist, $never)));
}

foreach ($never as $mustLive) {
    in_array($mustLive, $exists, true) || $fail("`{$mustLive}` is not present, so the instance is not the one measured");
}

$present = array_values(array_intersect($allowlist, $exists));
$missing = array_values(array_diff($allowlist, $exists));

if ($missing !== []) {
    echo 'already gone: '.implode(', ', $missing)."\n";
}

$before = [];
foreach ($present as $s) {
    $before[$s] = (int) DB::selectOne(
        'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ? AND table_type = \'BASE TABLE\'', [$s])->c;
}

printf("%s: %d schema(s) to drop (%d already gone)\n", $apply ? 'APPLY' : 'DRY RUN', count($present), count($missing));
foreach ($present as $s) {
    printf("  %-22s %2d tables\n", $s, $before[$s]);
}

if (! $apply) {
    echo "\nDRY RUN — pass --apply to drop these eight. Nothing else is named.\n";
    exit(0);
}

foreach ($present as $s) {
    // Backtick-doubled anyway, though every name here came from the literal list above.
    DB::statement('DROP DATABASE `'.str_replace('`', '``', $s).'`');
    echo "dropped {$s}\n";
}

$after = array_column(DB::select('SELECT schema_name AS s FROM information_schema.schemata'), 's');
foreach ($present as $s) {
    in_array($s, $after, true) && $fail("{$s} survived the drop");
}
foreach ($never as $mustLive) {
    in_array($mustLive, $after, true) || $fail("`{$mustLive}` is GONE — it was never on the drop list");
}

$ems = DB::selectOne('SELECT COUNT(*) AS c FROM ems.enrollments');
$audit = DB::selectOne('SELECT COUNT(*) AS c FROM ems.auditlogs');
$boxes = DB::selectOne('SELECT COUNT(*) AS c FROM ems.workflowsteps');
$staff = DB::selectOne('SELECT COUNT(*) AS c FROM ems.staffusers');

printf("\nlive ems after the batch: %d enrollments, %d boxes, %d staff, %d audit rows\n",
    $ems->c, $boxes->c, $staff->c, $audit->c);
echo "expected:                   30 enrollments, 157 boxes, 21 staff, >=3351 audit rows\n";
if ($ems->c !== 30 || $boxes->c !== 157 || $staff->c !== 21 || $audit->c < 3351) {
    $fail('live ems does not match the state measured before the drops');
}

echo "\nDROPPED eight scratch schemas; ems and the four foreign schemas verified intact.\n";
