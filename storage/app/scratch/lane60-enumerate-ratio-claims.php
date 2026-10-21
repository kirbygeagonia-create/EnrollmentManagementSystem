<?php

/**
 * Lane 60 step 1 — enumerate every ratio, fraction, percentage and "x of y" the documents assert.
 *
 * Lane 58 bound the suite totals to instrument output and lane 59 bound the counts the tools print. This family is the
 * third shape: a figure written in prose over live rows or over the document's own lists ("57 of 63 tables agree column
 * for column", "93 permissions across 14 modules", "a 100% aid cap", "21 assignments over 17 roles"). No existing
 * checker reads those, so nothing moves when the data moves.
 *
 * Read-only, and deliberately unjudging: this prints every candidate with its file, line ordinal and surrounding words,
 * flags whether the sentence carries its own date, and stops. Choosing which claims to bind happens after the count is
 * on the table, not by whoever wrote this script.
 *
 *   php storage/app/scratch/lane60-enumerate-ratio-claims.php
 *   php storage/app/scratch/lane60-enumerate-ratio-claims.php --show=20
 */

$root = dirname(__DIR__, 3);

$files = [
    'docx' => $root.'/storage/app/scratch/docx-flat.txt',
    'AGENTS.md' => $root.'/AGENTS.md',
    'DEPLOYMENT.md' => $root.'/DEPLOYMENT.md',
    'Round3' => $root.'/SEAIT-EMS-Round3-Current-State.md',
    'Audit-Remediation' => $root.'/Documentation/Audit-Remediation-2026-08-29.md',
];

$show = 0;
foreach ($argv as $a) {
    if (preg_match('/^--show=(\d+)$/', $a, $m)) {
        $show = (int) $m[1];
    }
}

$patterns = [
    'x-of-y' => '/\b(\d[\d,]*)\s+of\s+(?:the\s+)?(\d[\d,]*)\b/',
    'percent' => '/\b(\d+(?:\.\d+)?)\s*%(?!\w)/',
    'fraction' => '/\b(\d[\d,]*)\s*\/\s*(\d[\d,]*)\b/',
    'both-nouns' => '/\b(\d[\d,]*)\s+(?:rows|tables|permissions|roles|accounts|assignments|columns|keys|cases|tests|figures|lines|legs|indexes|constraints)\b/i',
];

// A sentence that names its own day is history and stays true; one that does not is a present claim about current data.
$DATED = '/\b(?:20\d\d-\d\d-\d\d|(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},?\s+20\d\d|on\s+20\d\d|at\s+the\s+time|back\s+in|used\s+to|was\s+measured|re-measured|then|before\s+the\s+change|as\s+of)\b/i';

$all = [];

foreach ($files as $label => $path) {
    if (! is_file($path)) {
        echo str_pad($label, 20).'MISSING — '.$path.' is not there, so this enumeration is incomplete'."\n";

        continue;
    }
    $lines = explode("\n", (string) file_get_contents($path));
    foreach ($lines as $i => $line) {
        // The dump stamps its own locator into each line ("ROW 2198:", "P 829:"); read that rather than counting,
        // so a reported line always matches the number another reader sees on the page.
        $ordinal = preg_match('/^(?:ROW|P)\s+(\d+):/', $line, $om) ? (int) $om[1] : ($i + 1);
        $body = preg_replace('/^(?:ROW|P)\s+\d+:\s*/', '', $line);
        foreach ($patterns as $kind => $re) {
            if (! preg_match_all($re, $body, $ms, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($ms as $m) {
                $figure = $m[0];
                // mb_* on both sides: strpos counts bytes and mb_substr counts characters, so mixing them shifts the
                // context window on any line that carries a ₱ or an en dash before the figure.
                $pos = (int) mb_strpos($body, $figure);
                $ctx = trim(str_replace("\n", ' ', mb_substr($body, max(0, $pos - 70), 150)));
                $all[] = ['file' => $label, 'line' => $ordinal ?: ($i + 1), 'kind' => $kind,
                    'figure' => $figure, 'dated' => preg_match($DATED, $body) ? 'history' : 'PRESENT', 'ctx' => $ctx];
            }
        }
    }
}

$byFile = [];
$byKind = [];
$byTense = [];
foreach ($all as $c) {
    $byFile[$c['file']] = ($byFile[$c['file']] ?? 0) + 1;
    $byKind[$c['kind']] = ($byKind[$c['kind']] ?? 0) + 1;
    $byTense[$c['file'].' '.$c['dated']] = ($byTense[$c['file'].' '.$c['dated']] ?? 0) + 1;
}

printf("candidates found: %d over %d files\n", count($all), count($files));
echo 'by shape: ';
foreach ($byKind as $k => $n) {
    echo $k.'='.$n.' ';
}
echo "\nby file and tense:\n";
foreach ($byTense as $key => $n) {
    echo '  '.str_pad((string) $n, 6, ' ', STR_PAD_LEFT).'  '.$key."\n";
}

$present = array_values(array_filter($all, static fn (array $c): bool => $c['dated'] === 'PRESENT'));
echo "\npresent-tense candidates: ".count($present)."\n";
usort($present, static fn (array $a, array $b): int => [$a['file'], $a['line']] <=> [$b['file'], $b['line']]);
foreach (array_slice($present, 0, $show > 0 ? $show : count($present)) as $c) {
    printf("  %-16s %5d  %-9s %-14s %s\n", $c['file'], $c['line'], $c['kind'], $c['figure'], $c['ctx']);
}

if ($show === 0) {
    echo "\n(pass --show=N to print N of them; nothing here is judged yet)\n";
}

exit(0);
