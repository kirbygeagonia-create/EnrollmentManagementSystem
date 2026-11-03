<?php

/**
 * Falsifier for the two Admin/UserManagement lists (§28.3 G-40, the thirtieth and thirty-first wired tables).
 *
 * Both screens are catalogues rather than queues, so the promise worth killing is the ascending default, and each
 * screen's own defect family differs: the permission list has three stored columns and no derived cell, while the
 * role list carries a Permissions count the browser computes from an eager-loaded relation, which is exactly the
 * shape that must NOT become a header. That half is planted on both sides — the handler offers the key, the page
 * paints it — because the two runtimes hold the two halves of one claim.
 *
 * Every needle is anchored on a line that occurs ONCE in a file where the sibling action carries the same shape:
 * `roles.id` and `permissions.id` are the stable tie-breaks, so the direction default is reached through them rather
 * than through `'direction', 'asc'`, which appears in both actions.
 *
 * Usage: php storage/app/scratch/mutate-g40-usermanagement-sort-tests.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = PHP_BINARY;

$controller = 'app/Http/Controllers/Admin/UserManagementController.php';
$rolesJsx = 'resources/js/Pages/Admin/UserManagement/Roles.jsx';
$testFile = 'tests/Feature/Admin/UserManagementListSortTest.php';

$targets = [$controller, $rolesJsx];
$originals = [];
foreach ($targets as $f) {
    if (! is_file($f)) {
        fwrite(STDERR, "REFUSED: {$f} is not in the tree.\n");

        exit(1);
    }
    $originals[$f] = (string) file_get_contents($f);
}
$hashes = array_map('md5', $originals);
$restore = static function () use ($originals): void {
    foreach ($originals as $f => $body) {
        if (file_put_contents($f, $body) === false) {
            fwrite(STDERR, "REFUSED: the restore could not write {$f}.\n");

            exit(1);
        }
    }
};
register_shutdown_function($restore);

$bad = 0;
$legs = 0;

$runFile = static function (string $filter) use ($root, $php, $testFile): array {
    $out = [];
    exec('cd '.$root.' && "'.$php.'" artisan test '.$testFile.' --filter '.$filter.' 2>&1', $out, $code);

    return [(int) $code, preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $out))];
};

$assertRestored = static function () use ($targets, $hashes): bool {
    foreach ($targets as $f) {
        if (md5((string) file_get_contents($f)) !== $hashes[$f]) {
            echo '  TREE NOT RESTORED after '.basename($f)."\n";

            return false;
        }
    }

    return true;
};

$leg = static function (string $name, string $file, string $from, string $to, string $filter, string $needle, string $why) use (
    &$bad, &$legs, $runFile, $restore, $assertRestored
): void {
    $legs++;
    $body = (string) file_get_contents($file);
    $hits = substr_count($body, $from);
    if ($hits !== 1) {
        echo str_pad($name, 46)."BAD   needle occurs {$hits} time(s), wanted 1 — the leg's age, not a defect\n";
        $bad++;

        return;
    }
    if ($from === $to) {
        echo str_pad($name, 46)."BAD   the plant is a no-op by construction\n";
        $bad++;

        return;
    }
    $mutated = str_replace($from, $to, $body);
    // The landing check is "the replacement is on disk and the bytes moved", not "the replacement occurs exactly
    // once": this file legitimately holds the same `'permissions' => $permissions,` line in two renders, so a count
    // of one would refuse a correct shrink. A plant that cannot be observed is the harness's age, never a defect.
    if (file_put_contents($file, $mutated) === false
        || ! str_contains((string) file_get_contents($file), $to)
        || (string) file_get_contents($file) === $body) {
        echo str_pad($name, 46)."BAD   the plant did not land on disk\n";
        $bad++;
        $restore();

        return;
    }
    // A deletion must leave nothing behind; an insertion must change bytes and add its own text. Checking for the
    // retired needle unconditionally refuses an append that is perfectly correct — which is this file's own
    // documented read-back defect, met again inside a harness.
    if (! str_contains($to, $from) && str_contains((string) file_get_contents($file), $from)) {
        echo str_pad($name, 46)."BAD   the needle survived its own replacement, so the plant was partial\n";
        $bad++;
        $restore();

        return;
    }

    [$code, $out] = $runFile($filter);
    $restore();

    if (str_contains($out, 'No tests found')) {
        echo str_pad($name, 46)."BAD   the filter selected no test\n";
        $bad++;
    } elseif ($code === 0 || ! str_contains($out, '1 failed')) {
        echo str_pad($name, 46).'SURVIVED — the promise is real but the case stayed green'."\n";
        $bad++;
    } elseif (! str_contains($out, $needle)) {
        echo str_pad($name, 46)."BAD   failed, but not naming its own assertion (wanted [{$needle}])\n";
        $bad++;
    } elseif (! $assertRestored()) {
        $bad++;
    } else {
        echo str_pad($name, 46)."ok    killed by the case that owns it — {$why}\n";
    }
};

$control = static function (string $name, string $filter) use (&$bad, &$legs, $runFile): void {
    $legs++;
    [$code, $out] = $runFile($filter);
    if ($code !== 0 || ! str_contains($out, '1 passed')) {
        echo str_pad($name, 46).'BAD   control is not green: '.str_replace("\n", ' / ', mb_substr($out, -220))."\n";
        $bad++;

        return;
    }
    echo str_pad($name, 46)."ok    control green\n";
};

// --------------------------------------------------------------------------- the blocks each leg reaches

$permissionsApply = <<<'PA'
            (string) $request->input('direction', 'asc'),
            $sortable,
            'name',
            'permissions.id'
PA;

$rolesApply = <<<'RA'
            (string) $request->input('direction', 'asc'),
            $sortable,
            'name',
            'roles.id'
RA;

// The permissions render is the ONLY one whose first key is `permissions` — the roles render also carries a
// `permissions` key beside its `sort`, so a two-line needle matched both and the leg refused its own plant.
$permissionEcho = "        return Inertia::render('Admin/UserManagement/Permissions', [\n"
    ."            'permissions' => \$permissions,\n"
    ."            'sort' => ListSort::present(\$sort, \$sortable),";

$rolesDescriptionSpec = "            'description' => ['column' => 'description'],";

$permissionsPaginate = '        $permissions = $query->paginate(20)->withQueryString();';

$rolesPaginate = '        $roles = $query->paginate(20)->withQueryString();';

// --------------------------------------------------------------------------- the controls

$control('control-permission-default', 'the_permission_list_opens_with_the_names_reading_a_to_z');
$control('control-role-default', 'the_role_list_opens_with_the_names_reading_a_to_z');
$control('control-guard-order', 'the_guard_header_orders_the_column_the_screen_paints');
$control('control-pairing', 'every_header_a_page_paints_is_a_key_its_action_obeys');
$control('control-source-read', 'both_actions_read_their_own_request_and_no_hard_coded_order_survives');

// --------------------------------------------------------------------------- the plants

$leg(
    'permission-default-flipped-to-zees',
    $controller,
    $permissionsApply,
    str_replace("'direction', 'asc'", "'direction', 'desc'", $permissionsApply),
    'the_permission_list_opens_with_the_names_reading_a_to_z',
    'opens ascending',
    'a catalogue of right names opens A to Z; descending on arrival is a queue answer'
);

$leg(
    'role-default-flipped-to-zees',
    $controller,
    $rolesApply,
    str_replace("'direction', 'asc'", "'direction', 'desc'", $rolesApply),
    'the_role_list_opens_with_the_names_reading_a_to_z',
    'did not open with the names ascending',
    'the role directory is read by name, so its default is the other end of the alphabet when this moves'
);

$leg(
    'permission-order-applied-but-never-echoed',
    $controller,
    $permissionEcho,
    // The replacement keeps the render line and drops only the echo. An earlier cut of this `$to` replaced the whole
    // three-line needle with a single prop line, which deleted the `Inertia::render(` opener too: the plant then
    // failed the case through a PHP parse error — a `1 failed` that named nothing about the promise.
    "        return Inertia::render('Admin/UserManagement/Permissions', [\n            'permissions' => \$permissions,",
    'the_permission_list_opens_with_the_names_reading_a_to_z',
    'not told which order was applied',
    "lane 46's NO-RETURN from the sort side: the header resets while the list stays ordered"
);

$rolesEcho = "        return Inertia::render('Admin/UserManagement/Roles', [\n"
    ."            'roles' => \$roles,\n"
    ."            'permissions' => \$permissions,\n"
    ."            'sort' => ListSort::present(\$sort, \$sortable),";

$leg(
    'role-order-applied-but-never-echoed',
    $controller,
    $rolesEcho,
    "        return Inertia::render('Admin/UserManagement/Roles', [\n            'roles' => \$roles,\n            'permissions' => \$permissions,",
    'the_role_list_opens_with_the_names_reading_a_to_z',
    'the role page was not told which order was applied',
    'the screen keeps an arrow the handler never reported, which is the control-resets defect on the role desk'
);

// The derived-count half, planted on both sides of the pairing.
$leg(
    'derived-count-offered-by-the-handler',
    $controller,
    $rolesDescriptionSpec,
    $rolesDescriptionSpec."\n            'permissionsCount' => ['column' => 'description'],",
    'the_derived_permission_count_is_not_offered_as_an_order',
    'a header was offered for a count the screen computes',
    'the count comes from the loaded relation in the browser, so the query holds no column to order by'
);

$leg(
    'role-page-paints-a-header-the-handler-refuses',
    $rolesJsx,
    "sortKey: 'description'",
    "sortKey: 'created_at'",
    'every_header_a_page_paints_is_a_key_its_action_obeys',
    'offers a header for',
    'a sortKey the action does not declare paints nothing, so the desk silently loses the control'
);

// Dropping the applied order is invisible to the two default cases (the fixture is inserted in name order on
// purpose), so this leg is aimed at the header whose column has no alphabetical relationship to insertion order.
$leg(
    'permission-order-never-applied',
    $controller,
    $permissionsPaginate,
    '        $permissions = Permissions::query()->paginate(20)->withQueryString();',
    'the_guard_header_orders_the_column_the_screen_paints',
    'did not return its own column in order',
    'the echo still names a column while the builder the desk reads was never ordered'
);

$leg(
    'role-hard-coded-order-returns',
    $controller,
    $rolesPaginate,
    "        \$roles = \$query->orderByDesc('id')->paginate(20)->withQueryString();",
    'both_actions_read_their_own_request_and_no_hard_coded_order_survives',
    'hard-coded newest-first order',
    'the order the mechanism replaced sits beside it, so the last write wins while every header still reports its column'
);

// --------------------------------------------------------------------------- the controls again

$control('control-permission-default-after-plants', 'the_permission_list_opens_with_the_names_reading_a_to_z');
$control('control-role-default-after-plants', 'the_role_list_opens_with_the_names_reading_a_to_z');
$control('control-pairing-after-plants', 'every_header_a_page_paints_is_a_key_its_action_obeys');
$control('control-source-read-after-plants', 'both_actions_read_their_own_request_and_no_hard_coded_order_survives');

echo "\n=== verdict ===\n";
echo $bad === 0
    ? "ALL KILLED: {$legs} leg(s), 0 bad\n"
    : 'NOT ALL KILLED: '.$legs.' leg(s), '.$bad." bad\n";
exit($bad === 0 ? 0 : 1);
