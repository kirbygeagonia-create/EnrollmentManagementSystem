<?php

namespace Tests\Feature\Admin;

use App\Models\Offices;
use App\Models\Staffusers;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Prompt §14's sorting control on the staff directory — the fifteenth table wired (§28.3 G-40 stays Open).
 *
 * Two shapes here are new to this family. The office column is one hop away and the key that reaches it is
 * nullable in the migration, so the order travels through a LEFT join and an account with no office stays on the
 * list instead of vanishing (the audit log's rule, met on a second screen). And this action's search was chained at
 * the builder's top level, which ORed it against the office and status filters — so a case below says which office
 * it filtered and which name it typed, and proves the two controls compose.
 *
 * The status case names no sequence: an ENUM is ordered by its declaration index on the school's server and by the
 * stored word on the suite's SQLite, so the expectation is read from the connection that answered. The desk's own
 * account is part of the roster and appears in every list; its surname is Viewer and its username is fixed, so the
 * expected orders are exact rather than approximate.
 */
class StaffDirectorySortTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $desk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        Offices::create(['officeId' => 1, 'officeName' => 'Registrar Office', 'officeCode' => 'REG']);
        Offices::create(['officeId' => 2, 'officeName' => 'Accounting Office', 'officeCode' => 'ACCT']);

        $this->desk = $this->makeStaff('Directory', 'Viewer', 1, 'active', 'aaa_desk');
        $this->desk->assignRole('SysAdmin');
    }

    /**
     * Staff are created Mercado, Zamora, Alonzo, Bautista in the cases below, so the id orders, the surname order
     * and its reverse are four different sequences — a fixture that lets a wrong order agree with the right one by
     * accident is the defect this family has already met twice.
     */
    private function makeStaff(string $first, string $last, ?int $officeId, string $status = 'active', ?string $username = null): Staffusers
    {
        return Staffusers::create([
            'employeeNo' => 'EMP-'.strtoupper(substr($last, 0, 4)).'-'.uniqid(),
            'firstName' => $first,
            'middleName' => '',
            'lastName' => $last,
            'username' => $username ?? strtolower($last).'_'.uniqid(),
            'passwordHash' => bcrypt('password'),
            'role' => 'staff',
            'email' => strtolower($last).'@seait.edu.ph',
            'contactNo' => '09170000000',
            'status' => $status,
            'officeId' => $officeId,
        ]);
    }

    /** @return array<int, mixed> */
    private function props(mixed $response): array
    {
        return $response->viewData('page')['props'];
    }

    /** @return list<string> the surnames in the order the screen listed them */
    private function surnames(mixed $response): array
    {
        return collect($this->props($response)['users']['data'])->pluck('lastName')->all();
    }

    #[Test]
    public function the_directory_opens_on_the_surname_ascending_and_not_on_the_newest_record(): void
    {
        $this->makeStaff('Ana', 'Mercado', 1);
        $this->makeStaff('Ben', 'Zamora', 2);
        $this->makeStaff('Cia', 'Alonzo', 1);
        $this->makeStaff('Dev', 'Bautista', 2);

        $response = $this->actingAs($this->desk)->get(route('admin.users.index'));
        $response->assertOk();
        $props = $this->props($response);

        $this->assertSame('name', $props['sort']['column']);
        $this->assertSame('asc', $props['sort']['direction']);
        $this->assertSame('asc', $props['filters']['direction'], 'the screen is told the default it got');
        $this->assertSame(['Alonzo', 'Bautista', 'Mercado', 'Viewer', 'Zamora'], $this->surnames($response));
    }

    #[Test]
    public function the_second_click_on_the_name_header_reverses_the_sequence(): void
    {
        $this->makeStaff('Ana', 'Mercado', 1);
        $this->makeStaff('Ben', 'Zamora', 2);
        $this->makeStaff('Cia', 'Alonzo', 1);
        $this->makeStaff('Dev', 'Bautista', 2);

        $response = $this->actingAs($this->desk)->get(route('admin.users.index', [
            'sort' => 'name', 'direction' => 'desc',
        ]));
        $response->assertOk();

        $this->assertSame(['Zamora', 'Viewer', 'Mercado', 'Bautista', 'Alonzo'], $this->surnames($response));
        $this->assertSame('name', $this->props($response)['filters']['sort']);
    }

    #[Test]
    public function ordering_by_office_keeps_the_account_that_has_none_on_the_list(): void
    {
        $this->makeStaff('Ana', 'Mercado', 1);
        $this->makeStaff('Ben', 'Zamora', 2);
        $this->makeStaff('Cia', 'Alonzo', null);
        $this->makeStaff('Dev', 'Bautista', 2);

        $response = $this->actingAs($this->desk)->get(route('admin.users.index', [
            'sort' => 'office', 'direction' => 'asc',
        ]));
        $response->assertOk();
        $props = $this->props($response);

        // `staffusers.officeId` is nullable, so the hop is LEFT: the office-less row is ordered with the others
        // instead of deleted from the desk's list. An INNER hop would answer four rows here, not five.
        $this->assertSame(5, $props['users']['total'], 'a join added only to order may not drop a row');
        $this->assertContains('Alonzo', $this->surnames($response));

        $expected = Staffusers::query()
            ->leftJoin('offices as hop', 'hop.officeId', '=', 'staffusers.officeId')
            ->orderBy('hop.officeName')
            ->orderBy('staffusers.userId')
            ->pluck('lastName')
            ->all();
        $this->assertSame($expected, $this->surnames($response), 'the screen orders the office the way this connection does');
    }

    #[Test]
    public function the_username_header_orders_and_an_unlisted_key_falls_back_to_the_directory_default(): void
    {
        $this->makeStaff('Ana', 'Mercado', 1, 'active', 'zzz_mercado');
        $this->makeStaff('Ben', 'Zamora', 2, 'active', 'aaa_zamora');
        $this->makeStaff('Cia', 'Alonzo', 1, 'active', 'mmm_alonzo');

        $byUser = $this->actingAs($this->desk)->get(route('admin.users.index', [
            'sort' => 'username', 'direction' => 'asc',
        ]));
        $byUser->assertOk();
        // aaa_desk < aaa_zamora < mmm_alonzo < zzz_mercado — the surnames come out in no alphabetical order, so a
        // list that ignored the requested key cannot satisfy this and the surname order at once.
        $this->assertSame(['Viewer', 'Zamora', 'Alonzo', 'Mercado'], $this->surnames($byUser));

        $askedElsewhere = $this->actingAs($this->desk)->get(route('admin.users.index', [
            'sort' => 'passwordHash',
        ]));
        $askedElsewhere->assertOk();
        $this->assertSame('name', $this->props($askedElsewhere)['sort']['column']);

        // The fallback answers exactly what the fallback key asked for openly, with nothing about the direction
        // mixed in: both requests carry `sort` alone, so the comparison is about the column and not about a
        // sequence arithmetic done here. (The first cut of this case asked one with `desc` and the other with
        // `asc` and reported a correct handler as broken — the trap this family has recorded twice.)
        $askedPlainly = $this->actingAs($this->desk)->get(route('admin.users.index', [
            'sort' => 'name',
        ]));
        $askedPlainly->assertOk();
        $this->assertSame($this->surnames($askedPlainly), $this->surnames($askedElsewhere));
    }

    #[Test]
    public function the_status_column_is_an_enum_and_is_offered_at_this_connections_order(): void
    {
        $this->makeStaff('Ana', 'Mercado', 1, 'inactive');
        $this->makeStaff('Ben', 'Zamora', 2, 'active');
        $this->makeStaff('Cia', 'Alonzo', 1, 'inactive');

        $response = $this->actingAs($this->desk)->get(route('admin.users.index', [
            'sort' => 'status', 'direction' => 'asc',
        ]));
        $response->assertOk();
        $this->assertContains('status', $this->props($response)['sort']['available']);

        // The model casts `status` to a StaffStatus enum, so the query side answers objects while the payload
        // answers the string the desk renders — the order is the claim, so both sides are read as words.
        $this->assertSame(
            Staffusers::query()->orderBy('status')->orderBy('userId')->pluck('status')->map(fn ($s) => $s->value)->all(),
            collect($this->props($response)['users']['data'])->pluck('status')->all(),
            'the desk must order an ENUM the way the same connection does, not a remembered sequence'
        );
    }

    #[Test]
    public function a_column_built_from_a_to_many_relation_offers_no_header(): void
    {
        $this->makeStaff('Ana', 'Mercado', 1);

        $response = $this->actingAs($this->desk)->get(route('admin.users.index'));
        $response->assertOk();

        // The screen paints a role badge assembled from `roles` (to many) and the position title, so no column of
        // `staffusers` carries what that header would order by, and no header is painted for it.
        $this->assertSame(['name', 'username', 'office', 'status'], $this->props($response)['sort']['available']);
    }

    #[Test]
    public function the_office_filter_survives_the_search_because_the_alternatives_are_grouped(): void
    {
        $this->makeStaff('Ana', 'Mercado', 1);
        $this->makeStaff('Ben', 'Zamora', 2);

        $filtered = $this->actingAs($this->desk)->get(route('admin.users.index', [
            'officeId' => 1, 'search' => 'Zam',
        ]));
        $filtered->assertOk();

        // Zamora is in office 2. Chained at the builder's top level, the search ORed the office filter away and
        // this request answered Zamora — measured on live `ems` as 11 of 21 accounts for a single office.
        $this->assertSame(0, $this->props($filtered)['users']['total'], 'a search may not widen an office filter');
        $this->assertNotContains('Zamora', $this->surnames($filtered));

        // The same pair, typed for someone who really is in that office, still answers exactly one row.
        $matched = $this->actingAs($this->desk)->get(route('admin.users.index', [
            'officeId' => 1, 'search' => 'Merc',
        ]));
        $matched->assertOk();
        $this->assertSame(['Mercado'], $this->surnames($matched));
    }

    #[Test]
    public function filter_order_and_direction_are_all_echoed_so_no_control_resets(): void
    {
        $this->makeStaff('Ana', 'Mercado', 1);
        $this->makeStaff('Ben', 'Zamora', 2);

        $response = $this->actingAs($this->desk)->get(route('admin.users.index', [
            'officeId' => 1, 'search' => 'Merc', 'sort' => 'name', 'direction' => 'desc',
        ]));
        $response->assertOk();
        $props = $this->props($response);

        $this->assertSame('1', (string) $props['filters']['officeId']);
        $this->assertSame('Merc', $props['filters']['search']);
        $this->assertSame('name', $props['filters']['sort']);
        $this->assertSame('desc', $props['filters']['direction']);
        $this->assertSame('name', $props['sort']['column']);
    }

    #[Test]
    public function the_listed_row_carries_no_column_from_the_far_side_of_the_hop(): void
    {
        $this->makeStaff('Ana', 'Mercado', 1);

        $response = $this->actingAs($this->desk)->get(route('admin.users.index', [
            'sort' => 'office', 'direction' => 'asc',
        ]));
        $response->assertOk();
        $row = $this->props($response)['users']['data'][0];

        // §28.3 G-51: the hop exists to order, so the select is pinned to the base table. A column of `offices`
        // must not ride on the row the desk is handed, and the office name reaches the screen through the eager
        // load the page already reads.
        $this->assertArrayNotHasKey('passwordHash', $row);
        $this->assertArrayNotHasKey('officeName', $row);
        $this->assertSame('Registrar Office', $row['office']['officeName'] ?? null);
    }

    #[Test]
    public function the_office_filter_and_the_office_header_are_one_request_because_both_name_the_same_key(): void
    {
        $this->makeStaff('Ana', 'Mercado', 1);
        $this->makeStaff('Ben', 'Zamora', 2);

        // `offices` holds `officeId` as its primary key and `staffusers` holds it as its filter, so the bare name
        // this filter asked with was ambiguous the moment the Office header joined that table — MySQL answers
        // "Column 'officeId' in where clause is ambiguous" and SQLite refuses with "ambiguous column name: officeId".
        // No case in this class ever put the filter and that header on one request, which is the whole reason the
        // defect survived the screen's own suite: a header-only request and a filter-only request are each legal.
        $response = $this->actingAs($this->desk)->get(route('admin.users.index', [
            'officeId' => 1, 'sort' => 'office', 'direction' => 'asc',
        ]));
        $response->assertOk();

        $surnames = $this->surnames($response);
        $this->assertContains('Mercado', $surnames, 'the office the desk filtered for lost its own account');
        $this->assertNotContains('Zamora', $surnames, 'the hop widened the filter it was supposed to sit under');
        $this->assertSame('office', $this->props($response)['filters']['sort']);
    }
}
