<?php

namespace App\Support;

use App\Models\Staffusers;
use App\Models\Students;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The college an Academic Department desk answers to, and the one query rule that follows from it.
 *
 * Ruled 2026-10-13: "per college department, each college department must receive every student or
 * applicant related to their college or the course on their department." Two halves, and the second is
 * what makes this a rule rather than a filter — the desk must see ALL of its own college's records, and
 * nothing of another's.
 *
 * An account with no `unitId` is bounded by nothing. That is deliberate: the Registrar, Accounting,
 * Guidance and the clinic have no college, and so does `office4_head`, which on the installed database
 * still holds 29 enrollments — 15 of them in progress — in three different colleges. Bounding that
 * account would strand those papers with no desk able to close them, which is why the boundary is
 * expressed as "a college desk sees its college" rather than "every desk sees one college".
 *
 * A record whose course names no college is not the desk's either. `unitId` is required on the course
 * screens (`ReferenceDataController.php:101` and `:126`), so this cannot be reached through a screen; the
 * conservative direction is chosen anyway, because a list that silently includes what the policy refuses
 * teaches the desk to expect work it cannot do.
 *
 * The `of*` methods take and return the same builder type so the caller's model keeps its identity: a
 * helper whose parameter is typed plainly as `Builder` turns `Students::query()->get()` into a collection
 * of `Model`, and every `->map(fn (Students $s))` downstream loses its types with it.
 */
final class CollegeScope
{
    /**
     * The college this account is bound to, or null when it belongs to no single college.
     */
    public static function unitOf(?Staffusers $user): ?int
    {
        if ($user === null) {
            return null;
        }

        return $user->unitId === null ? null : (int) $user->unitId;
    }

    /**
     * May this account act on a record that belongs to the college `$courseUnitId` names?
     */
    public static function holds(?Staffusers $user, ?int $courseUnitId): bool
    {
        $mine = self::unitOf($user);

        if ($mine === null) {
            return true;
        }

        return $courseUnitId !== null && $mine === $courseUnitId;
    }

    /**
     * Is this student related to the desk's college? A student row carries no course, so the answer comes
     * from the records that do: an enrollment, or an application for a student not yet enrolled.
     */
    public static function holdsStudent(?Staffusers $user, Students $student): bool
    {
        $mine = self::unitOf($user);

        if ($mine === null) {
            return true;
        }

        return $student->enrollments()
            ->whereHas('course', fn (Builder $c) => $c->where('unitId', $mine))
            ->exists()
            || $student->admissions()
                ->whereHas('course', fn (Builder $c) => $c->where('unitId', $mine))
                ->exists();
    }

    /**
     * Enrollments of the desk's own college. `course` is the only path from an enrollment to a college.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function ofEnrollments(Builder $query, ?Staffusers $user): Builder
    {
        $mine = self::unitOf($user);

        return $mine === null
            ? $query
            : $query->whereHas('course', fn (Builder $c) => $c->where('unitId', $mine));
    }

    /**
     * Applications to the desk's own college.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function ofAdmissions(Builder $query, ?Staffusers $user): Builder
    {
        $mine = self::unitOf($user);

        return $mine === null
            ? $query
            : $query->whereHas('course', fn (Builder $c) => $c->where('unitId', $mine));
    }

    /**
     * Students related to the desk's college — through an enrollment or through an application, because
     * `students` carries no course of its own and an applicant has not been enrolled yet.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function ofStudents(Builder $query, ?Staffusers $user): Builder
    {
        $mine = self::unitOf($user);

        return $mine === null
            ? $query
            : $query->where(fn (Builder $w) => $w
                ->whereHas('enrollments.course', fn (Builder $c) => $c->where('unitId', $mine))
                ->orWhereHas('admissions.course', fn (Builder $c) => $c->where('unitId', $mine)));
    }

    /**
     * Shift papers with the desk's college on either side of the move. Ruled the same day: the college a
     * student leaves endorses the paper and the college they arrive at receives it, so both must see it —
     * a one-sided rule would leave the receiving department blind to a student it is about to take on.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function ofShiftRequests(Builder $query, ?Staffusers $user): Builder
    {
        $mine = self::unitOf($user);

        return $mine === null
            ? $query
            : $query->where(fn (Builder $w) => $w
                ->whereHas('currentCourse', fn (Builder $c) => $c->where('unitId', $mine))
                ->orWhereHas('targetCourse', fn (Builder $c) => $c->where('unitId', $mine)));
    }

    /**
     * The programs this desk may enrol into: its own college's, or the whole catalog for a desk that
     * belongs to no college. The dropdown and the policy must answer from the same rule, or a desk is
     * offered a program the save then refuses.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function ofCourses(Builder $query, ?Staffusers $user): Builder
    {
        $mine = self::unitOf($user);

        return $mine === null ? $query : $query->where('unitId', $mine);
    }
}
