<?php

namespace Tests\Feature\Sorting;

use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Prompt §14's sorting control on the student directory — the second screen wired after the two queues
 * (§28.3 G-40, which stays open until every desk table is wired).
 *
 * The directory had exactly one order since it was written — surname, then given name — so a desk looking for
 * one student among the inactive rows could only page. The order belongs in the query because the list
 * paginates on the server: a client sort would arrange the twenty rows already on screen and present that as
 * the directory, which is the page-versus-set defect §28.3 records for summary tiles.
 *
 * One thing these cases pin that the two queue tests do not: this list's own default direction is ASCENDING.
 * `ListSort` answers descending when a request carries no direction, which is right for a newest-first queue and
 * wrong for a surname list, so the handler asks for `asc` on its own behalf. A case that only checked "a sort
 * happened" would pass with either default, and the desk would open the directory to Z.
 */
class StudentsDirectorySortTest extends TestCase
{
    use RefreshDatabase;

    private Staffusers $desk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        Offices::firstOrCreate(['officeId' => 1], ['officeName' => 'Office of the Registrar']);
        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        $staff = Staffusers::factory()->create([
            'officeId' => 1,
            'employeeNo' => 'EMP-DSORT-'.uniqid(),
            'username' => 'dir_sort_'.uniqid(),
            'email' => 'dir_sort_'.uniqid().'@example.com',
        ]);
        // The directory's own boundary is `students.view` — the widest read right in the system, and the one
        // bare Staff and Instructor accounts do not hold. Granting it is what lets this file say nothing about
        // roles, which is not the subject of a sort.
        $staff->givePermissionTo('students.view');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->desk = $staff;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows($response): array
    {
        return $response->viewData('page')['props']['students']['data'];
    }

    /** @return list<string> */
    private function surnames($response): array
    {
        return array_map(fn (array $row): string => (string) $row['lastName'], $this->rows($response));
    }

    /** @return list<string> */
    private function schoolIds($response): array
    {
        return array_map(fn (array $row): string => (string) $row['schoolIdNumber'], $this->rows($response));
    }

    private function student(string $lastName, string $schoolId, string $status = 'active'): Students
    {
        return Students::create([
            'schoolIdNumber' => $schoolId,
            'lastName' => $lastName,
            'firstName' => 'Given',
            'middleName' => 'M',
            'suffix' => 'N/A',
            'gender' => 'female',
            'birthdate' => '2004-01-01',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'email' => 'dir_sort_'.uniqid().'@example.com',
            'username' => 'dir_sort_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => $status,
        ]);
    }

    /**
     * Created in id order Bautista, Magsaysay, Zulueta, and numbered so the ID order is the *reverse*.
     *
     * Two orders that disagree are what make every case below able to fail: a fixture whose surname order equalled
     * its ID order would let a mutant that ignored the requested column pass the column test, which is the lane 88
     * lesson about a surviving leg whose fixture, not guard, was wrong.
     */
    private function trio(): void
    {
        $this->student('Bautista', 'DIR-003');
        $this->student('Magsaysay', 'DIR-002');
        $this->student('Zulueta', 'DIR-001');
    }

    #[Test]
    public function the_directory_opens_in_surname_order_ascending(): void
    {
        $this->trio();

        $response = $this->actingAs($this->desk)->get(route('students.index'));

        $this->assertSame(['Bautista', 'Magsaysay', 'Zulueta'], $this->surnames($response));
        $sort = $response->viewData('page')['props']['sort'];
        $this->assertSame('name', $sort['column']);
        $this->assertSame('asc', $sort['direction'], 'a directory reads top to bottom unless the desk says otherwise');
    }

    #[Test]
    public function asking_the_same_header_again_reverses_it_instead_of_resetting(): void
    {
        $this->trio();

        $response = $this->actingAs($this->desk)
            ->get(route('students.index', ['sort' => 'name', 'direction' => 'desc']));

        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($response));
        $this->assertSame('desc', $response->viewData('page')['props']['sort']['direction']);
    }

    #[Test]
    public function a_different_column_orders_the_rows_differently_from_the_surname_order(): void
    {
        $this->trio();

        $response = $this->actingAs($this->desk)
            ->get(route('students.index', ['sort' => 'schoolId', 'direction' => 'asc']));

        // The numbers were assigned inverse to the surnames, so this sequence cannot be produced by a sort that
        // ignored the requested column.
        $this->assertSame(['DIR-001', 'DIR-002', 'DIR-003'], $this->schoolIds($response));
        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($response));
        $this->assertSame('schoolId', $response->viewData('page')['props']['sort']['column']);
    }

    #[Test]
    public function a_column_the_handler_never_listed_falls_back_and_never_reaches_the_query(): void
    {
        $this->trio();

        $asked = $this->actingAs($this->desk)
            ->get(route('students.index', ['sort' => 'passwordHash', 'direction' => 'asc']));

        $sort = $asked->viewData('page')['props']['sort'];
        $this->assertSame('name', $sort['column'], 'an unlisted key must fall back to the list’s own column');
        $this->assertNotContains('passwordHash', $sort['available']);

        // Compared with the unsorted request rather than with arithmetic done by hand: the fallback's promise is
        // that it is the default, and an assertion written from mental ordering once agreed with a retired order
        // and reported the code wrong when the code was right.
        $plain = $this->actingAs($this->desk)->get(route('students.index'));
        $this->assertSame($this->schoolIds($plain), $this->schoolIds($asked));
        $this->assertSame(['Bautista', 'Magsaysay', 'Zulueta'], $this->surnames($asked));
    }

    #[Test]
    public function the_direction_is_reduced_to_ascending_or_descending_and_not_passed_through(): void
    {
        $this->trio();

        $response = $this->actingAs($this->desk)
            ->get(route('students.index', ['sort' => 'name', 'direction' => 'asc; drop table students']));

        $this->assertSame('desc', $response->viewData('page')['props']['sort']['direction']);
        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($response));
    }

    #[Test]
    public function a_search_and_an_order_are_both_echoed_so_neither_control_resets(): void
    {
        $this->trio();
        $this->student('Aquino', 'DIR-004', 'inactive');

        $props = $this->actingAs($this->desk)
            ->get(route('students.index', ['search' => 'Aquino', 'sort' => 'status', 'direction' => 'asc']))
            ->viewData('page')['props'];

        // The filter contract §28.3 owns: a key the desk sent that the handler consults but never reports leaves
        // the control blank on the next render while the list stays narrowed.
        $this->assertSame('Aquino', $props['filters']['search']);
        $this->assertSame('status', $props['filters']['sort']);
        $this->assertSame('asc', $props['filters']['direction']);
        $this->assertSame('status', $props['sort']['column']);
        $this->assertCount(1, $props['students']['data'], 'the search still narrows the ordered set');
    }

    #[Test]
    public function the_page_is_told_which_order_was_applied_and_which_it_may_ask_for(): void
    {
        $this->student('Bautista', 'DIR-001');

        $sort = $this->actingAs($this->desk)
            ->get(route('students.index', ['sort' => 'gender', 'direction' => 'asc']))
            ->viewData('page')['props']['sort'];

        $this->assertSame('gender', $sort['column']);
        $this->assertSame('asc', $sort['direction']);
        // Exact and in order, because the header renders a sortable control only from this list: a key that stops
        // being obeyed server-side must stop being clickable on the screen with it.
        $this->assertSame(['name', 'firstName', 'schoolId', 'gender', 'status', 'studentId'], $sort['available']);
    }
}
