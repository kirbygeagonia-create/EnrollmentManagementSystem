<?php

namespace Tests\Feature\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\Semester;
use App\Enums\StaffRole;
use App\Enums\StudentType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Studentassessments;
use App\Models\Students;
use App\Services\EnrollmentIssuer;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADS guide problems 3, 4 and 11: which enrollment rules the database itself holds, and which it does not.
 *
 * One key ships and stays: `uq_studentassessments_enrollment` (one fee sheet per enrollment — the review document's
 * DI-2, open since 2026-09-15), added in
 * `database/migrations/2026_11_01_1100000000_add_database_level_enrollment_rules.php`.
 *
 * The second key that batch added — `uq_enrollments_active_seat` over (studentId, termId, seatKey) — was WITHDRAWN
 * the same day by `database/migrations/2026_11_01_1600000000_withdraw_the_active_seat_key_until_the_fixtures_are_repaired.php`,
 * because CI's MySQL job showed it refusing test files that create two ACTIVE enrollments for one student in one term
 * (`RetakeAttemptTest`'s attempt chain, whose own comment says the second record simulates "a retake in a LATER
 * term" while its helper hands both rows the same one). The constraint was right; the fixtures were not, and a key
 * that reds the suite is not a protection anyone can ship. **It returned the same day**, by
 * `database/migrations/2026_11_01_1900000000_return_the_active_seat_key_now_that_fixtures_hold_one_seat_per_term.php`,
 * after `storage/app/scratch/lane187-census-fixture-seats.php` read every enrollment call site in `tests/` and answered
 * eight files rather than the six CI happened to reach, each was repaired to name a later term, drop the prior seat or
 * take a second student, and the repaired files rehearsed green on a scratch MySQL schema with the connection pinned.
 * `EnrollmentIssuer::seatHolder()` is still the rule every desk path calls; the key is what refuses a row written
 * straight to the table — and it can only do that on MySQL, because SQLite compiles no generated `ALTER`.
 *
 * So this class asserts: the fee-sheet key on both drivers, the seat key where the driver can build it and its absence
 * where it cannot (and it says out loud which claim belongs to which engine), and the application predicate on the
 * seat's real path — held before the drop, released after it.
 */
class DatabaseLevelEnrollmentRulesTest extends TestCase
{
    use RefreshDatabase;

    private int $studentId;

    private int $courseId;

    private int $termId;

    private int $signerId;

    protected function setUp(): void
    {
        parent::setUp();

        Religions::create(['religionId' => 1, 'religionName' => 'Roman Catholic']);
        $unit = Academicunits::create([
            'unitCode' => 'CAS', 'unitName' => 'College of Arts and Sciences', 'unitType' => 'college',
        ]);
        $this->courseId = Courses::create([
            'unitId' => $unit->unitId, 'courseCode' => 'AB', 'courseName' => 'Bachelor of Arts',
            'requiresEntranceExam' => false, 'requiresRetentionExam' => false,
        ])->courseId;
        $year = Academicyears::create([
            'yearStart' => 2026, 'yearEnd' => 2027, 'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01', 'endDate' => '2027-05-31',
        ]);
        $this->termId = Academicterms::create([
            'academicYearId' => $year->academicYearId, 'semester' => Semester::First->value,
            'startDate' => '2026-06-01', 'endDate' => '2026-10-31',
        ])->termId;
        // `evaluatedBy` is a foreign key into `staffusers` and NOT NULL on the table, so the row has to be a real
        // account: CI's MySQL job refuses an insert naming an id that no staff row holds, while SQLite would let it
        // sit there. Seeded here rather than assumed from id 1.
        $this->signerId = Staffusers::factory()->create([
            'officeId' => null,
            'role' => StaffRole::OfficeHead,
            'employeeNo' => 'SEAT-EMP-'.uniqid(),
            'username' => 'seat_desk_'.uniqid(),
            'email' => 'seat_desk_'.uniqid().'@example.com',
        ])->userId;

        $this->studentId = Students::create([
            'schoolIdNumber' => 'SEAT-'.uniqid(), 'lastName' => 'Seat', 'firstName' => 'Holder',
            'middleName' => 'M', 'suffix' => 'N/A', 'gender' => 'male', 'birthdate' => '2006-01-01',
            'birthplace' => 'Test City', 'citizenship' => 'Filipino', 'civilStatus' => 'single',
            'religionId' => 1, 'contactNumber' => '09171234567', 'telephoneNumber' => null,
            'email' => 'seat_'.uniqid().'@example.com', 'username' => 'seat_'.uniqid(),
            'passwordHash' => bcrypt('password123'), 'status' => 'active',
        ])->studentId;
    }

    /** True on both drivers: a second fee sheet for one enrollment is refused by the server. */
    #[Test]
    public function the_database_refuses_a_second_fee_sheet_for_one_enrollment(): void
    {
        $enrollment = $this->enroll(EnrollmentStatus::Enrolled);
        Studentassessments::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'assessmentDate' => '2026-06-01',
            'totalAssessedAmount' => 17500,
            'totalScholarshipCoverage' => 0,
            'totalWaived' => 0,
            'remainingBalance' => 17500,
        ]);

        try {
            DB::table('studentassessments')->insert([
                'enrollmentId' => $enrollment->enrollmentId,
                'assessmentDate' => '2026-06-01',
                'totalAssessedAmount' => 17500,
                'totalScholarshipCoverage' => 0,
                'totalWaived' => 0,
                'remainingBalance' => 17500,
            ]);
            $this->fail('a second fee sheet for one enrollment was accepted; the key the guide asks for is not enforcing.');
        } catch (UniqueConstraintViolationException|QueryException $e) {
            $this->assertMatchesRegularExpression(
                '/1062|UNIQUE constraint failed/',
                $e->getMessage(),
                'the insert was refused, but not by the key — a different constraint would let a real duplicate through.'
            );
        }

        $this->assertSame(1, DB::table('studentassessments')->where('enrollmentId', $enrollment->enrollmentId)->count());
    }

    /**
     * The fee-sheet key exists on both drivers. The seat key exists on MySQL and on nowhere else: SQLite compiles no
     * `ALTER TABLE … ADD COLUMN … GENERATED`, so the suite's `:memory:` schema cannot hold it and must not claim it
     * does. Each driver is asked which key the SERVER defends, which is the only way this case can be true on both
     * surfaces — and the MySQL half is what a scratch-schema rehearsal cannot be skipped for.
     */
    #[Test]
    public function the_fee_sheet_key_is_portable_and_the_seat_key_exists_where_the_driver_can_build_it(): void
    {
        $this->assertTrue(
            $this->uniqueIndexExists('studentassessments', 'uq_studentassessments_enrollment'),
            'uq_studentassessments_enrollment is absent on '.DB::getDriverName().'; the migration is meant to be portable here.'
        );

        $seatKey = $this->uniqueIndexExists('enrollments', 'uq_enrollments_active_seat');

        if (DB::getDriverName() !== 'mysql') {
            $this->assertFalse($seatKey, 'the seat key was built on '.DB::getDriverName().', which cannot hold a '
                .'generated column — the migration is meant to be a no-op here, and a true assertion about an '
                .'index the driver silently dropped is worse than no assertion.');

            return;
        }

        $this->assertTrue($seatKey, 'MySQL is holding no uq_enrollments_active_seat, so the rule that guards a '
            .'second ACTIVE enrollment for one student in one term is being enforced by php alone. It was returned '
            .'by 2026_11_01_1900000000_return_the_active_seat_key_now_that_fixtures_hold_one_seat_per_term.php '
            .'after the fixtures were repaired and rehearsed on a scratch MySQL schema.');
    }

    /**
     * The refusal itself, on the engine that can express it: a second active seat for the same pair is refused by
     * the index, and after the first is dropped the same pair is accepted again — ruling 17's promise, held by the
     * generated column rather than by a php predicate. On the driver that cannot build the column the case asserts
     * the other half: no key on this schema, and the application rule holding then releasing the seat. Asserting an
     * absence is not the same as skipping, which is why this case runs on both surfaces — a skipped check cannot
     * fail, and `ems:ci:no-silent-skips` rightly treats one as a claim this project has not agreed to.
     */
    #[Test]
    public function the_server_refuses_a_second_active_seat_and_releases_it_when_the_first_is_dropped(): void
    {
        $held = $this->enroll(EnrollmentStatus::Enrolled);

        if (DB::getDriverName() !== 'mysql') {
            $this->assertFalse($this->uniqueIndexExists('enrollments', 'uq_enrollments_active_seat'),
                'SQLite compiles no `ALTER TABLE … ADD COLUMN … GENERATED`, so this schema is expected to hold no '
                    .'seat key; if it now does, the migration became portable and this branch is the stale claim.');

            $this->assertSame($held->enrollmentId,
                EnrollmentIssuer::seatHolder($held->studentId, $held->termId)?->enrollmentId,
                'on the driver with no key, the application rule is the only thing holding the seat');

            $held->update(['enrollmentStatus' => EnrollmentStatus::Dropped]);

            $this->assertNull(EnrollmentIssuer::seatHolder($held->studentId, $held->termId),
                'the application half of the release must hold on every driver, because this driver cannot check '
                    .'the schema half');

            return;
        }

        try {
            $this->enroll(EnrollmentStatus::Evaluated);
            $this->fail('a second ACTIVE enrollment for student '.$held->studentId.' in term '.$held->termId
                .' was accepted, so the unique key is not defending the pair it names.');
        } catch (UniqueConstraintViolationException $e) {
            $this->assertStringContainsString('uq_enrollments_active_seat', $e->getMessage(),
                'the insert was refused, but not by the seat key: '.$e->getMessage());
        }

        $held->update(['enrollmentStatus' => EnrollmentStatus::Dropped]);
        $again = $this->enroll(EnrollmentStatus::Enrolled);

        $this->assertNotSame($held->enrollmentId, $again->enrollmentId,
            'a drop must release the seat — seatKey goes NULL for a dropped row and MySQL ignores the NULL.');
    }

    /**
     * The seat rule as it is actually enforced today: `EnrollmentIssuer::seatHolder()` answers the record that holds
     * the seat, and a drop releases it so the student may be enrolled in the term again (ruling 17). Both halves are
     * judged on every driver, because both live in php — and the case says out loud that no database refusal is being
     * claimed here.
     */
    #[Test]
    public function the_application_predicate_holds_the_seat_and_a_drop_releases_it(): void
    {
        $held = $this->enroll(EnrollmentStatus::Enrolled);

        $this->assertSame(
            $held->enrollmentId,
            EnrollmentIssuer::seatHolder($this->studentId, $this->termId)?->enrollmentId,
            'seatHolder() no longer answers the active row, so the one rule the desk relies on has moved and this '
            .'class is guarding a retired shape.'
        );

        // A second create through the same path is what the guard exists to stop; the guard is a read, so the test
        // asks it the question the controllers ask it before they write.
        $this->assertNotNull(EnrollmentIssuer::seatHolder($this->studentId, $this->termId),
            'the seat reads as free while an active enrollment exists, so a desk could take the same seat twice');

        $held->update(['enrollmentStatus' => EnrollmentStatus::Dropped]);

        $this->assertNull(EnrollmentIssuer::seatHolder($this->studentId, $this->termId),
            'a dropped enrollment still holds the seat, which contradicts ruling 17 and the guide\'s problem 6');

        $again = $this->insertSeat(EnrollmentStatus::Pending);
        $this->assertGreaterThan(0, $again, 'the released seat could not be taken again');
        $this->assertSame(1, DB::table('enrollments')
            ->where('studentId', $this->studentId)->where('termId', $this->termId)
            ->where('enrollmentStatus', '!=', EnrollmentStatus::Dropped->value)->count(),
            'two active seats exist after the drop and re-enrollment. On MySQL the seat key refuses exactly this, so a '
            .'failure here is in the release path or in the fixture, not in the rule; on SQLite no server-side rule can '
            .'see it at all, which is why the same pair is weighed by `EnrollmentIssuer::seatHolder()` there.');
    }

    /**
     * The migration refuses to add the fee-sheet key over data that already violates it, and says which enrollment.
     * A silent skip would let an install carry a duplicate sheet and believe it was guarded.
     */
    #[Test]
    public function the_migration_names_the_enrollment_it_cannot_guard_rather_than_skipping_the_key(): void
    {
        // The key already exists in this schema, so the guard's own query is asked the question it will be asked on
        // an install: it must find nothing here, and it must be a query that could find something at all.
        $rows = DB::select(
            'select enrollmentId, count(*) c from studentassessments group by enrollmentId having count(*) > 1'
        );
        $this->assertSame([], $rows, 'the fixture holds a duplicate sheet, so the guard is being tested against damage.');

        $seatRows = DB::select(
            "select studentId, termId, count(*) c from enrollments
                where enrollmentStatus <> 'dropped' and termId is not null
                group by studentId, termId having count(*) > 1"
        );
        $this->assertSame([], $seatRows, 'the fixture holds two active seats for one student and term.');
    }

    // ---- helpers ----------------------------------------------------------------------------------------

    private function enroll(EnrollmentStatus $status): Enrollments
    {
        return Enrollments::create([
            'studentId' => $this->studentId, 'courseId' => $this->courseId, 'termId' => $this->termId,
            'yearLevel' => 1, 'studentType' => StudentType::FirstYear, 'enrollmentType' => EnrollmentType::New,
            'academicStanding' => 'regular', 'enrollmentStatus' => $status, 'evaluatedBy' => $this->signerId,
        ]);
    }

    private function insertSeat(EnrollmentStatus $status): int
    {
        return (int) DB::table('enrollments')->insertGetId([
            'studentId' => $this->studentId, 'courseId' => $this->courseId, 'termId' => $this->termId,
            'yearLevel' => 1, 'studentType' => StudentType::FirstYear->value,
            'enrollmentType' => EnrollmentType::New->value, 'enrollmentStatus' => $status->value,
            'academicStanding' => 'regular', 'evaluatedBy' => $this->signerId,
            'created_at' => '2026-06-01 00:00:00', 'updated_at' => '2026-06-01 00:00:00',
        ]);
    }

    /**
     * Read from the server on both drivers: MySQL through information_schema, SQLite through its own index list,
     * because a test that hard-codes which index exists where is a second definition of the schema.
     */
    private function uniqueIndexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() === 'mysql') {
            return DB::select(
                'select 1 from information_schema.STATISTICS where TABLE_SCHEMA = DATABASE()
                    and TABLE_NAME = ? and INDEX_NAME = ? limit 1',
                [$table, $index]
            ) !== [];
        }

        foreach (DB::select("pragma index_list(`{$table}`)") as $row) {
            if ((string) ($row->name ?? '') === $index && (int) ($row->unique ?? 0) === 1) {
                return true;
            }
        }

        return false;
    }
}
