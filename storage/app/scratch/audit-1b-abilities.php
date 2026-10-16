<?php

/**
 * Audit lane 1b — abilities the policies DEFINE against abilities the code ever ASKS FOR.
 *
 * #47 (exam.verify.general) was this shape: a policy method existed, was spelled like a right,
 * and nothing in the app ever passed its ability name to authorize()/can(). The permission row was
 * seeded and granted so the dead method looked supported. A method-only death is invisible to the
 * permission scan because the name may never have been seeded at all.
 *
 * So this reads the other side: every public method of every App\Policies\* class, every
 * Gate::define() name, and every ability string the app passes to a guard call. Anything defined
 * but never asked is reported; anything asked but never defined is reported too (that one throws
 * "Attempt to call an undefined ability" only when the gate is consulted, i.e. in production).
 *
 * Read-only.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$root = dirname(__DIR__, 3);

$guardCall = '~(?:->authorize|::authorize|\$this->authorize|Gate::allows|Gate::denies|Gate::forUser\(\s*\$?\w*\s*\)->(?:allows|denies|check)|->can|->cannot|->allows|->denies|abort_unless|checkPermissionTo|hasPermissionTo)\s*\(~';

// ---- what the code ASKS for ----------------------------------------------------
$asked = [];
$askedIn = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app')) as $f) {
    if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.php')) {
        continue;
    }
    $rel = str_replace($root.'\\', '', $f->getPathname());
    foreach (file($f->getPathname()) as $no => $line) {
        $t = trim($line);
        if ($t === '' || str_starts_with($t, '//') || str_starts_with($t, '*')) {
            continue;
        }
        if (! preg_match($guardCall, $t)) {
            continue;
        }
        // The first quoted word, or a bare::Class + [Class, ...] form, is the ability.
        if (preg_match('/[\'"]([a-z][\w]*\.[\w.]+)[\'"]/', $t, $m)) {
            $asked[$m[1]] = true;
            $askedIn[$m[1]][] = $rel.':'.($no + 1);

            continue;
        }
        if (preg_match('/->authorize\(\s*[\'"](\w+)[\'"]/', $t, $m)) {
            $asked[$m[1]] = true;
            $askedIn[$m[1]][] = $rel.':'.($no + 1);

            continue;
        }
        if (preg_match('/->authorize\(\s*([A-Za-z]\w*)::class/', $t, $m)) {
            // Resource-registers (authorize('create', Model::class)) name a policy method plainly.
            $asked[strtolower($m[1])] = true;
            $askedIn[strtolower($m[1])][] = $rel.':'.($no + 1).' (resource)';
        }
    }
}

// ---- what the policies DEFINE --------------------------------------------------
$defined = [];
foreach (glob($root.'/app/Policies/*.php') as $f) {
    $src = file_get_contents($f);
    preg_match('/namespace\s+([\w\\\\]+);/', $src, $ns);
    preg_match('/class\s+(\w+)/', $src, $cl);
    $class = ($ns[1] ?? 'App\\Policies').'\\'.($cl[1] ?? '?');
    foreach (explode("\n", $src) as $no => $line) {
        if (preg_match('/^\s{4}public function (\w+)\s*\(/', $line, $m)) {
            if (in_array($m[1], ['__construct'], true)) {
                continue;
            }
            $defined[$m[1]][] = $class.'::'.$m[1].'  ('.basename($f).':'.($no + 1).')';
        }
    }
}
foreach (glob($root.'/app/Providers/*.php') as $f) {
    if (preg_match_all('/Gate::define\(\s*[\'"]([\w.]+)[\'"]/', file_get_contents($f), $gm)) {
        foreach ($gm[1] as $g) {
            $defined[$g][] = basename($f)." (Gate::define)";
        }
    }
}

// Laravel's own resource abilities are asked by Route::resource-style registers; none exist here,
// but the framework also asks 'beforeAnyRole'/'viewAny' etc. implicitly through authorizeResource.
$resourceAbilities = ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'];

$neverAsked = [];
foreach ($defined as $ability => $where) {
    if (isset($asked[$ability])) {
        continue;
    }
    $neverAsked[] = sprintf('%-34s %s', $ability, implode(' | ', $where));
}

$neverDefined = [];
foreach (array_keys($asked) as $ability) {
    if (isset($defined[$ability]) || in_array($ability, $resourceAbilities, true)) {
        continue;
    }
    // Permission-name guards (can('x.y')) are checked against spatie by name and need no policy.
    $neverDefined[] = sprintf('%-34s %s', $ability, implode(' | ', array_slice($askedIn[$ability] ?? [], 0, 3)));
}

printf("abilities asked in code: %d   abilities defined (policy methods + gates): %d\n\n",
    count($asked), count($defined));

echo "== DEFINED BUT NEVER ASKED (dead ability, the #47 shape) ==\n";
echo $neverAsked === [] ? "  (none)\n" : '  '.implode("\n  ", $neverAsked)."\n";

echo "\n== ASKED BUT NEVER DEFINED (falls through to spatie-by-name, or throws when it does not exist) ==\n";
echo $neverDefined === [] ? "  (none)\n" : '  '.implode("\n  ", array_unique($neverDefined))."\n";
