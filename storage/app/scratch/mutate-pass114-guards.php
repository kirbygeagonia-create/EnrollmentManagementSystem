<?php

/**
 * Mutate pass 114 and require that its own checks refuse, one mutant per run.
 *
 * "guard ok" is worth nothing until the checker has been seen failing. Each mutant breaks one thing pass 114
 * is supposed to notice, runs it in DRY-RUN mode, and requires a non-zero exit carrying the expected refusal —
 * with the document's hash proven unchanged, because a dry run that wrote would be the worst possible defect
 * in a harness whose whole job is to prove the writer safe.
 *
 *   N1  a cited range this pass never verified             → "does not know what that span"
 *   N2  a cited range past the end of the file             → "runs past the end"
 *   N3  a verified range asked to hold a line it does not  → "does not hold"
 *   N4  one of the two ranges dropped from the citation    → "carries 1 ranges"
 *   N5  a run anchor that is not in the document           → "appears 0 time(s)"
 *   N6  an append anchor that is not in the document       → "append anchor appears 0"
 *   N7  a markdown backtick in the new prose               → "no markdown backticks"
 *   N8  a text element smuggled into a replacement run     → "no text element gained or lost"
 *
 * Usage: php mutate-pass114-guards.php N1|…|N8     (exit 0 = refused, as it must be)
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$src = __DIR__.'/refresh-docx-pass114.php';
$tmp = __DIR__.'/.pass114-mutant.php';
$docx = $root.'/Documentation/EMS Complete Documentation.docx';

$cases = [
    'N1' => ['find' => "\$citation = 'AuthenticatedLayout.jsx:60-82, :84-112';",
        'fix' => "\$citation = 'AuthenticatedLayout.jsx:10-30, :84-112';",
        'what' => 'a cited span that is not the poll', 'guard' => '10-30: the range does not hold'],
    'N2' => ['find' => "\$citation = 'AuthenticatedLayout.jsx:60-82, :84-112';",
        'fix' => "\$citation = 'AuthenticatedLayout.jsx:60-99999, :84-112';",
        'what' => 'a cited range past the end of the file', 'guard' => 'runs past the end'],
    'N3' => ['find' => "    ['setInterval(fetchNotifications, 30000)',",
        'fix' => "    ['this line is nowhere in the file',",
        'what' => 'a range asked to hold a line it has not', 'guard' => '60-82: the range does not hold'],
    'N4' => ['find' => "\$citation = 'AuthenticatedLayout.jsx:60-82, :84-112';",
        'fix' => "\$citation = 'AuthenticatedLayout.jsx:60-82';",
        'what' => 'one of the two ranges dropped from the citation', 'guard' => 'carries 1 ranges'],
    'N5' => ['find' => "\$oldCitation = 'AuthenticatedLayout.jsx:57-69';",
        'fix' => "\$oldCitation = 'AuthenticatedLayout.jsx:7-69';",
        'what' => 'a run anchor absent from the document', 'guard' => 'appears 0 time(s)'],
    'N6' => ['find' => "'so a Student-addressed row cannot even be marked read.',",
        'fix' => "'so a Student-addressed row cannot even be marked readZZZ',",
        'what' => 'an append anchor absent from the document', 'guard' => 'append anchor appears 0'],
    'N7' => ['find' => 'The same sweep found three more loads',
        'fix' => 'The same `sweep` found three more loads',
        'what' => 'markdown backticks in the new prose', 'guard' => 'no markdown backticks'],
    'N8' => ['find' => 'names which of three states it is in: ',
        'fix' => 'names which of three states <w:t xml:space="preserve">it is in: ',
        'what' => 'a text element smuggled into a run', 'guard' => 'no text element gained or lost'],
];

$id = $argv[1] ?? '';
if (! isset($cases[$id])) {
    fwrite(STDERR, "usage: php ".basename(__FILE__)." N1|N2|N3|N4|N5|N6|N7|N8\n");
    exit(2);
}

$c = $cases[$id];
$base = file_get_contents($src);
$hits = substr_count($base, $c['find']);
if ($hits !== 1) {
    printf("%s  %-50s NOT USABLE: mutation anchor occurs %d time(s)\n", $id, $c['what'], $hits);
    exit(1);
}

file_put_contents($tmp, str_replace($c['find'], $c['fix'], $base));
$before = md5_file($docx);

$out = [];
$code = 0;
exec(sprintf('cd "%s" && "%s" storage/app/scratch/.pass114-mutant.php 2>&1', $root, $php), $out, $code);
$body = implode("\n", $out);
unlink($tmp);

if (md5_file($docx) !== $before) {
    printf("%s  %-50s WROTE THE DOCUMENT from a dry run — aborting\n", $id, $c['what']);
    exit(1);
}

if ($code === 0) {
    printf("%s  %-50s SURVIVED: the pass accepted its own defect\n%s\n", $id, $c['what'], $body);
    exit(1);
}

if (! str_contains($body, $c['guard'])) {
    printf("%s  %-50s REFUSED FOR THE WRONG REASON (wanted 「%s」)\n%s\n", $id, $c['what'], $c['guard'], $body);
    exit(1);
}

printf("%s  %-50s refused as designed: %s  (document hash unchanged)\n", $id, $c['what'], $c['guard']);
exit(0);
