<?php

/**
 * The two open owner decisions, measured before they are asked.
 *
 * #89 — who the demo data and the live rows credit with the Department Evaluation act. The docx office
 * table names office 7 (Academic Department) for it, the launcher's card is keyed officeId: 4
 * (Guidance), the demo pipeline signs with Guidance in most places and the Admission head in two, and
 * the seeder ships a DeptEvaluator role described as "the owning academic department". Four sources
 * cannot all be right, so this prints what each one actually says and what the stored rows say.
 *
 * #91 — the server's time zone. `ems:preflight` fails on it by design until someone chooses; the choice
 * is between moving the database session to the app's zone, moving the app to Manila, or taking the
 * default away from the columns so only one process ever stamps. The blast radius differs completely:
 * one changes nothing a desk sees, one moves every printed timestamp by eight hours and can change
 * which calendar day a pre-08:00 record belongs to, one is a migration.
 *
 * Read-only. No row is written, updated or deleted; the live database is only SELECTed.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\OfficeId;
use Illuminate\Support\Facades\DB;

echo "=== #89: the four sources that name the Department Evaluation desk ===\n";

$rows = DB::select("
    SELECT p.name AS permission, r.name AS role
      FROM permissions p
      JOIN role_permissions rp ON rp.permissionId = p.id
      JOIN roles r ON r.id = rp.roleId
     WHERE p.name IN ('evaluation.view','evaluation.sign','evaluation.create')
     ORDER BY p.name, r.name
");
foreach (['evaluation.view', 'evaluation.create', 'evaluation.sign'] as $perm) {
    $who = array_values(array_map(fn ($r) => $r->role, array_filter($rows, fn ($r) => $r->permission === $perm)));
    printf("  %-16s held by: %s\n", $perm, $who === [] ? '(nobody)' : implode(', ', $who));
}

printf("  OfficeId enum   : Academic=%d, Guidance=%d, Admission=%d\n",
    OfficeId::Academic->value, OfficeId::Guidance->value, OfficeId::Admission->value);

echo "\n  accounts per office that hold evaluation.view:\n";
$holders = DB::select("
    SELECT su.officeId, COUNT(*) AS accounts
      FROM staffusers su
      JOIN staff_roles sr ON sr.roleId IN (SELECT role_permissions.roleId FROM role_permissions
                                   JOIN permissions ON permissions.id = role_permissions.permissionId
                                   WHERE permissions.name = 'evaluation.view')
        AND sr.model_type LIKE '%Staffusers' AND sr.roleId = su.userId
     GROUP BY su.officeId ORDER BY accounts DESC
");
if ($holders === []) {
    // The pivot is renamed and model_type is double-escaped, so the join above is fragile: fall back
    // to resolving it in PHP through Spatie, which is the answer the app itself gives.
    $byOffice = [];
    foreach (DB::table('staffusers')->whereNotNull('officeId')->pluck('userId') as $id) {
        $staff = \App\Models\Staffusers::find($id);
        if ($staff !== null && $staff->checkPermissionTo('evaluation.view')) {
            $byOffice[$staff->officeId] = ($byOffice[$staff->officeId] ?? 0) + 1;
        }
    }
    arsort($byOffice);
    foreach ($byOffice as $office => $count) {
        printf("    office %-3d : %d account(s) that may open the Evaluation desk\n", $office, $count);
    }
    if ($byOffice === []) {
        echo "    (no account resolves evaluation.view — check the double-escaped model_type trap)\n";
    }
} else {
    foreach ($holders as $h) {
        printf("    office %-3d : %d account(s)\n", $h->officeId, $h->accounts);
    }
}

echo "\n  who the 30 demo/live enrollments actually credit with the act:\n";
$eval = DB::select("
    SELECT su.officeId, su.username, COUNT(*) AS rows_stamped
      FROM enrollments e
      LEFT JOIN staffusers su ON su.userId = e.evaluatedBy
     GROUP BY su.officeId, su.username
     ORDER BY rows_stamped DESC
");
foreach ($eval as $r) {
    printf("    office %-6s user %-22s : %d enrollment(s)\n",
        $r->officeId ?? '(null)', $r->username ?? '(no signer row)', $r->rows_stamped);
}

echo "\n=== #91: the two clocks and the columns they both write ===\n";
$stamp = DB::selectOne('SELECT NOW() AS db_now, @@global.time_zone AS g, @@session.time_zone AS s, @@system_time_zone AS sys');
printf("  config/app.php timezone : %s\n", (string) config('app.timezone'));
printf("  PHP now()               : %s  (epoch %d)\n", \Carbon\Carbon::now()->toDateTimeString(), \Carbon\Carbon::now()->getTimestamp());
printf("  MySQL NOW()             : %s  (epoch %d)\n", $stamp->db_now, strtotime((string) $stamp->db_now));
printf("  MySQL zones             : global=%s session=%s system=%s\n", $stamp->g, $stamp->s, $stamp->sys);

$defaults = DB::select("
    SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND COLUMN_DEFAULT LIKE '%current_timestamp%'
     ORDER BY TABLE_NAME, COLUMN_NAME
");
printf("  columns whose default is stamped by the database: %d\n", count($defaults));
foreach ($defaults as $d) {
    printf("    %-26s %-14s %-8s default=%s\n", $d->TABLE_NAME, $d->COLUMN_NAME, $d->DATA_TYPE, $d->COLUMN_DEFAULT);
}
exit(0);
