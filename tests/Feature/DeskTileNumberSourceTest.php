<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Models\Auditlogs;
use App\Models\Offices;
use App\Models\Staffusers;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Lane 48 — when a desk shows a zero, that zero must be the server's answer.
 *
 * Every tile on these screens used to carry a fallback (`?? 0`, `|| 0`, or a `peso()` that read
 * `Number(n || 0)`), so a screen whose handler never answered painted a plausible figure anyway. The screens
 * now read the handler's value and `Components/ui/StatCard.jsx` renders a dash when there is none, which moves
 * the whole question onto the server: these cases run against a database with no rows and require each tile's
 * number to arrive as an actual 0. That is what makes the dash meaningful on the desk — an absence, never an
 * empty queue.
 *
 * SysAdmin is used deliberately, as in lane 47: `Gate::before` grants it everything, so these cases measure
 * what the handler answers and not who may open the screen.
 *
 * The one deliberate exception is the exam pass rate: with no paper marked, a rate is not 0% but unknown, and
 * that case pins the null.
 */
class DeskTileNumberSourceTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $desk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        foreach ([1, 2, 3, 4, 5, 6, 7, 11, 22] as $officeId) {
            Offices::firstOrCreate(['officeId' => $officeId], ['officeName' => 'Office '.$officeId]);
        }

        $staff = Staffusers::factory()->create([
            'officeId' => 1,
            'role' => StaffRole::Staff,
            'employeeNo' => 'EMP-LANE48-'.uniqid(),
            'username' => 'lane48_'.uniqid(),
            'email' => 'lane48_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('SysAdmin');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->desk = $staff;
    }

    /**
     * Open one desk on an empty database and require every figure its tiles read to be answered.
     *
     * @param  array<string,array<string,string>>  $map  prop name => [stat key => int|float]
     */
    private function assertNumbersFromTheServer(string $route, array $map): array
    {
        $props = $this->actingAs($this->desk)->get(route($route))
            ->assertOk()
            ->viewData('page')['props'];

        foreach ($map as $prop => $keys) {
            $this->assertArrayHasKey($prop, $props, $route.' answers no `'.$prop.'` prop, so every tile above that list would dash');
            foreach ($keys as $key => $kind) {
                $value = $props[$prop][$key] ?? null;
                $this->assertArrayHasKey($key, (array) $props[$prop], $route.'. '.$prop.'.'.$key.' is not answered — the tile falls back to whatever the screen decides');
                $this->assertNotNull($value, $route.'. '.$prop.'.'.$key.' is null: a count of an empty set is 0, not nothing');

                if ($kind === 'int') {
                    $this->assertIsInt($value, $route.'. '.$prop.'.'.$key.' is not an int');
                    $this->assertSame(0, $value, $route.'. '.$prop.'.'.$key.' is not 0 on a database with no rows');
                } elseif ($kind === 'int-any') {
                    // a count whose queue is genuinely not empty in this test — the audit log records the seeder
                    // and the role assignment that set the desk up — still must be the server's int, not a dash
                    $this->assertIsInt($value, $route.'. '.$prop.'.'.$key.' is not an int');
                    $this->assertGreaterThanOrEqual(0, $value);
                } else {
                    $this->assertIsNumeric($value, $route.'. '.$prop.'.'.$key.' is not numeric');
                    $this->assertEquals(0, (float) $value, $route.'. '.$prop.'.'.$key.' is not 0 on a database with no rows');
                }
            }
        }

        return $props;
    }

    #[Test]
    public function the_queue_desks_answer_zero_for_an_empty_queue(): void
    {
        $this->assertNumbersFromTheServer('admission.index', [
            'stats' => ['total' => 'int', 'pending' => 'int', 'approved' => 'int', 'rejected' => 'int'],
        ]);

        $this->assertNumbersFromTheServer('clearance.index', [
            'stats' => ['pending' => 'int', 'approved' => 'int', 'rejected' => 'int', 'waived' => 'int', 'incomplete' => 'int'],
        ]);

        $this->assertNumbersFromTheServer('evaluation.index', [
            'stats' => ['total' => 'int', 'pending' => 'int', 'returned' => 'int', 'evaluated' => 'int', 'transferees' => 'int'],
        ]);

        $this->assertNumbersFromTheServer('id.index', [
            'stats' => ['pending' => 'int', 'validated' => 'int'],
        ]);

        $this->assertNumbersFromTheServer('blocking.index', [
            'stats' => ['blocks' => 'int', 'capacity' => 'int', 'enrolled' => 'int', 'available' => 'int'],
        ]);

        $this->assertNumbersFromTheServer('clinic.index', [
            'stats' => ['queue' => 'int', 'completed' => 'int', 'pending' => 'int'],
        ]);

        $this->assertNumbersFromTheServer('exam.index', [
            'stats' => ['total' => 'int', 'pass' => 'int', 'fail' => 'int'],
        ]);

        $this->assertNumbersFromTheServer('shift.index', [
            'stats' => ['pending' => 'int', 'endorsed' => 'int', 'granted' => 'int', 'rejected' => 'int'],
        ]);
    }

    #[Test]
    public function the_money_desks_answer_zero_pesos_rather_than_leaving_the_tile_to_decide(): void
    {
        // A zero balance is a fact about an empty queue; ₱0.00 rendered by a formatter that coerces a null is
        // not. The handlers answer floats so the tile has a number to show and the dash keeps its meaning.
        $this->assertNumbersFromTheServer('accounting.index', [
            'summary' => [
                'totalAssessed' => 'float',
                'totalBalance' => 'float',
                'totalCovered' => 'float',
                'partialCount' => 'int',
                'unpaidCount' => 'int',
            ],
            'queueCounts' => ['due' => 'int', 'no_balance' => 'int'],
        ]);

        $this->assertNumbersFromTheServer('assessment.index', [
            'summary' => [
                'total' => 'float',
                'balance' => 'float',
                'partialCount' => 'int',
                'settledCount' => 'int',
            ],
        ]);

        $this->assertNumbersFromTheServer('accounting.daily-report', [
            'summary' => [
                'totalAmount' => 'float',
                'totalCount' => 'int',
                'refundedAmount' => 'float',
                'refundedCount' => 'int',
            ],
        ]);
    }

    #[Test]
    public function the_admin_desks_answer_every_count_their_tiles_read(): void
    {
        $props = $this->assertNumbersFromTheServer('admin.users.audit-logs', [
            'stats' => ['total' => 'int-any', 'created' => 'int-any', 'updated' => 'int-any', 'deleted' => 'int-any'],
        ]);

        // the number's meaning, not only its type: the tile counts the log, not the 50 rows on this page
        $this->assertSame(Auditlogs::count(), $props['stats']['total'], 'the audit desk must count the log it labels');

        $props = $this->assertNumbersFromTheServer('admin.reference-data.index', [
            'stats' => [
                'courses' => 'int',
                'majors' => 'int',
                'curriculums' => 'int',
                'subjects' => 'int',
                'terms' => 'int',
                'feeTypes' => 'int',
                'gradeScale' => 'int',
                'scholarshipTypes' => 'int',
                // the fixture above seats nine offices to give the desks a home, so this tile's honest answer
                // on this database is nine — pinned below against the table rather than against zero
                'offices' => 'int-any',
                'rooms' => 'int',
                'blocks' => 'int',
            ],
        ]);

        $this->assertSame(Offices::count(), $props['stats']['offices'], 'the catalog desk must count the offices that exist');
    }

    #[Test]
    public function a_rate_the_server_cannot_compute_is_answered_as_null_so_the_tile_dashes(): void
    {
        // examresults.examResult is nullable, so with nothing marked a pass rate is unknown rather than 0%.
        // This is the one figure the handler is required NOT to coerce, and the tile shows a dash for it.
        $props = $this->assertNumbersFromTheServer('exam.results', [
            'stats' => ['pass' => 'int', 'fail' => 'int', 'scored' => 'int'],
        ]);

        $this->assertArrayHasKey('passRate', $props['stats']);
        $this->assertNull($props['stats']['passRate'], 'a rate over nothing-scored must be null, not 0 — the desk must see it is unknown');
    }
}
