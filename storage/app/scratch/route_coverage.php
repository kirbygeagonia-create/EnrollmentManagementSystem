<?php

// Read-only audit scratch tool (gitignored path): which mutating named routes are
// never reached by any test — matched either by route name (route('x')) or by the
// literal URI the test posts to ('/x').

$repo = dirname(__DIR__, 3);
// The route table is asked of the running application, not read from `routes.json`: that snapshot was hand-saved on
// 2026-10-15 and nothing rebuilt it, so this audit was matching test coverage against an older build's routes — the
// same defect lane 34 was found carrying. A tool that shells out to `artisan` must run from the project directory.
$routesJson = (string) shell_exec('cd '.escapeshellarg($repo).' && '.escapeshellarg(PHP_BINARY).' artisan route:list --json 2>&1');
$routes = json_decode($routesJson, true);
if (! is_array($routes) || $routes === []) {
    printf("REFUSED: `artisan route:list --json` answered no routes (%s) — an empty route table would clear every\n"
        ."        mutating route as covered, which is the failure this audit exists to catch.\n",
        substr(str_replace("\n", ' ', $routesJson), 0, 160));
    exit(1);
}

$hay = '';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($repo.'/tests'));
foreach ($files as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
        $hay .= file_get_contents($f->getPathname());
    }
}

$framework = ['storage.', 'sanctum', 'login', 'logout', 'register', 'up', 'favicon'];

$missing = [];
$total = 0;
foreach ($routes as $r) {
    $methods = explode('|', $r['method']);
    if (! array_intersect($methods, ['POST', 'PATCH', 'PUT', 'DELETE'])) {
        continue;
    }
    $name = $r['name'] ?? '';
    if ($name === '' || str_contains($name, 'clockwork')) {
        continue;
    }
    foreach ($framework as $skip) {
        if (str_starts_with($name, $skip) || str_contains($name, $skip)) {
            continue 2;
        }
    }

    // /registrar/enrollments/{enrollment}/void  ->  '/registrar/enrollments'
    $uri = preg_replace('/\{[^}]*\}/', '', '/'.ltrim($r['uri'], '/'));
    $uri = rtrim($uri, '/');

    $total++;
    $byName = $name !== '' && str_contains($hay, $name);
    $byUri = $uri !== '' && $uri !== '/' && str_contains($hay, "'".$uri."'");

    if (! $byName && ! $byUri) {
        $controller = str_replace('App\\Http\\Controllers\\', '', $r['action'] ?? '');
        $missing[] = str_pad(implode('|', array_intersect($methods, ['POST', 'PATCH', 'PUT', 'DELETE'])), 22)
            .$name.'   '.$uri.'   ('.$controller.')';
    }
}

echo implode("\n", $missing)."\n\n".count($missing).' of '.$total." app mutating named routes never reached by a test\n";
