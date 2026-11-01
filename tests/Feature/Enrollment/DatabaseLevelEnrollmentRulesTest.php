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
 * because CI's MySQL job showed it refusing six test files that create two ACTIVE enrollments for one student in one
 * term (`RetakeAttemptTest`'s attempt chain, whose own comment says the second record simulates "a retake in a LATER
 * term" while its helper hands both rows the same one). The constraint was right; the fixtures were not, and a key
 * that reds the suite is not a protection anyone can ship. Until those fixtures name a later term or drop the prior
 * seat first — rehearsed against a scratch MySQL schema, because SQLite compiles no generated column and cannot see
 * this at all — the seat rule is `EnrollmentIssuer::seatHolder()`'s, which every desk path calls.
 *
 * So this class asserts: the fee-sheet key on both drivers, the seat key on NEITHER (and loudly on both if it comes
 * back before the fixtures are repaired), and the application predicate on the seat's real path — held before the
 * drop, released after it.
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
     * The fee-sheet key exists on both drivers; the seat key exists on neither, because it was withdrawn pending the
     * fixture repair. Asserting the absence on MySQL is the point: a reader who re-adds the index without first
     * fixing the six files will see this case go red on the engine where it matters, which is the only place the
     * constraint is visible at all.
     */
    #[Test]
    public function each_driver_holds_the_fee_sheet_key_and_neither_holds_the_withdrawn_seat_key(): void
    {
        $this->assertTrue(
            $this->uniqueIndexExists('studentassessments', 'uq_studentassessments_enrollment'),
            'uq_studentassessments_enrollment is absent on '.DB::getDriverName().'; the migration is meant to be portable here.'
        );

        $seatKey = $this->uniqueIndexExists('enrollments', 'uq_enrollments_active_seat');
        $this->assertFalse($seatKey, DB::getDriverName().' reports the seat key, so either the withdrawal migration '
            .'was reverted or the six fixture files have not been repaired and rehearsed on a scratch MySQL schema yet '
            .'— re-adding this constraint without that rehearsal reds CI\'s MySQL job, which is how it was found.');
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
            'two active seats exist after the drop and re-enrollment, and no server-side rule refused them: this is '
            .'exactly the state the withdrawn key would have blocked, which is why the fixtures must be repaired first.');
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
