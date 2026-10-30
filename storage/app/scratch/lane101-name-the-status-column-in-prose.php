<?php

/**
 * Task #43, third step: say in the matrix's own prose where a verdict now lives.
 *
 * A column a reader does not know about is a column that gets ignored, and §10's lead sentence already used the
 * phrase "the verdict column" when no such column existed. Both passages are re-pointed at the real thing.
 *
 * Usage: php storage/app/scratch/lane101-name-the-status-column-in-prose.php [--apply]
 */
$root = dirname(__DIR__, 3);
$file = $root.'/Documentation/Prompt-Requirement-Matrix-2026-10-26.md';
$apply = in_array('--apply', $argv, true);

$edits = [
    [
        'Risk is the prompt\'s §13 vocabulary. "Needs ruling" means the prompt\'s §3.1 forbids inventing the rule.',
        'Risk is the prompt\'s §13 vocabulary. "Needs ruling" means the prompt\'s §3.1 forbids inventing the rule. '
        .'The last column, `Status`, is where a row states its verdict in the words a checker reads — `Closed`, `Built`, '
        .'`Partly built` or `Not built`, each leading its cell and each dated where a date was ruled. Ask that column '
        .'for "how many gaps are open"; the prose cells carry the evidence and the plan.',
    ],
    [
        'claim the evidence above does not already support: the verdict column names the state, not a fix that has happened.',
        'claim the evidence above does not already support: the `Status` column names the state, not a fix that has '
        .'happened. The column was added on 2026-10-30, and `lane94-matrix-claims.php` judges a row of a table that '
        .'has one by that cell alone — a Built claim planted in the prose beside a cell reading `Not built` does not '
        .'move the verdict, which is what the harness leg exists to prove.',
    ],
];

$text = (string) file_get_contents($file);
foreach ($edits as [$from, $to]) {
    $n = substr_count($text, $from);
    if ($n !== 1) {
        fwrite(STDERR, "REFUSED: the needle occurs {$n} time(s), want 1:\n  ".mb_substr($from, 0, 70)."\n");

        exit(1);
    }
}

printf("%s: two passages say where a verdict lives now\n", $apply ? 'APPLY' : 'DRY RUN');

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

file_put_contents($file, str_replace(array_column($edits, 0), array_column($edits, 1), $text));
echo "APPLIED.\n";
