<?php

namespace App\Support;

use App\Models\Schedulemeetings;
use App\Models\Schedules;
use Illuminate\Support\Collection;

/**
 * The three questions Blocking asks before a timetable line is published: is the officer free, is
 * the room free, and is *this block* free. The third is the one a desk form cannot show —
 * `storeSchedule` is addressed to a block, so adding a second subject to a group that already meets
 * at that hour is the ordinary action rather than an edge case, and only the block branch sees it.
 *
 * This lives outside `BlockingController` because a second surface now asks the same questions:
 * `ems:import:timetable` must name a collision before publication, and a preview that disagrees with
 * the desk about what a collision is would be the worst kind of promise. Two copies of "these two
 * meetings overlap" is the defect this file exists to prevent, so the half-open rule is stated once
 * in `overlaps()` and pinned from both surfaces by `tests/Feature/Console/TimetableImportTest.php`:
 * one back-to-back pair must read as no collision whether it is a row of a sheet or a meeting on
 * file, and one overlapping pair must refuse on both.
 *
 * All three questions are scoped to the term the schedule's own block belongs to. `schedules` carries
 * no term — it lives on the block — so an unscoped query reads a meeting from a retired term as a
 * conflict for the term being built now, and rooms and officers are reused every year on purpose. A
 * schedule with no block has no term to scope by, and the query then widens rather than answering "no
 * conflict" from a filter it could not apply.
 *
 * The edited schedule is excluded by its own id when one exists: `updateSchedule` replaces its
 * meetings, so they must not be read against the proposal. There is deliberately no `meetingId`
 * clause here — on the create path the proposed meetings are unsaved models, and Laravel turns a null
 * comparison into `IS NOT NULL`, so that clause passes today by an accident of the query builder
 * rather than by intent.
 */
class TimetableConflicts
{
    /**
     * The proposed schedule read against the meetings already on file.
     *
     * @return Collection<int, string>
     */
    public static function againstDatabase(Schedules $schedule, ?int $exceptScheduleId = null): Collection
    {
        $conflicts = collect();
        $termId = $schedule->block?->termId;

        foreach ($schedule->meetings as $meeting) {
            $day = $meeting->dayOfWeek->value;

            // One builder, so the day, the overlap and both exclusions cannot drift between the three questions.
            //
            // Both bind values are padded to `HH:MM:SS` before they reach the comparison. MySQL answers a
            // TIME-vs-string comparison as two TIMES, so a bind spelled `09:30` and a row stored `09:30:00`
            // agree there — but the SQLite suite stores a `time` column as plain text, where `'09:30:00'`
            // sorts above `'09:30'` and every back-to-back pair would read as a collision on one driver
            // and not the other. Padding the bind makes the two spellings compare alike on both.
            $overlapping = fn () => Schedulemeetings::query()
                ->where('dayOfWeek', $day)
                ->where(function ($q) use ($meeting) {
                    $q->where('startTime', '<', self::toSeconds($meeting->endTime))
                        ->where('endTime', '>', self::toSeconds($meeting->startTime));
                })
                ->when($exceptScheduleId !== null, fn ($q) => $q->where('scheduleId', '!=', $exceptScheduleId))
                ->when($termId !== null, fn ($q) => $q->whereHas('schedule.block', fn ($b) => $b->where('termId', $termId)));

            $shared = fn (string $column, int|string $value): bool => $overlapping()
                ->whereHas('schedule', fn ($q) => $q->where($column, $value))
                ->exists();

            // Instructor conflict.
            if ($shared('instructorId', $schedule->instructorId)) {
                $conflicts->push(self::message('Instructor', $day, $meeting->startTime, $meeting->endTime));
            }

            // Room conflict.
            if ($shared('roomId', $schedule->roomId)) {
                $conflicts->push(self::message('Room', $day, $meeting->startTime, $meeting->endTime));
            }

            // The block's own hour: one group cannot be in two rooms at once.
            if ($shared('blockId', $schedule->blockId)) {
                $conflicts->push(self::message('Block', $day, $meeting->startTime, $meeting->endTime).' — this block already meets at that hour');
            }
        }

        return $conflicts;
    }

