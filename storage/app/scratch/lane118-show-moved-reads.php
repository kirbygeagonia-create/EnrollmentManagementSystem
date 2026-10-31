<?php
/**
 * Read-only: show the queue file's shape and every record naming one of the two files this batch shifted, with the
 * date each carries. The honesty test AGENTS.md names for a regenerated read record is the record's DATE against
 * the day the line moved — an inherited verdict wears the older date, and a moved pointer that silently keeps it is
 * how "105 of 105" once meant nothing.
 */
$root = dirname(__DIR__, 3);
$raw = (string) file_get_contents($root.'/storage/app/scratch/lane70-queue-reads.json');
$queue = json_decode($raw, true);
if (! is_array($queue)) {
    fwrite(STDERR, "REFUSED: the queue file did not decode.\n");

    exit(1);
}

echo 'top-level keys: '.implode(', ', array_slice(array_keys($queue), 0, 3))."\n";
$first = reset($queue);
echo 'record shape: '.(is_array($first) ? implode('|', array_keys($first)) : gettype($first))."\n\n";

$rows = $queue['records'] ?? $queue;
$want = ['EvaluationController', 'ExamController', 'WorkflowService'];
$n = 0;
foreach ($rows as $key => $rec) {
    if (! is_string($key)) {
        continue;
    }
    foreach ($want as $w) {
        if (str_contains($key, $w.'.php:')) {
            echo str_pad($key, 56).' | '
                .str_pad((string) (is_array($rec)['date'] ?? '?'), 12).' | '
                .substr((string) (is_array($rec)['verdict'] ?? ''), 0, 70)."\n";
            $n++;
        }
    }
}
echo "\nrecords shown: {$n} of ".count($rows)." total\n";
