<?php

/**
 * Mutate pass 113 and require that its own guards refuse, one mutant per run.
 *
 * A clean "guard ok" line is worth nothing until the checker has been shown failing at its job. Each mutant
 * breaks one thing the pass is supposed to notice, runs it in DRY-RUN mode (so the document is never
 * touched), and requires a non-zero exit naming the guard that fired.
 *
 *   M1  a new range that does not contain the callback it cites     → the by-meaning assert
 *   M2  an anchor expected twice, present three times               → the occurrence count
 *   M3  the added sentence carries a backtick                       → no markdown in the document
 *   M4  the added sentence carries a paragraph end tag              → structure unchanged
 *   M5  the insertion anchor rewritten to something absent          → the tail must still be there
 *   M6  a new range that runs past the end of the cited file        → the bound check
 *
 * Usage: php mutate-pass113-guards.php M1|M2|M3|M4|M5|M6      (exit 0 = mutant refused, as it must be)
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$src = __DIR__.'/refresh-docx-pass113.php';
$tmp = __DIR__.'/.pass113-mutant.php';

$cases = [
    'M1' => ['find' => "'bootstrap/app.php:56-69',\n    'the generic stage message'",
        'fix' => "'bootstrap/app.php:100-110',\n    'the generic stage message'",
        'what' => 'a range holding a different callback', 'guard' => 'does not hold'],
    'M2' => ['find' => "'the generic stage message', 3,", 'fix' => "'the generic stage message', 2,",
        'what' => 'the wrong occurrence count', 'guard' => 'expected 2'],
    'M3' => ['find' => 'shows the refusal where an empty list', 'fix' => 'shows the refusal where an `empty list`',
        'what' => 'markdown backticks in the new sentence', 'guard' => 'no markdown backticks'],
    'M4' => ['find' => "'halves — a refusal", 'fix' => "'halves</w:p> — a refusal",
        'what' => 'a paragraph end tag smuggled into the sentence', 'guard' => 'no paragraph gained or lost'],
    'M5' => ['find' => '$tail = ', 'fix' => '$tail = \'ZZZ absent anchor \'.',
        'what' => 'an insertion anchor that is not in the document', 'guard' => 'no longer ends where this pass expects'],
    'M6' => ['find' => "'bootstrap/app.php:165-178',\n    'the no-browser refusal'",
        'fix' => "'bootstrap/app.php:165-99999',\n    'the no-browser refusal'",
        'what' => 'a range past the end of bootstrap/app.php', 'guard' => 'runs past the end'],
];

$id = $argv[1] ?? '';
if (! isset($cases[$id])) {
    fwrite(STDERR, "usage: php ".basename(__FILE__)." M1|M2|M3|M4|M5|M6\n");
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

$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$before = md5_file($docx);

$out = [];
$code = 0;
exec(sprintf('cd "%s" && "%s" storage/app/scratch/.pass113-mutant.php 2>&1', $root, $php), $out, $code);
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
