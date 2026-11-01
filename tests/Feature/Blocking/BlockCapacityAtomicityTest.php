<?php

namespace Tests\Feature\Blocking;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\OfficeId;
use App\Enums\Semester;
use App\Enums\StaffRole;
use App\Enums\StudentType;
use App\Enums\UnitType;
use App\Enums\WorkflowStatus;
use App\Enums\WorkflowStepStatus;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Blocks;
use App\Models\Courses;
use App\Models\Enrolledsubjects;
use App\Models\Enrollments;
use App\Models\Enrollmentworkflow;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Rooms;
use App\Models\Schedulemeetings;
use App\Models\Schedules;
use App\Models\Staffusers;
use App\Models\Students;
use App\Models\Subjects;
use App\Models\Workflowsteps;
use App\Support\RetryOnDeadlock;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ADS guide problems 2, 4, 7, 10 and 11 — the last-seat race, and what a refused write must leave behind.
 *
 * `System-Design-Quality-Review` (DI-5, 2026-09-15) named the defect and nothing closed it: `assignStudents()`
 * weighed the block's capacity OUTSIDE the transaction it guards, so two desks assigning the last seat both read
 * the same number and both wrote. The repair locks the block row as the transaction's first act, re-counts under
 * that lock, takes the room row second in a fixed order, and retries only a deadlock or a lock-wait timeout.
 *
 * SQLite cannot deadlock, so no case here drives the race itself — `storage/app/scratch/lane168-probe-last-seat-race.php`
 * does that over two real MySQL connections and proves it both ways by swapping HEAD's controller in. What this
 * class can pin, and does: the count sits INSIDE the transaction and AFTER the block lock (a shape guard, because
 * moving it back out is a one-line edit no behavioural test on this driver would notice), a refused or a failed
 * write leaves no partial row, and the retry helper distinguishes the two conditions it may repeat from every
 * condition it may not.
 */
class BlockCapacityAtomicityTest extends TestCase
{
    use RefreshDatabase;

    private int $courseId;

    private int $termId;

    protected function setUp(): void
    {
        parent::setUp();

        Offices::insert([
            ['officeId' => 1, 'officeName' => 'Office of the Registrar'],
            ['officeId' => 2, 'officeName' => 'Accounting Office'],
            ['officeId' => 3, 'officeName' => 'Assessment Office'],
            ['officeId' => 4, 'officeName' => 'Department Evaluation'],
            ['officeId' => 5, 'officeName' => 'Blocking and Scheduling'],
            ['officeId' => 11, 'officeName' => 'Clinic'],
            ['officeId' => 22, 'officeName' => 'ID Office'],
        ]);
        $this->seed(RbacSeeder::class);

        Religions::create(['religionId' => 1, 'religionName' => 'Roman Catholic']);
        $unit = Academicunits::create([
            'unitCode' => 'CCS', 'unitName' => 'College of Computer Studies', 'unitType' => UnitType::College,
        ]);
        $this->courseId = Courses::create([
            'unitId' => $unit->unitId, 'courseCode' => 'BSCS',
            'courseName' => 'Bachelor of Science in Computer Science',
            'requiresEntranceExam' => false, 'requiresRetentionExam' => false,
        ])->courseId;
        $year = Academicyears::create([
            'yearStart' => 2024, 'yearEnd' => 2025, 'yearLabel' => '2024-2025',
            'startDate' => '2024-06-01', 'endDate' => '2025-05-31',
        ]);
        $this->termId = Academicterms::create([
            'academicYearId' => $year->academicYearId, 'semester' => Semester::First->value,
            'startDate' => '2024-06-01', 'endDate' => '2024-10-31',
        ])->termId;

        Subjects::insert([
            ['subjectCode' => 'CS101', 'subjectName' => 'Introduction to Programming',
                'lectureUnits' => 3, 'labUnits' => 0, 'subjectType' => 'lecture'],
            ['subjectCode' => 'CS102', 'subjectName' => 'Data Structures',
                'lectureUnits' => 3, 'labUnits' => 0, 'subjectType' => 'lecture'],
        ]);
        Rooms::insert([
            ['roomName' => 'Room 101', 'capacity' => 40, 'building' => 'Main Building'],
        ]);
    }

