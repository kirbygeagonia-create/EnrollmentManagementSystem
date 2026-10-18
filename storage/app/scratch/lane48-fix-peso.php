<?php

/**
 * Lane 48 — make every private `peso()` refuse to invent a peso zero.
 *
 * Six pages carry their own copy of this formatter and all six read `Number(n || 0)`, so a null amount arrives
 * at the desk as ₱0.00 — money the server never said was zero. Each copy is rewritten to the same guard, and
 * the run refuses unless every file matched exactly once, because a bulk edit that silently skips a file leaves
 * six screens where the reader cannot tell which one still lies.
 */

$root = dirname(__DIR__, 3);

$files = [
    'Pages/Accounting/Index.jsx',
    'Pages/Accounting/Show.jsx',
    'Pages/Accounting/DailyReport.jsx',
    'Pages/Assessment/Index.jsx',
    'Pages/Assessment/Show.jsx',
    'Pages/Clearance/Index.jsx',
];

$from = 'const peso = (n) => `₱${Number(n || 0).toLocaleString(\'en-PH\', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;';

$to = <<<'JS'
// An absent amount is not zero money: it renders a dash, so a desk never reads "₱0.00" for a figure the
// server did not send. A real 0 still renders as ₱0.00.
const peso = (n) => (n === null || n === undefined || n === '' || !Number.isFinite(Number(n))
    ? '—'
    : `₱${Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
JS;

$originals = [];
foreach ($files as $rel) {
    $path = $root.'/resources/js/'.$rel;
    $src = @file_get_contents($path);
    if ($src === false) {
        fwrite(STDERR, "REFUSED: cannot read {$rel}\n");
        exit(1);
    }
    $hits = substr_count($src, $from);
    if ($hits !== 1) {
        fwrite(STDERR, "REFUSED: {$rel} matched the peso definition {$hits} time(s), expected exactly 1 — nothing was written.\n");
        exit(1);
    }
    $originals[$path] = $src;
}

foreach ($originals as $path => $src) {
    $out = str_replace($from, $to, $src);
    if (file_put_contents($path, $out) === false) {
        fwrite(STDERR, 'FAILED to write '.basename($path)." — restoring every file.\n");
        foreach ($originals as $p => $s) {
            file_put_contents($p, $s);
        }
        exit(1);
    }
    if (substr_count((string) file_get_contents($path), 'Number(n || 0)') !== 0) {
        fwrite(STDERR, 'FAILED: '.basename($path)." still carries the coercing peso.\n");
        exit(1);
    }
    echo 'rewritten: '.str_replace($root.'/resources/js/', '', $path)."\n";
}
