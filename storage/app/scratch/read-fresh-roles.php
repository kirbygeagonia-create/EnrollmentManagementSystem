<?php

/**
 * Read-only companion to rehearse-fresh-roles.php: what roles does a freshly seeded install actually
 * give the accounts the demo and the walkthrough depend on?
 *
 * Split out because the first version crashed on `(string) $staff->role` — `role` is cast to the
 * StaffRole enum, and an enum object is not a string — after it had already re-seeded the database, so
 * the fix had to be measurement-only rather than "run the whole thing again".
 */

$scratch = 'ems_fresh_check';
$root = dirname(__DIR__, 3);

putenv('DB_DATABASE='.$scratch);
$_ENV['DB_DATABASE'] = $scratch;
$_SERVER['DB_DATABASE'] = $scratch;

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Staffusers;
use Illuminate\Support\Facades\DB;

$resolved = (string) config('database.connections.mysql.database');
if (DB::connection()->getDriverName() !== 'mysql' || $resolved !== $scratch) {
    fwrite(STDERR, "ABORT: resolved {$resolved}, expected {$scratch}\n");
    exit(1);
}

$roleless = [];
foreach (Staffusers::all() as $staff) {
    if ($staff->getRoleNames()->all() === []) {
        $roleless[] = sprintf('%-18s office %-4s enum %-12s',
            (string) $staff->username,
            (string) $staff->officeId,
            $staff->role instanceof BackedEnum ? $staff->role->value : (string) $staff->role);
    }
}

printf("== accounts with no Spatie roles on a fresh install ==\n  %d of %d account(s):\n", count($roleless), Staffusers::count());
echo $roleless === [] ? "    none\n" : '    '.implode("\n    ", $roleless)."\n";

echo "\n== the accounts the walkthrough and the demo name ==\n";
foreach (['office1_head', 'office4_head', 'office7_head', 'dean_academic', 'instr.mendoza', 'dept.caf', 'dept.ctic'] as $u) {
    $staff = Staffusers::where('username', $u)->first();
    if ($staff === null) {
        printf("  %-16s (not created on a fresh install)\n", $u);
        continue;
    }
    printf("  %-16s roles=%-32s eval.view=%s eval.sign=%s\n",
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
printf("\n== the pattern in one number ==\n  officeHead accounts: %d, role-less: %d\n", $heads, $headRoleless);
printf("  pivot rows in staff_roles: %d\n", DB::table('staff_roles')->count());
exit(0);
