<?php

namespace Tests\Feature\Sorting;

use App\Enums\StaffRole;
use App\Models\Auditlogs;
use App\Models\Staffusers;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Prompt §14's sorting control on the audit log — the thirteenth table wired, and the first whose hop can be
 * absent. §28.3 G-40 stays Open on purpose: this is one screen, not the mechanism's whole population.
 *
 * Two things make this queue different from the twelve before it, and each owns its own case rather than hiding
 * inside a general "sorting works" test. The user column is reached through `auditlogs.userId`, which live `ems`
 * leaves NULL on 3,294 of 3,525 rows, so the hop is a LEFT one and the case asks whether the rows without an
 * account are STILL LISTED — a sequence alone cannot tell a LEFT join from an INNER one that happens to match.
 * The second is the tile: its aggregate is cloned from the builder before the order is applied, so an order that
 * joins cannot turn the tile into a grouped star on MySQL 8 (§28.3 G-51's follow-up).
 *
 * Every request is asserted to answer, because a 500 response and an unsorted list both arrive as "the two lists
 * differ" — the lesson task #52's first cut paid for when a wrong column list made three queues answer 500 while
 * every sort case stayed green.
 */
class AuditLogSortTest extends TestCase
{
    use RefreshDatabase;

    private const PROBE = 'auditprobetable';

    private Staffusers $desk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->desk = Staffusers::create([
            'employeeNo' => 'EMP-SORT-'.uniqid(),
            'firstName' => 'Auditor',
            'middleName' => '',
            'lastName' => 'General',
            'username' => 'sort_auditor_'.uniqid(),
            'passwordHash' => bcrypt('password'),
            'role' => StaffRole::Admin,
            'email' => 'sort.auditor@seait.edu.ph',
            'contactNo' => '09170000000',
            'status' => 'active',
        ]);
        $this->desk->assignRole('SysAdmin');
    }

    private function probe(?string $user, string $action, string $entityTable, int $entityId, string $day): void
    {
        Auditlogs::create([
            'userId' => $user,
            'action' => $action,
            'entityTable' => $entityTable,
            'entityId' => $entityId,
            'newValues' => null,
            'ipAddress' => '127.0.0.1',
            'createdAt' => $day,
        ]);
    }

    /** @return array<int, mixed> the page props, read the way an Inertia screen receives them */
    private function props(mixed $response): array
    {
        return $response->viewData('page')['props'];
    }

    #[Test]
    public function the_log_opens_newest_first_and_says_so(): void
    {
        $this->probe(null, 'created', self::PROBE, 1, '2026-10-01 01:00:00');
        $this->probe(null, 'updated', self::PROBE, 2, '2026-10-09 01:00:00');
        $this->probe(null, 'deleted', self::PROBE, 3, '2026-10-05 01:00:00');

        $response = $this->actingAs($this->desk)->get(route('admin.users.audit-logs', ['entityTable' => self::PROBE]));
        $response->assertOk();

        $props = $this->props($response);
        $this->assertSame('createdAt', $props['sort']['column']);
        $this->assertSame('desc', $props['sort']['direction']);
        // The echo carries both keys, so the control cannot reset while the list stays narrowed.
        $this->assertSame('createdAt', $props['filters']['sort']);
        $this->assertSame('desc', $props['filters']['direction']);

        $this->assertSame(
            ['updated', 'deleted', 'created'],
            collect($props['logs']['data'])->pluck('action')->all()
        );
    }

    #[Test]
    public function each_offered_column_orders_the_list_by_that_column_and_not_by_the_record_key(): void
    {
        $this->probe(null, 'deleted', self::PROBE, 30, '2026-10-01 01:00:00');
        $this->probe(null, 'printed', self::PROBE, 10, '2026-10-02 01:00:00');
        $this->probe(null, 'approved', self::PROBE, 20, '2026-10-03 01:00:00');

        // entityId ascending answers 10, 20, 30 — a different sequence from either id order and from the default,
        // so a mutant that ignores the requested column cannot satisfy this by accident.
        $response = $this->actingAs($this->desk)->get(route('admin.users.audit-logs', [
            'entityTable' => self::PROBE, 'sort' => 'entityId', 'direction' => 'asc',
        ]));
        $response->assertOk();
        $props = $this->props($response);
        // `entityId` arrives as a string: the model declares no cast for the column, so the wire shape is the
        // truth and a strictly-typed desk comparing it to a number would break on it. The ORDER is what this case
        // pins, so the ids are read through intval and the type fact is stated rather than papered over.
        $this->assertSame([10, 20, 30], array_map('intval', collect($props['logs']['data'])->pluck('entityId')->all()));
        $this->assertSame('entityId', $props['filters']['sort']);

        $byAction = $this->actingAs($this->desk)->get(route('admin.users.audit-logs', [
            'entityTable' => self::PROBE, 'sort' => 'action', 'direction' => 'asc',
        ]));
        $byAction->assertOk();
        $this->assertSame(
            ['approved', 'deleted', 'printed'],
            collect($this->props($byAction)['logs']['data'])->pluck('action')->all()
        );

        $desc = $this->actingAs($this->desk)->get(route('admin.users.audit-logs', [
            'entityTable' => self::PROBE, 'sort' => 'entityId', 'direction' => 'desc',
        ]));
        $desc->assertOk();
        $this->assertSame([30, 20, 10], array_map('intval', collect($this->props($desc)['logs']['data'])->pluck('entityId')->all()));
    }

    #[Test]
    public function ordering_by_the_user_keeps_the_rows_that_have_no_account_behind_them(): void
    {
        $ana = Staffusers::create([
            'employeeNo' => 'EMP-ANA-'.uniqid(), 'firstName' => 'Ana', 'middleName' => '', 'lastName' => 'Registrar',
            'username' => 'ana_sort_'.uniqid(), 'passwordHash' => bcrypt('password'), 'role' => StaffRole::Staff,
            'email' => 'ana.sort@seait.edu.ph', 'contactNo' => '09170000000', 'status' => 'active',
        ]);

        $this->probe($ana->userId, 'created', self::PROBE, 1, '2026-10-01 01:00:00');
        // The bulk of a real log: written by the system, with nobody standing behind it.
        $this->probe(null, 'login', self::PROBE, 2, '2026-10-02 01:00:00');
        $this->probe(null, 'logout', self::PROBE, 3, '2026-10-03 01:00:00');

        $response = $this->actingAs($this->desk)->get(route('admin.users.audit-logs', [
            'entityTable' => self::PROBE, 'sort' => 'user', 'direction' => 'asc',
        ]));
        $response->assertOk();
        $props = $this->props($response);

        // Three rows, not one. An INNER hop would answer a list of one and still look like a correct sort.
        $this->assertSame(3, $props['logs']['total']);
        $this->assertCount(3, $props['logs']['data']);
        $this->assertSame(
            ['login', 'logout', 'created'],
            collect($props['logs']['data'])->pluck('action')->all()
        );
    }

    #[Test]
    public function a_column_the_desk_paints_from_json_offers_no_order(): void
    {
        $this->probe(null, 'created', self::PROBE, 1, '2026-10-01 01:00:00');

        $response = $this->actingAs($this->desk)->get(route('admin.users.audit-logs'));
        $response->assertOk();
        $available = $this->props($response)['sort']['available'];

        // `description` is composed from oldValues/newValues at read time, so the query holds nothing to order by
        // and the header must not be painted. The offered set is the handler's own, never the page's guess.
        $this->assertNotContains('description', $available);
        $this->assertContains('user', $available);
        $this->assertContains('entityTable', $available);
        $this->assertSame(['createdAt', 'action', 'entityTable', 'entityId', 'auditId', 'user'], $available);
    }

    #[Test]
    public function a_key_that_is_not_listed_falls_back_without_reaching_the_query(): void
    {
        $this->probe(null, 'created', self::PROBE, 1, '2026-10-01 01:00:00');
        $this->probe(null, 'updated', self::PROBE, 2, '2026-10-02 01:00:00');

        $response = $this->actingAs($this->desk)->get(route('admin.users.audit-logs', [
            'entityTable' => self::PROBE, 'sort' => 'passwordHash', 'direction' => 'asc',
        ]));
        $response->assertOk();
        $props = $this->props($response);

        // The answer names the fallback, never the key that was asked for — a desk reads the reported state.
        $this->assertSame('createdAt', $props['sort']['column']);
        $this->assertSame('createdAt', $props['filters']['sort']);

        // And the fallback behaves exactly like the column it falls back to, with the direction the desk sent.
        // The first cut of this assertion typed ['updated','created'] from my own arithmetic and forgot that this
        // request asks `asc`, so the code was right and the case was wrong — the same trap this file records for
        // the admission queue. Comparing against the queue's own answer cannot agree by accident.
        $askedExplicitly = $this->actingAs($this->desk)->get(route('admin.users.audit-logs', [
            'entityTable' => self::PROBE, 'sort' => 'createdAt', 'direction' => 'asc',
        ]));
        $askedExplicitly->assertOk();
        $this->assertSame(
            collect($this->props($askedExplicitly)['logs']['data'])->pluck('action')->all(),
            collect($props['logs']['data'])->pluck('action')->all(),
            'an unlisted key must answer the same list as the fallback column asked for openly'
        );

        // A direction that is not `asc` is reduced, never interpolated.
        $injected = $this->actingAs($this->desk)->get(route('admin.users.audit-logs', [
            'entityTable' => self::PROBE, 'sort' => 'entityId', 'direction' => 'desc; drop table auditlogs',
        ]));
        $injected->assertOk();
        $this->assertSame('desc', $this->props($injected)['sort']['direction']);
        $this->assertSame([2, 1], array_map('intval', collect($this->props($injected)['logs']['data'])->pluck('entityId')->all()));
    }

    #[Test]
    public function a_filter_submit_and_an_order_survive_each_other(): void
    {
        $this->probe(null, 'created', self::PROBE, 1, '2026-10-01 01:00:00');
        $this->probe(null, 'created', self::PROBE, 9, '2026-10-05 01:00:00');
        $this->probe(null, 'deleted', self::PROBE, 5, '2026-10-03 01:00:00');

        $response = $this->actingAs($this->desk)->get(route('admin.users.audit-logs', [
            'entityTable' => self::PROBE, 'action' => 'created', 'sort' => 'entityId', 'direction' => 'asc',
        ]));
        $response->assertOk();
        $props = $this->props($response);

        // The filter still narrows while the order is applied: two keys in, two keys back.
        $this->assertSame('created', $props['filters']['action']);
        $this->assertSame('entityId', $props['filters']['sort']);
        // The direction is read here because a mutant that drops it from the echo survived its own leg: the case
        // asserted the filter and the column, and nothing looked at the third key the desk had just sent.
        $this->assertSame('asc', $props['filters']['direction']);
        $this->assertSame('asc', $this->props($response)['sort']['direction']);
        $this->assertSame([1, 9], array_map('intval', collect($props['logs']['data'])->pluck('entityId')->all()));
    }

    #[Test]
    public function the_tile_counts_the_whole_filtered_set_and_is_untouched_by_the_order(): void
    {
        $this->probe(null, 'created', self::PROBE, 1, '2026-10-01 01:00:00');
        $this->probe(null, 'created', self::PROBE, 2, '2026-10-02 01:00:00');
        $this->probe(null, 'deleted', self::PROBE, 3, '2026-10-03 01:00:00');

        $rest = $this->actingAs($this->desk)->get(route('admin.users.audit-logs', ['entityTable' => self::PROBE]));
        $sorted = $this->actingAs($this->desk)->get(route('admin.users.audit-logs', [
            'entityTable' => self::PROBE, 'sort' => 'user', 'direction' => 'asc',
        ]));
        $rest->assertOk();
        $sorted->assertOk();

        // Same figures under both readings. The aggregate is cloned before the hop exists, so ordering by a joined
        // column cannot narrow the tile or make MySQL 8 refuse a grouped star.
        $this->assertSame(3, $this->props($rest)['stats']['total']);
        $this->assertSame(3, $this->props($sorted)['stats']['total']);
        $this->assertSame(
            $this->props($rest)['stats'],
            $this->props($sorted)['stats'],
            'a header click must not change what the tiles count'
        );
    }

    #[Test]
    public function a_column_reached_through_the_hop_stays_out_of_the_listed_row(): void
    {
        $ana = Staffusers::create([
            'employeeNo' => 'EMP-BEYA-'.uniqid(), 'firstName' => 'Beya', 'middleName' => '', 'lastName' => 'Cashier',
            'username' => 'beya_sort_'.uniqid(), 'passwordHash' => bcrypt('password'), 'role' => StaffRole::Staff,
            'email' => 'beya.sort@seait.edu.ph', 'contactNo' => '09170000000', 'status' => 'active',
        ]);
        $this->probe($ana->userId, 'created', self::PROBE, 1, '2026-10-01 01:00:00');

        $response = $this->actingAs($this->desk)->get(route('admin.users.audit-logs', [
            'entityTable' => self::PROBE, 'sort' => 'user', 'direction' => 'asc',
        ]));
        $response->assertOk();

        $row = $this->props($response)['logs']['data'][0];
        // §28.3 G-51, at the desk: the hop exists to order the list, never to widen the row it lists. The nested
        // user is the eager load the screen reads; the hop's own columns must not sit on the row at all.
        $this->assertArrayNotHasKey('passwordHash', $row);
        $this->assertArrayNotHasKey('employeeNo', $row);
        $this->assertArrayNotHasKey('srt0', array_change_key_case($row));
        $this->assertSame('created', $row['action']);
        $this->assertArrayHasKey('user', $row);
    }
}
