<?php

/**
 * Read the ADS matrix's own §1 Status column and print the buckets, so §6's sentence is derived from the table
 * rather than typed from memory.
 *
 * The Status cell is the verdict (task #43's rule for the requirement matrix, applied here): prose beside it is
 * not consulted. A cell is classified by the word it LEADS with, because a cell may mention "Not built" in the
 * explanation of what is still owed — and §6 is exactly the sentence a later reader takes as the register's state.
 */

$doc = dirname(__DIR__, 3).'/Documentation/REQUIREMENTS-TRACEABILITY-ADS.md';
if (! is_file($doc)) {
    fwrite(STDERR, "REFUSED: the matrix is not at {$doc} — the reader found no register to read.\n");
    exit(1);
}
$lines = file($doc);

$inSummary = false;
$buckets = ['built' => [], 'measured' => [], 'partly' => [], 'not-built' => [], 'implemented' => [], 'other' => []];
$rows = 0;
foreach ($lines as $line) {
    if (str_starts_with(trim($line), '## 1. Status summary')) {
        $inSummary = true;

        continue;
    }
    if ($inSummary && str_starts_with(trim($line), '## ')) {
        break;
    }
    if (! $inSummary || ! str_starts_with(trim($line), '| P')) {
        continue;
    }
    // Trim the newline too: `'| '` leaves the row's trailing "\n" attached to the last cell, so every well-formed
    // row reads as a fifth empty cell and the guard refuses a document that is fine. The refusal is right to be
    // loud; it was pointed at the reader.
    $cells = array_map('trim', explode('|', trim($line, "| \r\n")));
    if (count($cells) !== 4) {
        fwrite(STDERR, 'REFUSED: a §1 row carries '.count($cells)." cells, not 4 — the row is wrapped or a cell holds a pipe.\n");
        exit(1);
    }
    $rows++;
    $status = ltrim($cells[3], '*');
    $raw = strtolower(preg_replace('/\s.*$/', '', $status) ?? '');
    // Letters only: "Measured, not repaired" and "Implemented, demonstration owed" are the same two verdicts as
    // their unpunctuated heads, and a classifier that keys on a comma invents a third vocabulary beside it.
    $lead = preg_replace('/[^a-z]/', '', $raw) ?? '';
    // Classified on the leading word alone, with the vocabulary named in one place. An unknown lead refuses the run
    // rather than dropping into a silent catch-all: two answers to "what does this row say" is the defect this
    // repository has refused in lanes 46, 69, 90 and 127.
    $bucket = match ($lead) {
        'built' => 'built',
        'measured' => 'measured',
        'partly', 'partially', 'justified' => 'partly',
        'not' => 'not-built',
        'implemented' => 'implemented',
        default => 'UNKNOWN',
    };
    if ($bucket === 'UNKNOWN') {
        fwrite(STDERR, "REFUSED: {$cells[0]}'s Status cell leads with an unknown word: ".substr($status, 0, 60)
            ."\n");
        exit(1);
    }
    $buckets[$bucket][] = $cells[0].' (leads "'.$lead.'")';
}

if ($rows !== 24) {
    fwrite(STDERR, "REFUSED: §1 holds {$rows} problem rows and the guide names 24 — the population this document is a register over.\n");
    exit(1);
}

echo "rows judged: {$rows}\n";
foreach ($buckets as $name => $ids) {
    if ($ids === []) {
        printf("  %-11s 0\n", $name);

        continue;
    }
    printf("  %-11s %d  %s\n", $name, count($ids), implode(', ', $ids));
}
$total = array_sum(array_map('count', $buckets));
if ($total !== $rows) {
    fwrite(STDERR, "REFUSED: the buckets add to {$total} against {$rows} rows — one row fell through a `continue`.\n");
    exit(1);
}
echo "buckets add to the population: {$total} = {$rows}\n";
