<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\StudentType;
use App\Events\EnrollmentStatusChanged;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Notifications;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
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
 * Second, addressing: §19 of the documentation states the position these tests pin —
 * the bell reads rows addressed to App\Models\Staffusers, while every writer in the
 * application addresses App\Models\Students — so nothing the system produces is ever
 * displayed, and who is actually meant to receive these messages (C-9) is still
 * SEAIT's to decide. The read side is therefore tested against rows placed the way
 * the read side expects them, and the last test measures the gap rather than
 * papering over it.
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

        foreach ([1, 2, 8] as $officeId) {
            Offices::firstOrCreate(['officeId' => $officeId], ['officeName' => 'Office '.$officeId]);
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

        $this->assertCount(1, $written, 'The event was handled more than once, so the student has duplicates.');

        $message = $written->first()->data;
        $this->assertSame('Payment received. Proceed to Registrar for approval.', $message['message']);
        $this->assertSame($enrollment->enrollmentId, $message['enrollmentId']);
        $this->assertSame('assessed', $message['fromStatus']);
        $this->assertSame('paid', $message['toStatus']);
    }

    #[Test]
    public function a_message_the_application_actually_writes_is_invisible_to_every_bell(): void
    {
        // The real writer, driven by the real event: SendEnrollmentNotification
        // addresses the row at the student, which is the only notifiable the two
        // listeners ever use (N-1, with the recipient itself logged as C-9).
        $student = $this->student();
        $enrollment = $this->enrollment($student);

        event(new EnrollmentStatusChanged($enrollment, 'assessed', 'paid', $this->registrar, ''));

        $written = Notifications::where('type', 'enrollment_status_changed')->sole();
        $this->assertSame('App\Models\Students', $written->notifiable_type);
        $this->assertSame($student->studentId, (int) $written->notifiable_id);
        $this->assertIsArray($written->data);

        // Every desk's bell filters on App\Models\Staffusers, so the only message the
        // system really produces reaches nobody.
        foreach ([$this->registrar, $this->cashier] as $desk) {
            $this->actingAs($desk)
                ->getJson(route('notifications.index'))
                ->assertOk()
                ->assertJsonCount(0, 'notifications')
                ->assertJson(['unreadCount' => 0]);
        }
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
