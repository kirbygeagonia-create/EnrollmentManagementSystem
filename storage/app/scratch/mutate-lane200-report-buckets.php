<?php

/**
 * The falsifier for `lane200-check-report-buckets.php`, which compares the ADS final report's two id rows
 * against the buckets `lane169-read-ads-status.php` computes from the traceability matrix.
 *
 * A checker that has never disagreed is not a checker. Every leg runs a COPY of the report through `--report=`,
 * so the delivered document is never touched, and each leg asserts the specific complaint it planted — including
 * the leg that moves a record clause back inside the membership cell, which is what proves the cell boundary is
 * honoured rather than tolerated, and the leg that proves `--report=` is read at all.
 *
 * Usage: php storage/app/scratch/mutate-lane200-report-buckets.php
 */
$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$tool = $root.'/storage/app/scratch/lane200-check-report-buckets.php';
$report = $root.'/Documentation/FINAL-REPORT-ADS.md';
$scratch = $root.'/storage/app/scratch';

foreach ([$tool, $report] as $need) {
    if (! is_file($need)) {
        fwrite(STDERR, "REFUSED: {$need} is not on disk.\n");
        exit(1);
    }
}

$copies = [];
register_shutdown_function(function () use (&$copies, $scratch): void {
    foreach ($copies as $c) {
        if (is_file($c)) {
            @unlink($c);
        }
    }
    foreach (glob($scratch.'/lane200-copy-*.md') ?: [] as $stray) {
        @unlink($stray);
    }
});

$bad = 0;
$legs = [];
$report_leg = static function (string $label, bool $pass, string $detail = '') use (&$bad, &$legs): void {
    $legs[] = $label;
    echo str_pad($label, 40).' '.($pass ? 'ok' : 'BAD').' '.$detail."\n";
    if (! $pass) {
        $bad++;
    }
};

$run = static function (string $args) use ($php, $tool): array {
    $lines = [];
    $status = 0;
    exec('"'.$php.'" '.escapeshellarg($tool).' '.$args.' 2>&1', $lines, $status);

    return [$status, implode("\n", $lines)];
};

$builtMarker = 'Built (the census';
$implMarker = 'Implemented, lead word';

// ---------------------------------------------------------------- 1. control
[$st, $out] = $run('');
$report_leg('control-agrees', $st === 0 && str_contains($out, 'VERDICT:'), "exit {$st}");
if ($st !== 0) {
    fwrite(STDERR, "REFUSED: the control disagrees, so a planted DISAGREE below would prove nothing.\n");
    exit(1);
}

// ---------------------------------------------------------------- helper to build a mutated copy
$copy = static function (string $name, callable $edit) use ($report, $scratch, &$copies): ?string {
    $bytes = (string) file_get_contents($report);
    $mutated = $edit($bytes);
    if ($mutated === $bytes) {
        return null;
    }
    $path = $scratch.'/lane200-copy-'.$name.'.md';
    if (file_put_contents($path, $mutated) === false) {
        return null;
    }
    $copies[] = $path;

    return $path;
};

/** Replace the membership cell of one row with a computed string. */
$editCell = static function (string $marker, callable $cellFn): callable {
    return static function (string $bytes) use ($marker, $cellFn): string {
        $at = strpos($bytes, $marker);
        if ($at === false) {
            return $bytes;
        }
        $end = strpos($bytes, "\n", $at);
        $row = substr($bytes, $at, $end - $at);
        $cells = array_map('trim', explode('|', $row));
        $cells = array_values(array_filter($cells, static fn (string $c): bool => $c !== ''));
        if (count($cells) < 2) {
            return $bytes;
        }
        $cells[1] = ($cellFn)($cells[1]);

        return substr_replace($bytes, '| '.implode(' | ', $cells).' |'."\n", $at, $end - $at + 1);
    };
};

