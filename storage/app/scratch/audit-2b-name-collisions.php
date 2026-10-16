<?php

/**
 * Audit lane 2b — the name-collision question.
 *
 * Spatie registers a Gate::before that returns TRUE for any ability whose name is a permission the
 * user holds (vendor/spatie/laravel-permission/src/PermissionRegistrar.php:121). That hook runs
 * before Laravel consults a policy or a Gate::define, so:
 *
 *   $this->authorize('blocking.assignStudents', $block)
 *
 * is answered by the permission row alone. A policy method named assignStudents() that additionally
 * scopes the action to office 5 is skipped — silently, and only for users who hold the right but not
 * the scope. AuthServiceProvider already knows this: assessment.computeAtDesk and
 * payment.recordAtDesk were named that way on purpose (see its comment at line ~158).
 *
 * This lists every ability string the app passes to a guard call, and for each one says whether it
 * collides with a seeded permission name, whether an explicit Gate::define owns it, and whether a
 * policy has a same-suffix method that a reader would assume is running.
 *
 * Read-only.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 3);
config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'ems',
    'database.connections.mysql.username' => 'root', 'database.connections.mysql.password' => '']);
DB::purge('mysql');

$permissions = DB::table('permissions')->pluck('name')->all();
$permSet = array_flip($permissions);

// Gate::define names, and the policy method each one forwards to.
$defines = [];
foreach (glob($root.'/app/Providers/*.php') as $f) {
    $src = file_get_contents($f);
    if (preg_match_all('/Gate::define\(\s*[\'"]([\w.-]+)[\'"]\s*,\s*function[^{]*{\s*return app\((\w+)::class\)->(\w+)\(/s', $src, $gm, PREG_SET_ORDER)) {
        foreach ($gm as $set) {
            $defines[$set[1]] = $set[2].'::'.$set[3];
        }
    }
}
$otherDefines = [];
foreach (glob($root.'/app/Providers/*.php') as $f) {
    if (preg_match_all('/Gate::define\(\s*[\'"]([\w.-]+)[\'"]/', file_get_contents($f), $gm)) {
        foreach ($gm[1] as $g) {
            $otherDefines[$g] = true;
        }
    }
}

// Every policy method, keyed by method name.
$policyMethods = [];
foreach (glob($root.'/app/Policies/*.php') as $f) {
    $cls = basename($f, '.php');
    foreach (explode("\n", file_get_contents($f)) as $line) {
        if (preg_match('/^\s{4}public function (\w+)\s*\(/', $line, $m)) {
            $policyMethods[$m[1]][] = $cls;
        }
    }
}

// Abilities the app asks for.
$guardCall = '~(?:\$this->authorize|->authorize|Gate::allows|Gate::denies|->can|->cannot|->allows|->denies|abort_unless)\s*\(~';
$asked = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app')) as $f) {
    if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.php')) {
        continue;
    }
    $rel = str_replace($root.'\\', '', $f->getPathname());
    foreach (file($f->getPathname()) as $no => $line) {
        $t = trim($line);
        if ($t === '' || str_starts_with($t, '//') || str_starts_with($t, '*') || ! preg_match($guardCall, $t)) {
            continue;
        }
        if (! preg_match('/[\'"]([a-z][\w]*(?:\.[\w]+)+)[\'"]/', $t, $m)) {
            continue;
        }
        $asked[$m[1]]['at'][] = $rel.':'.($no + 1);
    }
}

$collision = [];
$owned = [];
$orphan = [];
foreach ($asked as $ability => $info) {
    $suffix = substr($ability, (int) strrpos($ability, '.') + 1);
    $definesIt = isset($otherDefines[$ability]);
    $collides = isset($permSet[$ability]);
    $shadowed = $policyMethods[$suffix] ?? [];

    $row = sprintf('%-32s perm=%s gate=%s%s   %s',
        $ability,
        $collides ? 'YES' : 'no ',
        $definesIt ? 'YES->'.($defines[$ability] ?? 'closure') : 'no ',
        $shadowed === [] ? '' : ' policy-method-exists: '.implode('/', $shadowed),
        implode(' ', array_slice($info['at'], 0, 2)));

    if ($definesIt) {
        $owned[] = $row;
    } elseif ($collides && $shadowed !== []) {
        $collision[] = $row;
    } elseif ($collides) {
        $owned[] = $row;
    } else {
        $orphan[] = $row;
    }
}

printf("seeded permissions: %d   Gate::define names: %d   dotted abilities asked: %d\n",
    count($permissions), count($otherDefines), count($asked));

echo "\n== COLLISION: ability IS a permission name and a same-suffix policy method exists, with NO gate owning it ==\n";
echo "   (for a holder of the permission the before-hook returns true and the policy body never runs)\n";
echo $collision === [] ? "  (none)\n" : '  '.implode("\n  ", $collision)."\n";

echo "\n== OWNED: an explicit Gate::define forwards it, or it is a bare permission check with no policy body ==\n";
echo $owned === [] ? "  (none)\n" : '  '.implode("\n  ", $owned)."\n";

echo "\n== NEITHER a permission nor a defined gate (would need a policy method verbatim) ==\n";
echo $orphan === [] ? "  (none)\n" : '  '.implode("\n  ", $orphan)."\n";
