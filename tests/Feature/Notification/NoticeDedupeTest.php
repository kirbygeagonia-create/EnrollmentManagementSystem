<?php

namespace Tests\Feature\Notification;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\OfficeId;
use App\Enums\StudentType;
use App\Events\EnrollmentStatusChanged;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Enrollmentstatushistory;
use App\Models\Notifications;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use App\Services\EnrollmentStateMachine;
use App\Support\NoticeDelivery;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A notice is delivered once per act per recipient (§28.3 G-36).
 *
 * Both listeners are `ShouldQueue`, so a worker that takes a job again used to write the bell row a second time and
 * nothing in the schema objected. The guard is `NoticeDelivery::once()` plus the unique key `uq_notifications_dedupe`,
 * and these cases pin the three things that make it a guard rather than a filter:
 *
 *  - a retried act writes ONE row (the pre-check, the shape of an ordinary retry);
 *  - a *different* act with the same words and the same recipient writes TWO (the key is the act, so keying on the
 *    message, the type, or the recipient alone would silently delete a real notice);
 *  - an act with no identity at all — a hand-built event with no history row, which is what two cases in
 *    `NotificationBellTest` construct — is always delivered, because a missing key must never swallow a bell.
 *
 * The last two cases go at the refusal itself: the index is read back from whichever server is open (MySQL reports a
 * unique index through `information_schema`, SQLite through `PRAGMA index_list`, and an assertion that only holds on
 * one of them is a claim about a machine, not about the code), and a failure that is NOT a duplicate key must
 * propagate — a `catch (QueryException)` that returns null on every error would turn a broken write into a lost
 * notice, which is the same outcome G-36 exists to prevent.
 */
class NoticeDedupeTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $registrar;

    private Staffusers $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        foreach (OfficeId::cases() as $office) {
            Offices::firstOrCreate(['officeId' => $office->value], ['officeName' => 'Office '.$office->value]);
        }

        $this->registrar = $this->staff(1);
        $this->cashier = $this->staff(2);
    }

    private function staff(int $officeId): Staffusers
    {
        return Staffusers::factory()->create([
            'officeId' => $officeId,
            'employeeNo' => 'EMP-DUPE-'.uniqid(),
            'username' => 'dupe_'.$officeId.'_'.uniqid(),
            'email' => 'dupe_'.$officeId.'_'.uniqid().'@example.com',
        ]);
    }

    private function student(): Students
    {
        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        return Students::create([
            'schoolIdNumber' => 'DUPE-'.uniqid(),
            'lastName' => 'Retried',
            'firstName' => 'Student',
            'middleName' => 'C',
            'suffix' => 'N/A',
            'gender' => 'female',
            'birthdate' => '2004-02-02',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234568',
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
            'email' => 'dupe_student_'.uniqid().'@example.com',
            'username' => 'dupe_student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);
    }

    private function enrollment(Students $student): Enrollments
    {
        $unit = Academicunits::create(['unitName' => 'College of Computer Studies', 'unitType' => 'college']);
        $year = Academicyears::create([
            'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01',
            'endDate' => '2027-03-31',
        ]);
        $term = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ]);
        $course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCS',
            'courseName' => 'Bachelor of Science in Computer Science',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);

        return Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $course->courseId,
            'termId' => $term->termId,
            'yearLevel' => 1,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => EnrollmentType::New,
            'academicStanding' => 'regular',
            'enrollmentStatus' => EnrollmentStatus::Assessed,
            'evaluatedBy' => $this->registrar->userId,
        ]);
    }

    #[Test]
    public function a_real_transition_carries_the_history_row_that_names_the_act(): void
    {
        $enrollment = $this->enrollment($this->student());

        app(EnrollmentStateMachine::class)->transition($enrollment, EnrollmentStatus::Paid, $this->registrar, 'settled');

        $history = Enrollmentstatushistory::latest('historyId')->first();
        $this->assertNotNull($history, 'the transition wrote no history row, so there is no act to key on');

        $notice = Notifications::where('type', 'enrollment_status_changed')->sole();
        $this->assertSame(
            "enrollment_status_changed:{$history->historyId}:{$this->registrar->userId}",
            $notice->dedupeKey,
            'the notice was written without the key, so a retried job would write it again'
        );
    }

    #[Test]
    public function a_notice_written_by_a_real_transition_refuses_a_retried_delivery(): void
    {
        // The whole path, not the parts: run the act through the state machine, then deliver the same event a second
        // time the way a retried queued listener would. This is the case that dies when the machine stops handing the
        // history id to the event — the hand-built events below cannot see that defect, because they bring their own id.
        $enrollment = $this->enrollment($this->student());

        app(EnrollmentStateMachine::class)->transition($enrollment, EnrollmentStatus::Paid, $this->registrar, 'settled');

        $historyId = (int) Enrollmentstatushistory::where('enrollmentId', $enrollment->enrollmentId)->max('historyId');
        event(new EnrollmentStatusChanged($enrollment, 'assessed', 'paid', $this->registrar, '', $historyId));

        $this->assertSame(
            1,
            Notifications::where('type', 'enrollment_status_changed')->count(),
            'the delivery itself duplicated, so the retry was not recognised as the same act'
        );
    }

    #[Test]
    public function a_retried_listener_leaves_one_notice_for_the_desk(): void
    {
        $enrollment = $this->enrollment($this->student());
        $historyId = (int) Enrollmentstatushistory::query()
            ->when(true, fn ($q) => $q->where('enrollmentId', $enrollment->enrollmentId))
            ->max('historyId');

        // The retry shape: the same act, delivered again by a worker that took the job twice. The history id is the
        // same, so the second delivery is refused; the message and the recipient would be identical too, which is why
        // neither of them can be the key.
        event(new EnrollmentStatusChanged($enrollment, 'assessed', 'paid', $this->registrar, '', $historyId ?: 1));
        event(new EnrollmentStatusChanged($enrollment, 'assessed', 'paid', $this->registrar, '', $historyId ?: 1));

        $this->assertSame(
            1,
            Notifications::where('type', 'enrollment_status_changed')->count(),
            'a retried job rang the same desk twice (§28.3 G-36)'
        );
    }

    #[Test]
    public function a_different_act_with_the_same_words_is_still_delivered(): void
    {
        $enrollment = $this->enrollment($this->student());

        // Same recipient, same type, same payload — the only difference is the act. A key built from the message, the
        // type, or the addressee alone would delete this second notice, which is the failure mode this guard must not
        // have: two desks legitimately reaching the same state at different moments are two facts.
        event(new EnrollmentStatusChanged($enrollment, 'assessed', 'paid', $this->registrar, '', 101));
        event(new EnrollmentStatusChanged($enrollment, 'assessed', 'paid', $this->registrar, '', 102));

        $this->assertSame(2, Notifications::where('type', 'enrollment_status_changed')->count());
        $this->assertSame(
            ['enrollment_status_changed:101:'.$this->registrar->userId, 'enrollment_status_changed:102:'.$this->registrar->userId],
            Notifications::where('type', 'enrollment_status_changed')->orderBy('id')->pluck('dedupeKey')->all()
        );
    }

    #[Test]
    public function an_act_with_no_identity_is_delivered_rather_than_dropped(): void
    {
        $enrollment = $this->enrollment($this->student());

        // Two hand-built events with no history row: the shape NotificationBellTest uses. Absence of a key must not
        // read as "already delivered" — a guard that swallowed unkeyed notices would be worse than the duplicate.
        event(new EnrollmentStatusChanged($enrollment, 'assessed', 'paid', $this->registrar, ''));
        event(new EnrollmentStatusChanged($enrollment, 'assessed', 'paid', $this->registrar, ''));

        $rows = Notifications::where('type', 'enrollment_status_changed')->get();

        $this->assertCount(2, $rows, 'an unkeyed notice was treated as a duplicate and lost');
        $this->assertNull($rows->first()->dedupeKey, 'a key was invented for an act that has no identity');
    }

    #[Test]
    public function the_server_holds_a_unique_index_on_the_key_whichever_driver_is_open(): void
    {
        $connection = DB::connection()->getDriverName();

        if ($connection === 'mysql') {
            $found = DB::select("SHOW INDEX FROM notifications WHERE Key_name = 'uq_notifications_dedupe'");
            $this->assertNotCount(0, $found, 'no such index on the school’s server');
            $this->assertSame(0, (int) $found[0]->Non_unique, 'the index is not unique, so it enforces nothing');
            $this->assertSame('dedupeKey', $found[0]->Column_name);

            return;
        }

        $indexed = DB::select('PRAGMA index_list("notifications")');
        $unique = array_values(array_filter($indexed, fn ($i) => (int) $i->unique === 1));
        $this->assertNotCount(0, $unique, 'the suite’s schema carries no unique index at all');

        $columns = [];
        foreach ($unique as $index) {
            foreach (DB::select('PRAGMA index_info('.json_encode($index->name).')') as $col) {
                $columns[(string) $index->name][] = (string) $col->name;
            }
        }

        $this->assertContains(
            'dedupeKey',
            array_merge(...array_values($columns)),
            'the only unique index here is not on dedupeKey: '.json_encode($columns)
        );
    }

    #[Test]
    public function a_failure_that_is_not_a_duplicate_still_propagates(): void
    {
        // `notifiable_id` is NOT NULL on both servers, so this write fails for a reason that has nothing to do with
        // the key. Returning null here would make a broken write indistinguishable from an already-delivered notice,
        // which is the same loss G-36 exists to prevent — so the catch must be on the duplicate code, not on
        // QueryException at large.
        $thrown = null;

        try {
            NoticeDelivery::once('g36-not-a-duplicate', [
                'type' => 'enrollment_status_changed',
                'notifiable_type' => Staffusers::class,
                'notifiable_id' => null,
                'data' => ['message' => 'a write that cannot land'],
            ]);
        } catch (QueryException $e) {
            $thrown = $e->getMessage();
        }

        $this->assertNotNull($thrown, 'a non-duplicate query error was swallowed as an already-delivered notice');
        // The two servers word the same refusal differently — SQLite says "NOT NULL constraint failed", MySQL 8 says
        // "Column 'notifiable_id' cannot be null" (1048) — so the pattern carries both. The MySQL half was read off a
        // scratch-schema rehearsal, not written from the SQLite message.
        $this->assertMatchesRegularExpression('/NOT NULL constraint failed|cannot be null/i', (string) $thrown);
        $this->assertDatabaseMissing('notifications', ['dedupeKey' => 'g36-not-a-duplicate']);
    }
}
