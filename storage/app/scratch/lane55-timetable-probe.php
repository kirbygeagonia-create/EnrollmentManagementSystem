<?php

/**
 * Lane 55 — what the timetable actually refuses, and what live `ems` already holds.
 *
 * `BlockingController::detectConflicts()` compares meetings by day and overlapping time for two things: the
 * instructor and the room. Two properties of that query are worth measuring rather than reading. It has no term
 * condition, and cannot easily have one — `schedules` carries no term; the term lives on the block — so a
 * meeting from a retired term is still a conflict for the term a desk is building now. And its exclusion clause
 * is `meetingId != $meeting->meetingId`, which on the create path refers to a meeting that has not been
 * inserted yet: in MySQL `meetingId <> NULL` is NULL, not true, so a query whose only job is to skip the row
 * being edited may match nothing at all.
 *
 * Neither is checked here. This probe writes nothing and answers the four questions a desk would notice:
 * who shares a room at the same hour, who shares an instructor, whether the pair is inside one term or across
 * two, and whether any room holds more students than it has seats.
 *
 * Usage: php storage/app/scratch/lane55-timetable-probe.php
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$database = (string) DB::connection()->getDatabaseName();
if ($database !== 'ems') {
    fwrite(STDERR, "REFUSE: this probe reads live `ems` and is connected to '{$database}'.\n");
    exit(1);
}

$rows = DB::table('schedulemeetings as m')
    ->join('schedules as s', 's.scheduleId', '=', 'm.scheduleId')
    ->join('blocks as b', 'b.blockId', '=', 's.blockId')
    ->join('rooms as r', 'r.roomId', '=', 's.roomId')
    ->join('subjects as sub', 'sub.subjectId', '=', 's.subjectId')
    ->leftJoin('staffusers as st', 'st.userId', '=', 's.instructorId')
    ->orderBy('b.termId')->orderBy('m.dayOfWeek')->orderBy('m.startTime')
    ->get([
        'm.meetingId', 'm.scheduleId', 'm.dayOfWeek', 'm.startTime', 'm.endTime',
        's.blockId', 's.subjectId', 's.instructorId', 's.roomId',
        'b.blockName', 'b.termId', 'b.maxStudents', 'b.scheduleStatus',
        'r.roomName', 'r.capacity', 'sub.subjectCode',
        DB::raw("CONCAT(COALESCE(st.firstName,'') ,' ', COALESCE(st.lastName,'')) AS instructor"),
    ]);

printf("Connected to `%s`: %d meeting row(s) across %d schedule(s).\n\n", $database, $rows->count(), $rows->pluck('scheduleId')->unique()->count());

if ($rows->isEmpty()) {
    fwrite(STDERR, "NO CONCLUSION: this database holds no meetings, so every count below would read 0 by absence.\n");
    exit(1);
}

/** Half-open overlap: [aStart, aEnd) intersecting [bStart, bEnd). */
$overlaps = fn (string $aS, string $aE, string $bS, string $bE): bool => $aS < $bE && $aE > $bS;

$pair = function (string $field, bool $sameTermOnly) use ($rows, $overlaps): array {
    $out = [];
    $list = $rows->all();
    for ($i = 0; $i < count($list); $i++) {
        for ($j = $i + 1; $j < count($list); $j++) {
            $x = $list[$i];
            $y = $list[$j];
            if ($x->$field != $y->$field) {
                continue;
            }
            if ($x->scheduleId === $y->scheduleId) {
                continue;
            }
            if ($x->dayOfWeek !== $y->dayOfWeek) {
                continue;
            }
            if (! $overlaps($x->startTime, $x->endTime, $y->startTime, $y->endTime)) {
                continue;
            }
            if ($sameTermOnly && (int) $x->termId !== (int) $y->termId) {
                continue;
            }
            if (! $sameTermOnly && (int) $x->termId === (int) $y->termId) {
                continue;
            }
            $out[] = [$x, $y];
        }
    }

    return $out;
};

$describe = function (object $x, object $y, string $field): string {
    $shared = $field === 'roomId' ? "{$x->roomName} ({$x->roomName})" : (string) ($x->instructor ?: "user {$x->instructorId}");
    if ($field === 'roomId') {
        $shared = $x->roomName;
    }

    return sprintf(
        "     %s: %s — %s %s %s–%s (schedule %d, term %d)  ✕  %s %s %s–%s (schedule %d, term %d)",
        $field === 'roomId' ? 'room' : 'instructor',
        $shared,
        $x->blockName, $x->subjectCode, $x->dayOfWeek, $x->startTime, $x->endTime, $x->scheduleId, $x->termId,
        $y->blockName, $y->subjectCode, $y->dayOfWeek, $y->startTime, $y->endTime, $y->scheduleId, $y->termId
    );
};