    /**
     * The lines of one sheet read against each other. These rows are not in the database yet, so
     * `againstDatabase()` cannot see them: a sheet that double-books a room between its own row 3 and
     * its own row 7 would publish, and the collision would only surface on the next desk edit.
     *
     * Every pair is compared, including two rows that resolve to the same block, subject, instructor
     * and room — such a pair collapses into one schedule carrying two meetings at one hour, which is
     * self-contradictory data and is refused here rather than written.
     *
     * @param  array<int, array{row: int, blockId: int, instructorId: int, roomId: int, dayOfWeek: string, startTime: string, endTime: string}>  $lines
     * @return Collection<int, string>
     */
    public static function withinSheet(array $lines): Collection
    {
        $conflicts = collect();
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $lines[$i];
                $b = $lines[$j];

                if (! self::overlaps($a['dayOfWeek'], $a['startTime'], $a['endTime'], $b['dayOfWeek'], $b['startTime'], $b['endTime'])) {
                    continue;
                }

                $pair = " between rows {$a['row']} and {$b['row']} of this file";

                foreach ([
                    ['Instructor', 'instructorId'],
                    ['Room', 'roomId'],
                    ['Block', 'blockId'],
                ] as [$label, $key]) {
                    if ($a[$key] === $b[$key]) {
                        $sentence = self::message($label, $a['dayOfWeek'], $a['startTime'], $a['endTime']).$pair;
                        $conflicts->push($label === 'Block' ? $sentence.' — one group cannot be in two places at once' : $sentence);
                    }
                }
            }
        }

        return $conflicts;
    }

    /**
     * The half-open overlap rule: same day, and neither meeting begins at or after the other ends. A
     * meeting that starts exactly when another ends is back-to-back and is not a collision — the same
     * boundary `ems:audit:record-states` judges, so the two surfaces cannot disagree about a timetable
     * the desks have already been told is clean.
     *
     * Both sides are padded to `HH:MM:SS` first. Byte order is clock order only when every value has
     * the same shape, and this table holds both spellings: a desk form writes `09:30` (the raw `<input
     * type="time">` value, which MySQL widens to `09:30:00` on the way in and SQLite does not), while a
     * seeder writes `09:30:00`. Un-padded, `'09:30:00' > '09:30'` is true and the suite would report a
     * collision the server does not see. A `date`/`datetime` cast on these columns is deliberately
     * absent — see the note in `App\Models\Schedulemeetings` — so the strings stay raw for both.
     */
    public static function overlaps(string $aDay, string $aStart, string $aEnd, string $bDay, string $bStart, string $bEnd): bool
    {
        if ($aDay !== $bDay) {
            return false;
        }

        return self::toSeconds($aStart) < self::toSeconds($bEnd)
            && self::toSeconds($bStart) < self::toSeconds($aEnd);
    }

    /**
     * `9:05`, `09:05` and `09:05:00` all become `09:05:00`, so a comparison between any two of them is
     * a comparison between the same shape. Values that are not a clock time never reach here: the
     * importer refuses them before the conflict pass runs, and the desk's own validation is `date_format:H:i`.
     */
    private static function toSeconds(string $clock): string
    {
        $parts = array_map('intval', array_pad(explode(':', trim($clock)), 3, 0));

        return sprintf('%02d:%02d:%02d', $parts[0], $parts[1], $parts[2]);
    }

    /**
     * One sentence shape for both surfaces, so a reader comparing a desk refusal with a sheet refusal
     * is reading the same words. Seconds are dropped: the desk writes what the form gave it, and a
     * message saying `08:00:00-09:30:00` beside one saying `08:00-09:30` would look like two rules.
     */
    private static function message(string $label, string $day, string $start, string $end): string
    {
        $clock = fn (string $t): string => substr($t, 0, 5);

        return "{$label} conflict on {$day} ".$clock($start).'-'.$clock($end);
    }
}
