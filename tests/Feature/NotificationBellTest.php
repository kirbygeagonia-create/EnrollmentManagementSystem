<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\OfficeId;
use App\Enums\StudentType;
use App\Events\EnrollmentStatusChanged;
use App\Events\WorkflowStepSigned;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Enrollmentworkflow;
use App\Models\Notifications;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use App\Services\WorkflowService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The bell in the top bar and its three JSON endpoints.
 *
 * Two things are pinned here.
 *
 * First, wiring: Application::configure() turns on listener discovery by default, and
 * the project also maps the same listeners in app/Providers/EventServiceProvider.php.
 * Both registrations ran, so every EnrollmentStatusChanged and WorkflowStepSigned was
 * handled twice and wrote two identical notification rows per status change. Discovery
 * is now off in bootstrap/app.php, and one_status_change_writes_exactly_one_message
 * is the guard against it coming back.
 *
 * Second, addressing: §19 used to record this as an open question (C-9) because the
 * bell reads rows addressed to App\Models\Staffusers while every writer addressed
 * App\Models\Students — so nothing the system produced was ever displayed. Both
 * writers now resolve the desk that inherits the record through
 * App\Support\WorkflowInbox, and the last two tests drive the real event to prove a
 * notice lands in a bell that can actually read it.
 */
