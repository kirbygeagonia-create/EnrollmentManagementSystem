<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two enrollment rules the Advanced Database Systems guide asks the database to hold rather than the php:
     * one fee sheet per enrollment (the review document's DI-2, open since 2026-09-15), and one active seat per
     * student per term (P03, enforced today only by `EnrollmentIssuer::seatHolder()`).
     *
     * Both are additive and both refuse to run over data that would violate them, so an install with existing
     * damage stops with the offending rows named instead of failing mid-ALTER with an errno nobody reads.
     *
     * The seat rule travels as a VIRTUAL generated column, because the guide's own scenario is "a drop releases
     * the seat": `seatKey` is non-null exactly when the row holds one, and MySQL ignores NULLs in a unique index,
     * so the same key that refuses a second active enrollment lets a dropped student re-enroll in that term
     * without a trigger and without a deleted row. The predicate is `enrollmentStatus <> 'dropped'`, which is
     * `seatHolder()`'s `whereNotIn(…, [Dropped])` read at `app/Services/EnrollmentIssuer.php:37-43` — one
     * definition of "holds a seat", stated twice because a generated column cannot call php.
     *
     * VIRTUAL rather than STORED, and that was measured rather than chosen for style: adding a STORED generated
     * column rebuilds the table, and on `enrollments` — which eleven child tables reference by foreign key (read
     * from `information_schema.KEY_COLUMN_USAGE`, not recalled) — MySQL
     * 8.4 answers `1215 Cannot add foreign key constraint` for it, with `foreign_key_checks` off as well as on.
     * A VIRTUAL column is added in place, the unique index over it is accepted, and the two behaviours the guide
     * asks for were then driven on a clone of live `ems` holding its 37 enrollments: the second active seat for
     * student 53 in term 11 returned `1062 Duplicate entry '53-11-53:11' for key
     * 'enrollments.uq_enrollments_active_seat'`, and the same insert after the held row was set to `dropped`
     * succeeded, leaving `dropped,pending` for that student and term. A second fee sheet on one enrollment
     * returned `1062 Duplicate entry '53' for key 'studentassessments.uq_studentassessments_enrollment'`.
     *
     * SQLite is deliberately left without the generated column: `ALTER TABLE … ADD COLUMN … GENERATED` is not
     * supported there, so the suite's `:memory:` schema carries the application rule and the MySQL job carries the
     * database one. `tests/Feature/Enrollment/DatabaseLevelEnrollmentRulesTest.php` pins each driver's shape
     * rather than pretending one answers the other's question.
     */
    public function up(): void
    {
        $mysql = DB::getDriverName() === 'mysql';

        $this->refuseOverlappingFeeSheets();

        if ($mysql) {
            $this->refuseOverlappingSeats();
        }

        // Portable: a plain unique key over a column that already exists.
        Schema::table('studentassessments', function ($table) {
            $table->unique('enrollmentId', 'uq_studentassessments_enrollment');
        });

        if (! $mysql) {
            return;
        }

        // Raw because Laravel has no generated-column builder that both drivers compile. `termId IS NOT NULL`
        // guards the CONCAT: a row naming no term holds no seat in any term, and letting NULL through CONCAT would
        // fold two such rows onto the same key. Measured before this was written: live `ems` answers 0 rows with a
        // NULL termId, so the branch protects a future import rather than describing today's data.
        DB::statement(
            'alter table `enrollments`
                add column `seatKey` varchar(32) generated always as (
                    case when `enrollmentStatus` <> \'dropped\' and `termId` is not null
                         then concat(`studentId`, \':\', `termId`) else null end
                ) virtual,
                add unique key `uq_enrollments_active_seat` (`studentId`, `termId`, `seatKey`)'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('alter table `enrollments` drop index `uq_enrollments_active_seat`, drop column `seatKey`');
        }

        Schema::table('studentassessments', function ($table) {
            $table->dropUnique('uq_studentassessments_enrollment');
        });
    }

    /**
     * Two sheets for one enrollment is the state DI-2 describes. Named before the ALTER, because MySQL's 1062
     * after it would say only that a duplicate exists, and a desk reading the log needs the enrollment.
     */
    private function refuseOverlappingFeeSheets(): void
    {
        $rows = DB::select(
            'select enrollmentId, count(*) c from studentassessments group by enrollmentId having count(*) > 1
             order by c desc, enrollmentId limit 10'
        );
        if ($rows !== []) {
            $ids = implode(', ', array_map(fn ($r) => $r->enrollmentId.' ('.$r->c.' sheets)', $rows));
            throw new RuntimeException(
                'uq_studentassessments_enrollment cannot be added: '.$ids
                .'. Reconcile those sheets first — the key refuses the second sheet, so it will not accept the data as it stands.'
            );
        }
    }

    /** The same argument for the seat rule, on the driver that can hold it. */
    private function refuseOverlappingSeats(): void
    {
        $rows = DB::select(
            "select studentId, termId, count(*) c from enrollments
                where enrollmentStatus <> 'dropped' and termId is not null
                group by studentId, termId having count(*) > 1 order by c desc, studentId limit 10"
        );
        if ($rows !== []) {
            $pairs = implode(', ', array_map(fn ($r) => 'student '.$r->studentId.'/term '.$r->termId.' ('.$r->c.')', $rows));
            throw new RuntimeException(
                'uq_enrollments_active_seat cannot be added: '.$pairs.
                '. Two active enrollments for one student in one term is exactly the state the guide\'s problem 3 '
                .'says must be impossible; retire the extra rows (a drop releases a seat) before re-running.'
            );
        }
    }
};
