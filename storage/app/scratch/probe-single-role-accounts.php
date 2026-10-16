<?php
/**
 * Which live accounts hold exactly one role? The UAT script's negative-test identity depends on
 * there being an account that is OfficeHead and nothing else, so this asks the application rather
 * than the SQL literal (staff_roles.model_type is stored double-escaped and a hand-written
 * comparison silently matches nothing).
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Staffusers;

$single = [];
$heads = [];
foreach (Staffusers::with('roles')->get() as $u) {
    $names = $u->roles->pluck('name')->all();
    if (count($names) === 1) {
        $single[] = $u->username.' (office '.($u->officeId ?? '—').'): '.$names[0];
    }
    if (in_array('OfficeHead', $names, true)) {
        $heads[] = $u->username.'(office '.($u->officeId ?? '—').') = '.implode('+', $names);
    }
}

echo "accounts holding exactly one role (".count($single)."):\n";
echo $single === [] ? "  (none)\n" : '  '.implode("\n  ", $single)."\n";

echo "\naccounts holding OfficeHead (".count($heads)."):\n";
echo '  '.implode("\n  ", $heads)."\n";
