<?php

/**
 * Mutate pass 107 and require a refusal each time, one mutant per process.
 *
 * Every guard and every pre-write code check in that pass is decorative until a mutant proves it fires.
 * Seven cases, each aimed at a different kind of mistake the pass could make:
 *
 *   A  a citation moved to a line that holds an SVG path instead of the Phase 6 label   → the code check
 *   B  the admission-requirements cite aimed at the fee list instead                     → the row-content
 *                                                                                          check
 *   C  the role-call span left at the old :226-:477, which hides one of the seventeen    → the count check
 *   D  the live expectation set to 94 permissions, which is the number this pass rejects → the measurement
 *   E  the permission total edited to the wrong word                                     → the guard
 *   F  the account denominator left at 15 of 15                                          → the guard
 *   G  a replacement that injects a paragraph                                            → the structure guard
 *
 * Usage: php mutate-pass107-guards.php A|B|C|D|E|F|G   (exit 0 = the mutant was refused, 1 = it was not)
 */

$root = dirname(__DIR__, 3);
$engine = $root.'/storage/app/scratch/refresh-docx-pass107.php';
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$src = file_get_contents($engine);

$cases = [
    'A' => [
        'what' => 'MegaAppLauncher cite moved onto an SVG path line',
        'find' => "['resources/js/Components/navigation/MegaAppLauncher.jsx', 133, 139],",
        'fix' => "['resources/js/Components/navigation/MegaAppLauncher.jsx', 133, 131],",
    ],
    'B' => [
        'what' => 'admission-requirements cite aimed at the fee list',
        'find' => "\$spanText = \$span('database/seeders/DevReferenceDataSeeder.php', 725, 736);",
        'fix' => "\$spanText = \$span('database/seeders/DevReferenceDataSeeder.php', 706, 722);",
    ],
    'C' => [
        'what' => 'role-call span left at the unreadable :226-:477',
        'find' => 'for ($i = 223; $i <= 474; $i++) {',
        'fix' => 'for ($i = 226; $i <= 477; $i++) {',
    ],
    'D' => [
        'what' => 'live permission expectation moved to the wrong figure',
        'find' => "if (\$counts['permissions'] !== 93 || \$counts['roles'] !== 17 || \$counts['staffusers'] !== 21) {",
        'fix' => "if (\$counts['permissions'] !== 94 || \$counts['roles'] !== 17 || \$counts['staffusers'] !== 21) {",
    ],
    'E' => [
        'what' => 'permission total written as the stale word',
        'find' => "\$swap('ninety-four permissions', 'ninety-three permissions', 'the permission total');",
        'fix' => "\$swap('ninety-four permissions', 'ninety-four permissions', 'the permission total');",
    ],
    'F' => [
        'what' => 'account denominator left at 15 of 15',
        'find' => "    'answer for 21 of 21 staff accounts — re-measured 2026-10-13, after the six per-college department '",
        'fix' => "    'answer for 15 of 15 staff accounts — re-measured 2026-10-13, after the six per-college department '",
    ],
    'G' => [
        'what' => 'a replacement that injects a paragraph',
        'find' => "    .\"permissions' — are read from the database at RbacSeeder.php:198 and :483 instead of typed in here.\",",
        'fix' => "    .\"permissions' — are read </w:t></w:r></w:p><w:p><w:r><w:t>at RbacSeeder.php:198 and :483 instead of typed in here.\",",
    ],
];

$id = $argv[1] ?? '';
if (! isset($cases[$id])) {
    fwrite(STDERR, "usage: php ".basename(__FILE__)." A|B|C|D|E|F|G\n");
    exit(2);
}

$c = $cases[$id];
$hits = substr_count($src, $c['find']);
if ($hits !== 1) {
    printf("mutant %s  %s\n  NOT USABLE: find string occurs %d time(s) — a shared literal would move the guard with it\n",
        $id, $c['what'], $hits);
    exit(1);
}

$body = str_replace($c['find'], $c['fix'], $src);
if ($body === $src) {
    printf("mutant %s  %s\n  NOT PLANTED\n", $id, $c['what']);
    exit(1);
}

// A syntax error is not a refusal: lint the mutant before spending a run on it.
file_put_contents($root.'/storage/app/scratch/mutant-pass107.php', $body);
clearstatcache(true, $root.'/storage/app/scratch/mutant-pass107.php');
exec(sprintf('cd "%s" && "%s" -l storage/app/scratch/mutant-pass107.php 2>&1', $root, $php), $lint, $lintCode);
if ($lintCode !== 0) {
    printf("mutant %s  %s\n  NOT USABLE: the mutant does not parse — %s\n", $id, $c['what'], trim(implode(' ', $lint)));
    @unlink($root.'/storage/app/scratch/mutant-pass107.php');
    exit(1);
}

$lines = [];
exec(sprintf('cd "%s" && "%s" storage/app/scratch/mutant-pass107.php 2>&1', $root, $php), $lines, $code);
@unlink($root.'/storage/app/scratch/mutant-pass107.php');

$out = preg_replace('/\s+/', ' ', implode(' ', $lines));

// The log the mutation produced, kept so the refusal can be read rather than inferred from an exit code:
// a mutant refused by the *wrong* check proves nothing about the one it was aimed at.
file_put_contents($root."/storage/app/scratch/mutate-pass107-{$id}.log", implode("\n", $lines)."\n");
preg_match('/(\d+) problem\(s\)/', $out, $p);
preg_match('/REFUSE[^A-Z]*(?:guard: )?([^—]{0,90})/', $out, $r);

printf("mutant %s  %-52s exit=%d  %-32s %s\n",
    $id,
    $c['what'],
    $code,
    $code !== 0 ? 'refused' : '*** ACCEPTED — this check is decorative ***',
    trim($r[1] ?? $p[1] ?? ''));

// Dry run only, so a mutant that somehow reaches the write step still touches nothing.
exit($code !== 0 ? 0 : 1);
