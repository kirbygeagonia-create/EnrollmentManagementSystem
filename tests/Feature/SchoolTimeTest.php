<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Models\Academicterms;
use App\Models\Academicyears;
use App\Models\Auditlogs;
use App\Models\Offices;
use App\Models\Staffusers;
use App\Support\SchoolTime;
use Carbon\CarbonImmutable;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Lane 51 — the school's calendar is one declaration, and every desk boundary is drawn in it.
 *
 * The application stores instants in UTC and always will; `config/app.timezone` is `UTC` and `config/database.php`
 * forces every MySQL session to `+00:00`. What was wrong is that the same UTC midnight was then used to answer
 * questions only a person at SEAIT can be asking: which term is open, which day's audit rows does this officer
 * want, which receipts belong on today's sheet. The school keeps Manila time, so those boundaries were cut eight
 * hours early — measured on live `ems`, 1,459 of 3,426 audit rows, 56 of 187 signed boxes and 11 of 15 payment
 * rows land on a different date depending on which midnight you use.
 *
 * The browser is held to the same rule by the first case, which is a source pin rather than a behaviour test:
 * `Intl` cannot be exercised from PHP, and two answers to "which zone is SEAIT in" is exactly how the tone maps
 * and the `OfficeId` lists went stale in earlier lanes.
 */
class SchoolTimeTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $desk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        foreach ([1, 2, 3, 4, 5, 11, 22] as $officeId) {
            Offices::firstOrCreate(['officeId' => $officeId], ['officeName' => 'Office '.$officeId]);
        }

        $staff = Staffusers::factory()->create([
            'officeId' => 1,
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-LANE51-'.uniqid(),
            'username' => 'lane51_'.uniqid(),
            'email' => 'lane51_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('SysAdmin');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->desk = $staff;
    }

    #[Test]
    public function the_zone_is_declared_once_and_the_screen_reads_the_same_one_the_server_uses(): void
    {
        $this->assertSame('UTC', config('app.timezone'), 'instants stay in UTC; only the edges convert');
        $this->assertSame('Asia/Manila', SchoolTime::zone(), 'the school keeps a different civil time');

        // The session zone the MySQL connection opens with must be the one the application stamps in, or the two
        // writers of a `DATETIME` disagree — which is the whole subject of lane 31 and of preflight's "Clocks agree".
        $init = (config('database.connections.mysql.options') ?? [])[PDO::MYSQL_ATTR_INIT_COMMAND] ?? null;

        if (is_string($init)) {
            $this->assertStringContainsString('+00:00', $init);
        }

        $source = file_get_contents(base_path('resources/js/Components/ui/calendarDay.js'));

        $this->assertStringContainsString(
            "export const SCHOOL_TIMEZONE = '".config('school.timezone')."';",
            $source,
            'the browser must format institutional moments in the zone the server declares, not its own guess');
    }

    #[Test]
    public function a_school_day_opens_at_sixteen_hundred_utc_the_day_before(): void
    {
        $opens = SchoolTime::startOfSchoolDay('2026-10-18');
        $closes = SchoolTime::startOfNextSchoolDay('2026-10-18');

        $this->assertSame('2026-10-17', $opens->toDateString());
        $this->assertSame('16:00:00', $opens->toTimeString());
        $this->assertSame('2026-10-18', $closes->toDateString());
        $this->assertSame('16:00:00', $closes->toTimeString());
        $this->assertSame(86400, $closes->timestamp - $opens->timestamp, 'a school day is exactly 24 hours');

        // The hour that decides everything: 18:00Z is the 19th for the school, and the 18th for its own database.
        $this->assertSame('2026-10-19', SchoolTime::civilDateOf(Carbon::parse('2026-10-18 18:00:00')));
        $this->assertSame('2026-10-18', SchoolTime::civilDateOf(Carbon::parse('2026-10-18 15:59:59')));

        $this->assertSame('2026-10-18', SchoolTime::dayOrNull(' 2026-10-18 '));
        $this->assertNull(SchoolTime::dayOrNull('2026/10/18'));
        $this->assertNull(SchoolTime::dayOrNull('today'));
        $this->assertNull(SchoolTime::dayOrNull(''));
        $this->assertNull(SchoolTime::dayOrNull(null));
    }

    #[Test]
    public function an_impossible_date_is_refused_rather_than_rolled_into_a_real_one(): void
    {
        // Measured before the guard was added: Carbon answered 2027-02-13 for `2026-13-45` and March 1st for
        // `2026-02-30`, so a desk that typed nonsense would have received a genuine report for a day it never
        // asked for. A filter that ignores a junk bound is wrong in a way anyone can see; one that answers a
        // different day is not wrong in a way anyone can catch.
        foreach (['2026-13-45', '2026-02-30', '2026-02-29'] as $date) {
            $this->assertNull(SchoolTime::dayOrNull($date), $date.' is not a day that exists');

            try {
                SchoolTime::startOfSchoolDay($date);
                $this->fail($date.' was accepted as a calendar date');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('real calendar date', $e->getMessage());
            }
        }

        // A real date still opens, so the refusal is the guard working and not the method broken.
        $this->assertSame('2026-10-17', SchoolTime::startOfSchoolDay('2026-10-18')->toDateString());
    }

    #[Test]
    public function the_audit_logs_date_filter_returns_the_days_the_officer_asked_for_in_their_own_calendar(): void
    {
        // Both rows are stamped on the 18th by the database. Only one of them happened on the school's 18th.
        Auditlogs::create(['userId' => $this->desk->userId, 'action' => 'created', 'entityTable' => 'lane51morning', 'entityId' => 1, 'createdAt' => '2026-10-18 06:00:00']);
        Auditlogs::create(['userId' => $this->desk->userId, 'action' => 'created', 'entityTable' => 'lane51evening', 'entityId' => 2, 'createdAt' => '2026-10-18 17:00:00']);

        $response = $this->actingAs($this->desk)
            ->get(route('admin.users.audit-logs', ['dateFrom' => '2026-10-18', 'dateTo' => '2026-10-18']));

        // Asserted as membership rather than a count: observers write their own audit rows during setUp, and the
        // claim is about which rows are on the sheet. Under the old `whereDate` the evening row answered to the
        // 18th as well, so this pair of assertions is exactly the difference the lane makes.
        $tables = collect($response->viewData('page')['props']['logs']['data'])->pluck('entityTable');

        $this->assertTrue($tables->contains('lane51morning'), 'the school morning belongs to the school day');
        $this->assertFalse($tables->contains('lane51evening'),
            'a row stamped 17:00Z is the 19th at the school; a UTC midnight cut filed it on the 18th');

        // And the evening row is not lost — it belongs to the next sheet.
        $next = collect($this->actingAs($this->desk)
            ->get(route('admin.users.audit-logs', ['dateFrom' => '2026-10-19', 'dateTo' => '2026-10-19']))
            ->viewData('page')['props']['logs']['data'])
            ->pluck('entityTable');

        $this->assertTrue($next->contains('lane51evening'), 'the evening signature is on the day it happened');
        $this->assertFalse($next->contains('lane51morning'));
    }

    #[Test]
    public function a_junk_date_bound_narrows_nothing_instead_of_answering_a_different_day(): void
    {
        Auditlogs::create(['userId' => $this->desk->userId, 'action' => 'created', 'entityTable' => 'lane51any', 'entityId' => 1, 'createdAt' => '2026-10-18 06:00:00']);

        $props = $this->actingAs($this->desk)
            ->get(route('admin.users.audit-logs', ['dateFrom' => '2026-13-45']))
            ->viewData('page')['props'];

        // Nothing was excluded — the bound was ignored, not silently answered as some other day — and the screen
        // still receives what it sent, so the officer can see the filter did not take.
        $this->assertCount(Auditlogs::count(), $props['logs']['data']);
        $this->assertTrue(collect($props['logs']['data'])->pluck('entityTable')->contains('lane51any'));
        $this->assertSame('2026-13-45', $props['filters']['dateFrom']);
    }

    #[Test]
    public function a_term_that_opens_on_the_schools_today_is_open_even_though_utc_still_calls_it_yesterday(): void
    {
        $year = Academicyears::create(['yearLabel' => '2026-2027', 'startDate' => '2026-06-01', 'endDate' => '2027-03-31']);

        $term = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2026-10-18',
            'endDate' => '2026-10-31',
        ]);

        try {
            // 18:00Z on the 17th is 02:00 on the 18th in Manila: the term has opened for everyone at the school.
            Carbon::setTestNow(CarbonImmutable::parse('2026-10-17 18:00:00', 'UTC'));
            $this->assertNotNull(Academicterms::covering(), 'the opening day must already be inside the window');
            $this->assertSame($term->termId, Academicterms::covering()?->termId);

            // 15:00Z the same day is 23:00 on the 17th: still yesterday at the school, so the term is not open.
            Carbon::setTestNow(CarbonImmutable::parse('2026-10-17 15:00:00', 'UTC'));
            $this->assertNull(Academicterms::covering());
        } finally {
            Carbon::setTestNow();
        }
    }
}
