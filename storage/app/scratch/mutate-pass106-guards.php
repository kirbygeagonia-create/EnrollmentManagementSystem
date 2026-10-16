<?php

/**
 * Mutate pass 106 and require a refusal each time, with the mutation checked to have landed.
 *
 * A checker that cannot be broken is not a checker. Four cases, one per claim the pass makes:
 *   A  an anchor the document does not contain          → the edit must be refused (count 0)
 *   B  the ruling text left out of the replacement     → "the ruling is present once" must fail
 *   C  the pass aimed at the jobs.failed_at row        → the row-content check or the kept-default guard fails
 *   D  a replacement that injects block markup          → "no paragraph gained or lost" must fail
 *
 * Each case runs in its own process (invoked by the shell loop) because a harness that applied several
 * mutants in one process mis-reported which test died on 2026-10-13.
 */

$root = dirname(__DIR__, 3);
$engine = $root.'/storage/app/scratch/refresh-docx-pass106.php';
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$src = file_get_contents($engine);

$cases = [
    'A' => [
        'what' => 'an anchor the document does not contain',
        // The whole assignment line, terminated as it appears in the source: a first draft ended it with a
        // comma and matched nothing, which is an unusable mutant, not a caught one.
        'find' => "\$from1 = 'which is what the two columns carrying a MySQL CURRENT_TIMESTAMP default would silently inherit';",
        'fix' => "\$from1 = 'which is what the two columns carrying a MySQL CURRENT_TIMESTAMP default would silently inherit-ed';",
    ],
    'B' => [
        'what' => 'the ruling sentence left out of the appended text',
        'find' => "' Ruled 2026-10-13 on the last open desk question, and the answer is the college that owns '",
        'fix' => "' The college that owns '",
    ],
    'C' => [
        'what' => 'the dictionary edits aimed at the jobs row instead',
        'find' => "[['changedAt', 3148382], ['createdAt', 4037352]]",
        'fix' => "[['failed_at', 4341297], ['createdAt', 4037352]]",
    ],
    'D' => [
        'what' => 'a replacement that injects a paragraph',
        // A fragment unique to the replacement text. The obvious choice — the string the two dictionary
        // cells are written with — occurs twice in the script (payload and the guard that checks it), so
        // mutating it moves both together and the guard "passes" having proven nothing; that is exactly
        // the trap this harness is built to refuse.
        'find' => "    .'inherited, until the ruling of 2026-10-13 took both defaults away and made the application the only '",
        'fix' => "    .'inherited. </w:t></w:r></w:p><w:p><w:r><w:t>until the ruling of 2026-10-13 took both defaults away and made the application the only '",
    ],
];

$id = $argv[1] ?? '';
if (! isset($cases[$id])) {
    fwrite(STDERR, "usage: php ".basename(__FILE__)." A|B|C|D\n");
    exit(2);
}

$c = $cases[$id];
$hits = substr_count($src, $c['find']);
if ($hits !== 1) {
    printf("mutant %s  %s\n  NOT USABLE: find string occurs %d times (a shared literal would move the guard with it)\n",
        $id, $c['what'], $hits);
    exit(1);
}

$body = str_replace($c['find'], $c['fix'], $src);
if ($body === $src) {
    printf("mutant %s  %s\n  NOT PLANTED\n", $id, $c['what']);
    exit(1);
}

$path = $root.'/storage/app/scratch/mutant-pass106.php';
file_put_contents($path, $body);
clearstatcache(true, $path);
if (file_get_contents($path) !== $body) {
    printf("mutant %s  %s\n  NOT PLANTED on disk\n", $id, $c['what']);
    @unlink($path);
    exit(1);
}

exec(sprintf('cd "%s" && "%s" storage/app/scratch/mutant-pass106.php 2>&1', $root, $php), $lines, $code);
@unlink($path);

$out = preg_replace('/\s+/', ' ', implode(' ', $lines));
preg_match('/(\d+) problem\(s\)/', $out, $p);
printf("mutant %s  %-46s exit=%d  %s  problems=%s\n",
    $id, $c['what'], $code, $code !== 0 ? 'refused' : '*** ACCEPTED — guard is decorative ***',
    $p[1] ?? '?');
// The pass must also refuse to write in dry-run mode regardless, so --apply is never in these commands.
exit($code !== 0 ? 0 : 1);
