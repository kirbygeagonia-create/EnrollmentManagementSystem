<?php

/**
 * Repair the sentence this morning's census re-take left awkward.
 *
 * The generated clause was dropped between "resolves against" and the inventory file it resolves against, which
 * splits a verb from its object — and it repeated the finding-row count the original parenthetical already states.
 * The note belongs after the sentence ends; the number stays where the tool printed it.
 *
 * Usage: php storage/app/scratch/lane101-untangle-the-census-clause.php [--apply]
 */

$root = dirname(__DIR__, 3);
$file = $root.'/AGENTS.md';
$apply = in_array('--apply', $argv, true);

$moved = ' (read off the sweep on 2026-10-30, when three docx passes rewrote §28.3\'s G-40 row and the day\'s §1.1 row '
    .'and four pointers sat inside the sentences that were replaced; that line prints 135 finding rows and 1027 '
    .'references today, and this clause was generated from it rather than retyped)';

$edits = [
    [$moved, ''],
    ['three separate numbering systems.',
        'three separate numbering systems. The 1027 was read off the sweep\'s own line on 2026-10-30, after three docx '
        .'passes rewrote §28.3\'s G-40 row and the day\'s §1.1 row and four pointers sat inside the sentences that were '
        .'replaced — a census this file states has to be generated from the tool that prints it, and lane 59 refuses '
        .'the run when it is not.',
    ],
];

$text = (string) file_get_contents($file);
foreach ($edits as $i => [$from, $to]) {
    $n = substr_count($text, $from);
    if ($n !== ($i === 0 ? 1 : 1)) {
        fwrite(STDERR, "REFUSED: edit {$i} needle occurs {$n}, want 1:\n  ".mb_substr($from, 0, 70)."\n");

        exit(1);
    }
}

printf("%s: the census note moves out of the verb's way\n", $apply ? 'APPLY' : 'DRY RUN');

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

file_put_contents($file, str_replace(array_column($edits, 0), array_column($edits, 1), $text));
echo "APPLIED.\n";
