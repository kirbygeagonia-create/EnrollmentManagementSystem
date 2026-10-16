<?php

// Read-only audit scratch tool (gitignored path): which mutating named routes are
// never reached by any test — matched either by route name (route('x')) or by the
// literal URI the test posts to ('/x').

$repo = dirname(__DIR__, 3);
$routes = json_decode(file_get_contents($repo.'/storage/app/scratch/routes.json'), true);

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

echo implode("\n", $missing)."\n\n".count($missing)." of ".$total." app mutating named routes never reached by a test\n";
