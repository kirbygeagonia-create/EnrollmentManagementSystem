<?php

// Proves the lane-49 Paid-cell fix is what changed the screen: put the pre-fix guard back, then restore.
//   php storage/app/scratch/lane49-prove-paid-guard.php plant
//   php storage/app/scratch/lane49-prove-paid-guard.php restore
$src = dirname(__DIR__, 3).'/resources/js/Pages/Students/Show.jsx';
$backup = __DIR__.'/lane49-prove-orig.jsx';

if (! is_file($src)) {
    fwrite(STDERR, "REFUSE: no such file — $src\n");
    exit(1);
}

$fixed = <<<'TXT'
{totalAssessment == null ? '—' : `₱${totalPaid.toLocaleString('en-PH')}`}
TXT;

$prefix = <<<'TXT'
{totalPaid > 0 ? `₱${totalPaid.toLocaleString('en-PH')}` : '—'}
TXT;

$mode = $argv[1] ?? '';

if ($mode === 'plant') {
    $s = file_get_contents($src);
    if (substr_count($s, $fixed) !== 1) {
        fwrite(STDERR, "NOT PLANTED: the fixed line matched ".substr_count($s, $fixed)." times\n");
        exit(1);
    }
    file_put_contents($backup, $s);
    file_put_contents($src, str_replace($fixed, $prefix, $s));
    $after = file_get_contents($src);
    if ($after === $s) {
        fwrite(STDERR, "NOT PLANTED: the file is byte-identical after the mutation\n");
        exit(1);
    }
    echo "planted: ".trim($prefix)."\n";
    echo "differ: ".(int) ($after !== $s)."  backup: $backup\n";
    exit(0);
}

if ($mode === 'restore') {
    $orig = file_get_contents($backup);
    $now = file_get_contents($src);
    if (substr_count($now, $prefix) !== 1) {
        fwrite(STDERR, "REFUSE: the planted line is not the one place I expect (found ".substr_count($now, $prefix).")\n");
        exit(1);
    }
    file_put_contents($src, $orig);
    clearstatcache();
    if (file_get_contents($src) !== $orig) {
        fwrite(STDERR, "REFUSE: restore did not land byte-for-byte\n");
        exit(1);
    }
    echo "restored byte-for-byte\n";
    exit(0);
}

fwrite(STDERR, "usage: plant|restore\n");
exit(1);
