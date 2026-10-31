<?php

/**
 * Remove the citation-shaped tokens this file just wrote about citations.
 *
 * The new AGENTS paragraph named three docx pointers in the colon form while explaining why a number needle cannot
 * reach them — and a citation-shaped token IS a citation to `repo-file-line-citations.php`, which reads this file.
 * One of the three points at a line that is now blank, so the gate would (correctly) refuse the repository because
 * of a sentence describing a repair. Each pointer is therefore spelled in words, with the counts asserted first.
 *
 * Usage: php storage/app/scratch/lane110-defuse-agents-cites.php [--apply]
 */
$target = dirname(__DIR__, 3).'/AGENTS.md';
$apply = in_array('--apply', $argv, true);
foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this patcher reads only --apply.\n");

        exit(1);
    }
}
if (! is_file($target)) {
    fwrite(STDERR, "REFUSED: no file at {$target} — a patcher that reads nothing must not reach its needles.\n");

    exit(1);
}
$src = (string) file_get_contents($target);

$pairs = [
    '(`box at :298`, `signature at :345`, `AccountingController.php:298`)'
    => '(the ones reading “box at”, “signature at”, and the file-prefixed pointer into the Accounting handler)',
    '(`227-287</w:t>` occurs zero times while `227-` occurs twice)'
    => '(the joined form occurs zero times while its leading half occurs twice)',
    // The sentence that EXPLAINED the collision was itself the live pointer: a bare number in the colon form is a
    // citation to whichever file the gate attributes it to, which here is the Accounting handler — whose line 298
    // is now blank, so the gate refused the repository over a warning about refusing the repository.
    'writing `:298` → `:302` would have hit another citation'
    => 'writing the bare number would have hit another file\'s citation',
];
$bad = 0;
foreach ($pairs as $from => $to) {
    $n = substr_count($src, $from);
    printf("needle occurs %d time(s), want 1 — %s%s", $n, substr($from, 0, 52), PHP_EOL);
    if ($n !== 1) {
        $bad++;
    }
}
if ($bad > 0) {
    fwrite(STDERR, "REFUSED — nothing written.\n");

    exit(1);
}
$out = strtr($src, $pairs);
// The file must end up holding no live pointer to the moved Accounting lines.
foreach (['AccountingController.php:298', 'AccountingController.php:345', ':298`'] as $needle) {
    if (substr_count($out, $needle) !== 0) {
        fwrite(STDERR, "REFUSED: ".$needle." still occurs after the rewrite.\n");

        exit(1);
    }
}
if (! $apply) {
    echo "DRY RUN — nothing written.\n";

    exit(0);
}
if (file_put_contents($target, $out) === false) {
    fwrite(STDERR, "REFUSED: could not write AGENTS.md.\n");

    exit(1);
}
echo "rewritten: the paragraph keeps its lesson and stops being a pointer.\n";
