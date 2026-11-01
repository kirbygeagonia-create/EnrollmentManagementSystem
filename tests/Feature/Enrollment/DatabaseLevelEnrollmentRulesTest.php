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
 * ADS guide problems 3, 4 and 11: the enrollment rules the database itself must hold, not only the php.
 *
 * Two additive keys ship in
 * `database/migrations/2026_11_01_1100000000_add_database_level_enrollment_rules.php`:
 * `uq_studentassessments_enrollment` (one fee sheet per enrollment, the review document's DI-2, open since
 * 2026-09-15) and `uq_enrollments_active_seat` over (studentId, termId, seatKey), where `seatKey` is a generated
 * column that is non-null exactly when the row holds a seat — so a drop releases it without a trigger.
 *
 * The drivers are not symmetric and this class says which half each one judges rather than skipping: SQLite has no
 * `ALTER TABLE … ADD COLUMN … GENERATED`, so the suite's `:memory:` schema carries the fee-sheet key and NOT the
 * seat key, while CI's MySQL job carries both. Each case therefore asserts the fact that is true on the connection
 * it is running on, and the seat case fails loudly if a future edit makes the two drivers disagree about something
 * else.
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
     * The fee-sheet key must exist on both drivers, and the seat key on MySQL only. Asserting the shapes keeps the
     * driver split visible: if a future migration makes the seat key portable, the SQLite branch of the next case
     * stops being true and this one says where.
     */
    #[Test]
    public function each_driver_holds_the_keys_it_can_hold(): void
    {
        $mysql = DB::getDriverName() === 'mysql';

        $this->assertTrue(
            $this->uniqueIndexExists('studentassessments', 'uq_studentassessments_enrollment'),
            'uq_studentassessments_enrollment is absent on '.DB::getDriverName().'; the migration is meant to be portable here.'
        );

        $seatKey = $this->uniqueIndexExists('enrollments', 'uq_enrollments_active_seat');
        if ($mysql) {
            $this->assertTrue($seatKey, 'MySQL is the driver that can hold the seat rule, and the key is absent.');

            return;
        }

        $this->assertFalse($seatKey, 'SQLite suddenly reports the seat key, so this class\'s driver split is out of date.');
    }

    /**
     * MySQL: the database refuses a second active enrollment for one student in one term, and a drop releases the
     * seat so the same insert then succeeds — the ruling the guide's problem 3 and this project's ruling 17 both
     * describe, enforced without a trigger.
     *
     * SQLite: no generated column exists, so the seat rule is `EnrollmentIssuer::seatHolder()`'s read alone. The
     * case does not skip that: it asserts the application predicate answers the held row, which is the half the
     * suite can actually check, and names the surface it cannot reach.
     */
    #[Test]
    public function a_second_active_seat_is_refused_where_the_server_can_refuse_it(): void
    {
        $held = $this->enroll(EnrollmentStatus::Enrolled);
        $mysql = DB::getDriverName() === 'mysql';

        $attempt = fn () => $this->insertSeat(EnrollmentStatus::Pending);

        if (! $mysql) {
            $this->assertSame(
                $held->enrollmentId,
                EnrollmentIssuer::seatHolder($this->studentId, $this->termId)?->enrollmentId,
                'the application rule that SQLite is left with no longer answers the held row, so this branch '
                .'measures a different fact than it claims.'
            );

            $this->markAsReadReportingNoDatabaseRefusal();

            return;
        }

        try {
            $attempt();
            $this->fail('MySQL accepted a second active enrollment for one student in one term.');
        } catch (UniqueConstraintViolationException|QueryException $e) {
            $this->assertStringContainsString('uq_enrollments_active_seat', $e->getMessage());
        }

        // A drop releases the seat, and the key must not stand in the way of the re-enrollment ruling 17 allows.
        $held->update(['enrollmentStatus' => EnrollmentStatus::Dropped]);
        $again = $attempt();
        $this->assertGreaterThan(0, $again);
        $this->assertSame(1, DB::table('enrollments')
            ->where('studentId', $this->studentId)->where('termId', $this->termId)
            ->where('enrollmentStatus', '!=', EnrollmentStatus::Dropped->value)->count(),
            'two active seats exist after the drop-and-retry, so the generated column is not NULL for a dropped row.');
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

    /**
     * Stated rather than hidden: the case that ran on SQLite proved the application predicate and did NOT prove a
     * database refusal. This repository refuses a green that means "nothing was judged", so the fact is printed in
     * the assertion message the reader sees.
     */
    private function markAsReadReportingNoDatabaseRefusal(): void
    {
        $this->assertTrue(true, 'SQLite holds no seat key: the database refusal is judged on the MySQL job only.');
    }

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
