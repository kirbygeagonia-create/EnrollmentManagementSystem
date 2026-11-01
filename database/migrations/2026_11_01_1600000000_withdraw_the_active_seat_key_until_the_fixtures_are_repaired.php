<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Withdraw the active-seat unique key, keep the fee-sheet one, and say why in the schema's own history.
 *
 * `uq_enrollments_active_seat` was correct as a statement of ruling 3 — one active enrollment per student per term —
 * and the server enforced it exactly as designed: on CI's MySQL 8 job it refused six test files that create a second
 * ACTIVE enrollment for one student in one term (`RetakeAttemptTest`'s attempt-numbering chain, `ElectiveValidationTest`,
 * `AcademicStandingOwnershipTest`, `ShiftRequestFlowTest`, `ClearanceWindowGateTest`, `EnrollmentDropTest`), and
 * `RetakeAttemptTest`'s own comment says the second record simulates "a retake in a later term" while its helper hands
 * both rows the SAME term. So the constraint found a real divergence between the rule and the repository's fixtures;
 * it did not find bad data (live `ems` answers 0 duplicate groups, measured twice).
 *
 * The index is dropped here rather than edited out of the earlier migration, because that migration has already run on
 * the school's database and on this box — history that never happened is the worse defect. The fee-sheet key from the
 * same batch stays: it refused nothing, because no path writes a second sheet for one enrollment.
 *
 * Re-adding the seat key is a real task with a real order of work: repair the six fixtures so a re-enrollment names a
 * later term or drops the prior seat first (which is what the product does), rehearse the whole suite against a
 * scratch MySQL schema with `DB_CONNECTION` pinned — the SQLite suite CANNOT see this constraint, since it compiles no
 * generated column, so local green proves nothing about it — then restore the index. Until then the seat rule is
 * enforced by `EnrollmentIssuer::seatHolder()`, which every desk path calls, and by nothing below it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->indexExists('uq_enrollments_active_seat')) {
            DB::statement('ALTER TABLE `enrollments` DROP INDEX `uq_enrollments_active_seat`');
        }
        if ($this->columnExists('seatKey')) {
            DB::statement('ALTER TABLE `enrollments` DROP COLUMN `seatKey`');
        }
    }

    public function down(): void
    {
        if (! $this->columnExists('seatKey')) {
            DB::statement('ALTER TABLE `enrollments` ADD COLUMN `seatKey` VARCHAR(32) GENERATED ALWAYS AS ('
                ."CASE WHEN `enrollmentStatus` <> 'dropped' AND `termId` IS NOT NULL"
                ." THEN CONCAT(`studentId`, ':', `termId`) ELSE NULL END) VIRTUAL");
        }
        if (! $this->indexExists('uq_enrollments_active_seat')) {
            DB::statement('ALTER TABLE `enrollments` ADD UNIQUE KEY `uq_enrollments_active_seat`'
                .' (`studentId`, `termId`, `seatKey`)');
        }
    }

    private function indexExists(string $name): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            // The SQLite suite never built either object, so on that driver the honest answer is "not there".
            return false;
        }

        return (int) DB::selectOne(
            'SELECT COUNT(*) c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()'
            .' AND TABLE_NAME = ? AND INDEX_NAME = ?',
            ['enrollments', $name]
        )->c > 0;
    }

    private function columnExists(string $name): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        return (int) DB::selectOne(
            'SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()'
            .' AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['enrollments', $name]
        )->c > 0;
    }
};
