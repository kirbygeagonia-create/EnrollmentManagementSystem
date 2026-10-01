<?php

namespace Tests\Feature\Clearance;

use App\Enums\StaffRole;
use App\Models\Academicterms;
use App\Models\Academicyears;
use App\Models\Clearanceperiods;
use App\Models\Offices;
use App\Models\Staffusers;
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
 * future edit cannot silently re-open a closed window through this route.
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

        foreach ([1, 2, 3, 4, 5, 8, 11, 22] as $officeId) {
            Offices::firstOrCreate(['officeId' => $officeId], ['officeName' => 'Office '.$officeId]);
        }

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
        $this->officer = $this->staffInOffice(8, 'OfficeHead');
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
        $viewer = $this->staffInOffice(8, 'Staff');
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
}