foreach ([['roomId', true], ['roomId', false], ['instructorId', true], ['instructorId', false]] as $k => [$field, $sameTerm]) {
    $hits = $pair($field, $sameTerm);
    printf(
        "%d. %s overlap %s: %d\n",
        $k + 1,
        $field === 'roomId' ? 'Room' : 'Instructor',
        $sameTerm ? 'INSIDE one term (the state the guard exists to refuse)' : 'ACROSS terms (a retired term still blocks a save)',
        count($hits)
    );
    foreach ($hits as [$x, $y]) {
        echo $describe($x, $y, $field), "\n";
    }
    echo "\n";
}

// 3 — one block in two places at once: nothing in detectConflicts() asks about the block at all.
$blockHits = [];
foreach ($rows as $x) {
    foreach ($rows as $y) {
        if ($y->meetingId <= $x->meetingId || $y->blockId !== $x->blockId || $y->dayOfWeek !== $x->dayOfWeek) {
            continue;
        }
        if ($overlaps($x->startTime, $x->endTime, $y->startTime, $y->endTime)) {
            $blockHits[] = [$x, $y];
        }
    }
}
echo "3. Same block, two schedules, overlapping hours (no check asks this question):\n";
foreach ($blockHits as [$x, $y]) {
    printf(
        "     %s: %s %s–%s in %s (schedule %d)  ✕  %s %s–%s in %s (schedule %d)\n",
        $x->blockName, $x->subjectCode, $x->startTime, $x->endTime, $x->roomName, $x->scheduleId,
        $y->subjectCode, $y->startTime, $y->endTime, $y->roomName, $y->scheduleId
    );
}
printf("   %d pair(s)\n\n", count($blockHits));

// 4 — seats: a block larger than the room it is scheduled into, or larger than its own maxStudents.
//     The block's people come through `enrolledsubjects`, because `enrollments` carries no block column.
$seats = DB::table('blocks as b')
    ->leftJoin('enrolledsubjects as es', 'es.blockId', '=', 'b.blockId')
    ->groupBy('b.blockId', 'b.blockName', 'b.termId', 'b.maxStudents')
    ->get(['b.blockId', 'b.blockName', 'b.termId', 'b.maxStudents', DB::raw('COUNT(DISTINCT es.enrollmentId) enrolled')]);

$roomsByBlock = [];
foreach ($rows as $r) {
    $roomsByBlock[$r->blockId][$r->roomId] = (int) $r->capacity;
}

echo "4. Seats against the room and against the block's own cap:\n";
$seatProblems = 0;
foreach ($seats as $b) {
    $smallest = null;
    foreach ($roomsByBlock[$b->blockId] ?? [] as $cap) {
        $smallest = $smallest === null ? $cap : min($smallest, $cap);
    }
    $overRoom = $smallest !== null && (int) $b->enrolled > $smallest;
    $overCap = (int) $b->enrolled > (int) $b->maxStudents;
    if (! $overRoom && ! $overCap) {
        continue;
    }
    $seatProblems++;
    printf(
        "     %s (term %d): %d enrolled%s%s\n",
        $b->blockName, $b->termId, (int) $b->enrolled,
        $overRoom ? ", scheduled into rooms of {$smallest} seats" : '',
        $overCap ? ", block cap {$b->maxStudents}" : ''
    );
}
printf("   %d block(s) over their seats\n\n", $seatProblems);

// 5 — how many of these meetings sit on a term the desks are not using.
$byTerm = $rows->groupBy('termId')->map(fn ($g) => $g->count());
echo "5. Meetings by the term their block belongs to (the conflict query never filters on this):\n";
foreach ($byTerm as $term => $count) {
    $name = DB::table('academicterms')->where('termId', $term)->value('termName') ?? 'term ?';
    printf("     term %d (%s): %d meeting(s)\n", $term, $name, $count);
}

echo "\nA row is listed under (1) or (2) only when two different schedules put the same room or the same officer\n";
echo "at one hour. Cross-term pairs are shown separately, because that is the shape the guard cannot tell apart.\n";

echo "
ALL SECTIONS ANSWERED — seven of them, on ".$database.", and this probe wrote nothing.
";
