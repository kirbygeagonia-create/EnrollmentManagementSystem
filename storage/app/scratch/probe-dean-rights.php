<?php
/**
 * Does the demo's dean account actually hold the endorsement right? Asked through the application,
 * because staff_roles.model_type is stored double-escaped and a hand-written SQL literal for it
 * matches nothing at all.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Staffusers;

foreach (['dean_academic', 'office7_head', 'office8_head'] as $username) {
    $u = Staffusers::with('roles')->where('username', $username)->first();
    if ($u === null) {
        echo "{$username}: no such account\n";

        continue;
    }
    $rights = collect($u->getPermissionNames())
        ->filter(fn ($n) => str_contains($n, 'shift.') || str_contains($n, 'evaluation.sign') || $n === 'enrollment.approve' || $n === 'print.enrollmentForm')
        ->sort()
        ->values()
        ->all();
    printf("%-15s roles: %-34s rights asked about: %s\n", $username, implode('+', $u->roles->pluck('name')->all()), implode(', ', $rights) ?: '(none)');
}
