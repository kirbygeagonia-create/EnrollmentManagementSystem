<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Return the active-seat key, now that the fixtures write the state the product allows.
 *
 * `2026_11_01_1600000000_withdraw_the_active_seat_key_until_the_fixtures_are_repaired.php` took this index out on
 * 2026-11-01 because CI's MySQL 8 job — the only engine that could see it at all — refused six test files that seat
 * one student twice in one term. Those six are repaired, each in the way its own case claims rather than the way a
 * constraint wants:
 *
 *  - `RetakeAttemptTest` and `ElectiveValidationTest` seat the second record in a LATER term, which is what their
 *    comments already said ("simulating retake in a later term", a Year-2 enrollment); attempt numbering is a rule
 *    about prior `enrolledsubjects` rows and reads no term, so the assertions are untouched.
 *  - `AcademicStandingOwnershipTest` and `QueueStandingColumnsTest` make the earlier row DROPPED. Both read a pair
 *    where the newest write wins, and one retired row beneath one active row is the only such pair the key permits —
 *    `standingMapFor()` applies no status filter, and `gradedPriorSubjects()` excludes by term, not by status.
 *  - `ShiftRequestFlowTest` and `EnrollmentDropTest` release the first seat through an ACT (a `->update()` and the
 *    `registrar.drop` route). Those two carry a `seat-released-by-act:` marker in the method, which is what
 *    `lane187-census-fixture-seats.php` reads: an act is invisible to a static pass, and an exemption that lives
 *    only in a person's head is the defect this repository keeps meeting, so it is written where an instrument can
 *    see it and the harness proves the marker is honoured by deleting it and demanding the row back.
 *  - `ClearancePassSlipConfirmationTest` and `ClearanceWindowGateTest` point each refusal at its OWN student through
 *    `anotherStudent()`, because those cases are about records the desk posts against, not about one person seated
 *    three times.
 *
 * Measured, not assumed: `lane187-census-fixture-seats.php` reads 503 create sites and reports 0 pairs holding two
 * active seats (4 pairs remain, all released by a dropped row or a marked act), and its falsifier
 * `mutate-lane187-provenance.php` answers every provenance class it claims. The 10 sites it still cannot prove
 * arrive through an array-return destructure; the census REFUSES on those rather than clearing them, which is why
 * the constraint's real gate is this file's rehearsal on the school's engine and not the static pass.
 *
 * Why VIRTUAL and not STORED, from the same batch's measurement: `ALTER TABLE enrollments ADD COLUMN … STORED`
 * answers `SQLSTATE[HY000] 1215 Cannot add foreign key constraint` even though no foreign key is being added — the
 * message names a foreign key because a STORED change rebuilds the table, and `enrollments` is referenced by eleven
 * child tables (read from `information_schema.KEY_COLUMN_USAGE`). VIRTUAL adds in place, takes the unique index, and
 * keeps the behaviour that makes the rule work: `seatKey` is non-null exactly when the row is not dropped and names
 * a term, MySQL ignores the NULL, so a drop releases the seat with no trigger and no deleted history — ruling 17's
 * promise that a Registrar may re-enroll the student they dropped in the same term.
 *
 * The duplicate guard runs BEFORE the ALTER and names the rows, because a 1062 after the fact says only that some
 * duplicate exists. SQLite gets nothing: it compiles no `ADD COLUMN … GENERATED`, so on the suite's driver this rule
 * is held by `EnrollmentIssuer::seatHolder()` and by the audit, exactly as it was before this index existed — and the
 * rehearsal that proves THIS migration is a scratch MySQL schema with `DB_CONNECTION` pinned, never a local green run.
 */
return new class extends Migration
{
    private const INDEX = 'uq_enrollments_active_seat';

    private const COLUMN = 'seatKey';

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if ($this->indexExists(self::INDEX)) {
            return;
        }

        $dupes = DB::select('select studentId, termId, count(*) c from enrollments'
            ." where enrollmentStatus <> 'dropped' and termId is not null"
            .' group by studentId, termId having count(*) > 1');

        if ($dupes !== []) {
            $pairs = implode(', ', array_map(
                fn ($r): string => 'student '.$r->studentId.' in term '.$r->termId.' ('.$r->c.' active rows)',
                $dupes
            ));

            throw new RuntimeException('Refusing to add '.self::INDEX.' over data that already violates it: '
                .$pairs.'. A re-enrollment in the same term must drop the prior seat first — that is ruling 17, and '
                .'a writer that skips it is the defect the key exists to catch, not data to migrate around.');
        }

        if (! $this->columnExists(self::COLUMN)) {
            DB::statement('ALTER TABLE `enrollments` ADD COLUMN `'.self::COLUMN.'` VARCHAR(32) GENERATED ALWAYS AS ('
                ."CASE WHEN `enrollmentStatus` <> 'dropped' AND `termId` IS NOT NULL"
                .' THEN CONCAT(`studentId`, \':\', `termId`) ELSE NULL END) VIRTUAL');
        }

        try {
            DB::statement('ALTER TABLE `enrollments` ADD UNIQUE KEY `'.self::INDEX.'`'
                .' (`studentId`, `termId`, `'.self::COLUMN.'`)');
        } catch (Throwable $e) {
            // The column is real to information_schema, so a half-applied migration would leave the table carrying a
            // generated column no other migration owns. Take it back before the exception reaches the desk.
            if ($this->columnExists(self::COLUMN)) {
                DB::statement('ALTER TABLE `enrollments` DROP COLUMN `'.self::COLUMN.'`');
            }

            throw $e;
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if ($this->indexExists(self::INDEX)) {
            DB::statement('ALTER TABLE `enrollments` DROP INDEX `'.self::INDEX.'`');
        }

        if ($this->columnExists(self::COLUMN)) {
            DB::statement('ALTER TABLE `enrollments` DROP COLUMN `'.self::COLUMN.'`');
        }
    }

    private function indexExists(string $name): bool
    {
        return (int) DB::selectOne(
            'SELECT COUNT(*) c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE()'
            .' AND TABLE_NAME = ? AND INDEX_NAME = ?',
            ['enrollments', $name]
        )->c > 0;
    }

    private function columnExists(string $name): bool
    {
        return (int) DB::selectOne(
            'SELECT COUNT(*) c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()'
            .' AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['enrollments', $name]
        )->c > 0;
    }
};
