<?php

/**
 * Mutate pass 107b and require a refusal each time, one mutant per process. Four cases, one per kind of
 * mistake this pass could make: wrong quotes, injected markup, an anchor whose count was assumed, and a
 * guard whose expected delta was edited to match whatever the write produced.
 *
 * Usage: php mutate-pass107b-guard.php A|B|C|D   (exit 0 = refused, 1 = accepted)
 */

$root = dirname(__DIR__, 3);
$engine = $root.'/storage/app/scratch/refresh-docx-pass107b.php';
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$src = file_get_contents($engine);

$cases = [
    'A' => [
        'what' => 'the replacement left ASCII apostrophes in place of curly quotes',
        'find' => '$to = "\\u{201C}Created N permissions across M modules\\u{201D} and \\u{201C}Created N desk and faculty roles "',
        'fix' => '$to = "Created N permissions across M modules and Created N desk and faculty roles "',
    ],
    'B' => [
        'what' => 'the replacement injects a paragraph',
        'find' => '."with module permissions\\u{201D}";',
        'fix' => '."with module </w:t></w:r></w:p><w:p><w:r><w:t>permissions\\u{201D}";',
    ],
    'C' => [
        'what' => 'the anchor asserted a second occurrence the document does not have',
        'find' => 'if ($n !== 1) {',
        'fix' => 'if ($n !== 2) {',
    ],
    'D' => [
        'what' => 'the apostrophe-delta guard relaxed to accept any count',
        'find' => '    \'no stray ASCII quote count shift\' => substr_count($xml, "\'") === substr_count($original, "\'") - 4,',
        'fix' => '    \'no stray ASCII quote count shift\' => true,',
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
    printf("mutant %s  %-56s NOT USABLE: find occurs %d time(s)\n", $id, $c['what'], $hits);
    exit(1);
}

$body = str_replace($c['find'], $c['fix'], $src);
file_put_contents($root.'/storage/app/scratch/mutant-pass107b.php', $body);
clearstatcache(true, $root.'/storage/app/scratch/mutant-pass107b.php');

$lint = [];
exec(sprintf('cd "%s" && "%s" -l storage/app/scratch/mutant-pass107b.php 2>&1', $root, $php), $lint, $lintCode);
if ($lintCode !== 0) {
    printf("mutant %s  %-56s NOT USABLE: does not parse — %s\n", $id, $c['what'], trim(implode(' ', $lint)));
    @unlink($root.'/storage/app/scratch/mutant-pass107b.php');
    exit(1);
}

$lines = [];
exec(sprintf('cd "%s" && "%s" storage/app/scratch/mutant-pass107b.php 2>&1', $root, $php), $lines, $code);
@unlink($root.'/storage/app/scratch/mutant-pass107b.php');

file_put_contents($root."/storage/app/scratch/mutate-pass107b-{$id}.log", implode("\n", $lines)."\n");

printf("mutant %s  %-56s exit=%d  %s\n", $id, $c['what'], $code, $code !== 0 ? 'refused' : '*** ACCEPTED — decorative ***');
exit($code !== 0 ? 0 : 1);
