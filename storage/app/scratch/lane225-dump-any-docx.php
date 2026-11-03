<?php

/**
 * Read-only: print the paragraphs of ANY .docx in document order, so a second deliverable can be inspected with the
 * same flattened reading `dump-docx-flat.php` gives the authority document.
 *
 * `dump-docx-flat.php` takes no arguments at all (it is bound to one file), so pointing a `--file=` at it would be
 * accepted and ignored — the accepted-and-inert flag this repository has refused five times. This tool therefore
 * makes its argument REQUIRED and refuses a path that is not a file.
 *
 * Usage: php storage/app/scratch/lane225-dump-any-docx.php --file=<path.docx> [--from=N] [--count=M] [--all]
 */
$root = dirname(__DIR__, 3);

$known = ['file', 'from', 'count', 'all'];
$args = [];
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if (! preg_match('/^--([a-z0-9-]+)(?:=(.*))?$/', $arg, $m)) {
        fwrite(STDERR, "REFUSED: unrecognised argument {$arg}; this tool reads only --".implode(', --', $known).".\n");
        exit(1);
    }
    if (! in_array($m[1], $known, true)) {
        fwrite(STDERR, "REFUSED: --{$m[1]} is not a flag this tool reads (known: --".implode(', --', $known).").\n");
        exit(1);
    }
    $args[$m[1]] = $m[2] ?? true;
}

$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

if (! isset($args['file'])) {
    $fail('no --file=<path.docx>; this tool exists precisely because the other dumper reads one fixed document.');
}
$path = $args['file'];
if (! is_file($path)) {
    $fail("[{$path}] is not a file.");
}

$zip = new ZipArchive;
if ($zip->open($path) !== true) {
    $fail("the archive did not open (a zip-only reader: {$path}).");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
if ($xml === '') {
    $fail('word/document.xml read back empty.');
}

$from = isset($args['from']) ? max(1, (int) $args['from']) : 1;
$count = isset($args['count']) ? max(1, (int) $args['count']) : 60;
$all = isset($args['all']);

$paragraphs = preg_split('/<\/w:p>/', $xml) ?: [];
$texts = [];
foreach ($paragraphs as $p) {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $p, $tm);
    $line = trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
    if ($line !== '') {
        $texts[] = $line;
    }
}

$total = count($texts);
printf("document: %s\nnon-empty paragraphs: %d\n", $path, $total);

$limit = $all ? $total : min($total, $from + $count - 1);
for ($i = $from; $i <= $limit; $i++) {
    printf("P %d: %s\n", $i, $texts[$i - 1]);
}
if (! $all && $limit < $total) {
    printf("\n(printed %d of %d paragraphs; --all or --from/--count for the rest)\n", $limit - $from + 1, $total);
}
