<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pin the catalog version on the enrollments that never had one (item 7).
 *
 * `enrollments.curriculumId` was added so a student is evaluated, priced and printed
 * against the curriculum their record was created under, and the column is read first by
 * every lookup — but nothing ever wrote it, so on every install in use the whole table
 * falls through to "the newest catalog". With a single `effectiveYear` across the catalog
 * that is not a rule, it is whatever the database returns first, and a later amendment to
 * a program's subjects would silently re-decide which version a student two years past
 * graduation was graded against.
 *
 * Rows already carrying a version are left alone. The same ordering the desks now use at
 * creation is applied here — newest effective year for the program, narrowed by the
 * student's major where the curriculum carries one, falling back to the program-only
 * version when it does not — restated rather than calling the model, because a migration
 * must keep meaning what it meant the day it ran even after the application changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // chunkById rather than chunk: the filter is `whereNull`, so rows drop out of the
        // result set as they are pinned, and an offset-based page would skip its own work.
        DB::table('enrollments')
            ->whereNull('curriculumId')
            ->orderBy('enrollmentId')
            ->chunkById(500, function (Collection $enrollments) {
                foreach ($enrollments as $enrollment) {
                    $version = $this->newestCurriculum((int) $enrollment->courseId, $enrollment->majorId);

                    // A program with no catalog row at all stays null rather than being
                    // pinned to some other program's version.
                    if ($version !== null) {
                        DB::table('enrollments')->where('enrollmentId', $enrollment->enrollmentId)->update([
                            'curriculumId' => $version,
                        ]);
                    }
                }
            }, 'enrollmentId');
    }

    /**
     * Not reversible, deliberately. This writes a decision each record had never carried;
     * rolling it back would blank the column again and destroy any version pinned on purpose
     * since — the pin is worth more to the record than its own undo is.
     */
    public function down(): void
    {
        // No operation: see above.
    }

    private function newestCurriculum(int $courseId, ?int $majorId): ?int
    {
        $lookup = fn (?int $major): ?int => DB::table('curriculums')
            ->where('courseId', $courseId)
            ->when($major, fn ($q) => $q->where('majorId', $major))
            ->orderByDesc('effectiveYear')
            ->orderByDesc('curriculumId')
            ->value('curriculumId');

        return $lookup($majorId) ?? $lookup(null);
    }
};
