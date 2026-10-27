<?php

/**
 * Reverts the two schedules `ems:import:timetable --commit` published into live `ems` on 2026-10-27.
 *
 * The write was an accident: the run was labelled a preview and carried `--commit`. These are rows this
 * session created seconds earlier, and they are removed through the models rather than with SQL so the
 * audit log records both halves of what happened — the observer writes a row for the delete as it did
 * for the create.
 *
 * It refuses rather than deleting when a named id is not one of the four rows the commit made, so a
 * stale copy of this script cannot remove a schedule somebody else built.
 *
 * Before running it: C:\Users\ADMIN\ems-backups\ems-pre-g5-accidental-commit-2026-10-27.sql
 * Usage: php storage/app/scratch/lane79-revert-g5-commit.php [--apply]
 */

require __DIR__.'/../../../vendor/autoload.php';

use App\Models\Schedulemeetings;
use App\Models\Schedules;
// Imported above the bootstrap on purpose: PHP resolves an unqualified name at compile time, in
// statement order, so an alias filed below its first use is a fatal "Class not found".
use Illuminate\Contracts\Console\Kernel;

$app = require_once __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$apply = in_array('--apply', $argv, true);

// Exactly what the commit created, read back from the database rather than recalled.
$scheduleIds = [325, 326];
$meetingIds = [1502, 1503];

$refuse = [];

foreach ($scheduleIds as $id) {
    $schedule = Schedules::find($id);

    if ($schedule === null) {
        $refuse[] = "schedule {$id} is not on file — this script is keyed to a layout that has moved";

        continue;
    }

    // The fingerprint of the accidental rows: block 53 (BSCrim 1-A), instr.salazar, Room 102, Friday.
    if ((int) $schedule->blockId !== 53 || (int) $schedule->instructorId !== 3371 || (int) $schedule->roomId !== 2) {
        $refuse[] = "schedule {$id} is block {$schedule->blockId} / instructor {$schedule->instructorId} / room {$schedule->roomId},"
            .' which is not the row the commit made — refusing to delete it';
    }
}

foreach ($meetingIds as $id) {
    $meeting = Schedulemeetings::find($id);

    if ($meeting === null) {
        $refuse[] = "meeting {$id} is not on file";

        continue;
    }

    if (! in_array((int) $meeting->scheduleId, $scheduleIds, true) || $meeting->dayOfWeek->value !== 'Friday') {
        $refuse[] = "meeting {$id} belongs to schedule {$meeting->scheduleId} on {$meeting->dayOfWeek->value}, not to this commit";
    }
}

$before = [
    'schedules' => Schedules::count(),
    'schedulemeetings' => Schedulemeetings::count(),
];

echo 'before: schedules='.$before['schedules'].' meetings='.$before['schedulemeetings']."\n";

if ($refuse !== []) {
    foreach ($refuse as $r) {
        fwrite(STDERR, "REFUSED: {$r}\n");
    }

    exit(1);
}

if (! $apply) {
    echo "DRY RUN — would delete schedules ".implode(', ', $scheduleIds).' and meetings '.implode(', ', $meetingIds).".\n";
    echo "Pass --apply to remove them.\n";
    exit(0);
}

foreach ($meetingIds as $id) {
    Schedulemeetings::find($id)->delete();
    echo "deleted meeting {$id}\n";
}

foreach ($scheduleIds as $id) {
    Schedules::find($id)->delete();
    echo "deleted schedule {$id}\n";
}

$after = [
    'schedules' => Schedules::count(),
    'schedulemeetings' => Schedulemeetings::count(),
];

echo 'after: schedules='.$after['schedules'].' meetings='.$after['schedulemeetings']."\n";

if ($after['schedules'] !== $before['schedules'] - 2 || $after['schedulemeetings'] !== $before['schedulemeetings'] - 2) {
    fwrite(STDERR, "REFUSED: the counts did not fall by exactly two each — read the table before trusting this run.\n");

    exit(1);
}

echo "reverted: the two schedules and two meetings this session wrote are gone, and auditlogs carries both the create and the delete.\n";
