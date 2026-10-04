<?php

namespace Tests\Feature\Clearance;

use App\Enums\ClearanceOverallStatus;
use App\Enums\ClearancePeriodStatus;
use App\Enums\StaffRole;
use App\Models\Academicterms;
use App\Models\Academicyears;
use App\Models\Clearanceperiods;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Studentclearances;
use App\Models\Students;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The clearance calendar. §17 describes clearance as a recurring end-of-term
 * process, and every slip a student can draw is scoped to one of these rows, so the
 * window and its open/closed status are what actually gate the desk.
 *
 * `updatePeriod` deliberately accepts only the status: the screen shows the dates
 * read-only, and a posted date is ignored rather than written — pinned below so a
 * future edit cannot silently re-open a closed window through this route. Moving a
 * date is its own action (ruling 13's extend), which is refused while the window is
 * closed, and closing itself waits for the offices to finish (ruling 16).
 */
class ClearancePeriodTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $officer;

    private Academicterms $term;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        foreach ([1, 2, 3, 4, 5, 11, 22] as $officeId) {
            Offices::firstOrCreate(['officeId' => $officeId], ['officeName' => 'Office '.$officeId]);
        }

        // A student row needs a religion on MySQL, where the foreign key is enforced.
        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        $year = Academicyears::create([
            'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01',
            'endDate' => '2027-03-31',
        ]);

        $this->term = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ]);

        // OfficeHead is the role the seater gives a clearance office head, and it is
        // the only seeded role carrying clearance.periods.manage.
        $this->officer = $this->staffInOffice(1, 'OfficeHead');
    }

    private function staffInOffice(int $officeId, string $spatieRole): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => $officeId,
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-PERIOD-'.uniqid(),
            'username' => 'period_'.$officeId.'_'.uniqid(),
            'email' => 'period_'.$officeId.'_'.uniqid().'@example.com',
        ]);
        $staff->assignRole($spatieRole);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function periodPayload(array $overrides = []): array
    {
        return array_merge([
            'termId' => $this->term->termId,
            'clearanceStartDate' => '2026-09-01',
            'clearanceEndDate' => '2026-10-31',
            'periodStatus' => 'open',
        ], $overrides);
    }

    #[Test]
    public function a_period_opened_on_screen_is_the_period_the_desk_then_lists(): void
    {
        $this->actingAs($this->officer)
            ->post(route('clearance.periods.store'), $this->periodPayload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $period = Clearanceperiods::sole();
        $this->assertSame($this->term->termId, $period->termId);
        $this->assertSame('open', $period->periodStatus->value);

        $this->actingAs($this->officer)
            ->get(route('clearance.periods'))
            ->assertInertia(fn ($page) => $page
                ->component('Clearance/Periods')
                ->has('periods', 1)
                ->where('periods.0.clearancePeriodId', $period->clearancePeriodId)
                ->where('periods.0.periodStatus', 'open')
                ->where('periods.0.term.academicYear.yearLabel', '2026-2027')
            );
    }

    #[Test]
    public function the_window_is_refused_before_it_is_filed(): void
    {
        $cases = [
            'clearanceEndDate' => '2026-08-31',  // ends before it starts
            'termId' => 99999,                   // no such term
            'periodStatus' => 'extended',        // the screen only offers open/closed
            'clearanceStartDate' => '',
        ];

        foreach ($cases as $field => $badValue) {
            $this->actingAs($this->officer)
                ->from(route('clearance.periods'))
                ->post(route('clearance.periods.store'), $this->periodPayload([$field => $badValue]))
                ->assertSessionHasErrors($field);
        }

        $this->assertSame(0, Clearanceperiods::count());
    }

    #[Test]
    public function the_status_radio_moves_a_period_open_then_closed(): void
    {
        $period = Clearanceperiods::create($this->periodPayload(['periodStatus' => 'open']));

        $this->actingAs($this->officer)
            ->patch(route('clearance.periods.update', $period), ['periodStatus' => 'closed'])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame('closed', $period->fresh()->periodStatus->value);

        $this->actingAs($this->officer)
            ->patch(route('clearance.periods.update', $period), ['periodStatus' => 'open'])
            ->assertSessionHasNoErrors();
        $this->assertSame('open', $period->fresh()->periodStatus->value);

        // Anything else the request carries is not written: the dates shown on the
        // screen are read-only, and this route cannot move a window.
        $this->actingAs($this->officer)
            ->patch(route('clearance.periods.update', $period), [
                'periodStatus' => 'closed',
                'clearanceEndDate' => '2027-12-31',
            ])
            ->assertSessionHasNoErrors();

        $period->refresh();
        $this->assertSame('closed', $period->periodStatus->value);
        $this->assertSame('2026-10-31', $period->clearanceEndDate->toDateString());
    }

    #[Test]
    public function a_status_outside_open_and_closed_never_reaches_the_row(): void
    {
        $period = Clearanceperiods::create($this->periodPayload());

        $this->actingAs($this->officer)
            ->patch(route('clearance.periods.update', $period), ['periodStatus' => 'extended'])
            ->assertSessionHasErrors('periodStatus');

        $this->assertSame('open', $period->fresh()->periodStatus->value);
    }

    #[Test]
    public function a_desk_without_the_period_permission_cannot_open_or_close_one(): void
    {
        $viewer = $this->staffInOffice(1, 'Staff');
        $period = Clearanceperiods::create($this->periodPayload());

        $this->actingAs($viewer)
            ->post(route('clearance.periods.store'), $this->periodPayload())
            ->assertForbidden();

        $this->actingAs($viewer)
            ->patch(route('clearance.periods.update', $period), ['periodStatus' => 'closed'])
            ->assertForbidden();

        $this->assertSame(1, Clearanceperiods::count());
        $this->assertSame('open', $period->fresh()->periodStatus->value);
    }

    #[Test]
    public function nothing_currently_stops_two_open_periods_in_the_same_term(): void
    {
        $this->actingAs($this->officer)
            ->post(route('clearance.periods.store'), $this->periodPayload())
            ->assertSessionHasNoErrors();

        // Pinned as it stands, not as it should be: BR33 grants one free slip per
        // student per period, so a second open period for the same term is a second
        // free slip. clearanceperiods has no per-term rule and storePeriod adds none
        // — whether the Registrar wants one is an open decision, not a code bug.
        $this->actingAs($this->officer)
            ->post(route('clearance.periods.store'), $this->periodPayload([
                'clearanceStartDate' => '2026-11-01',
                'clearanceEndDate' => '2026-12-15',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Clearanceperiods::where('termId', $this->term->termId)->count());
    }

    // ------------------------------------------------------------- close & extend

    private function slipIn(Clearanceperiods $period, ClearanceOverallStatus $status = ClearanceOverallStatus::Pending): Studentclearances
    {
        $student = Students::create([
            'schoolIdNumber' => 'PERIOD-'.uniqid(),
            'lastName' => 'Period',
            'firstName' => 'Student',
            'middleName' => 'P',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'semestersCompleted' => 1,
            'yearsInInstitution' => 1,
            'email' => 'period_student_'.uniqid().'@example.com',
            'username' => 'period_student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        return Studentclearances::create([
            'studentId' => $student->studentId,
            'clearancePeriodId' => $period->clearancePeriodId,
            'overallStatus' => $status,
        ]);
    }

    #[Test]
    public function closing_is_refused_while_clearances_in_the_window_are_still_pending(): void
    {
        $period = Clearanceperiods::create($this->periodPayload());
        $this->slipIn($period);
        $this->slipIn($period);

        // Ruling 16: a window does not close on top of the offices' unfinished work,
        // and the refusal says how many are unfinished rather than leaving the desk
        // to count them.
        $this->actingAs($this->officer)
            ->patch(route('clearance.periods.update', $period), ['periodStatus' => 'closed'])
            ->assertSessionHasErrors('periodStatus');

        $this->assertStringContainsString(
            '2 clearance(s)',
            session('errors')->first('periodStatus')
        );
        $this->assertSame('open', $period->fresh()->periodStatus->value);
    }

    #[Test]
    public function a_window_whose_clearances_are_all_decided_closes_and_reopens(): void
    {
        $period = Clearanceperiods::create($this->periodPayload());
        $this->slipIn($period, ClearanceOverallStatus::Approved);
        $this->slipIn($period, ClearanceOverallStatus::Rejected);

        $this->actingAs($this->officer)
            ->patch(route('clearance.periods.update', $period), ['periodStatus' => 'closed'])
            ->assertSessionHasNoErrors();
        $this->assertSame('closed', $period->fresh()->periodStatus->value);

        // Only Pending work holds the window open; a decided slip of any outcome does not.
        $this->actingAs($this->officer)
            ->patch(route('clearance.periods.update', $period), ['periodStatus' => 'open'])
            ->assertSessionHasNoErrors();
        $this->assertSame('open', $period->fresh()->periodStatus->value);
    }

    #[Test]
    public function extending_a_window_moves_the_end_date_out_and_keeps_it_taking_slips(): void
    {
        $period = Clearanceperiods::create($this->periodPayload());

        $this->actingAs($this->officer)
            ->patch(route('clearance.periods.extend', $period), ['clearanceEndDate' => '2026-12-20'])
            ->assertSessionHasNoErrors();

        $period->refresh();
        $this->assertSame('extended', $period->periodStatus->value);
        $this->assertSame('2026-12-20', $period->clearanceEndDate->toDateString());
        $this->assertTrue($period->isAccepting());

        // The point of the extension: the desk still draws slips in this window.
        $student = Students::create([
            'schoolIdNumber' => 'EXT-'.uniqid(),
            'lastName' => 'Extended',
            'firstName' => 'Student',
            'middleName' => 'E',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'semestersCompleted' => 1,
            'yearsInInstitution' => 1,
            'email' => 'ext_student_'.uniqid().'@example.com',
            'username' => 'ext_student_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->actingAs($this->officer)
            ->post(route('clearance.slip.generate'), [
                'studentId' => $student->studentId,
                'clearancePeriodId' => $period->clearancePeriodId,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('studentclearances', [
            'studentId' => $student->studentId,
            'clearancePeriodId' => $period->clearancePeriodId,
        ]);
    }

    #[Test]
    public function an_extension_has_to_move_the_date_forward_and_a_closed_window_is_reopened_first(): void
    {
        $period = Clearanceperiods::create($this->periodPayload());

        // Not later than where the window already stops is a shortening wearing the
        // word "extend".
        $this->actingAs($this->officer)
            ->patch(route('clearance.periods.extend', $period), ['clearanceEndDate' => '2026-10-31'])
            ->assertSessionHasErrors('clearanceEndDate');
        $this->assertSame('open', $period->fresh()->periodStatus->value);

        $this->actingAs($this->officer)
            ->patch(route('clearance.periods.extend', $period), ['clearanceEndDate' => ''])
            ->assertSessionHasErrors('clearanceEndDate');

        $closed = Clearanceperiods::create($this->periodPayload(['periodStatus' => 'closed']));
        $this->actingAs($this->officer)
            ->patch(route('clearance.periods.extend', $closed), ['clearanceEndDate' => '2027-01-31'])
            ->assertSessionHasErrors('clearanceEndDate');
        $this->assertSame('closed', $closed->fresh()->periodStatus->value);
    }

    #[Test]
    public function a_desk_without_the_period_permission_cannot_extend_one(): void
    {
        $viewer = $this->staffInOffice(1, 'Staff');
        $period = Clearanceperiods::create($this->periodPayload());

        $this->actingAs($viewer)
            ->patch(route('clearance.periods.extend', $period), ['clearanceEndDate' => '2026-12-20'])
            ->assertForbidden();

        $this->assertSame('open', $period->fresh()->periodStatus->value);
        $this->assertSame('2026-10-31', $period->fresh()->clearanceEndDate->toDateString());
    }

    #[Test]
    public function an_extended_window_is_the_window_the_desk_and_the_registrar_read(): void
    {
        $this->actingAs($this->officer)
            ->post(route('clearance.periods.store'), $this->periodPayload())
            ->assertSessionHasNoErrors();

        $period = Clearanceperiods::sole();
        $period->update(['periodStatus' => ClearancePeriodStatus::Extended]);

        // One lookup answers "is clearance season?" for every desk, so an extension
        // cannot be invisible to the gate that blocks on a missing window (ruling 4).
        $this->assertSame($period->clearancePeriodId, Clearanceperiods::accepting()->sole()->clearancePeriodId);

        $period->update(['periodStatus' => ClearancePeriodStatus::Closed]);
        $this->assertSame(0, Clearanceperiods::accepting()->count());
    }
}
