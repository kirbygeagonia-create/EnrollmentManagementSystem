<?php

/**
 * Two measurements that decide what the owner is actually being asked.
 *
 * A: `probe-two-decisions.php` reported 0 columns stamped by a database default, which contradicts the
 * sentence this project wrote into DEPLOYMENT.md §2 and docx pass 104 ("auditlogs.createdAt and
 * enrollmentstatushistory.changedAt carry a MySQL CURRENT_TIMESTAMP default"). Either the databases
 * differ (the suite's SQLite schema vs live `ems`), or the claim is wrong. Check it three ways: the
 * live columns, the migrations' own text, and the fresh SQLite schema a new server would get.
 *
 * B: #89's options depend on who could sign if the credit moved. The Evaluation desk is opened today by
 * 10 accounts sitting in offices 4, 1, 22 and 5 — and none in office 7, the office the docx names for
 * Department evaluation. If re-attribution means naming an office with no account in it, the demo has
 * no signer and the choice is a different one than I framed.
 *
 * Read-only: SELECT and file text, never an UPDATE or a delete.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\OfficeId;
use App\Models\Staffusers;
use Illuminate\Support\Facades\DB;

echo "=== A. who stamps the two audit columns, in the live database ===\n";
$live = DB::select("
    SELECT TABLE_NAME, COLUMN_NAME, COLUMN_DEFAULT, EXTRA
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND ((TABLE_NAME = 'auditlogs' AND COLUMN_NAME = 'createdAt')
         OR (TABLE_NAME = 'enrollmentstatushistory' AND COLUMN_NAME = 'changedAt'))
     ORDER BY TABLE_NAME
");
if ($live === []) {
    echo "  (one of the two tables/columns is not in this database — say so rather than assume)\n";
}
foreach ($live as $c) {
    printf("  %-24s %-12s default=%-18s extra=%s\n", $c->TABLE_NAME, $c->COLUMN_NAME,
        var_export($c->COLUMN_DEFAULT, true), $c->EXTRA);
}

echo "\n  any column at all in live `ems` with a CURRENT_TIMESTAMP default: ";
$any = DB::select("
    SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_DEFAULT LIKE '%current_timestamp%'
");
echo count($any)." → ".($any === [] ? 'NONE' : json_encode(array_map(fn ($r) => $r->TABLE_NAME.'.'.$r->COLUMN_NAME, $any)))."\n";

echo "\n  what the migrations say (useCurrent occurrences in database/migrations):\n";
$hits = [];
foreach (glob(database_path('migrations/*.php')) as $f) {
    foreach (file($f) as $no => $line) {
        if (str_contains($line, 'useCurrent')) {
            $hits[] = basename($f).':'.($no + 1).'  '.trim($line);
        }
    }
}
echo $hits === [] ? "    (none)\n" : '    '.implode("\n    ", array_slice($hits, 0, 12))."\n";
printf("    total useCurrent() sites: %d\n", count($hits));

echo "\n=== B. who could sign the Evaluation act if the credit moved ===\n";
$byOffice = [];
foreach (Staffusers::whereNotNull('officeId')->get() as $staff) {
    $canView = $staff->checkPermissionTo('evaluation.view');
    $canSign = $staff->checkPermissionTo('evaluation.sign');
    $byOffice[$staff->officeId][] = sprintf('%-18s view=%s sign=%s roles=%s',
        (string) $staff->username, $canView ? 'Y' : 'n', $canSign ? 'Y' : 'n',
        implode('/', $staff->getRoleNames()->all()) ?: '(none)');
}

foreach ([OfficeId::Guidance, OfficeId::Academic, OfficeId::Admission] as $office) {
    $list = $byOffice[$office->value] ?? [];
    printf("  office %-2d (%-22s) : %d account(s)\n", $office->value, $office->name, count($list));
    foreach ($list as $row) {
        echo '      '.$row."\n";
    }
}

echo "\n  accounts holding the DeptEvaluator role, by office:\n";
$de = DB::table('roles')->where('name', 'DeptEvaluator')->first();
if ($de === null) {
    echo "    (no such role)\n";
} else {
    $ids = DB::select('SELECT model_id FROM staff_roles WHERE roleId = ?', [$de->id]);
    $counted = 0;
    foreach ($ids as $row) {
        $staff = Staffusers::find((int) (is_string($row->model_id) ? stripcslashes($row->model_id) : $row->model_id));
        if ($staff === null) {
            continue;
        }
        $counted++;
        printf("    %-20s office %s\n", (string) $staff->username, (string) $staff->officeId);
    }
    echo "    DeptEvaluator accounts found: {$counted}\n";
}

echo "\n  staffusers rows that exist in office 7 at all: ";
echo DB::table('staffusers')->where('officeId', OfficeId::Academic->value)->count()."\n";
exit(0);
