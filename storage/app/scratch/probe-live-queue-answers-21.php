<?php

/**
 * Re-measure the document's "answer for 15 of 15 staff accounts" claim now that the install has more.
 *
 * §?P 953 says admission.index, evaluation.index and registrar.index open for every seeded account. The
 * batch that seated six per-college department desks added accounts to live ems, so the denominator moved;
 * the numerator has to be read again rather than assumed, because a new account could hold a role that
 * lacks the view right. This drives the same calls the policies make — hasPermissionTo on the loaded
 * model — and reads the ability out of each policy file instead of hardcoding it, so a policy that stops
 * checking a permission shows up here as "no verdict" rather than as a silent pass.
 *
 * Read-only: nothing is written, no request is dispatched.
 */
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Staffusers;
use Illuminate\Support\Facades\DB;

$queues = [
    'admission.index' => 'app/Policies/AdmissionPolicy.php',
    'evaluation.index' => 'app/Policies/EvaluationPolicy.php',
    // routes/web.php:147 → RegistrarController::index → authorize('viewAny', Enrollments::class), and
    // AuthServiceProvider maps Enrollments to EvaluationPolicy, not RegistrarPolicy.
    'registrar.index' => 'app/Policies/EvaluationPolicy.php',
];

$abilityFrom = function (string $policyFile): ?string {
    $src = file_get_contents($policyFile);
    if (preg_match('/public function viewAny.*?\{.*?hasPermissionTo\(\s*[\'"]([^\'"]+)[\'"]/s', $src, $m) === 1) {
        return $m[1];
    }

    return null;
};

$accounts = Staffusers::orderBy('userId')->get();
printf("staff accounts on this install: %d\n", $accounts->count());

foreach ($queues as $route => $policyFile) {
    $ability = $abilityFrom($policyFile);
    printf("\n%s — %s::viewAny asks '%s'\n", $route, basename($policyFile, '.php'), $ability ?? '(no permission read)');

    if ($ability === null) {
        echo "  NO VERDICT: the policy body does not read a permission name.\n";

        continue;
    }

    $yes = [];
    $no = [];

    foreach ($accounts as $a) {
        if ($a->hasPermissionTo($ability)) {
            $yes[] = $a->username;
        } else {
            $no[] = $a->username;
        }
    }

    // The positive control: a probe in which nothing ever passes cannot tell "every desk is closed" from
    // "this probe never asked". Both halves must be counted before either is quoted.
    if ($yes === []) {
        printf("  NO CONCLUSION: no account passes '%s' — the probe or the grants are broken, not the claim.\n", $ability);

        continue;
    }

    printf("  opens for %d of %d\n", count($yes), $accounts->count());
    printf("  accounted for : %d of %d (yes+no must equal the total or a bucket was dropped)\n",
        count($yes) + count($no), $accounts->count());
    printf("  refused       : %s\n", $no === [] ? 'none' : implode(', ', $no));
}

echo "\nrole enum of every account, so a new desk is attributable:\n";
foreach ($accounts as $a) {
    $roles = DB::table('staff_roles')
        ->join('roles', 'roles.id', '=', 'staff_roles.roleId')
        ->where('staff_roles.model_id', $a->userId)
        ->where('staff_roles.model_type', $a->getMorphClass())
        ->pluck('roles.name')
        ->implode('/') ?: '(NO ROLE)';

    printf("  %-22s enum=%-14s office=%-4s unit=%-4s spatie=%s\n",
        $a->username, (string) $a->role?->value, (string) $a->officeId, (string) $a->unitId, $roles);
}
