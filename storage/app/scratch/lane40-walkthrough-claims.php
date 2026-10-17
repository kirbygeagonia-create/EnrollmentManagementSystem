<?php

/**
 * Lane 40 — does every record the per-office UAT walkthrough tells a desk to open still read that way?
 *
 * Lane 39 found Documentation/Demo-Day-Guide.md promising a restore it could not perform, and its sibling
 * walkthrough was never checked the same way. This file reads the walkthrough's own tables and pulls every
 * "open this record, it says <status>" claim out of them: `<id> \`pending\``, `admission 41
 * \`pending\``, `slip 27 approved`, and the school id the row names. Each pair is then resolved against the
 * live table it belongs to.
 *
 * Why a checker rather than a read: the walkthrough is 500+ lines of desk instructions where a stale id is not
 * a typo but a failed step — the desk cannot find the record, or finds a record in the wrong state and signs
 * off on the wrong evidence. The claims are enumerable, so they can be re-checked before every walk.
 *
 * Nothing here edits anything. A MISS means the document and the database disagree; which one is wrong is the
 * reader's call, and AGENTS.md's standing rule is that probe residue is the owner's decision.
 *
 * Usage: php storage/app/scratch/lane40-walkthrough-claims.php            (live ems)
 *        DB_DATABASE=ems_lane40_roundtrip php storage/app/scratch/lane40-walkthrough-claims.php
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 3);
// An optional first argument points the reader at another document — that is how a mutant is fed to prove this
// checker can fail, rather than trusting a silence that may be a parser that matched nothing.
$args = array_values(array_filter(array_slice($argv ?? [], 1), fn ($a) => ! str_starts_with($a, '--')));
$doc = $args[0] ?? $root.'/Documentation/Per-Office-UAT-Walkthrough.md';
$lines = file($doc);
$database = DB::connection()->getDatabaseName();

// Which table each preceding word names. An id with no qualifier is an enrollment — that is how the
// walkthrough's data-inventory table is built (Name | School ID | enrollment ids | …).
$tables = [
    'enrollment' => ['table' => 'enrollments', 'key' => 'enrollmentId', 'status' => 'enrollmentStatus'],
    'admission' => ['table' => 'admissions', 'key' => 'admissionId', 'status' => 'admissionStatus'],
    'slip' => ['table' => 'studentclearances', 'key' => 'studentClearanceId', 'status' => 'overallStatus'],
];

$claims = [];
$seen = [];

foreach ($lines as $no => $line) {
    $lineNo = $no + 1;

    // The row's school id, when the line is one of the inventory rows.
    $rowStudent = null;
    if (preg_match('/DEMO-2026-\d{3}/', $line, $m)) {
        $rowStudent = $m[0];
    }

    // `admission 41 \`pending\`` / `53 \`enrolled\`` / `287 **\`returnedToEvaluation\`**` / `654 (term 2) \`enrolled\``
    //
    // The optional `(term N)` between the id and the status is not decoration: leaving it out made this file
    // report 33 claims when the document makes 34, and the one it never read — `654 (term 2) \`enrolled\``, a row
    // that has been dropped since 2026-10-15 — was exactly the kind of claim the checker exists to find.
    if (preg_match_all('/(?:(admission|slip)\s+)?(\d{1,4})(?:\s*\([^)]*\))?\s+\*{0,2}`([a-zA-Z]+)`\*{0,2}/', $line, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $x) {
            $kind = ($x[1] ?? '') === '' ? 'enrollment' : strtolower($x[1]);
            $id = (int) $x[2];
            $status = $x[3];
            $token = $kind.':'.$id;

            if (isset($seen[$token])) {
                continue;
            }
            $seen[$token] = true;
            $claims[] = ['line' => $lineNo, 'kind' => $kind, 'id' => $id, 'status' => $status, 'student' => $rowStudent];
        }
    }

    // `clearance slip 27 approved and receipted` — the status is prose, not backticked
    if (preg_match_all('/slip (\d{1,4}) (approved|pending|rejected|waived|incomplete)\b/i', $line, $sl, PREG_SET_ORDER)) {
        foreach ($sl as $x) {
            $token = 'slip:'.$x[1];
            if (isset($seen[$token])) {
                continue;
            }
            $seen[$token] = true;
            $claims[] = ['line' => $lineNo, 'kind' => 'slip', 'id' => (int) $x[1], 'status' => strtolower($x[2]), 'student' => $rowStudent];
        }
    }
}

printf("Claims pulled from %s against database %s\n\n", basename($doc), $database);

$misses = 0;
$checked = 0;

foreach ($claims as $c) {
    $spec = $tables[$c['kind']];
    $row = DB::table($spec['table'])->where($spec['key'], $c['id'])->first();

    if ($row === null) {
        printf("  MISS  line %-4d %s %d: no such row in %s\n", $c['line'], $c['kind'], $c['id'], $spec['table']);
        $misses++;

        continue;
    }

    $actual = $row->{$spec['status']} ?? null;
    $ok = is_string($actual) && strcasecmp($actual, $c['status']) === 0;
    $note = '';

    // A row that names a student must belong to that student — the wrong id with the right status is the
    // failure mode that still reads as a pass.
    if ($ok && $c['student'] !== null && property_exists($row, 'studentId')) {
        $schoolId = DB::table('students')->where('studentId', $row->studentId)->value('schoolIdNumber');
        if ($schoolId !== $c['student']) {
            $ok = false;
            $note = " — the row belongs to ".($schoolId ?? 'no student').", not ".$c['student'];
        }
    }

    if (! $ok) {
        printf("  MISS  line %-4d %s %d: document says `%s`, the table reads `%s`%s\n", $c['line'], $c['kind'], $c['id'], $c['status'], (string) $actual, $note);
        $misses++;
    } else {
        printf("  ok    line %-4d %s %d `%s`\n", $c['line'], $c['kind'], $c['id'], $c['status']);
    }
    $checked++;
}

// The "Sections already open" line: block id, its name, and how many students the document claims are seated.
// The line is prose with three shapes in it — `(**3 students** / 40 — … 6 class meetings)`, `(1/40 — … 6)` and
// `(0/40, 6)` — so each block's parentheses are taken as a unit and the numbers are read out of that.
foreach ($lines as $no => $line) {
    if (! str_contains($line, 'Sections already open')) {
        continue;
    }

    // The sentence wraps across four lines, so the block of text searched is this line plus the four after it.
    $text = implode('', array_slice($lines, $no, 5));
    if (! preg_match_all('/block (\d{1,4}) `([^`]+)`(?: year (\d+))?[^(]*\(([^)]*)\)/', $text, $bm, PREG_SET_ORDER)) {
        echo "\n  MISS  the walkthrough's block line yielded no `block N \`Name\` (seats/capacity)` claims\n";
        $misses++;

        break;
    }

    echo "\n  block seats, read from the walkthrough's own line:\n";
    foreach ($bm as $b) {
        $blockId = (int) $b[1];
        $inside = preg_replace('/\s+/', ' ', $b[4]);
        if (! preg_match('/(\d+)(?:\s*\*\*)?\s*students?.*?\/\s*(\d+)/s', $inside, $n)
            && ! preg_match('/(\d+)\s*\/\s*(\d+)/', $inside, $n)) {
            printf("  MISS  block %d: no `N/M` seat count could be read from (%s)\n", $blockId, substr($inside, 0, 40));
            $misses++;

            continue;
        }
        $claimedSeats = (int) $n[1];
        $claimedCapacity = (int) $n[2];

        $block = DB::table('blocks')->where('blockId', $blockId)->first();
        if ($block === null) {
            printf("  MISS  block %d `%s`: no such row in blocks\n", $blockId, $b[2]);
            $misses++;
            $checked++;

            continue;
        }

        $seated = DB::table('enrolledsubjects')->where('blockId', $blockId)->distinct()->count('enrollmentId');
        $meetings = DB::table('schedulemeetings as sm')
            ->join('schedules as sc', 'sc.scheduleId', '=', 'sm.scheduleId')
            ->where('sc.blockId', $blockId)
            ->count();

        $seatOk = $seated === $claimedSeats;
        $capOk = (int) $block->maxStudents === $claimedCapacity;
        $nameOk = $block->blockName === $b[2];

        printf(
            "  %s block %d: document says %d seated / %d capacity; the table holds %d / %d and %d timetable meetings%s\n",
            $seatOk && $capOk && $nameOk ? 'ok  ' : 'MISS',
            $blockId,
            $claimedSeats,
            $claimedCapacity,
            $seated,
            (int) $block->maxStudents,
            $meetings,
            $nameOk ? '' : " — the block is named `{$block->blockName}`, not `{$b[2]}`"
        );
        if (! ($seatOk && $capOk && $nameOk)) {
            $misses++;
        }
        $checked++;
    }

    break;
}

printf("\n  %d claim(s) checked, %d mismatch(es).\n", $checked, $misses);
printf("  A mismatch is the document or the database disagreeing; neither is edited here.\n");

exit($misses === 0 ? 0 : 1);
