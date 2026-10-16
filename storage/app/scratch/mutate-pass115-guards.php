<?php

/**
 * Mutate pass 115 and require that its own guards refuse, one mutant per run.
 *
 *   P1  a from-anchor that is not in the document                → "appears 0 time(s)"
 *   P2  a markdown backtick in the new prose                     → "no markdown backticks"
 *   P3  a paragraph end tag smuggled into the new prose          → "no paragraph gained or lost"
 *   P4  the replacement leaves the stale clause standing         → "the two stale clauses are gone"
 *   P5  the distribution dropped out of the replacement          → "with the distribution"
 *   P6  the edit swallows a sentence the pass claims not to touch → "still read as before"
 *
 * Each runs in DRY-RUN mode and the document's hash is proven unchanged either way.
 * Usage: php mutate-pass115-guards.php P1|…|P6      (exit 0 = refused, as it must be)
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$src = __DIR__.'/refresh-docx-pass115.php';
$tmp = __DIR__.'/.pass115-mutant.php';
$docx = $root.'/Documentation/EMS Complete Documentation.docx';

$cases = [
    'P1' => ['find' => "the 29 enrollments that already credit office4_head, '",
        'fix' => "the 29 enrollments that already credit office4_headZZZ, '",
        'what' => 'an anchor not in the document', 'guard' => 'appears 0 time(s)'],
    'P2' => ['find' => 'boundary had been measured against is now credited',
        'fix' => 'boundary had been measured against is now `credited`',
        'what' => 'markdown backticks in the new prose', 'guard' => 'no markdown backticks'],
    'P3' => ['find' => 'reported, not repaired, for the same reason the signatures are left alone.',
        'fix' => 'reported, not repaired, for the same reason.</w:p>',
        'what' => 'a paragraph end tag smuggled into the sentence', 'guard' => 'no paragraph gained or lost'],
    'P4' => ['find' => "'An account with no college is bounded by nothing, and that stays a decision rather than a gap — though '",
        'fix' => "'An account with no college is bounded by nothing, and office4_head holds the 29 enrollments above, though '",
        'what' => 'a replacement that leaves the stale clause standing', 'guard' => 'the two stale clauses are gone'],
    'P5' => ['find' => '(dept.ctic 16, dept.cjc 10, dept.cbg 3)',
        'fix' => '(dept.ctic 16)',
        'what' => 'the measured distribution dropped from the replacement', 'guard' => 'with the distribution'],
    'P6' => ['find' => "'stayed where they were.',",
        'fix' => "'stayed where they were. Seating the desks is what surfaced a second finding',",
        'what' => 'an edit that swallows the sentence after it', 'guard' => 'still read as before'],
];

$id = $argv[1] ?? '';
if (! isset($cases[$id])) {
    fwrite(STDERR, "usage: php ".basename(__FILE__)." P1|P2|P3|P4|P5|P6\n");
    exit(2);
}

$c = $cases[$id];
$base = file_get_contents($src);
$hits = substr_count($base, $c['find']);
if ($hits !== 1) {
    printf("%s  %-52s NOT USABLE: mutation anchor occurs %d time(s)\n", $id, $c['what'], $hits);
    exit(1);
}

file_put_contents($tmp, str_replace($c['find'], $c['fix'], $base));
$before = md5_file($docx);

$out = [];
$code = 0;
exec(sprintf('cd "%s" && "%s" storage/app/scratch/.pass115-mutant.php 2>&1', $root, $php), $out, $code);
$body = implode("\n", $out);
unlink($tmp);

if (md5_file($docx) !== $before) {
    printf("%s  %-52s WROTE THE DOCUMENT from a dry run — aborting\n", $id, $c['what']);
    exit(1);
}

if ($code === 0) {
    printf("%s  %-52s SURVIVED: the pass accepted its own defect\n%s\n", $id, $c['what'], $body);
    exit(1);
}

if (! str_contains($body, $c['guard'])) {
    printf("%s  %-52s REFUSED FOR THE WRONG REASON (wanted 「%s」)\n%s\n", $id, $c['what'], $c['guard'], $body);
    exit(1);
}

printf("%s  %-52s refused as designed: %s  (document hash unchanged)\n", $id, $c['what'], $c['guard']);
exit(0);
