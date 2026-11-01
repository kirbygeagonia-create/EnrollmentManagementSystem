<?php

/**
 * Falsifier for lane 189 — the checker that compares a §24 table's heading with the rows under it.
 *
 * Every leg plants a defect in a COPY of the flat dump and requires the named verdict line, because a census
 * that can only print agreement is decoration. Refusals are told apart by their own messages, not by exit code
 * (all of them exit 1): an unreachable schema, an unknown flag, a missing dump and a dump older than the docx
 * are four different claims about the instrument. The byte-identical control is what proves `--dump=` is read
 * rather than accepted — the accepted-and-inert flag this repository has met six times.
 *
 * Run it on its own, never beside a suite run: it shells out to the checker repeatedly and a concurrent writer
 * makes its own verdicts unread. Usage: php storage/app/scratch/mutate-lane189-weigh.php
 */
$root = dirname(__DIR__, 3);
$php = getenv('EMS_PHP') ?: 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$checker = $root.'/storage/app/scratch/lane189-weigh-column-claims.php';
$dump = $root.'/storage/app/scratch/docx-flat.txt';
$copy = $root.'/storage/app/scratch/lane189-mutant-dump.txt';

$original = (string) file_get_contents($dump);
$failures = 0;

$run = static function (string $args) use ($php, $checker): string {
    return (string) shell_exec(escapeshellcmd($php).' '.$checker.' '.$args.' 2>&1');
};

$report = static function (string $name, bool $pass, string $detail = '') use (&$failures): void {
    printf("  %-4s %-40s %s\n", $pass ? 'ok' : 'BAD', $name, $detail);
    if (! $pass) {
        $failures++;
    }
};

$case = static function (string $name, string $mutated, string $needle) use ($run, $copy, $original, $report): void {
    if ($mutated === $original) {
        $report($name, false, 'MUTANT DID NOT APPLY — the anchor matched nothing, so this leg plants no defect');

        return;
    }
    if (file_put_contents($copy, $mutated) === false) {
        $report($name, false, 'the plant could not be written');

        return;
    }
    $out = $run('--dump='.escapeshellarg($copy));
    $report($name, str_contains($out, $needle), str_contains($out, $needle) ? 'needle found'
        : 'needle MISSING: '.trim(preg_replace('/\s+/', ' ', substr($out, 0, 160))));
};

// Control: the real dump through the copy path must read clean, or every leg below measures a broken checker.
$control = $run('--dump='.escapeshellarg($dump));
$report('control — the delivered document', str_contains($control, 'every §24 heading equals its own server count'),
    trim(preg_replace('/\s+/', ' ', substr(strrchr($control, "\n") ?: $control, 1, 90))));

// (1) A heading moved one column higher than its own list: the pair the batch met in the wild. The dump's line
// ordinal is NOT typed here — a mutant anchored on a generated ordinal sleeps the day a paragraph moves above it.
$case('a heading one above the server',
    preg_replace('/(P \d+: enrollments — )(\d+)( columns)/u', '${1}26${3}', $original, 1),
    'HEADING-SERVER');

// (2) A column row deleted from the list: the heading then over-states the table, and the omitted name must be named.
$lines = explode("\n", $original);
$cut = null;
foreach ($lines as $i => $l) {
    if (str_starts_with(trim($l), 'ROW ') && str_contains($l, 'requestKey | varchar(64)')) {
        $cut = $i;
        break;
    }
}
if ($cut === null) {
    $report('a column row removed', false, 'the anchor row (requestKey) is not in the dump — re-cut this leg');
} else {
    $mutated = $lines;
    unset($mutated[$cut]);
    $case('a column row removed', implode("\n", $mutated), 'omitted by the list: requestKey');
}

// (3) A heading naming a table the server does not hold: the census must call it UNBOUND, not agree with it.
$case('a heading naming no server table',
    preg_replace('/(P \d+: )(enrollments)( — \d+ columns)/u', '${1}$2s${3}', $original, 1),
    'UNBOUND');

// Refusals, each by its own message.
$noSchema = $run('--schema=ems_no_such_schema');
$report('an unreachable schema', str_contains($noSchema, 'REFUSED:') && str_contains($noSchema, 'answered nothing'),
    trim(preg_replace('/\s+/', ' ', substr($noSchema, 0, 90))));

$badFlag = $run('--nonsense-flag');
$report('an unknown flag refused by name', str_contains($badFlag, 'flag not read by this tool: --nonsense-flag'),
    trim(preg_replace('/\s+/', ' ', substr($badFlag, 0, 90))));

$noDump = $run('--dump='.escapeshellarg($root.'/storage/app/scratch/definitely-not-here.txt'));
$report('a missing dump refused', str_contains($noDump, 'no flat dump at'),
    trim(preg_replace('/\s+/', ' ', substr($noDump, 0, 90))));

// An empty population: with every §24 heading struck out the census has nothing to read and must say so,
// because a checker that reports agreement over an unjudgeable dump is the fake-clean verdict itself.
$stripped = preg_replace('/^P \d+: [a-z0-9_]+ — \d+ columns$/uim', 'P 1: (no heading here)', $original);
if ($stripped === $original) {
    $report('a dump with nothing to judge refuses', false, 'the heading strike matched nothing — re-cut this leg');
} else {
    file_put_contents($copy, $stripped);
    $empty = $run('--dump='.escapeshellarg($copy));
    $report('a dump with nothing to judge refuses', str_contains($empty, 'A census over nothing'),
        trim(preg_replace('/\s+/', ' ', substr($empty, 0, 90))));
}

@unlink($copy);
printf("\n  %d leg(s) failed.\n", $failures);
exit($failures === 0 ? 0 : 1);
