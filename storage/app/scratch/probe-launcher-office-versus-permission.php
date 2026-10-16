<?php
/**
 * Does the launcher's hand-kept office list agree with what the server will actually authorise?
 *
 * MegaAppLauncher.jsx gates most cards by `officeId`; the routes gate on a permission (evaluation.index asks
 * EvaluationPolicy::viewAny, which asks the ability named in its own source). Two independent answers can
 * disagree silently: a desk shown a card its account cannot open, or denied the shortcut to work it IS
 * permitted to do. The predicate below is a line-for-line transcription of isAuthorized() in the component,
 * and each ability is read out of the policy file rather than guessed, so a mismatch here is a fact about
 * the two gates and not about this probe.
 */
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

/** route => [card name, officeIds as written in the JSX, policy file whose viewAny decides] */
$cards = [
    'admission.index' => ['Admissions Desk', 6, 'app/Policies/AdmissionPolicy.php'],
    'exam.index' => ['Guidance & Exam Lab', [4, 7], 'app/Policies/ExamPolicy.php'],
    'evaluation.index' => ['Academic Evaluation', 4, 'app/Policies/EvaluationPolicy.php'],
    'assessment.index' => ['Scholarship & Assessment', 3, 'app/Policies/AssessmentPolicy.php'],
    'accounting.index' => ['Cashier Payment Desk', 2, 'app/Policies/AccountingPolicy.php'],
    'registrar.index' => ['Registrar Official Suite', 1, 'app/Policies/RegistrarPolicy.php'],
    'blocking.index' => ['Blocking & Timetables', 5, 'app/Policies/BlockingPolicy.php'],
    'clinic.index' => ['School Clinic', 11, 'app/Policies/ClinicPolicy.php'],
    'id.index' => ['Student ID Hub', 22, 'app/Policies/IDPolicy.php'],
];

/**
 * The component's own rule, transcribed. Roles come from staffusers.role; admin short-circuits first.
 */
$launcherSees = function (string $route, mixed $officeIds, string $role, ?int $officeId): bool {
    if ($role === 'admin') {
        return true;
    }

    $ids = is_array($officeIds) ? $officeIds : [$officeIds];

    if (in_array($role, ['dean', 'programHead', 'instructor'], true)) {
        if (in_array($route, ['evaluation.index', 'blocking.index', 'students.index'], true)) {
            return true;
        }
        if ($role === 'dean') {
            return count(array_intersect($ids, [4, 6, 7])) > 0;
        }
        if ($role === 'programHead') {
            return count(array_intersect($ids, [4, 6])) > 0;
        }

        return count(array_intersect($ids, [4])) > 0;
    }

    return in_array($officeId, $ids, true);
};

$abilityFrom = function (string $policyFile): ?string {
    $src = file_get_contents($policyFile);
    if (preg_match('/public function viewAny.*?\{.*?hasPermissionTo\(\s*[\'"]([^\'"]+)[\'"]/s', $src, $m) === 1) {
        return $m[1];
    }

    return null;
};

$grants = DB::table('role_permissions')
    ->join('roles', 'roles.id', '=', 'role_permissions.roleId')
    ->join('permissions', 'permissions.id', '=', 'role_permissions.permissionId')
    ->select('roles.name as roleName', 'permissions.name as permName')
    ->get()
    ->groupBy('roleName')
    ->map(fn ($rows) => $rows->pluck('permName')->flip()->all());

$roleNames = DB::table('roles')->pluck('name', 'id');
$pivot = DB::table('staff_roles')->where('model_type', 'App\\Models\\Staffusers')->get()->groupBy('model_id');

$accounts = DB::table('staffusers')->select('userId', 'username', 'officeId', 'role')
    ->whereNotNull('officeId')->orderBy('userId')->limit(500)->get();

printf("seeded accounts examined: %d\n\n", count($accounts));

foreach ($cards as $route => [$label, $officeIds, $policyFile]) {
    if (! is_file($policyFile)) {
        printf("%s: policy file %s not found — no verdict\n\n", $route, $policyFile);

        continue;
    }
    $ability = $abilityFrom($policyFile);
    if ($ability === null) {
        printf("%-20s %-24s viewAny does not read a permission in %s — no verdict\n\n", $route, $label, $policyFile);

        continue;
    }

    $sees = [];
    $can = [];

    foreach ($accounts as $a) {
        $roles = collect($pivot->get($a->userId, []))->map(fn ($p) => $roleNames[$p->roleId] ?? null)->filter();

        if ($launcherSees($route, $officeIds, strtolower((string) $a->role), $a->officeId)) {
            $sees[] = $a->username.'/'.$a->role.'/o'.$a->officeId;
        }

        foreach ($roles as $r) {
            if (isset($grants[$r][$ability])) {
                $can[] = $a->username.'/'.$a->role.'/o'.$a->officeId;

                break;
            }
        }
    }

    $cardWithoutAccess = array_values(array_diff($sees, $can));
    $workWithoutCard = array_values(array_diff($can, $sees));

    printf("%s (%s) — JSX officeId %s, server asks '%s'\n", $route, $label, json_encode($officeIds), $ability);
    printf("   shown to %d account(s); the ability reaches %d\n", count($sees), count($can));
    printf("   card but no access : %s\n", $cardWithoutAccess ? implode(', ', array_slice($cardWithoutAccess, 0, 8)) : 'none');
    printf("   access but no card : %s\n\n", $workWithoutCard ? implode(', ', array_slice($workWithoutCard, 0, 8)) : 'none');
}