class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $registrar;

    private Staffusers $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        // Every office the workflow vocabulary can name, because a workflow form
        // writes one box per desk and each box carries an officeId the schema
        // enforces.
        foreach (OfficeId::cases() as $office) {
            Offices::firstOrCreate(['officeId' => $office->value], ['officeName' => 'Office '.$office->value]);
        }

        $this->registrar = $this->staff(1);
        $this->cashier = $this->staff(2);
    }

    private function staff(int $officeId): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => $officeId,
            'employeeNo' => 'EMP-BELL-'.uniqid(),
            'username' => 'bell_'.$officeId.'_'.uniqid(),
            'email' => 'bell_'.$officeId.'_'.uniqid().'@example.com',
        ]);

        return $staff;
    }

    private function notify(Staffusers $for, string $message, string $at = '2026-09-01 08:00:00', ?string $readAt = null): Notifications
    {
        DB::table('notifications')->insert([
            'type' => 'workflow_step_signed',
            'notifiable_type' => Staffusers::class,
            'notifiable_id' => $for->userId,
            'data' => json_encode(['message' => $message]),
            'read_at' => $readAt,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        return Notifications::orderByDesc('id')->firstOrFail();
    }

    #[Test]
    public function the_bell_shows_only_the_signed_in_account_s_own_messages(): void
    {
        $mine = $this->notify($this->registrar, 'Registrar box signed', '2026-09-02 08:00:00');
        $older = $this->notify($this->registrar, 'Admission box signed', '2026-09-01 08:00:00');
        $this->notify($this->cashier, 'Cashier box signed', '2026-09-03 08:00:00');

        $response = $this->actingAs($this->registrar)->getJson(route('notifications.index'));

        $response->assertOk()->assertJsonCount(2, 'notifications')->assertJson(['unreadCount' => 2]);

        // Newest first, and the other desk's messages are not in the payload at all
        $this->assertSame($mine->id, $response->json('notifications.0.id'));
        $this->assertSame($older->id, $response->json('notifications.1.id'));
        $this->assertSame('Registrar box signed', $response->json('notifications.0.data.message'));
    }

    #[Test]
    public function the_bell_caps_the_list_at_twenty_but_counts_everything_unread(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->notify($this->registrar, "Message {$i}", sprintf('2026-09-%02d 08:00:00', $i));
        }

        $response = $this->actingAs($this->registrar)
            ->getJson(route('notifications.index'))
            ->assertOk();

        $this->assertCount(20, $response->json('notifications'));
        $this->assertSame(25, $response->json('unreadCount'));
        $this->assertSame('Message 25', $response->json('notifications.0.data.message'));
    }

    #[Test]
    public function marking_one_message_read_takes_it_out_of_the_badge(): void
    {
        $first = $this->notify($this->registrar, 'First', '2026-09-01 08:00:00');
        $second = $this->notify($this->registrar, 'Second', '2026-09-02 08:00:00');

        $this->actingAs($this->registrar)
            ->postJson(route('notifications.read', $first))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNull($second->fresh()->read_at);

        $this->actingAs($this->registrar)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJson(['unreadCount' => 1]);
    }

    #[Test]
    public function another_account_cannot_mark_your_message_read(): void
    {
        $mine = $this->notify($this->registrar, 'Yours', '2026-09-01 08:00:00');

        // The controller filters on ownership instead of authorizing, so the write
        // cannot land — but it still answers {"ok":true} whether it acted or not.
        // Pinned: any future fix should return a real refusal, not a quieter lie.
        $this->actingAs($this->cashier)
            ->postJson(route('notifications.read', $mine))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertNull($mine->fresh()->read_at);

        $this->actingAs($this->registrar)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJson(['unreadCount' => 1]);
    }

    #[Test]
    public function mark_all_read_clears_only_the_requester_s_queue(): void
    {
        $this->notify($this->registrar, 'Mine one');
        $this->notify($this->registrar, 'Mine two', '2026-09-02 08:00:00');
        $theirs = $this->notify($this->cashier, 'Not mine', '2026-09-03 08:00:00');

        $this->actingAs($this->registrar)
            ->postJson(route('notifications.read-all'))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(0, Notifications::where('notifiable_type', Staffusers::class)
            ->where('notifiable_id', $this->registrar->userId)
            ->whereNull('read_at')
            ->count());

        $this->assertNull($theirs->fresh()->read_at);
    }

    #[Test]
    public function one_status_change_writes_exactly_one_message(): void
    {
        $student = $this->student();
        $enrollment = $this->enrollment($student);

        event(new EnrollmentStatusChanged($enrollment, 'assessed', 'paid', $this->registrar, ''));

        $written = Notifications::where('type', 'enrollment_status_changed')->get();

        $this->assertCount(1, $written, 'The event was handled more than once, so the desk has duplicates.');

        $message = $written->first()->data;
        $this->assertStringContainsString($student->lastName, $message['message']);
        $this->assertStringContainsString('payment settled', $message['message']);
        $this->assertStringContainsString('Registrar approves next', $message['message']);
        $this->assertSame($enrollment->enrollmentId, $message['enrollmentId']);
        $this->assertSame('assessed', $message['fromStatus']);
        $this->assertSame('paid', $message['toStatus']);
    }

    #[Test]
    public function the_message_a_status_change_produces_reaches_the_desk_that_inherits_the_record(): void
    {
        // Both writers used to address every row at App\Models\Students, the only
        // notifiable they knew, while the bell filters on App\Models\Staffusers —
        // so the channel was dead at both ends (N-1, and C-9 asked who should
        // really receive these). They now resolve the office that owns the next
        // pending box, or the desk the status hands the record to.
        $student = $this->student();
        $enrollment = $this->enrollment($student);

        event(new EnrollmentStatusChanged($enrollment, 'assessed', 'paid', $this->registrar, ''));

        $written = Notifications::where('type', 'enrollment_status_changed')->sole();
        $this->assertSame(Staffusers::class, $written->notifiable_type);
        $this->assertSame($this->registrar->userId, (int) $written->notifiable_id);
        $this->assertIsArray($written->data);

        // 'paid' hands the record to the Registrar, so that desk reads it.
        $this->actingAs($this->registrar)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonCount(1, 'notifications')
            ->assertJson(['unreadCount' => 1]);

        // A desk that is not next in line is not pinged for someone else's turn.
        $this->actingAs($this->cashier)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonCount(0, 'notifications')
            ->assertJson(['unreadCount' => 0]);
    }

    #[Test]
    public function a_signed_box_warns_the_last_desk_that_it_is_the_last(): void
    {
        $idOffice = $this->staff(OfficeId::IdOffice->value);

        $enrollment = $this->enrollment($this->student());
        $justSigned = $this->boxes($this->signedThrough($enrollment, 6))[5];

        event(new WorkflowStepSigned($justSigned->workflow, $justSigned, $this->registrar));

        $written = Notifications::where('type', 'workflow_step_signed')->sole();

        $this->assertSame(Staffusers::class, $written->notifiable_type);
        $this->assertSame($idOffice->userId, (int) $written->notifiable_id, 'Only the desk still waiting is told.');
        $this->assertStringContainsString('Phase 6 of 7', $written->data['message']);
        $this->assertStringContainsString('Last desk before the enrollment form closes.', $written->data['message']);
        $this->assertTrue($written->data['lastDesk']);
    }

    #[Test]
    public function a_form_signed_through_notifies_the_records_custodian_that_it_is_complete(): void
    {
        $enrollment = $this->enrollment($this->student());
        $workflow = $this->signedThrough($enrollment, 7);

        event(new WorkflowStepSigned($workflow, $this->boxes($workflow)[6], $this->registrar));

        $signed = Notifications::where('type', 'workflow_step_signed')->count();
        $completed = Notifications::where('type', 'workflow_completed')->sole();

        $this->assertSame(0, $signed, 'Nothing is pending, so no desk is waiting on it.');
        $this->assertSame($this->registrar->userId, (int) $completed->notifiable_id);
        $this->assertStringContainsString('form complete', $completed->data['message']);
        $this->assertStringContainsString('all 7 desks signed', $completed->data['message']);
    }

    /**
     * Sign the first $count workflow boxes straight on the table — these tests
     * are about who is told, not about the order signStep enforces.
     */
    private function signedThrough(Enrollments $enrollment, int $count): Enrollmentworkflow
    {
        $workflow = app(WorkflowService::class)->createWorkflow($enrollment);

        foreach ($workflow->workflowsteps()->orderBy('stepOrder')->get()->take($count) as $box) {
            DB::table('workflowsteps')->where('workflowStepId', $box->workflowStepId)->update([
                'stepStatus' => 'completed',
                'signedBy' => $this->registrar->userId,
                'signedDate' => now(),
            ]);
        }

        return $workflow;
    }

    private function boxes(Enrollmentworkflow $workflow)
    {
        return $workflow->workflowsteps()->orderBy('stepOrder')->get();
    }

    private function student(): Students
    {
        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        return Students::create([
            'schoolIdNumber' => 'BELL-'.uniqid(),
            'lastName' => 'Notified',
            'firstName' => 'Student',
            'middleName' => 'B',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
            'email' => 'bell_student_'.uniqid().'@example.com',
            'username' => 'bell_student_'.uniqid(),
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
}
