<?php

/**
 * Does a fresh install, seeded in the documented order, give its desks any roles at all?
 *
 * DEPLOYMENT.md §3 and `.github/workflows/ci.yml` both run `db:seed` (RbacSeeder first) and then
 * `db:seed --class=DevReferenceDataSeeder`. RbacSeeder's step 3 re-derives every account's Spatie roles
 * from the `role` enum — but it runs BEFORE `DevReferenceDataSeeder` creates the nine office heads, the
 * dean and the instructors. So the question is whether those accounts end up with any roles at all, or
 * only the ones the seeder assigns by hand (which today is one account: dean_academic, 'Dean').
 *
 * The rehearsal database this runs against is created fresh; it is never `ems`. The guard below aborts
 * before a single statement if the resolved database is not the scratch name, because `migrate:fresh`
 * against the wrong connection would delete the school's records.
 */

$scratch = 'ems_fresh_check';
$root = dirname(__DIR__, 3);

putenv('DB_DATABASE='.$scratch);
$_ENV['DB_DATABASE'] = $scratch;
$_SERVER['DB_DATABASE'] = $scratch;

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Staffusers;
use Illuminate\Support\Facades\DB;

$resolved = (string) config('database.connections.mysql.database');
$driver = DB::connection()->getDriverName();
if ($driver !== 'mysql' || $resolved !== $scratch) {
    fwrite(STDERR, sprintf(
        "ABORT: the app resolved %s / %s, expected mysql / %s. Nothing was run.\n",
        $driver, $resolved, $scratch
    ));
    exit(1);
}
echo "guard passed: mysql / {$resolved} (a scratch database)\n";

DB::statement("CREATE DATABASE IF NOT EXISTS `{$scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
echo "database ready\n\n";

foreach ([['migrate:fresh', ['--force' => true]], ['db:seed', ['--force' => true]],
    ['db:seed', ['--class' => 'Database\\Seeders\\DevReferenceDataSeeder', '--force' => true]]] as [$cmd, $opts]) {
    $code = $kernel->call($cmd, $opts);
    $tail = array_slice(array_filter(explode("\n", trim($kernel->output()))), -2);
    printf("%-28s exit=%d  %s\n", $cmd, $code, implode(' / ', $tail));
}

echo "\n== accounts with no Spatie roles at all ==\n";
$roleless = [];
foreach (Staffusers::all() as $staff) {
    if ($staff->getRoleNames()->all() === []) {
        $roleless[] = sprintf('%s (office %s, enum %s)', $staff->username, (string) $staff->officeId, (string) $staff->role);
    }
}
printf("  %d of %d account(s) hold no role:%s\n", count($roleless), Staffusers::count(),
    $roleless === [] ? ' none' : "\n    ".implode("\n    ", $roleless));

echo "\n== the accounts the demo walkthrough names ==\n";
foreach (['office1_head', 'office4_head', 'office7_head', 'dean_academic', 'instr.mendoza', 'dept.caf'] as $u) {
    $staff = Staffusers::where('username', $u)->first();
    if ($staff === null) {
        printf("  %-16s (not created)\n", $u);
        continue;
    }
    printf("  %-16s roles=%-34s evaluation.view=%s evaluation.sign=%s\n",
        $u,
        implode('/', $staff->getRoleNames()->all()) ?: '(NONE)',
        $staff->checkPermissionTo('evaluation.view') ? 'Y' : 'n',
        $staff->checkPermissionTo('evaluation.sign') ? 'Y' : 'n');
}

$heads = Staffusers::where('role', 'officeHead')->count();
$headRoleless = 0;
foreach (Staffusers::where('role', 'officeHead')->get() as $h) {
    if ($h->getRoleNames()->all() === []) {
        $headRoleless++;
    }
}
printf("\n== the pattern in one number ==\n  officeHead accounts: %d, of which role-less: %d\n", $heads, $headRoleless);
exit(0);
