<?php

/**
 * Proof harness for lane41-doc-claims.php — plant each defect class and watch the checker fail.
 *
 * A clean run means nothing until the checker has been shown to be capable of not-clean. Four classes are
 * planted into a throwaway copy of the three documents, outside the repository's own Documentation/ directory
 * (the `--dir=` override exists for exactly this), and each must produce its own MISS line and exit 1:
 *
 *   1. a receipt amount that no payment row carries;
 *   2. an assessment figure the sheet does not hold;
 *   3. an aggregate the router and tables disagree with;
 *   4. a peso figure the parser cannot attach to any claim — the class that matters most, because an
 *      unattached claim is the one a checker silently drops. The run must report it as UNBOUND and refuse
 *      on "counted but not resolved".
 *
 * The last step re-runs the checker against the real documents and requires the clean verdict, so a mutant
 * left behind in the tree cannot pass this file.
 *
 * Usage: php storage/app/scratch/mutate-lane41-claims.php
 */

$root = dirname(__DIR__, 3);
$php = PHP_BINARY;
$docs = ['Demo-Day-Guide.md', 'Offline-Presentation-Guide.md', 'Per-Office-UAT-Walkthrough.md'];

$tmp = sys_get_temp_dir().'/lane41-mutants-'.getmypid();
mkdir($tmp);

$failures = 0;
$say = function (bool $ok, string $what) use (&$failures): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $failures++;
    }
};

$run = function (string $dir) use ($root, $php, $tmp): array {
    $cmd = escapeshellarg($php).' '.escapeshellarg($root.'/storage/app/scratch/lane41-doc-claims.php').' --dir='.escapeshellarg($dir).' 2>&1';
    exec($cmd, $out, $code);

    return [$code, implode("\n", $out)];
};

foreach ($docs as $d) {
    copy($root.'/Documentation/'.$d, $tmp.'/'.$d);
}

$plant = function (string $file, string $from, string $to) use ($tmp): void {
    $path = $tmp.'/'.$file;
    $text = (string) file_get_contents($path);
    if (substr_count($text, $from) !== 1) {
        throw new RuntimeException("plant target seen ".substr_count($text, $from)." times in {$file}: {$from}");
    }
    file_put_contents($path, str_replace($from, $to, $text));
};

$cases = [
    'wrong receipt amount' => ['Demo-Day-Guide.md', 'OR **DEMO-OR-0001** (₱12,000)', 'OR **DEMO-OR-0001** (₱13,000)', 'DEMO-OR-0001 ₱13,000.00'],
    'wrong assessment figure' => ['Per-Office-UAT-Walkthrough.md', 'assessed ₱18,500', 'assessed ₱19,500', 'enrollment 88 assessed'],
    'wrong aggregate' => ['Demo-Day-Guide.md', 'across the 13 catalogs', 'across the 11 catalogs', 'reference catalogs'],
];

foreach ($cases as $label => [$file, $from, $to, $expectIn]) {
    foreach ($docs as $d) {
        copy($root.'/Documentation/'.$d, $tmp.'/'.$d);
    }
    $plant($file, $from, $to);
    [$code, $out] = $run($tmp);
    $say($code === 1 && str_contains($out, 'MISS') && str_contains($out, $expectIn), $label.' was caught (exit '.$code.')');
    if ($code !== 1) {
        echo "      output tail: ".substr(str_replace("\n", ' | ', $out), -160)."\n";
    }
}

// Class 4: a peso figure attached to nothing. The checker must both report it and refuse on the arithmetic.
foreach ($docs as $d) {
    copy($root.'/Documentation/'.$d, $tmp.'/'.$d);
}
$text = (string) file_get_contents($tmp.'/Per-Office-UAT-Walkthrough.md');
$needle = '| Marisol Domingo | DEMO-2026-020 |';
if (! str_contains($text, $needle)) {
    throw new RuntimeException('the unbound-claim mutant has nowhere to sit');
}
$text = str_replace(
    $needle,
    "| Test Subject | DEMO-2026-001 | 53 `enrolled`, a charge of ₱4,321 was mentioned in passing |\n".$needle,
    $text
);
file_put_contents($tmp.'/Per-Office-UAT-Walkthrough.md', $text);
[$code, $out] = $run($tmp);
$say($code === 1 && str_contains($out, 'UNBOUND'), 'an unattachable peso figure is reported as UNBOUND rather than skipped (exit '.$code.')');
$say(str_contains($out, 'not clean'), 'and the run refuses on counted-but-unresolved claims');

// And the real documents must be clean, with the mutants gone.
[$code, $out] = $run($root.'/Documentation');
$say($code === 0 && str_contains($out, '0 mismatch'), 'the unmutated documents read clean (exit '.$code.')');

foreach ($docs as $d) {
    unlink($tmp.'/'.$d);
}
rmdir($tmp);

printf("\n%s\n", $failures === 0 ? 'all five defect classes caught, and the clean tree still passes' : $failures.' mutant class(es) the checker missed');
exit($failures === 0 ? 0 : 1);
