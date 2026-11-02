<?php

/**
 * Proves `lane191-attribute-registrar-queries.php` reads its own guards, and that the figure it prints is a
 * measurement of a population rather than a division of whatever happened to be on screen.
 *
 * Why this exists: the probe carried two defects of this repository's oldest family before its number was quoted.
 * Its first per-row figure divided EVERY statement by the rows listed (9.2), which counts the page's own eager
 * loads and would have "improved" on the 5.0 it was meant to refine; and a stray events dispatch made Laravel's
 * own exception renderer bind a zero-argument listener, so the run died instead of measuring. Both were caught by
 * reading the print, not by the tool refusing — which is the argument for a harness.
 *
 * Every leg here is either a refusal the tool reaches before it touches anything, or a bounded run that has to
 * still print a population. Nothing writes: the probe dispatches a GET.
 *
 * Usage: php storage/app/scratch/mutate-lane191-attribution.php
 */
$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$tool = 'storage/app/scratch/lane191-attribute-registrar-queries.php';
if (! is_file($php) || ! is_file($tool)) {
    fwrite(STDERR, "REFUSED: the interpreter or the probe is missing.\n");
    exit(1);
}

$legs = [
    // An unknown flag must be refused BY NAME: an accepted-and-inert option makes a scoped run print exactly the
    // verdict a whole one does.
    ['name' => 'unknown-flag-refused', 'args' => '--no-such-flag', 'want' => 'refuse',
        'needle' => 'this tool does not read'],

    // The page size is not this tool's to choose: --rows existed once, was accepted, and changed nothing, because
    // the controller hardcodes paginate(20). Proving a flag is DELETED is a different leg from proving it works —
    // ask for it and require the refusal that names it.
    ['name' => 'rows-arg-refused', 'args' => '--rows=1', 'want' => 'refuse',
        'needle' => 'this tool reads no --rows'],

    // Positive control: the run must still reach a population and print the divisor it used, so the refusals
    // above cannot be a tool that never measures anything.
    ['name' => 'run-prints-a-population', 'args' => '', 'want' => 'answer',
        'needle' => 'repeating per-row statements'],
];

$bad = 0;
foreach ($legs as $leg) {
    exec(escapeshellarg($php).' '.escapeshellarg($tool).' '.escapeshellarg($leg['args']).' 2>&1', $out, $code);
    $text = implode("\n", $out);
    $hasNeedle = str_contains($text, $leg['needle']);
    if ($leg['want'] === 'refuse') {
        $ok = $hasNeedle && $code === 1;
        printf("%s %-26s exit=%d %s\n", $ok ? 'ok  ' : 'BAD ', $leg['name'], $code,
            $ok ? 'refused by name' : 'needle='.($hasNeedle ? 'found' : 'MISSING')." code=$code :: ".trim(substr($text, 0, 160)));
    } else {
        // A refusal here is not a pass: this leg exists to prove the population was reached at all.
        $ok = $hasNeedle && $code === 0 && str_contains($text, 'row(s) listed');
        printf("%s %-26s exit=%d %s\n", $ok ? 'ok  ' : 'BAD ', $leg['name'], $code,
            $ok ? 'printed a population' : trim(substr($text, 0, 160)));
    }
    if (! $ok) {
        $bad++;
    }
    $out = [];
}

printf("\n%s\n", $bad === 0 ? 'ALL KILLED: every refusal fires by name and the bounded run still measures.'
    : $bad.' leg(s) BAD — read them as the harness or the probe, never as a clean run.');
exit($bad === 0 ? 0 : 1);
