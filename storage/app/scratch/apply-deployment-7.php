<?php
/**
 * Apply the two §7 corrections to DEPLOYMENT.md.
 *
 * The file is CRLF, so a multi-line insert cannot be typed through a line-oriented editor without mixing
 * endings. Each replacement is asserted to match exactly once before anything is written, and the new
 * paragraph carries the measured numbers rather than a summary of the incident.
 */
$path = 'DEPLOYMENT.md';
$crlf = "\r\n";
$bytes = file_get_contents($path);

if (! str_contains($bytes, $crlf)) {
    fwrite(STDERR, "ABORT: DEPLOYMENT.md is not CRLF as expected — refusing to write mixed endings.\n");
    exit(1);
}

$paragraph = <<<'TEXT'
**Seeding does not pin an id.** `StarterReferenceDataSeeder` keys its lists by *name*, so a reference row's id
is whatever the table's `AUTO_INCREMENT` counter hands out — and a rollback does not rewind that counter.
Measured on 2026-10-12 against a rehearsal database driven in CI's own order: after the suite the `religions`
table was empty with its counter at 53, so the seeders wrote ids 53..60 and the walkthrough's `religionId => 1`
died on `students_religionid_foreign` — four errors, exit 2 — while the SQLite and Frontend jobs stayed green.
The walkthrough now reads a religion out of the table instead of assuming one, and `religions` joined its guard
list so an install without it skips with the table named. Courses and terms are the exception and may stay
literals: `DevReferenceDataSeeder` pins those ids (`['courseId' => $id]`, `['termId' => $id]`), so an explicit
insert lands where the code says regardless of the counter.
TEXT;

$edits = [
    [
        'terms, the current curriculum, the seeded roles — so they need',
        'terms, the current curriculum, a religion and the seeded roles — so they need',
    ],
    [
        '**What CI still does not prove.**',
        // Normalise first: if this script was saved with CRLF, the heredoc already carries \r\n and a naive
        // replace would double every carriage return.
        preg_replace('/\R/', $crlf, $paragraph).$crlf.$crlf.'**What CI still does not prove.**',
    ],
];

foreach ($edits as [$from, $to]) {
    $hits = substr_count($bytes, $from);
    if ($hits !== 1) {
        fwrite(STDERR, "ABORT: anchor matched {$hits} times, expected exactly 1: ".substr($from, 0, 60)."…\n");
        exit(1);
    }
    $bytes = str_replace($from, $to, $bytes);
}

file_put_contents($path, $bytes);
echo 'DEPLOYMENT.md rewritten: '.strlen($bytes)." bytes\n";
echo 'CRLF lines: '.substr_count($bytes, $crlf).', bare LF: '.(substr_count($bytes, "\n") - substr_count($bytes, $crlf))."\n";