    /**
     * The whole point of moving the count inside the transaction: the answer a desk is refused on must be the
     * answer the lock produced, and the file must say so in that order. A behavioural test cannot see this on
     * SQLite (one connection, no second writer), and a later edit that hoists the count back above
     * `DB::transaction` would leave every other case in this class green.
     */
    #[Test]
    public function the_capacity_count_is_taken_inside_the_transaction_after_the_block_is_locked(): void
    {
        $body = $this->assignStudentsBody();

        $transaction = strpos($body, 'DB::transaction(');
        $blockLock = strpos($body, "Blocks::where('blockId'");
        $count = strpos($body, "Enrolledsubjects::where('blockId'");
        $roomLock = strpos($body, "Rooms::where('roomId'");

        foreach (['DB::transaction(' => $transaction, 'the block row lock' => $blockLock,
            'the capacity count' => $count, 'the room row lock' => $roomLock] as $label => $at) {
            $this->assertNotFalse($at, "assignStudents() no longer contains {$label} — the guard has nothing to order.");
        }

        $this->assertLessThan($count, $transaction, 'the capacity count was hoisted back out of the transaction it guards.');
        $this->assertLessThan($count, $blockLock, 'the block row is no longer locked before its own capacity is read.');
        $this->assertLessThan($roomLock, $blockLock, 'the two rows are locked in the opposite order, which is the deadlock this fix removes.');

        // A lock taken BEFORE the transaction opens protects nothing: it is released when the statement ends, so a
        // second desk can still read the old count. The order above would pass with the lock hoisted out; this does not.
        $this->assertGreaterThan($transaction, $blockLock, 'the block lock was hoisted out of the transaction, where it guards nothing.');
        $this->assertGreaterThan($transaction, $roomLock, 'the room lock was hoisted out of the transaction.');

        // Both rows must be locked, not just the block: one locked row still lets two assigns of two different
        // blocks cross on the shared room.
        $this->assertSame(2, substr_count($body, 'lockForUpdate()'),
            'a row the act counts against is no longer locked, so the count is a guess again.');

        // Both locks must sit inside the transaction, not merely before each other in the file.
        $close = strpos($body, 'RetryOnDeadlock::run(');
        $this->assertNotFalse($close, 'the deadlock retry was removed, so a 1213 is once again a 500 at the desk.');
    }

    /**
     * The requested ids are taken in one canonical order so two assigns that share students cannot cross while
     * holding the block lock. Sorting is the prevention half of problem 7, and it is invisible in a sequential run.
     */
    #[Test]
    public function the_requested_ids_are_deduplicated_and_sorted_before_the_transaction_opens(): void
    {
        $body = $this->assignStudentsBody();
        $before = strpos($body, 'DB::transaction(');

        foreach (['array_unique' => 'the same enrollment twice would be counted twice',
            'sort($requested)' => 'two desks taking the same students in opposite orders is the crossing'] as $needle => $why) {
            $at = strpos($body, $needle);
            $this->assertNotFalse($at, "assignStudents() no longer calls {$needle} — {$why}.");
            $this->assertLessThan($before, $at, "{$needle} must run before the transaction opens, not inside it.");
        }
    }

    #[Test]
    public function a_second_seat_request_is_refused_and_leaves_the_record_exactly_as_it_was(): void
    {
        $desk = $this->desk();
        $this->actingAs($desk);
        [$block, $schedule] = array_values($this->blockWithSchedule(1));

        $taken = $this->assignOne($block, $schedule);
        $refused = $this->studentReady($block, $schedule);

        $response = $this->post(route('blocking.assign', $block), [
            'enrollmentIds' => [$refused->enrollmentId],
            'scheduleId' => $schedule->scheduleId,
        ]);
        $response->assertSessionHasErrors('capacity');

        // Nothing about the refused record moved: no block, no schedule, no signature.
        $this->assertNull(Enrolledsubjects::where('enrollmentId', $refused->enrollmentId)
            ->where('blockId', $block->blockId)->first());
        $this->assertEquals(0, Enrolledsubjects::where('enrollmentId', $refused->enrollmentId)
            ->whereNotNull('scheduleId')->count());
        $this->assertDatabaseHas('workflowsteps', [
            'workflowId' => $refused->enrollmentworkflow->workflowId,
            'officeId' => OfficeId::Blocking->value,
            'stepStatus' => WorkflowStepStatus::Pending->value,
            'signedBy' => null,
        ]);
        // and the seat the first desk took is still exactly one seat.
        $this->assertSame(1, Enrolledsubjects::where('blockId', $block->blockId)
            ->where('status', '!=', 'dropped')->distinct()->count('enrollmentId'));
        $this->assertNotNull($taken->enrollmentworkflow->workflowsteps()
            ->where('officeId', OfficeId::Blocking->value)->first()->signedDate);
    }

