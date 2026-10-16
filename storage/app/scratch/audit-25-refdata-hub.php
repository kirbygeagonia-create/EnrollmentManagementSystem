<?php

/**
 * Audit lane 25 probe — the two rights behind Reference Data, and who actually holds them.
 *
 * `ReferenceDataSections::hubVisible()` offers the hub to an account that holds `refdata.view` AND at
 * least one `refdata.<section>.manage`; `ReferenceDataController::index()` answers to `viewAny` on
 * Courses, which is `refdata.view` alone. Those are not the same question, so one of them can be true
 * while the other is false — and the register already records the class of bug: the hub once linked
 * twelve tiles to a Registrar who could use one of them.
 *
 * This measures the installed catalogue rather than reasoning about it: which roles hold view, how
 * many manage rights each holds, and therefore whether any real account sits in the gap.
 * Read-only SQL against live `ems`.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\ReferenceDataSections;
use Illuminate\Support\Facades\DB;

$manage = array_values(array_unique(array_values(ReferenceDataSections::PERMISSIONS)));
echo 'catalog screens: '.count(ReferenceDataSections::PERMISSIONS).', distinct manage rights: '.count($manage)."\n";

// staff_roles.model_type is stored double-escaped, so a single-backslash literal matches nothing.
$rows = DB::select(
    'SELECT r.name AS role, p.name AS perm
       FROM roles r
       JOIN role_permissions rp ON rp.roleId = r.id
       JOIN permissions p ON p.id = rp.permissionId
      WHERE p.name LIKE ? OR p.name = ?',
    ['refdata.%', 'refdata.view']
);

$byRole = [];
foreach ($rows as $r) {
    $byRole[$r->role][] = $r->perm;
}

printf("\n%-24s %-12s %-10s %s\n", 'ROLE', 'refdata.view', 'manage #', 'which');
$gap = [];
$orphan = [];
foreach ($byRole as $role => $perms) {
    $held = array_values(array_intersect($perms, $manage));
    $view = in_array('refdata.view', $perms, true);
    printf("%-24s %-12s %-10s %s\n", $role, $view ? 'YES' : 'no', count($held),
        $held === [] ? '-' : implode(',', array_map(fn (string $p) => str_replace('refdata.', '', $p), $held)));

    if ($view && $held === []) {
        $gap[] = $role;
    }
    if (! $view && $held !== []) {
        $orphan[] = $role.'('.count($held).')';
    }
}

echo "\n== VERDICT ==\n";
echo '  holds refdata.view but no manage right (hub hidden, /admin/reference-data would open): '
    .($gap === [] ? 'no seeded role' : implode(', ', $gap))."\n";
echo '  holds a manage right but not refdata.view (hub hidden, and that is the honest answer): '
    .($orphan === [] ? 'none' : implode(', ', $orphan))."\n";

foreach (['permissions' => DB::table('permissions')->where('name', 'like', 'refdata.%')->count()] as $k => $v) {
    echo "  refdata.* permissions installed: {$v}\n";
}
exit(0);
