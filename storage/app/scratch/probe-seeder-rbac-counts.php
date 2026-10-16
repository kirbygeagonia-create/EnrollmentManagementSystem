<?php
// Counts the RBAC seeder's own figures, the way §22.3 states them, so the document's
// "read from the seeder text" claim can be re-checked instead of retyped.
$src = file_get_contents('database/seeders/RbacSeeder.php');

// Step 1's catalogue: $permissions = [ 'module' => [ 'a.b', 'a.c', ... ], ... ]
preg_match('/\$permissions\s*=\s*\[(.*?)\n        \];/s', $src, $block) || exit("no catalogue block\n");
$body = $block[1];

preg_match_all("/^            '([a-z][A-Za-z]*)' => \[/m", $body, $mods);
$total = 0;
foreach ($mods[1] as $mod) {
    if (! preg_match("/^            '".$mod."' => \[(.*?)\n            \],/ms", $body, $m)) {
        printf("  %-22s NOT PARSED\n", $mod);
        continue;
    }
    preg_match_all("/^                '([a-z][A-Za-z.]*)',/m", $m[1], $in);
    printf("  %-22s %d\n", $mod, count($in[1]));
    $total += count($in[1]);
}
echo 'modules in the catalogue   = ', count($mods[1]), PHP_EOL;
echo 'permissions defined        = ', $total, PHP_EOL;
echo 'Role::updateOrCreate calls = ', substr_count($src, 'Role::updateOrCreate'), PHP_EOL;
echo 'Role::firstOrCreate calls  = ', substr_count($src, 'Role::firstOrCreate'), PHP_EOL;

preg_match_all('/->syncPermissions\(([^;]*?)\);/s', $src, $calls);
$literal = $computed = 0;
foreach ($calls[1] as $arg) {
    str_contains($arg, 'array_keys') || str_contains($arg, '$') ? $computed++ : $literal++;
}
echo 'syncPermissions calls      = ', count($calls[1]), " (literal {$literal}, computed {$computed})", PHP_EOL;
