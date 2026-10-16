<?php

/**
 * Would re-deriving roles change anything on live `ems`? Measured inside a transaction that is rolled back.
 *
 * `RbacSeeder::assignDeskRolesFromEnums()` calls `syncRoles()`, and `sync` means "replace". The fresh-install
 * rehearsal proved it grants what is missing; nothing there proved what it does to an account the school has
 * already configured by hand — if someone gave a desk an extra role through Admin → User Management, this call
 * would remove it and the fix for one defect would be a silent regression on another machine.
 *
 * So: read every account's roles, run the real function, diff again, and ROLL BACK. InnoDB makes the pivot
 * writes transactional, and the script re-reads the state after the rollback to prove nothing stayed.
 *
 * If it reports "no change", the live reseed is safe. If it lists a difference, that account is configured
 * beyond the enum and the owner decides before anything runs on their data.
 */

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Staffusers;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\DB;

$db = (string) config('database.connections.'.config('database.default').'.database');
if ($db !== 'ems') {
    fwrite(STDERR, "This probe is written to describe live `ems`; it resolved {$db}. Stopping.\n");
    exit(1);
}
if (DB::connection()->getDriverName() !== 'mysql') {
    fwrite(STDERR, "Expected MySQL, got ".DB::connection()->getDriverName().". Stopping.\n");
    exit(1);
}

$snapshot = function (): array {
    $out = [];
    foreach (Staffusers::all() as $s) {
        $names = $s->roles()->pluck('name')->all();
        sort($names);
        $out[(string) $s->username] = $names;
    }
    ksort($out);

    return $out;
};

$before = $snapshot();
$pivotBefore = DB::table('staff_roles')->count();
echo "live ems: ".count($before)." accounts, pivot rows {$pivotBefore}\n";

DB::beginTransaction();

try {
    RbacSeeder::assignDeskRolesFromEnums();
    $after = $snapshot();
    $changed = [];
    foreach ($after as $user => $roles) {
        if (($before[$user] ?? null) !== $roles) {
            $changed[$user] = [$before[$user] ?? [], $roles];
        }
    }
    printf("\nin-transaction pivot rows after re-derivation: %d (was %d)\n",
        DB::table('staff_roles')->count(), $pivotBefore);
} finally {
    DB::rollBack();
}

$restored = $snapshot();
$untouched = $restored === $before;

echo "\n== what the live reseed would change ==\n";
if (! $untouched) {
    echo "  *** THE ROLLBACK DID NOT RESTORE THE STATE — stop and check staffusers/staff_roles by hand ***\n";
    exit(1);
}
echo "  rolled back cleanly: live state is byte-identical to the reading before the transaction\n";

if (($changed ?? []) === []) {
    echo "\n  NO CHANGES: every account's roles are exactly what the enum mapping produces, so the\n"
        ."  reseed on live ems is a no-op for roles — it only adds the six new desks and the accounts\n"
        ."  the migration touches.\n";
    exit(0);
}

echo "\n  ".count($changed)." account(s) differ from the mapping — these are configured beyond their enum:\n";
foreach ($changed as $user => [$had, $would]) {
    printf("    %-18s has [%s]%n", $user, implode(', ', $had ?: '(none)'));
    printf(" would set [%s]\n", implode(', ', $would));
}
echo "\n  Decide before reseeding: syncRoles replaces, so any hand-granted role above would be removed.\n";
exit(2);
