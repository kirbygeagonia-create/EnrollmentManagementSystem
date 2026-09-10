<?php
/**
 * FDD verification v2 — fixes v1 bugs:
 *  (a) wildcard refs (X.*) were dropped by the shape filter
 *  (b) single-word route names (dashboard, login) were excluded
 *  (c) wildcard matching missed the bare index route (X itself)
 *  (d) trait methods counted as controller actions
 */
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// ---------- 1. Real routes ----------
$real = [];
foreach (Route::getRoutes()->getRoutes() as $r) {
    if ($r->getName() === null) continue;
    $real[$r->getName()] = ['methods' => implode('|', array_diff($r->methods(), ['HEAD'])), 'uri' => $r->uri()];
}

// ---------- 2. FDD references (exact + wildcard) ----------
$doc = file_get_contents(__DIR__.'/Documentation/Functional-Decomposition-Diagram.md');
preg_match_all('/`([^`\n]+)`/', $doc, $m);
$exact = [];
$wild = [];
foreach (array_unique($m[1]) as $tok) {
    $tok = trim($tok);
    if (preg_match('/^[0-9a-f]{7,40}$/i', $tok)) continue; // git commit hashes (e.g. c8ea184), not routes
    if (preg_match('/^[a-z][a-z0-9]*(\.[a-z0-9\-]+)+\.\*$/', $tok)) {
        $wild[] = substr($tok, 0, -2); // strip trailing .* -> base
    } elseif (preg_match('/^[a-z][a-z0-9]*(\.[a-z0-9\-]+)*$/', $tok)) {
        $exact[] = $tok; // dotted OR single-word (e.g. dashboard, login)
    }
}
$exact = array_values(array_unique($exact));
$wild = array_values(array_unique($wild));

// ---------- 3. BACKWARD ----------
$invalid = [];
foreach ($exact as $name) {
    if (! isset($real[$name])) $invalid[] = $name;
}
foreach ($wild as $base) {
    $hits = 0;
    foreach ($real as $n => $meta) {
        if ($n === $base || strpos($n, $base.'.') === 0) $hits++;
    }
    if ($hits === 0) $invalid[] = $base.'.* (matches 0 routes - invented)';
}

// ---------- 4. FORWARD ----------
$framework = ['sanctum.csrf-cookie', 'storage.local', 'storage.local.upload']; // Laravel framework routes, not business functions
$covered = [];
foreach ($exact as $name) { if (isset($real[$name])) $covered[$name] = true; }
foreach ($wild as $base) {
    foreach ($real as $n => $meta) {
        if ($n === $base || strpos($n, $base.'.') === 0) $covered[$n] = true;
    }
}
$uncovered = [];
$frameworkSeen = [];
foreach ($real as $name => $meta) {
    if (! isset($covered[$name])) {
        if (in_array($name, $framework, true)) { $frameworkSeen[] = $name; continue; }
        $uncovered[] = $name.'  ['.$meta['methods'].' /'.$meta['uri'].']';
    }
}

// ---------- 5. Controller actions w/o route (trait-aware) ----------
$unroutable = [];
$dir = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/app/Http/Controllers'));
foreach ($dir as $file) {
    if ($file->getExtension() !== 'php') continue;
    $rel = substr($file->getPathname(), strlen(__DIR__.'/app/Http/Controllers/'), -4);
    $class = 'App\\Http\\Controllers\\'.str_replace('/', '\\', $rel);
    if (! class_exists($class)) continue;
    $ref = new ReflectionClass($class);
    $classFile = $ref->getFileName();
    foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $meth) {
        if ($meth->getFileName() !== $classFile) continue; // skip trait methods
        if (in_array($meth->getName(), ['__construct', '__invoke'], true)) continue;
        $sig = $ref->getShortName().'@'.$meth->getName();
        $found = false;
        foreach (Route::getRoutes()->getRoutes() as $r) {
            if (strpos($r->getActionName(), $sig) !== false) { $found = true; break; }
        }
        if (! $found) $unroutable[] = $sig;
    }
}

echo "REAL ROUTES: ".count($real)."\n";
echo "FDD exact refs: ".count($exact)." | wildcard bases: ".count($wild)."\n\n";
echo "--- BACKWARD (FDD -> app) ---\n".($invalid ? "INVALID:\n- ".implode("\n- ", $invalid)."\n" : "All FDD references resolve to real routes.\n");
echo "\n--- FORWARD (app -> FDD) ---\n".($uncovered ? ("UNCOVERED ROUTES (".count($uncovered)."):\n- ".implode("\n- ", $uncovered)."\n") : "Every business route is covered by the FDD.\n");
echo ($frameworkSeen ? "FRAMEWORK ROUTES (excluded, not business functions): ".implode(', ', $frameworkSeen)."\n" : "");
echo "\n--- CONTROLLER ACTIONS WITHOUT ROUTE ---\n".($unroutable ? "- ".implode("\n- ", $unroutable)."\n" : "None.\n");
echo "\nWILDCARD BASES: ".implode(', ', $wild)."\n";