// ---------------------------------------------------------------- 2. drop one id from the Built cell
$path = $copy('dropped-id', $editCell($builtMarker, static fn (string $cell): string => str_replace('P07 deadlock handling, ', '', $cell)));
if ($path === null) {
    $report_leg('dropped-id-disagrees', false, 'MUTANT DID NOT APPLY');
} else {
    [$st, $out] = $run('--report='.escapeshellarg($path));
    $report_leg('dropped-id-disagrees', $st === 1 && str_contains($out, 'P07') && str_contains($out, 'DISAGREE'), "exit {$st}");
    @unlink($path);
}

// ---------------------------------------------------------------- 3. add an id the census does not hold
$path = $copy('extra-id', $editCell($builtMarker, static fn (string $cell): string => $cell.' P97'));
if ($path === null) {
    $report_leg('extra-id-disagrees', false, 'MUTANT DID NOT APPLY');
} else {
    [$st, $out] = $run('--report='.escapeshellarg($path));
    $report_leg('extra-id-disagrees', $st === 1 && str_contains($out, 'P97') && str_contains($out, 'extra in the report'), "exit {$st}");
    @unlink($path);
}

// ---------------------------------------------------------------- 4. a record clause moved back INSIDE the cell
// The delivered document keeps such clauses outside the table on purpose; putting one back must inflate the
// cell and disagree, which is the only way to show the cell boundary is what the checker reads.
$path = $copy('tail-inside-cell', $editCell($implMarker, static fn (string $cell): string => $cell.' — P01, P06 and P16 left this list on 2026-11-02'));
if ($path === null) {
    $report_leg('tail-inside-cell-disagrees', false, 'MUTANT DID NOT APPLY');
} else {
    [$st, $out] = $run('--report='.escapeshellarg($path));
    $report_leg('tail-inside-cell-disagrees', $st === 1 && str_contains($out, 'implemented') && str_contains($out, 'DISAGREE'), "exit {$st}");
    @unlink($path);
}

// ---------------------------------------------------------------- 5. the marker renamed
$path = $copy('marker-gone', static fn (string $b): string => str_replace($builtMarker, 'Built list', $b));
if ($path === null) {
    $report_leg('renamed-marker-refuses', false, 'MUTANT DID NOT APPLY');
} else {
    [$st, $out] = $run('--report='.escapeshellarg($path));
    $report_leg('renamed-marker-refuses', $st === 1 && str_contains($out, 'REFUSED:') && str_contains($out, 'is not in'), "exit {$st}");
    @unlink($path);
}

// ---------------------------------------------------------------- 6. a third cell added
$path = $copy('third-cell', $editCell($builtMarker, static fn (string $cell): string => $cell.' | a note'));
if ($path === null) {
    $report_leg('third-cell-refuses-by-count', false, 'MUTANT DID NOT APPLY');
} else {
    [$st, $out] = $run('--report='.escapeshellarg($path));
    $report_leg('third-cell-refuses-by-count', $st === 1 && str_contains($out, 'cell(s), expected 2'), "exit {$st}");
    @unlink($path);
}

// ---------------------------------------------------------------- 7-8. both refusal paths
[$st, $out] = $run('--nope=1');
$report_leg('unknown-flag-refuses', $st === 1 && str_contains($out, 'does not understand'), "exit {$st}");

[$st, $out] = $run('--report='.escapeshellarg($scratch.'/lane200-no-such-file.md'));
$report_leg('missing-report-refuses', $st === 1 && str_contains($out, 'is not a file'), "exit {$st}");

// ---------------------------------------------------------------- 9. a byte-identical copy changes nothing
$path = $copy('control', static fn (string $b): string => $b."\n");
if ($path === null) {
    $report_leg('identical-copy-agrees', false, 'the copy could not be written');
} else {
    [$st, $out] = $run('--report='.escapeshellarg($path));
    $report_leg('identical-copy-agrees', $st === 0 && str_contains($out, 'VERDICT:'), "exit {$st}");
    @unlink($path);
}

echo "\n".($bad > 0 ? "{$bad} BAD" : 'ALL KILLED').' over '.count($legs)." leg(s).\n";
exit($bad > 0 ? 1 : 0);
