<?php
/**
 * What would change if the launcher's dean/programHead/instructor allow-list answered to a right?
 *
 * MegaAppLauncher.jsx:287 hands those three roles the evaluation and blocking cards no matter what their
 * roles hold. The project's rule says navigation should answer to the right the desk's own route asks, so the
 * candidate fix gates those two cards on evaluation.view and block.view through the Gate. This prints, for
 * every seeded account, the cards the two answers disagree about — so the change is reviewed as a list of
 * concrete effects rather than as an idea. Nothing is written.
 */
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Staffusers;
use Illuminate\Support\Facades\DB;

$accounts = Staffusers::whereNotNull('officeId')->orderBy('userId')->get();

/** The two cards the allow-list decides today, and the rights they would ask for instead. */
$cards = [
    'evaluation.index' => ['officeIds' => 4, 'ability' => 'evaluation.view'],
    'blocking.index' => ['officeIds' => 5, 'ability' => 'block.view'],
];

/** The component's current rule, transcribed for these two cards only. */
$today = function (string $route, mixed $officeIds, string $role, ?int $officeId): bool {
    if ($role === 'admin') {
        return true;
    }
    if (in_array($role, ['dean', 'programHead', 'instructor'], true)) {
        return true; // line 287: these routes are granted unconditionally
    }
    $ids = is_array($officeIds) ? $officeIds : [$officeIds];

    return in_array($officeId, $ids, true);
};

/** The proposed rule: the office list still applies to staff and office heads; the three academic roles
 *  keep their access only where the route's own right actually reaches them. */
$proposed = function (string $route, mixed $officeIds, string $role, ?int $officeId, bool $holds) use ($today): bool {
    if ($role === 'admin') {
        return true;
    }
    if (in_array($role, ['dean', 'programHead', 'instructor'], true)) {
        return $holds;
    }

    return $today($route, $officeIds, $role, $officeId);
};

$roleNames = DB::table('roles')->pluck('name', 'id');
$pivot = DB::table('staff_roles')->where('model_type', 'App\\Models\\Staffusers')->get()->groupBy('model_id');

$diffs = 0;

foreach ($accounts as $a) {
    $role = strtolower((string) $a->getRawOriginal('role'));

    foreach ($cards as $route => $card) {
        $grantedRoles = collect($pivot->get($a->userId, []))
            ->map(fn ($p) => $roleNames[$p->roleId] ?? null)
            ->filter()
            ->all();

        $holds = false;
        foreach ($grantedRoles as $r) {
            $n = DB::table('role_permissions')
                ->join('roles', 'roles.id', '=', 'role_permissions.roleId')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permissionId')
                ->where('roles.name', $r)
                ->where('permissions.name', $card['ability'])
                ->count();
            if ($n > 0) {
                $holds = true;

                break;
            }
        }

        $now = $today($route, $card['officeIds'], $role, $a->officeId);
        $then = $proposed($route, $card['officeIds'], $role, $a->officeId, $holds);

        if ($now !== $then) {
            $diffs++;
            printf(
                "%-16s office %-3s role %-12s %-17s %-8s %s   %s\n",
                $a->username,
                (string) $a->officeId,
                $role,
                $route,
                $card['ability'],
                $holds ? 'holds' : 'DOES NOT hold',
                $now ? 'today: card  -> would LOSE it' : 'today: no card -> would GAIN it'
            );
        }
    }
}

printf("\n%d account/card combinations would change.\n", $diffs);
