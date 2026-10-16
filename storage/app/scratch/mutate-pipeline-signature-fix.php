<?php

/**
 * Mutate fix-pipeline-signatures.php and require its guards refuse, one mutant per run.
 *
 *   Q1  the exact bug the first version of that script had: `$off` left unescaped inside a replacement
 *       string, so PHP interpolates the variable away and the code written says `desk_signer_for()`
 *   Q2  one literal signature site dropped from the plan, leaving `signedBy => 2` in the file
 *   Q3  the self-check not inserted
 *   Q4  a four-site anchor trusted to occur once
 *
 * Each runs in DRY-RUN mode; the pipeline file's hash is proven unchanged either way. Nowdocs throughout,
 * because these strings are full of `$` and backslashes and that is exactly how Q1 happened in the first place.
 *
 * Usage: php mutate-pipeline-signature-fix.php Q1|Q2|Q3|Q4      (exit 0 = refused, as it must be)
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$src = __DIR__.'/fix-pipeline-signatures.php';
$tmp = __DIR__.'/.fixsig-mutant.php';
$doc = $root.'/tools/seed_full_demo_pipeline.php';

$q1find = <<<'NOWDOC'
    "'signedBy' => \$done ? desk_signer_for(\$off) : null,",
    'site: Gabriel Ramos (four boxes, literal 2)');
NOWDOC;

$q1fix = <<<'NOWDOC'
    "'signedBy' => \$done ? desk_signer_for($off) : null,",
    'site: Gabriel Ramos (four boxes, literal 2)');
NOWDOC;

$q2find = <<<'NOWDOC'
$swap("'signedBy' => \$done ? 2 : null,",
    "'signedBy' => \$done ? desk_signer_for(\$off) : null,",
    'site: Gabriel Ramos (four boxes, literal 2)');
NOWDOC;

$q4find = <<<'NOWDOC'
    'site: registrarProcessedBy literal 2', 4);
NOWDOC;

$q4fix = <<<'NOWDOC'
    'site: registrarProcessedBy literal 2');
NOWDOC;

$cases = [
    'Q1' => ['find' => $q1find, 'fix' => $q1fix,
        'what' => 'unescaped $off interpolated away by PHP', 'guard' => 'mangled'],
    'Q2' => ['find' => $q2find, 'fix' => '// site removed by the mutant',
        'what' => 'one literal signature site left unfixed', 'guard' => 'no literal signedBy stamp remains'],
    'Q3' => ['find' => '$swap($tailAnchor, $selfCheck.$tailAnchor,',
        'fix' => '$swap($tailAnchor, $tailAnchor,',
        'what' => 'the self-check dropped from the run', 'guard' => 'the self-check landed once'],
    'Q4' => ['find' => $q4find, 'fix' => $q4fix,
        'what' => 'a four-site anchor assumed to occur once', 'guard' => 'anchor occurs 4 time(s)'],
];

$id = $argv[1] ?? '';
if (! isset($cases[$id])) {
    fwrite(STDERR, "usage: php ".basename(__FILE__)." Q1|Q2|Q3|Q4\n");
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
$lint = [];
exec(sprintf('"%s" -l "%s" 2>&1', $php, $tmp), $lint, $lintCode);
if ($lintCode !== 0) {
    printf("%s  %-52s NOT USABLE: mutant does not parse — %s\n", $id, $c['what'], trim(implode(' ', $lint)));
    unlink($tmp);
    exit(1);
}

$before = md5_file($doc);
$out = [];
$code = 0;
exec(sprintf('cd "%s" && "%s" storage/app/scratch/.fixsig-mutant.php 2>&1', $root, $php), $out, $code);
$body = implode("\n", $out);
unlink($tmp);

if (md5_file($doc) !== $before) {
    printf("%s  %-52s WROTE THE PIPELINE from a dry run — aborting\n", $id, $c['what']);
    exit(1);
}

if ($code === 0) {
    printf("%s  %-52s SURVIVED: the fixer accepted its own defect\n%s\n", $id, $c['what'], $body);
    exit(1);
}

if (! str_contains($body, $c['guard'])) {
    printf("%s  %-52s REFUSED FOR THE WRONG REASON (wanted 「%s」)\n%s\n", $id, $c['what'], $c['guard'], $body);
    exit(1);
}

printf("%s  %-52s refused as designed: %s  (pipeline file unchanged)\n", $id, $c['what'], $c['guard']);
exit(0);
