<?php

namespace App\Support;

use App\Models\Subjects;
use Illuminate\Support\Facades\DB;

/**
 * The two load ceilings the school has never stated a number for.
 *
 * Prompt §7.3 asks that a student cannot be loaded beyond a maximum study load, and §7.5 that a faculty member's
 * teaching load be bounded; both figures belong to the school, and the decision sheet still lists them as open
 * (items 10 and 11). Ruled 2026-11-03: the mechanism is built now and reads its number from the `settings` table, so
 * the day the Registrar types a figure on Admin → User Management → Settings the rule is live without a deploy — and
 * while the row is blank nothing is refused, because an invented ceiling would be the school's decision made for it.
 *
 * `SettingsServiceProvider` hydrates `config('settings.*')` from that table at boot, which is the same bridge every
 * printed letterhead already reads; a value entered on the screen therefore binds the next request, not the one that
 * saved it. Zero and a non-number mean the same thing as blank: no rule. A ceiling of zero units would not mean
 * "nobody may study", it would mean the field had not been filled in yet — and the difference matters, because a
 * rule that fires on its own placeholder refuses every proposal in the school.
 */
final class LoadLimits
{
    public const STUDENT_KEY = 'maxStudyLoadUnits';

    public const INSTRUCTOR_KEY = 'maxTeachingLoadUnits';

    /** The ceiling in units, or null when the school has not set one. */
    public static function studentCeiling(): ?float
    {
        return self::ceiling(self::STUDENT_KEY);
    }

    public static function instructorCeiling(): ?float
    {
        return self::ceiling(self::INSTRUCTOR_KEY);
    }

    private static function ceiling(string $key): ?float
    {
        $raw = config("settings.{$key}");
        if (! is_string($raw) && ! is_numeric($raw)) {
            return null;
        }

        $trimmed = trim((string) $raw);
        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return null;
        }

        $value = (float) $trimmed;

        return $value > 0 ? round($value, 2) : null;
    }

    /**
     * The units a set of subjects carries: a subject's own lecture plus laboratory units, summed once per subject.
     *
     * Ids that name no subject contribute nothing rather than throwing — the caller has already validated the ids
     * against `subjects`, and a ceiling check that 500s on a deleted subject would replace a clear refusal with a
     * crash the desk cannot read.
     *
     * @param  iterable<int|string>  $subjectIds
     */
    public static function unitsFor(iterable $subjectIds): float
    {
        $ids = array_values(array_unique(array_map('intval', iterator_to_array($subjectIds, false))));
        if ($ids === []) {
            return 0.0;
        }

        $sum = Subjects::query()
            ->whereIn('subjectId', $ids)
            ->selectRaw('COALESCE(SUM(lectureUnits + labUnits), 0) as units')
            ->value('units');

        return round((float) $sum, 2);
    }

    /**
     * Units already on the books for one instructor in one term.
     *
     * `schedules` carries no term: the term lives on the **block**, exactly as the collision rule discovered
     * (prompt §7.5, §28.3 G-55), so the scope reaches the term through `blocks.termId` rather than the schedule row.
     * A schedule being edited is excluded by id, or re-saving an existing row would count itself twice and the
     * ceiling would creep upward every time the desk touched it.
     */
    public static function instructorUnits(int $userId, int $termId, ?int $exceptScheduleId = null): float
    {
        $units = DB::table('schedules')
            ->join('blocks', 'blocks.blockId', '=', 'schedules.blockId')
            ->join('subjects', 'subjects.subjectId', '=', 'schedules.subjectId')
            ->where('schedules.instructorId', $userId)
            ->where('blocks.termId', $termId)
            ->when($exceptScheduleId !== null, fn ($q) => $q->where('schedules.scheduleId', '!=', $exceptScheduleId))
            ->selectRaw('COALESCE(SUM(subjects.lectureUnits + subjects.labUnits), 0) as units')
            ->value('units');

        return round((float) $units, 2);
    }

    /** Units a student already holds confirmed on one enrollment, beside the set being proposed. */
    public static function confirmedUnits(int $enrollmentId): float
    {
        // The column is `status` on this table — `enrollments` is the one that carries `enrollmentStatus`, and
        // asking a table for a column it has never held is an exception at the desk, not a refusal.
        $units = DB::table('enrolledsubjects')
            ->join('subjects', 'subjects.subjectId', '=', 'enrolledsubjects.subjectId')
            ->where('enrolledsubjects.enrollmentId', $enrollmentId)
            ->where('enrolledsubjects.status', 'confirmed')
            ->selectRaw('COALESCE(SUM(subjects.lectureUnits + subjects.labUnits), 0) as units')
            ->value('units');

        return round((float) $units, 2);
    }
}
