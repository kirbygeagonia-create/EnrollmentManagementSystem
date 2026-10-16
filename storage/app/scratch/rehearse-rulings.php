<?php

/**
 * Rehearse both rulings end to end on a disposable copy of live `ems`.
 *
 * Nothing here touches the developer's `ems`: the same dump that will back it up was loaded into
 * `ems_lane89_rehearse`, and every step runs against that database by overriding DB_DATABASE in the
 * process environment — Laravel reads env before .env, so the override wins and .env stays untouched.
 *
 * The order is the one a real install takes, because that is what makes the proof worth anything:
 * migrate (drops the two database-stamped defaults) → RbacSeeder (the roles the new accounts will be
 * mapped to) → DevReferenceDataSeeder (seats one Academic Department desk per college) → the demo
 * pipeline (must resolve each evaluator from the course its row already names). Running the pipeline
 * before the seeder would only prove it can fail.
 *
 * Then the measurements the rulings claim, read back from the database rather than inferred:
 *   - how many office-7 desks now carry a unitId, and one per college or not;
 *   - whether RbacSeeder step 3 really gave them evaluation.sign;
 *   - whether the two columns still carry a CURRENT_TIMESTAMP default;
 *   - who the demo enrollments credit with the act, and whether any still credit Guidance.
 */

$root = dirname(__DIR__, 3);
putenv('DB_DATABASE=ems_lane89_rehearse');
$_ENV['DB_DATABASE'] = 'ems_lane89_rehearse';
$_SERVER['DB_DATABASE'] = 'ems_lane89_rehearse';

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

if ((string) config('database.connections.mysql.database') !== 'ems_lane89_rehearse') {
    fwrite(STDERR, 'ABORT: the app resolved database '
        .config('database.connections.mysql.database').", not the rehearsal copy.\n");
    exit(1);
}
echo 'connected to: '.config('database.connections.mysql.database')."\n\n";

$run = function (array $argv) use ($kernel): int {
    $code = $kernel->call($argv[0], array_slice($argv, 1) === [] ? [] : $argv[1]);
    echo trim($kernel->output())."\n----\n";

    return $code;
};

printf("STEP 1  migrate            exit=%d\n----\n", $run(['migrate', ['--force' => true]]));
printf("STEP 2  RbacSeeder         exit=%d\n----\n", $run(['db:seed', ['--class' => 'Database\\Seeders\\RbacSeeder', '--force' => true]]));
printf("STEP 3  DevReferenceDataSeeder exit=%d\n----\n", $run(['db:seed', ['--class' => 'Database\\Seeders\\DevReferenceDataSeeder', '--force' => true]]));

echo "\n== A. the department desks the ruling asked for ==\n";
$desks = DB::table('staffusers')
    ->where('officeId', 7)
    ->whereNotNull('unitId')
    ->orderBy('unitId')
    ->get();
printf("  office-7 accounts carrying a unitId: %d\n", count($desks));
foreach ($desks as $d) {
    printf("    %-14s unit=%-3d role=%-11s\n", $d->username, $d->unitId, $d->role);
}

$colleges = DB::table('academicunits')->where('unitType', 'college')->count();
printf("  colleges in academicunits: %d  → one desk each? %s\n", $colleges,
    count($desks) === $colleges ? 'YES' : 'NO — '.abs($colleges - count($desks)).' short');

echo "\n== B. what RbacSeeder step 3 actually gave them ==\n";
foreach ($desks as $d) {
    $staff = App\Models\Staffusers::find($d->userId);
    printf("    %-14s roles=%-28s view=%s create=%s sign=%s\n",
        $d->username,
        implode('/', $staff->getRoleNames()->all()) ?: '(none)',
        $staff->checkPermissionTo('evaluation.view') ? 'Y' : 'n',
        $staff->checkPermissionTo('evaluation.create') ? 'Y' : 'n',
        $staff->checkPermissionTo('evaluation.sign') ? 'Y' : 'n');
}

echo "\n== C. the database-stamped defaults (case-sensitive dictionary on purpose) ==\n";
foreach ([['auditlogs', 'createdAt'], ['enrollmentstatushistory', 'changedAt'], ['jobs', 'failed_at']] as [$t, $c]) {
    $row = DB::selectOne("
        SELECT COLUMN_DEFAULT AS dft, EXTRA FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ", [$t, $c]);
    printf("    %-24s %-10s default=%-18s extra=%s\n", $t, $c,
        var_export($row->dft ?? null, true), (string) ($row->EXTRA ?? ''));
}

echo "\n== D. who the demo enrollments credit with Department Evaluation ==\n";
$before = DB::table('enrollments')->count();
echo "  enrollments before the pipeline: {$before}\n";

$pipeline = $root.'/tools/seed_full_demo_pipeline.php';
ob_start();
$exit = 0;
try {
    require $pipeline;
} catch (Throwable $e) {
    $exit = 1;
    echo "\nPIPELINE THREW: ".$e->getMessage()."\n  at ".$e->getFile().':'.$e->getLine()."\n";
}
$out = ob_get_clean();

$lines = preg_split('/\R/', $out);
$refusals = array_values(array_filter($lines, fn (string $l): bool => (bool) preg_match('/THREW|Fatal|Error|may not|does not exist|no Academic Department/i', $l)));
printf("pipeline output lines: %d   refusal-looking lines: %d   thrown=%d\n", count($lines), count($refusals), $exit);
foreach (array_slice($refusals, 0, 6) as $r) {
    echo '  ! '.trim($r)."\n";
}

echo "\n== E. the credit distribution after the pipeline ==\n";
$rows = DB::select("
    SELECT su.officeId, su.username, su.unitId, COUNT(*) AS n
      FROM enrollments e
      LEFT JOIN staffusers su ON su.userId = e.evaluatedBy
     GROUP BY su.officeId, su.username, su.unitId
     ORDER BY n DESC
");
foreach ($rows as $r) {
    printf("    office %-6s %-18s unit %-6s → %d enrollment(s)\n",
        $r->officeId ?? '(none)', $r->username ?? '(no signer)', $r->unitId ?? 'null', $r->n);
}

$stillGuidance = (int) DB::table('enrollments')
    ->join('staffusers', 'staffusers.userId', '=', 'enrollments.evaluatedBy')
    ->where('staffusers.officeId', 4)
    ->count();
printf("\n  enrollments still credited to a Guidance (office 4) account: %d\n", $stillGuidance);
echo '  (the pre-existing demo rows are expected to stay until the owner rules on rewriting history;'
    ."\n   what this proves is that rows written from now on resolve to the owning college)\n";
exit($exit);
