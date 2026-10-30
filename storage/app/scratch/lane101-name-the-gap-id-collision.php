<?php

/**
 * Task #43, fourth step: one status that reads as a state, and the gap-id collision named where a script can hit it.
 *
 * (1) G-6's Status cell led with "Built" although its own row says half the requirement is not built — the rule that
 *     put G-40, G-45 and G-47 under "Partly built" applies to it too.
 * (2) The matrix's §3 lead now states what AGENTS.md recorded for one id (`G-10`) and generalises: the matrix's gap
 *     numbers and the authority document's §28.3 numbers are different registers, and past G-5 they disagree. Once a
 *     column is machine-read, "count the open gaps" can be answered from either table and the two answers differ.
 *
 * Every needle here is a single-quoted string with no `$` and no `"` in it: a draft of this pass wrote needles inside
 * `"…"` and PHP interpolated `$matrix` out of the pattern, which is the same class of defect the file keeps naming.
 *
 * Usage: php storage/app/scratch/lane101-name-the-gap-id-collision.php [--apply]
 */
$root = dirname(__DIR__, 3);
$matrix = $root.'/Documentation/Prompt-Requirement-Matrix-2026-10-26.md';
$agents = $root.'/AGENTS.md';
$apply = in_array('--apply', $argv, true);

$curly = "\xE2\x80\x99";

$edits = [
    [$matrix,
        'Built 2026-10-27 for the monitor and the definitions table is Not built, waiting on the owner',
        'Partly built: the monitor closed 2026-10-27 and the definitions table waits on the owner',
    ],
    [$matrix,
        'Ask that column for "how many gaps are open"; the prose cells carry the evidence and the plan.',
        'Ask that column for "how many gaps are open"; the prose cells carry the evidence and the plan. The numbers in '
        .'the Id column are this matrix'.$curly.'s own register. They are not the authority document'.$curly.'s §28.3 '
        .'numbers: §28.3 G-6 is the two examination flags and its G-8 the course-specific result rule, both closed, '
        .'while this table'.$curly.'s G6 is the admin view of the pipeline and its G8 the step-state vocabulary, both '
        .'open. So write a gap id with its register named, and take a count of open gaps from one table rather than '
        .'from a grep that reads both.',
    ],
    [$matrix,
        'which is what the harness leg exists to prove.',
        'which is what the harness leg exists to prove. The ids below are the same ids §3 uses; they are not §28.3'.$curly.'s.',
    ],
    [$agents,
        '"prompt §6.4".',
        '"prompt §6.4". Measured 2026-10-30, when the matrix grew a machine-read `Status` column: the collision is not '
        .'one id but the first nine or so, since §28.3'.$curly.'s G-6 and G-8 are closed findings that share those '
        .'numbers with two open rows of the matrix. So a gap id is written with its register named, and a count of '
        .'open gaps is taken from one table, never from a grep.',
    ],
];

foreach ($edits as [$file, $from, $to]) {
    $n = substr_count((string) file_get_contents($file), $from);
    if ($n !== 1) {
        fwrite(STDERR, 'REFUSED: in '.basename($file)." the needle occurs {$n} time(s), want 1:\n  ".mb_substr($from, 0, 80)."\n");

        exit(1);
    }
}

printf("%s: one status re-spelled, and the collision named in both registers\n", $apply ? 'APPLY' : 'DRY RUN');

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

foreach ($edits as [$file, $from, $to]) {
    file_put_contents($file, str_replace($from, $to, (string) file_get_contents($file)));
}
echo "APPLIED.\n";
