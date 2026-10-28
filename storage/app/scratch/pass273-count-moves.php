<?php

/**
 * Count how the citation multiset of `word/document.xml` differs between two copies of the docx.
 *
 * A pass asserts its own needles, but "how many numbers did this batch re-take" is a figure the document and
 * AGENTS.md both state, and I have twice now written one from arithmetic in my head. This answers it from the
 * bytes: every citation-shaped token (file-prefixed or a bare `:NN`/`:NN-NN`) is tallied in each copy, and the
 * report is which counts fell and which rose. A moved number therefore appears once as removed and once as
 * added; a removed pointer appears only as the former; an added one only as the latter.
 *
 *   php storage/app/scratch/pass273-count-moves.php <old.docx> <new.docx>
 */
if ($argc < 3) {
    fwrite(STDERR, "REFUSED: two docx paths are required; nothing is measured from one.\n");

    exit(1);
}

function toks(string $file): array
{
    $z = new ZipArchive;
    // `!== true`, not `!`: on a file that is not a zip at all, open() answers an error CODE, which is truthy, so a
    // negated guard walks straight into "Uncaught ValueError: Invalid or uninitialized Zip object" and the refusal
    // this function exists to print never happens. Its own harness leg plants exactly that file.
    if ($z->open($file) !== true) {
        fwrite(STDERR, "REFUSED: cannot open {$file} as an archive.\n");

        exit(1);
    }
    $x = (string) $z->getFromName('word/document.xml');
    $z->close();
    if (strlen($x) < 100000) {
        fwrite(STDERR, "REFUSED: {$file} answers a document.xml of ".strlen($x)." bytes — that is not this file.\n");

        exit(1);
    }
    $x = html_entity_decode($x, ENT_QUOTES, 'UTF-8');
    // `#` delimiters: a `/` inside a character class with `/` delimiters is how a pattern dies at the first
    // unescaped one, and the "0 removed / 0 added" answer of that failure is indistinguishable from a clean diff.
    preg_match_all(
        '#(?:[A-Za-z0-9_./\\\\-]*\.(?:php|jsx|js|mjs))?:\d+(?:-\d+)?#u',
        $x,
        $m
    );
    $c = [];
    foreach ($m[0] as $t) {
        $c[$t] = ($c[$t] ?? 0) + 1;
    }

    return $c;
}

$before = toks($argv[1]);
$after = toks($argv[2]);

$gone = [];
$new = [];
foreach ($before as $t => $n) {
    $d = $n - ($after[$t] ?? 0);
    if ($d > 0) {
        $gone[$t] = $d;
    }
}
foreach ($after as $t => $n) {
    $d = $n - ($before[$t] ?? 0);
    if ($d > 0) {
        $new[$t] = $d;
    }
}

printf("BEFORE %s : %d occurrences over %d distinct tokens\n", basename($argv[1]), array_sum($before), count($before));
printf("AFTER  %s : %d occurrences over %d distinct tokens\n", basename($argv[2]), array_sum($after), count($after));
printf("fell: %d occurrence(s) over %d distinct token(s)\n", array_sum($gone), count($gone));
foreach ($gone as $t => $n) {
    echo "  - {$t} x{$n}\n";
}
printf("rose: %d occurrence(s) over %d distinct token(s)\n", array_sum($new), count($new));
foreach ($new as $t => $n) {
    echo "  + {$t} x{$n}\n";
}
