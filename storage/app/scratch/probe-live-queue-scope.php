<?php

/**
 * Read-only probe on live `ems`: for each demo account, what does the NEW queue rule answer, and does
 * it agree with the door each desk route actually opens? Also whether the Clearance launcher card and
 * the Clearance queue card would now render, which is what officeId 8 had switched off.
 *
 * Nothing is written: no POST, no save, no seed.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\DashboardController;
use App\Models\Staffusers;
use Illuminate\Support\Facades\DB;

config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'ems',
    'database.connections.mysql.username' => 'root', 'database.connections.mysql.password' => '']);
DB::purge('mysql');

$controller = new DashboardController;
$reflector = new ReflectionMethod($controller, 'visibleQueueKeys');
$reflector->setAccessible(true);

$desks = [
    'admission.index' => App\Models\Admissions::class,
    'evaluation.index' => App\Models\Enrollments::class,
    'assessment.index' => App\Models\Studentassessments::class,
    'accounting.index' => App\Models\Payments::class,
    'registrar.index' => App\Models\Enrollments::class,
    'clinic.index' => App\Models\Clinicrecords::class,
    'id.index' => App\Models\Idrequests::class,
    'clearance.index' => App\Models\Studentclearances::class,
];

printf("%-26s %-5s %-42s %s\n", 'account', 'off', 'queue keys the server now answers', 'clearance door');
$disagreements = [];
foreach (Staffusers::orderBy('officeId')->get() as $u) {
    $keys = $reflector->invoke($controller, $u);
    $door = $u->can('viewAny', App\Models\Studentclearances::class) ? 'opens' : '403';
    $cardInPayload = in_array('clearance', $keys, true);
    if (($door === 'opens') !== $cardInPayload) {
        $disagreements[] = $u->email;
    }
    printf("%-26s %-5s %-42s %s\n", $u->email, $u->officeId, implode(',', $keys) ?: '(none)', $door);
}

echo "\n";
$all = Staffusers::all();
foreach ($desks as $route => $model) {
    $reachable = $all->filter(fn (Staffusers $u) => $u->can('viewAny', $model))->count();
    printf("  %-20s reachable by %2d of %d accounts\n", $route, $reachable, $all->count());
}

echo "\naccounts where the Clearance card and the Clearance door disagree: "
.($disagreements === [] ? '(none)' : implode(', ', $disagreements))."\n";
$blocked = $all->filter(fn (Staffusers $u) => $u->can('viewAny', App\Models\Studentclearances::class)
    && $u->role?->value !== 'admin')->count();
echo "non-admin accounts entitled to open the Clearance desk: {$blocked} (all of them now get the card)\n";
