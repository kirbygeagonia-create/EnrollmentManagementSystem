<?php

/**
 * Lane 64's mutant harness — prove the empty-table checker refuses, and prove --dump= is read.
 *
 * The checker judges sets, not figures, so a mutant must break a SET: drop a member, add a member that
 * holds rows, un-name the framework parenthetical, move a count, rename a row's table reference. Each is
 * planted in a COPY of the flat dump (the document is never touched), and every case refuses the run when
 * its needle matches nothing — a mutation that changed no bytes produces the checker's clean output, and
 * a harness that reads that as a pass is lane 44's sleeping mutant.
 *
 *   php storage/app/scratch/mutate-lane64-empty-tables.php
 */

$root = dirname(__DIR__, 3);
// shell_exec('which php') is answered by cmd.exe on this box, which has no `which`: the phrase came back as
// the "path" and every case measured nothing. Take the interpreter from the env, or the known path.
$cands = array_filter([(string) getenv('EMS_PHP'), 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe'], 'is_file');
$php = $cands === [] ? 'php' : array_shift($cands);
$checker = $root.'/storage/app/scratch/lane64-empty-table-inventory.php';
$real = $root.'/storage/app/scratch/docx-flat.txt';
$tmp = $root.'/storage/app/scratch/lane64-mutant-dump.txt';
if (! is_file($checker) || ! is_file($real)) {
    exit("REFUSED: checker or dump missing ({$checker})\n");
}
$pristine = (string) file_get_contents($real);
$bad = 0;

/** Run the checker in verify mode against a copy; return [exitCode, output]. exec() answers the real code. */
$run = function (string $dumpFile): array {
    global $php, $checker;
    $lines = [];
    $code = 99;
    exec(escapeshellarg($php).' '.escapeshellarg($checker).' --verify --dump='.escapeshellarg($dumpFile).' 2>&1', $lines, $code);

    return [(int) $code, implode("\n", $lines)];
};
$report = function (string $label, bool $ok, string $detail) use (&$bad): void {
    printf("  %s %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, $detail === '' ? '' : ' — '.$detail);
    if (! $ok) {
        $bad++;
    }
};

// --- the flag is read, not accepted -------------------------------------------------------------
file_put_contents($tmp, $pristine);
[$code, $out] = $run($tmp);
$report('control: the document as it stands passes --verify on the copy', $code === 0, 'exit '.$code."\n     ".trim(implode(' | ', array_slice(explode("\n", trim($out)), -1))));
if ($code !== 0) {
    echo "\nREFUSED: a mutant run on a red control measures nothing. Fix the document or the checker first.\n";
    exit(1);
}
$noVerify = (string) shell_exec(escapeshellarg($php).' '.escapeshellarg($checker).' --dump='.escapeshellarg($tmp).' 2>&1');
$report('--dump= without --verify refuses rather than judging a copy silently', str_contains($noVerify, 'REFUSE: --dump= is only meaningful'), trim(substr($noVerify, 0, 90)));
$missing = (string) shell_exec(escapeshellarg($php).' '.escapeshellarg($checker).' --verify --dump='.escapeshellarg($tmp.'.nope').' 2>&1');
$report('a --dump= that does not exist refuses', str_contains($missing, 'REFUSE: the flat dump'), trim(substr($missing, 0, 90)));

$cases = [
    ['framework set loses a member',
        '(cache, cache_locks, failed_jobs, job_batches, jobs, sessions), so',
        '(cache, cache_locks, failed_jobs, job_batches, sessions), so',
        'framework inside it'],
    ['framework count says five',
        'six of them are Laravel framework tables', 'five of them are Laravel framework tables',
        'framework count'],
    ['a table that holds rows joins the empty list',
        'COUNT(*) is 0 are cache,', 'COUNT(*) is 0 are gradescale, cache,',
        'all COUNT(*)=0 tables'],
    ['the split goes back to a positional claim',
        'six of them are Laravel framework tables (cache, cache_locks, failed_jobs, job_batches, jobs, sessions)',
        'the last six of them framework tables',
        'does not NAME'],
    ['a domain member is dropped from the domain set',
        'creditedsubjects, model_has_permissions, shiftingrequests and transferacademicrecords',
        'creditedsubjects, model_has_permissions and transferacademicrecords',
        'domain inside it'],
    ['§24.18 counts nine',
        '4 domain tables are empty', '9 domain tables are empty',
        'a domain-empty count elsewhere'],
];

foreach ($cases as [$label, $from, $to, $wantInComplaint]) {
    $n = substr_count($pristine, $from);
    if ($n !== 1) {
        $report($label, false, 'BAD ANCHOR: its needle occurs '.$n.' times, so this case would measure an unmutated copy');
        continue;
    }
    file_put_contents($tmp, str_replace($from, $to, $pristine));
    if ((string) file_get_contents($tmp) === $pristine) {
        $report($label, false, 'BAD: the mutation left the copy byte-identical');
        continue;
    }
    [$code, $out] = $run($tmp);
    $lines = [];
    foreach (explode("\n", $out) as $l) {
        if (str_contains($l, $wantInComplaint)) {
            $lines[] = trim(mb_substr($l, 0, 150));
        }
    }
    $report($label, $code === 1 && $lines !== [],
        'exit '.$code.'; complaint line: '.($lines[0] ?? 'NONE — the checker stayed silent on '.substr($wantInComplaint, 0, 30)));
}

// A row's TABLE REFERENCE moves, not its label: renaming a heading would leave the claim intact.
$flatRow = null;
foreach (explode("\n", $pristine) as $l) {
    if (preg_match('/^(ROW \d+: )shiftingrequests \|/', $l) === 1) {
        $flatRow = $l;
    }
}
if ($flatRow === null) {
    $report('a §24.17 row stops naming a table', false, 'BAD ANCHOR: no §24.17 row for shiftingrequests in the dump');
} else {
    file_put_contents($tmp, str_replace($flatRow, str_replace('shiftingrequests |', 'shiftingrequestz |', $flatRow), $pristine));
    [$code, $out] = $run($tmp);
    $hit = str_contains($out, 'NOT A TABLE') && str_contains($out, 'missing row');
    $report('a §24.17 row stops naming a table', $code === 1 && $hit, 'exit '.$code);
}

@unlink($tmp);
echo "\n".($bad === 0
    ? "All lane 64 cases behaved: the checker is green on the document and red on every way its sets could disagree with COUNT(*).\n"
    : "{$bad} case(s) failed — the checker or this harness is the defect, not the document.\n");
exit($bad === 0 ? 0 : 1);