    /**
     * A desk selecting three students for a block with two seats must be told no, not told no *after* two of them
     * were seated. The guard is `current + requested > max`, so the refusal arrives before the loop — and this is
     * the reachable form of "no partial state" for this act. A mid-loop failure cannot be produced from the desk:
     * `enrollmentIds.*` is validated `exists:enrollments`, so a bad id is refused by validation and never reaches
     * the transaction, which is the first version of this case actually measuring. The transaction boundary itself
     * is guarded by shape in the first case, and the two-writer race is measured over real MySQL by
     * `storage/app/scratch/lane168-probe-last-seat-race.php`.
     */
    #[Test]
    public function a_request_larger_than_the_seats_left_seats_nobody(): void
    {
        $desk = $this->desk();
        $this->actingAs($desk);
        [$block, $schedule] = array_values($this->blockWithSchedule(2));

        $three = [$this->studentReady($block, $schedule), $this->studentReady($block, $schedule), $this->studentReady($block, $schedule)];

        $response = $this->post(route('blocking.assign', $block), [
            'enrollmentIds' => array_map(fn (Enrollments $e) => $e->enrollmentId, $three),
            'scheduleId' => $schedule->scheduleId,
        ]);

        $response->assertSessionHasErrors('capacity');
        $this->assertSame(0, Enrolledsubjects::where('blockId', $block->blockId)->count(),
            'a refused assign still seated someone.');
        $this->assertSame(0, Enrolledsubjects::where('scheduleId', $schedule->scheduleId)->count(),
            'a refused assign still stamped a schedule on a row it never committed to.');
        foreach ($three as $row) {
            $this->assertDatabaseHas('workflowsteps', [
                'workflowId' => $row->enrollmentworkflow->workflowId,
                'officeId' => OfficeId::Blocking->value,
                'stepStatus' => WorkflowStepStatus::Pending->value,
                'signedBy' => null,
            ]);
        }

        // And the two seats the block really has are still there to be taken by a request that fits.
        $fits = $this->post(route('blocking.assign', $block), [
            'enrollmentIds' => [$three[0]->enrollmentId, $three[1]->enrollmentId],
            'scheduleId' => $schedule->scheduleId,
        ]);
        $fits->assertSessionHasNoErrors();
        $this->assertSame(2, Enrolledsubjects::where('blockId', $block->blockId)
            ->distinct()->count('enrollmentId'));
    }

