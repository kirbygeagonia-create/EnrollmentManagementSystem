<?php

/**
 * Lane 150 — re-point every markdown pointer into the two listeners, and read two of them back as wrong targets.
 *
 * `NoticeFacts` added one `use` line to each listener, which ages every pointer below line 10. But two of the
 * pointers this pass moves were not merely aged: the AGENTS sentence cites `SendEnrollmentNotification.php:62-63`
 * and `SendWorkflowNotification.php:88-89` for "both listeners write `Staffusers::class` / the userId", and at
 * neither pair do those lines hold that write — the enrollment pair lands inside the payload array's phase/signed
 * keys and the workflow pair on a docblock line. Both are live lines of the right file, so the citation gate, the
 * meaning sweep and lane 32 all agree with them: lane 70's wrong-target class, arriving in a comment about
 * notification addressing. The new numbers are taken by reading each file for the write the sentence names.
 *
 * Usage: php storage/app/scratch/lane150-repoint-notice-listeners.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
$apply = in_array('--apply', $argv, true);

$paths = [
    'enrollment' => 'app/Listeners/SendEnrollmentNotification.php',
    'workflow' => 'app/Listeners/SendWorkflowNotification.php',
];
$live = [];
foreach ($paths as $key => $rel) {
    $live[$key] = file($rel);
}

/**
 * Each new number must carry the claim its sentence makes. Claims are spelled as token PAIRS rather than as whole
 * source lines, because a double-quoted PHP string interpolates `$userId` away — which is what made this pass's
 * first run refuse a correct file: it asserted against the empty string. Refusing was right; the assertion was the
 * bug, and it is recorded here so the next reader does not re-write the same one.
 *
 * @var array<int, array{0: string, 1: int, 2: list<string>}> $assertions
 */
$assertions = [
    ['enrollment', 17, ['public bool', 'afterCommit']],
    ['enrollment', 76, ['notifiable_type', 'Staffusers::class']],
    ['enrollment', 77, ['notifiable_id', 'userId']],
    ['workflow', 19, ['public bool', 'afterCommit']],
    ['workflow', 96, ['the caller could not name the act']],
    ['workflow', 109, ['notifiable_type', 'Staffusers::class']],
    ['workflow', 110, ['notifiable_id', 'userId']],
];
foreach ($assertions as [$key, $line, $tokens]) {
    $actual = trim($live[$key][$line - 1] ?? '');
    foreach ($tokens as $token) {
        if (! str_contains($actual, $token)) {
            fwrite(STDERR, sprintf("REFUSED: %s line %d reads [%s], which lacks [%s].\n",
                basename($paths[$key]), $line, $actual, $token));

            exit(1);
        }
    }
    printf("ok   %s:%d carries %s\n", basename($paths[$key]), $line, implode(' + ', $tokens));
}

$jobs = [
    'AGENTS.md' => [
        ['`SendEnrollmentNotification.php:62-63`, `SendWorkflowNotification.php:88-89`',
            '`SendEnrollmentNotification.php:76-77`, `SendWorkflowNotification.php:109-110`', 1],
    ],
    'Documentation/Prompt-Completion-Report-2026-10-27.md' => [
        ['`SendWorkflowNotification.php:95`', '`SendWorkflowNotification.php:96`', 1],
    ],
    'Documentation/Prompt-Requirement-Matrix-2026-10-26.md' => [
        ['`SendEnrollmentNotification.php:16`, `SendWorkflowNotification.php:18`',
            '`SendEnrollmentNotification.php:17`, `SendWorkflowNotification.php:19`', 1],
    ],
];

$fail = 0;
$backups = [];
foreach ($jobs as $rel => $edits) {
    $path = $root.'/'.$rel;
    $text = (string) file_get_contents($path);
    foreach ($edits as [$from, $to, $expect]) {
        $got = substr_count($text, $from);
        if ($got === 0 && substr_count($text, $to) === $expect) {
            printf("  ok   %-52s ALREADY APPLIED (%s)\n", substr($from, 0, 50), basename($rel));

            continue;
        }
        if ($got !== $expect) {
            printf("  BAD  occurs %d, wanted %d [%s]\n", $got, $expect, substr($from, 0, 60));
            $fail++;

            continue;
        }
        $text = str_replace($from, $to, $text);
        printf("  ok   %-52s -> %s\n", substr($from, 0, 50), substr($to, 0, 50));
    }
    if ($apply && $fail === 0) {
        $bak = $root.'/storage/app/scratch/lane150-'.basename($rel).'.bak';
        copy($path, $bak);
        $backups[$path] = $bak;
        $written = @file_put_contents($path, $text);
        if ($written !== strlen($text)) {
            printf("  BAD  the write of %s returned %s\n", basename($rel), var_export($written, true));
            $fail++;
        }
    }
}

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";

    exit($fail === 0 ? 0 : 1);
}

if ($fail > 0) {
    foreach ($backups as $path => $bak) {
        copy($bak, $path);
    }
    fwrite(STDERR, "REFUSED: {$fail} claim(s) did not match; each file restored from its own backup.\n");

    exit(1);
}

echo "written: five pointers re-taken, two of them onto the lines that actually hold the claim.\n";
