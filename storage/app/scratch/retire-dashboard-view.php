<?php

/**
 * Retire `dashboard.view` (owner ruling 2026-10-10, same class as #47).
 *
 * Every bulk edit goes through a script that asserts its own match count first, because a silent
 * near-miss in RBAC reads as success: the seeder would still run, the roles would still be granted
 * everything else, and the one line you meant to remove would still be there.
 *
 * Expected and asserted: 16 lines naming the permission (1 definition + 15 role grants), one
 * `dashboard` module block whose sole member is that permission (so the module itself goes —
 * leaving an empty module is the same smell as leaving a permission nobody reads), and 0 mentions
 * afterwards. Then php -l proves the array literals still parse.
 */

$path = dirname(__DIR__, 3).'/database/seeders/RbacSeeder.php';
$apply = in_array('--apply', $argv, true);

$src = file_get_contents($path);
$original = $src;

$mentions = preg_match_all('/dashboard\.view/', $src);
if ($mentions !== 16) {
    fwrite(STDERR, "REFUSE: ".print_r($mentions, true)." mentions of dashboard.view, expected 16\n");
    exit(1);
}

$moduleBlock = <<<'BLOCK'

            // Dashboard module (1)
            'dashboard' => [
                'dashboard.view',
            ],
BLOCK;

if (substr_count($src, $moduleBlock) !== 1) {
    fwrite(STDERR, "REFUSE: the dashboard module block was not found exactly once\n");
    exit(1);
}
$src = str_replace($moduleBlock, '', $src);

// Grant lists: the token appears either followed by another entry, or last before the close.
$patterns = [
    " 'dashboard.view'," => '',
    "'dashboard.view', " => '',
    "'dashboard.view'" => '',
];
$removed = 0;
foreach ($patterns as $needle => $blank) {
    $hits = substr_count($src, $needle);
    if ($hits === 0) {
        continue;
    }
    $src = str_replace($needle, $blank, $src);
    $removed += $hits;
}
if ($removed !== 15) {
    fwrite(STDERR, "REFUSE: removed {$removed} grant mentions, expected 15\n");
    exit(1);
}

if (preg_match('/,\s*,/', $src) === 1 || preg_match('/\[\s*,/', $src) === 1) {
    fwrite(STDERR, "REFUSE: removal left a dangling comma in an array literal\n");
    exit(1);
}
if (substr_count($src, 'dashboard.view') !== 0) {
    fwrite(STDERR, "REFUSE: ".substr_count($src, 'dashboard.view')." mention(s) survive\n");
    exit(1);
}
printf("removed 1 module block + %d grant mentions; file goes from %d to %d lines\n",
    $removed, substr_count($original, "\n"), substr_count($src, "\n"));

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}
file_put_contents($path, $src);
echo 'Written. Verify with: php -l '.basename($path)."\n";
