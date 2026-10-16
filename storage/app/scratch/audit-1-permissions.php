<?php
/**
 * Audit lane 1 — every seeded permission against its actual readers, and every permission name
 * the code checks against the seed catalogue.
 *
 * #47 was found by accident: exam.verify.general was granted to two roles and read by exactly one
 * policy method, which no route ever called. A right like that is not harmless — it appears on the
 * Roles screen, it gets asserted absent in tests as though that proved something, and the document
 * cites it. The inverse failure is worse: code that checks a name nobody seeds can never pass, so
 * the control is silently off.
 *
 * Both directions are reported here. A name counts as READ if it appears in a can()/hasPermissionTo()/
 * checkPermissionTo()/allows()/denies() call, in a Gate/callback string, in the HandleInertiaRequests
 * `can` map, or as a JSX prop key derived from it. Middleware strings like 'permission:x.y' count too.
 *
 * Read-only. Nothing is written.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

config([
    'database.default' => 'mysql',
    'database.connections.mysql.database' => 'ems',
    'database.connections.mysql.username' => 'root',
    'database.connections.mysql.password' => '',
]);
DB::purge('mysql');

$seeded = DB::table('permissions')->orderBy('name')->pluck('name')->all();

$grants = [];
foreach (DB::table('role_permissions as rp')
            ->join('roles as r', 'r.id', '=', 'rp.roleId')
            ->join('permissions as p', 'p.id', '=', 'rp.permissionId')
            ->select('r.name as role', 'p.name as permission')->get() as $g) {
    $grants[$g->permission][] = $g->role;
}
$grantCounts = [];
foreach ($seeded as $name) {
    $grantCounts[$name] = count($grants[$name] ?? []);
}

$srcRoots = [dirname(__DIR__, 3).'/app', dirname(__DIR__, 3).'/routes', dirname(__DIR__, 3).'/resources/js'];
$files = [];
foreach ($srcRoots as $root) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $f) {
        if ($f->isFile() && preg_match('/\.(php|jsx|js|blade\.php)$/', $f->getFilename())) {
            $files[] = $f->getPathname();
        }
    }
}
$haystack = '';
$hits = [];
foreach ($files as $f) {
    $text = file_get_contents($f);
    $haystack .= "\n" . $text;
    foreach ($seeded as $p) {
        if (str_contains($text, $p)) {
            $hits[$p][] = str_replace(dirname(__DIR__, 3) . '/', '', $f);
        }
    }
}

// A name counts as READ if it appears in a call that asks for it — and, because the print rights
// are passed as an ARGUMENT to a helper (RegistrarPolicy::mayPrint($user, $e, 'print.classCard')),
// any quoted appearance in a non-comment line of app/ counts too. Narrowing this to can() alone
// reported three live rights as dead on the first run, which is the wrong kind of confidence.
$readers = [];
// A name is only a permission GHOST if the line asks for it as a right. Without this context test,
// the report was ~120 lines of eager-load paths ('schedules.room'), route names ('accounting.show')
// and config keys ('filesystems.default') — none of which are RBAC at all, and the real signal
// (a can() call naming a right nobody seeds) was impossible to see.
$permissionCall = '~\b(can|cannot|allows|denies|hasPermissionTo|checkPermissionTo|hasAllPermissions|hasAnyPermission|authorize|abort|abortIf|abortUnless|mayPrint|isGrantedToRole)\s*\(\s*(\$\w+\s*,\s*)?[\'"]([a-z][\w]*\.[\w.]+)[\'"]|permission:([a-z][\w]*\.[\w.]+)~';
$ghosts = [];
foreach ($files as $f) {
    $rel = str_replace(dirname(__DIR__, 3) . '/', '', $f);
    if (str_contains($f, 'RbacSeeder.php') || str_starts_with($rel, 'resources/js')) {
        continue;
    }
    foreach (file($f) as $line) {
        $t = trim($line);
        if ($t === '' || str_starts_with($t, '//') || str_starts_with($t, '*')
            || str_starts_with($t, '#') || str_starts_with($t, '/*')) {
            continue;
        }
        if (preg_match_all('/[\'"]([a-z][\w]*\.[\w.]+)[\'"]/', $line, $m)) {
            foreach ($m[1] as $name) {
                $readers[$name][$rel] = ($readers[$name][$rel] ?? 0) + 1;
            }
        }
        if (preg_match_all($permissionCall, $line, $cm, PREG_SET_ORDER)) {
            foreach ($cm as $set) {
                $name = $set[3] ?? $set[4];
                $ghosts[$name][$rel] = ($ghosts[$name][$rel] ?? 0) + 1;
            }
        }
    }
}

// Gate::define abilities are not seeded permissions at all, so their names must not be reported as
// "checked but never seeded" — payment.settle is such an ability, and the first run flagged it.
$gates = [];
foreach ($files as $f) {
    if (preg_match_all('/Gate::define\(\s*[\'"]([\w.]+)[\'"]/', file_get_contents($f), $gm)) {
        foreach ($gm[1] as $g) { $gates[$g] = true; }
    }
}

$orphan = [];
foreach ($seeded as $p) {
    $where = $readers[$p] ?? [];
    if ($where === []) {
        $orphan[] = sprintf("%-42s granted to %2d role(s)  — no reader found anywhere", $p, $grantCounts[$p] ?? 0);
    }
}

$ghost = [];
foreach (array_keys($ghosts) as $n) {
    if (! in_array($n, $seeded, true) && ! isset($gates[$n])) {
        $ghost[] = sprintf("%-42s %s", $n, implode(',', array_keys($ghosts[$n])));
    }
}

printf("seeded permissions: %d   named abilities (Gate::define): %d   names quoted in code: %d   right-asserting call sites: %d\n",
    count($seeded), count($gates), count($readers), count($ghosts));
echo "\n== PERMISSIONS WITH NO RUNTIME READER (candidate dead rights) ==\n";
echo $orphan === [] ? "  (none)\n" : '  ' . implode("\n  ", $orphan) . "\n";
echo "\n== RIGHT-ASSERTING CALLS NAMING A RIGHT THAT IS NEITHER SEEDED NOR A GATE ABILITY ==\n";
echo $ghost === [] ? "  (none)\n" : '  ' . implode("\n  ", array_values(array_unique($ghost))) . "\n";

// Introspection for the falsification run: `php audit-1-permissions.php name...` shows whether each
// name was found as a reader and/or as a right-asserting call, so a mutant's expected verdict can be
// asserted instead of eyeballed.
foreach (array_slice($argv ?? [], 1) as $probe) {
    printf("\n-- probe %s: seeded=%s gate=%s readers=%s call-sites=%s\n",
        $probe,
        in_array($probe, $seeded, true) ? 'y' : 'n',
        isset($gates[$probe]) ? 'y' : 'n',
        ($readers[$probe] ?? []) === [] ? 'none' : implode(',', array_keys($readers[$probe])),
        ($ghosts[$probe] ?? []) === [] ? 'none' : implode(',', array_keys($ghosts[$probe])));
}