    /**
     * A seat a desk released is not a seat the block still holds — the count that the lock protects excludes
     * dropped rows, so a re-assign after a drop must succeed. Pinned because the count's DEFINITION is what two
     * racing transactions disagree about; if it ever grows back to counting dropped rows the race returns at once.
     */
    #[Test]
    public function a_dropped_subject_line_does_not_consume_a_seat(): void
    {
        $desk = $this->desk();
        $this->actingAs($desk);
        [$block, $schedule] = array_values($this->blockWithSchedule(1));

        $taken = $this->assignOne($block, $schedule);
        Enrolledsubjects::where('enrollmentId', $taken->enrollmentId)->update(['status' => 'dropped']);

        $next = $this->studentReady($block, $schedule);
        $response = $this->post(route('blocking.assign', $block), [
            'enrollmentIds' => [$next->enrollmentId],
            'scheduleId' => $schedule->scheduleId,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(1, Enrolledsubjects::where('blockId', $block->blockId)
            ->where('status', '!=', 'dropped')->distinct()->count('enrollmentId'));
    }

    #[Test]
    public function the_helper_retries_a_deadlock_and_a_lock_wait_and_nothing_else(): void
    {
        foreach ([RetryOnDeadlock::DEADLOCK => '1213 deadlock',
            RetryOnDeadlock::LOCK_WAIT_TIMEOUT => '1205 lock wait timeout'] as $code => $label) {
            $calls = 0;
            $result = RetryOnDeadlock::run(function () use (&$calls, $code) {
                $calls++;
                if ($calls < 2) {
                    throw $this->queryException($code);
                }

                return 'written';
            }, attempts: 3, baseDelayMs: 0);

            $this->assertSame('written', $result, "{$label} was not retried, so the desk meets a 500 for a condition that clears.");
            $this->assertSame(2, $calls, "{$label} retried the wrong number of times.");
        }

        // Every other driver condition must travel out on the first pass. `expectException` cannot do this in a
        // loop: the first throw it sees ends the test, so the rest of the list would never be measured.
        foreach ([1062 => 'duplicate key', 1146 => 'no such table', 2002 => 'cannot connect', 1451 => 'foreign key'] as $code => $label) {
            $calls = 0;
            $caught = null;
            try {
                RetryOnDeadlock::run(function () use (&$calls, $code) {
                    $calls++;
                    throw $this->queryException($code);
                }, attempts: 3, baseDelayMs: 0);
            } catch (QueryException $e) {
                $caught = $code;
            } catch (\Throwable $e) {
                $caught = get_class($e);
            }

            $this->assertSame($code, $caught, "{$label} was not propagated as a QueryException.");
            $this->assertSame(1, $calls, "{$label} was repeated, which is a silent loop around a refusal that will never clear.");
        }
    }

    #[Test]
    public function a_duplicate_key_is_never_retried_and_reaches_the_desk_unchanged(): void
    {
        $calls = 0;
        try {
            RetryOnDeadlock::run(function () use (&$calls) {
                $calls++;
                throw $this->queryException(1062, 'Duplicate entry for key uq_notifications_dedupe');
            }, attempts: 3, baseDelayMs: 0);
            $this->fail('a 1062 was swallowed by the retry helper.');
        } catch (QueryException $e) {
            $this->assertSame(1062, RetryOnDeadlock::codeOf($e));
        }

        $this->assertSame(1, $calls, 'a condition that will never clear was repeated, which is a silent loop with a name.');
    }

    #[Test]
    public function exhausting_the_attempts_refuses_with_a_message_that_says_nothing_was_saved(): void
    {
        $calls = 0;
        try {
            RetryOnDeadlock::run(function () use (&$calls) {
                $calls++;
                throw $this->queryException(RetryOnDeadlock::DEADLOCK);
            }, attempts: 3, baseDelayMs: 0);
            $this->fail('the helper ran an unbounded number of times.');
        } catch (ValidationException $e) {
            $this->assertSame(3, $calls);
            $this->assertArrayHasKey('capacity', $e->errors());
            $message = $e->errors()['capacity'][0];
            $this->assertStringContainsString('Nothing was saved', $message,
                'the desk was told to retry without being told the record is unchanged.');
            $this->assertStringContainsString('3 attempts', $message);
        }
    }

    /** An act that is not one transaction must not be wrapped by this helper — the guard is on the argument. */
    #[Test]
    public function the_helper_refuses_a_run_that_could_never_succeed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RetryOnDeadlock::run(fn () => 'unreachable', attempts: 0);
    }

    /**
     * A validation refusal thrown inside the transaction is not a QueryException, so it must travel out on the
     * first pass rather than be repeated until the desk gives up.
     */
    #[Test]
    public function a_capacity_refusal_is_never_retried(): void
    {
        $calls = 0;
        try {
            RetryOnDeadlock::run(function () use (&$calls) {
                $calls++;
                throw ValidationException::withMessages(['capacity' => 'Block capacity exceeded.']);
            }, attempts: 3, baseDelayMs: 0);
            $this->fail('the capacity refusal was swallowed.');
        } catch (ValidationException $e) {
            $this->assertSame(1, $calls, 'a refusal the desk must read was retried three times before it was shown.');
            $this->assertStringContainsString('Block capacity exceeded', $e->errors()['capacity'][0]);
        }
    }

    #[Test]
    public function the_code_is_read_from_the_driver_when_it_is_the_only_place_it_lives(): void
    {
        $withInfo = $this->queryException(1213);
        $this->assertSame(1213, RetryOnDeadlock::codeOf($withInfo));

        // Some drivers leave errorInfo empty; the code then has to come from getCode(), not from a guess.
        $pdo = new PDOException('deadlock');
        $pdo->errorInfo = [];
        $bare = new QueryException('mysql', 'update blocks set ...', [], $pdo);
        $this->assertSame(0, RetryOnDeadlock::codeOf($bare), 'a driver that answered nothing was read as a retryable code.');
        $this->assertFalse(RetryOnDeadlock::isRetryable(RetryOnDeadlock::codeOf($bare)));
    }

    /**
     * The write guard and the read audit must use one definition of "held", or the reconciliation reports the desk's
     * own legitimate work as damage. This case puts a second seat in by writing the row directly — which is exactly
     * the restored-dump / hand-edited-row state the audit rule exists to catch, and the one thing the locked act can
     * no longer produce — and requires `ems:audit:record-states` to name that block on its own FAIL line.
     */
    #[Test]
    public function the_audit_names_a_block_holding_more_students_than_it_declares(): void
    {
        $desk = $this->desk();
        $this->actingAs($desk);
        [$block, $schedule] = array_values($this->blockWithSchedule(1));

        $this->assignOne($block, $schedule);
        $extra = $this->studentReady($block, $schedule);

        // The desk could not do this: the guard refuses. Written under the table, it is the state the audit weighs.
        Enrolledsubjects::where('enrollmentId', $extra->enrollmentId)
            ->where('status', '!=', 'dropped')
            ->update(['blockId' => $block->blockId]);

        $code = Artisan::call('ems:audit:record-states');
        $out = Artisan::output();

        $this->assertSame(1, $code, 'a block over its own capacity did not fail the reconciliation run.');
        $this->assertMatchesRegularExpression(
            '/FAIL\s+A block holding more students than it declares/',
            $out,
            'the audit ran but did not name the over-capacity block on its own line.'
        );

        // And the same rule reads clean once the seat is released — a dropped line is not a held seat, which is the
        // half of the definition the write guard shares with it.
        Enrolledsubjects::where('enrollmentId', $extra->enrollmentId)->update(['status' => 'dropped']);
        Artisan::call('ems:audit:record-states');
        $out = Artisan::output();

        $this->assertMatchesRegularExpression(
            '/ok\s+A block holding more students than it declares/',
            $out,
            'a released seat was still counted as held, so the audit and the guard disagree about the word.'
        );
    }

    // ---- cast -------------------------------------------------------------------------------------------

    private function queryException(int $code, string $message = 'driver error'): QueryException
    {
        $pdo = new PDOException("SQLSTATE[HY000] [{$code}] {$message}");
        $pdo->errorInfo = ['HY000', $code, $message];

        return new QueryException('mysql', 'update `enrolledsubjects` set `blockId` = ? where 1 = 1', [1], $pdo);
    }

    /** The method's own text, so a shape guard reads the act it names rather than the whole file. */
    private function assignStudentsBody(): string
    {
        $file = file_get_contents(base_path('app/Http/Controllers/Blocking/BlockingController.php'));
        $start = strpos($file, 'public function assignStudents(');
        $this->assertNotFalse($start, 'assignStudents() is gone, so this class guards a method that no longer exists.');
        $rest = substr($file, $start + 1);
        $next = strpos($rest, "\n    public function ");
        $end = $next === false ? strlen($rest) : $next;

        return substr($file, $start, $end);
    }

    private function desk(): Staffusers
    {
        $staff = Staffusers::factory()->make([
            'officeId' => OfficeId::Blocking->value,
            'role' => StaffRole::OfficeHead,
            'employeeNo' => 'EMP-'.uniqid(),
            'username' => 'blocking_'.uniqid(),
            'email' => 'blocking_'.uniqid().'@example.com',
        ]);
        unset($staff->remember_token);
        $staff->save();
        $staff->assignRole(ucfirst(StaffRole::OfficeHead->value));

        return $staff;
    }

    /** @return array{block: Blocks, schedule: Schedules, room: Rooms} */
    private function blockWithSchedule(int $maxStudents): array
    {
        $block = Blocks::create([
            'courseId' => $this->courseId, 'termId' => $this->termId, 'yearLevel' => 1,
            'blockName' => 'ATOMIC-'.uniqid(), 'maxStudents' => $maxStudents,
        ]);
        $schedule = Schedules::create([
            'blockId' => $block->blockId,
            'subjectId' => Subjects::where('subjectCode', 'CS101')->value('subjectId'),
            'instructorId' => Staffusers::where('officeId', '!=', OfficeId::Registrar->value)->value('userId'),
            'roomId' => Rooms::value('roomId'),
        ]);
        Schedulemeetings::create([
            'scheduleId' => $schedule->scheduleId, 'dayOfWeek' => 'Monday',
            'startTime' => '09:00:00', 'endTime' => '10:00:00',
        ]);

        return ['block' => $block, 'schedule' => $schedule, 'room' => Rooms::first()];
    }

    /** A student whose own record says the Blocking box is the next thing that may be signed. */
    private function studentReady(Blocks $block, Schedules $schedule): Enrollments
    {
        $student = Students::create([
            'schoolIdNumber' => 'ATOMIC-'.uniqid(), 'lastName' => 'Seat', 'firstName' => 'Last',
            'middleName' => 'M', 'suffix' => 'N/A', 'gender' => 'male', 'birthdate' => '2004-01-01',
            'birthplace' => 'Test City', 'citizenship' => 'Filipino', 'civilStatus' => 'single',
            'religionId' => 1, 'contactNumber' => '09171234567', 'telephoneNumber' => null,
            'email' => 'seat_'.uniqid().'@example.com', 'username' => 'seat_'.uniqid(),
            'passwordHash' => bcrypt('password123'), 'status' => 'active',
        ]);
        $enrollment = Enrollments::create([
            'studentId' => $student->studentId, 'courseId' => $this->courseId, 'termId' => $this->termId,
            'yearLevel' => 1, 'studentType' => StudentType::FirstYear, 'enrollmentType' => EnrollmentType::New,
            'academicStanding' => 'regular', 'enrollmentStatus' => EnrollmentStatus::Enrolled,
            'evaluatedBy' => Staffusers::where('officeId', OfficeId::Guidance->value)->value('userId') ?? 1,
        ]);
        foreach (Subjects::pluck('subjectId') as $subjectId) {
            Enrolledsubjects::create([
                'enrollmentId' => $enrollment->enrollmentId, 'subjectId' => $subjectId, 'status' => 'confirmed',
            ]);
        }

        $workflow = Enrollmentworkflow::create([
            'enrollmentId' => $enrollment->enrollmentId, 'currentStep' => 4,
            'workflowStatus' => WorkflowStatus::InProgress,
        ]);
        $signer = Staffusers::where('officeId', OfficeId::Registrar->value)->value('userId');
        foreach ([[4, 1], [3, 2], [2, 3], [1, 4], [5, 5], [11, 6], [22, 7]] as [$officeId, $order]) {
            Workflowsteps::create([
                'workflowId' => $workflow->workflowId, 'officeId' => $officeId, 'stepOrder' => $order,
                'stepStatus' => $order <= 4 ? WorkflowStepStatus::Completed : WorkflowStepStatus::Pending,
                'signedBy' => $order <= 4 ? $signer : null,
                'signedDate' => $order <= 4 ? now() : null,
            ]);
        }

        return $enrollment;
    }

    private function assignOne(Blocks $block, Schedules $schedule): Enrollments
    {
        $enrollment = $this->studentReady($block, $schedule);
        $this->post(route('blocking.assign', $block), [
            'enrollmentIds' => [$enrollment->enrollmentId],
            'scheduleId' => $schedule->scheduleId,
        ])->assertSessionHasNoErrors();

        return $enrollment;
    }
}
