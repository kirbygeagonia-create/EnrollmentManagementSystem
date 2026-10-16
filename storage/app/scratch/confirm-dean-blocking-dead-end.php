<?php
/**
 * Confirm the launcher's dean allow-list against the RBAC API the server itself uses.
 *
 * The comparison probe said MegaAppLauncher shows the Blocking & Timetables card to `dean_academic` (its
 * hard-coded allow-list at line 287) while that account's role does not hold `block.view`. This asks Spatie
 * directly — the same call BlockingPolicy::viewAny makes — so the verdict comes from the authorisation layer,
 * not from a hand-written join over the RBAC pivots.
 */
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Staffusers;

$accounts = Staffusers::whereIn('username', ['dean_academic', 'office7_head', 'office4_head', 'staff8', 'instr.mendoza'])->get();

foreach ($accounts as $a) {
    $roles = $a->roles->pluck('name')->implode(', ') ?: '(no role row)';

    printf(
        "%-16s office %-4s role-column %-12s roles: %s\n",
        $a->username,
        (string) $a->officeId,
        // The launcher branches on the raw staffusers.role string, so read the column as stored rather than
        // the StaffRole enum the model casts it to.
        (string) $a->getRawOriginal('role'),
        $roles
    );

    foreach (['block.view', 'evaluation.view', 'students.view', 'enrollment.approve'] as $p) {
        $has = $a->hasPermissionTo($p) ? 'yes' : 'NO ';
        printf("                              %-18s %s\n", $p, $has);
    }
    echo "\n";
}
