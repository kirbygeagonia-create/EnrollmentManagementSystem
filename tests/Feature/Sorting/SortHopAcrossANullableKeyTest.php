<?php

namespace Tests\Feature\Sorting;

use App\Models\Auditlogs;
use App\Models\Staffusers;
use App\Support\ListSort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The hop that does not always exist. §28.3 G-40's remaining screens reach the person on the far side of a
 * nullable key — `auditlogs.userId` is null on 3,294 of the 3,525 rows live `ems` holds, and `staffusers.unitId`
 * on 15 of 21 accounts — so the first screen that asks for them cannot be wired to an INNER join: ordering a
 * list by the thing a row may not have would delete those rows from the list instead.
 *
 * Every query here is scoped to `entityTable = 'probetable'`, and that is not decoration. `AuditLogObserver`
 * writes an `auditlogs` row for every observed model it sees created, so building a `Staffusers` fixture adds a
 * row to the very table under test; the first cut of this class asserted "one row" and found two, which was the
 * observer being right and the fixture being sloppy. A real desk query always arrives with its filters already
 * on the builder, so scoping is the shape the handler actually has.
 *
 * The two halves are pinned separately on purpose: a case that only asserts a sequence cannot tell a LEFT join
 * from an INNER one whose fixture happens to have every key filled, and that is how this defect would survive a
 * green class.
 */
class SortHopAcrossANullableKeyTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $last, string $first): Staffusers
    {
        // `employeeNo`, `username` and `contactNo` are NOT NULL without a default, and the `name` a screen reads
        // is an accessor — the column a hop can order is `lastName`, which is the whole point of the hop below.
        return Staffusers::create([
            'employeeNo' => 'EMP-'.strtolower($last),
            'firstName' => $first,
            'middleName' => '',
            'lastName' => $last,
            'username' => strtolower($last).'.test',
            'passwordHash' => bcrypt('password'),
            'role' => 'staff',
            'email' => strtolower($last).'.test@seait.edu.ph',
            'contactNo' => '09170000000',
            'status' => 'active',
        ]);
    }

    private function log(?Staffusers $user, string $action): Auditlogs
    {
        return Auditlogs::create([
            'userId' => $user?->userId,
            'action' => $action,
            'entityTable' => 'probetable',
            'entityId' => 1,
            'newValues' => null,
            'ipAddress' => '127.0.0.1',
            'createdAt' => now(),
        ]);
    }

    /** The builder shape a desk handler hands `ListSort`: its own filters already applied. */
    private function probe(): Builder
    {
        return Auditlogs::query()->where('entityTable', 'probetable');
    }

    /** @return array<string, array<string, string>> */
    private function leftHop(): array
    {
        return [
            'user' => [
                'column' => 'lastName',
                'join' => 'staffusers',
                'foreign' => 'userId',
                'local' => 'auditlogs.userId',
                'type' => 'left',
            ],
            // The list's own key must be present, because `apply()` resolves its fallback through this array —
            // the same reason every wired handler carries its primary key as an entry.
            'createdAt' => ['column' => 'createdAt'],
        ];
    }

    #[Test]
    public function a_hop_asked_as_left_keeps_the_rows_that_have_no_one_on_the_far_side(): void
    {
        $this->log($this->staff('Registrar', 'Ana'), 'created');
        // Most of a real audit log is written by the system: no account stands behind it.
        $this->log(null, 'login');
        $this->log(null, 'logout');

        $left = $this->probe();
        ListSort::apply($left, 'user', 'asc', $this->leftHop(), 'createdAt', 'auditlogs.auditId');
        $this->assertCount(3, $left->get());

        $inner = $this->probe();
        ListSort::apply(
            $inner,
            'user',
            'asc',
            ['user' => ['column' => 'lastName', 'join' => 'staffusers', 'foreign' => 'userId', 'local' => 'auditlogs.userId'], 'createdAt' => ['column' => 'createdAt']],
            'createdAt',
            'auditlogs.auditId'
        );

        // Same column, same direction, no `type`: a spec that does not ask for a left join keeps the SQL every
        // already-wired screen was proved on. This is the falsification of the default, not a comment about it —
        // and it is the count that shows what the INNER reading would do to a desk's list.
        $this->assertCount(1, $inner->get());
    }

    #[Test]
    public function the_rows_without_a_user_sort_together_rather_than_scattering(): void
    {
        $this->log($this->staff('Cassier', 'Ben'), 'created');
        $this->log(null, 'login');
        $this->log($this->staff('Registrar', 'Ana'), 'updated');
        $this->log(null, 'logout');

        $query = $this->probe();
        ListSort::apply($query, 'user', 'asc', $this->leftHop(), 'createdAt', 'auditlogs.auditId');

        // Read the order off the row's OWN column. The hop's column is deliberately not in the payload — that is
        // G-51's select pin, asserted in the next case — so plucking `srt0.lastName` here would answer null for
        // every row and the case would pass by reading nothing.
        // Rows created: Cassier(created), no-one(login), Registrar(updated), no-one(logout).
        $this->assertSame(
            ['login', 'logout', 'created', 'updated'],
            $query->get()->pluck('action')->all()
        );
    }

    #[Test]
    public function the_hop_that_orders_a_nullable_column_still_never_reaches_the_payload(): void
    {
        $user = $this->staff('Registrar', 'Ana');
        $this->log($user, 'created');

        $query = $this->probe();
        ListSort::apply($query, 'user', 'asc', $this->leftHop(), 'createdAt', 'auditlogs.auditId');

        $attributes = $query->first()->getAttributes();

        // G-51 was found on an INNER hop. This is why the fix is a property of joining and not of join kind: a
        // LEFT join emits the hop's columns under a bare `select *` exactly the same way, so the select pin has
        // to fire for it too — `passwordHash` and `email` belong to staffusers, never to the row being listed.
        $this->assertSame('created', $attributes['action']);
        $this->assertArrayNotHasKey('passwordHash', $attributes);
        $this->assertArrayNotHasKey('email', $attributes);
        $this->assertArrayNotHasKey('remember_token', $attributes);
        $this->assertArrayNotHasKey('employeeNo', $attributes);
        $this->assertArrayHasKey('auditId', $attributes);
    }

    #[Test]
    public function a_key_that_is_not_listed_never_reaches_the_query(): void
    {
        $this->log($this->staff('Registrar', 'Ana'), 'created');

        $query = $this->probe();
        $applied = ListSort::apply($query, 'passwordHash', 'asc', $this->leftHop(), 'createdAt', 'auditlogs.auditId');

        // The unlisted key falls back, and the fallback is what the screen is told — never the name it asked for.
        $this->assertSame('createdAt', $applied['column']);
        $presented = ListSort::present($applied, $this->leftHop());
        $this->assertSame(['user', 'createdAt'], $presented['available']);
        $this->assertNotContains('passwordHash', $presented['available']);
        $this->assertCount(1, $query->get());
    }

    #[Test]
    public function the_observer_row_is_why_every_case_scopes_its_own_table(): void
    {
        $this->staff('Registrar', 'Ana');

        // A staff creation alone leaves a row in `auditlogs` — the observer recording the act. Without the
        // `probetable` scope above, "one row" would be two, and a failing count would look like a join defect.
        $this->assertCount(1, Auditlogs::where('entityTable', 'staffusers')->get());
        $this->assertCount(0, $this->probe()->get());
    }
}
