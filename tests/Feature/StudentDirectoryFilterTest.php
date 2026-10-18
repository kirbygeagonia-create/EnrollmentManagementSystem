<?php

namespace Tests\Feature;

use App\Enums\StudentStatus;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Lane 46 — the filter a desk sets must reach the query.
 *
 * The student directory's Status dropdown sent `status` while StudentController::index never read it, so
 * picking any option listed every student and then reset the control on the next render. Two of its four
 * options (`graduated`, `dropped`) were also unrepresentable: `students.status` is `enum('active','inactive')`.
 *
 * The cases are pinned to the rendered props rather than the builder, because what the desk sees is the
 * `filters` echo: a screen may only report the filter it actually applied.
 */
class StudentDirectoryFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Offices::insert(['officeId' => 1, 'officeName' => 'Office of the Registrar']);
        Religions::insert(['religionId' => 1, 'religionName' => 'Roman Catholic']);
    }

    /**
     * An account with no unitId is bounded by no college (App\Support\CollegeScope), so the directory
     * under test shows every student this fixture creates.
     */
    private function deskUser(): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => 1,
            'unitId' => null,
            'employeeNo' => 'EMP-FILTER-'.uniqid(),
            'username' => 'filter_'.uniqid(),
            'email' => 'filter_'.uniqid().'@example.com',
        ]);
        $staff->givePermissionTo('students.view');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    private function student(string $last, StudentStatus $status): Students
    {
        return Students::create([
            'schoolIdNumber' => 'FIL-'.strtoupper(substr(uniqid(), -6)),
            'lastName' => $last,
            'firstName' => 'Ana',
            'middleName' => 'M',
            'suffix' => 'N/A',
            'gender' => 'female',
            'birthdate' => '2005-01-10',
            'birthplace' => 'Tupi, South Cotabato',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09170000000',
            'telephoneNumber' => 'N/A',
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
            'email' => 'ana.example@example.com',
            'username' => 'ana.'.strtolower($last),
            'passwordHash' => 'not-a-real-hash',
            'status' => $status,
        ]);
    }

    /** @return array<string, mixed> */
    private function props(TestResponse $response): array
    {
        return $response->viewData('page')['props'];
    }

    #[Test]
    public function the_status_filter_narrows_the_directory_to_the_status_chosen(): void
    {
        $this->actingAs($this->deskUser());
        $active = $this->student('Alonzo', StudentStatus::Active);
        $inactive = $this->student('Bactol', StudentStatus::Inactive);

        $response = $this->get(route('students.index', ['status' => 'inactive']))
            ->assertOk();
        $props = $this->props($response);

        $ids = array_column($props['students']['data'], 'studentId');
        $this->assertSame([$inactive->studentId], $ids, 'picking Inactive must list the inactive students only');
        $this->assertSame('inactive', $props['filters']['status']);

        $all = array_column($this->props($this->get(route('students.index')))['students']['data'], 'studentId');
        $this->assertContains($active->studentId, $all);
        $this->assertContains($inactive->studentId, $all);
    }

    #[Test]
    public function a_status_the_column_cannot_hold_narrows_nothing_and_is_not_echoed_as_applied(): void
    {
        $this->actingAs($this->deskUser());
        $this->student('Alonzo', StudentStatus::Active);

        // `graduated` is what the dropdown used to offer. students.status is enum('active','inactive'), so
        // no row can carry it; the honest answer is an unfiltered list whose echo says nothing was applied —
        // never a screen that looks narrowed while it lists everyone.
        $props = $this->props($this->get(route('students.index', ['status' => 'graduated'])));

        $this->assertCount(1, $props['students']['data']);
        $this->assertNull($props['filters']['status']);
    }

    #[Test]
    public function the_status_vocabulary_the_handler_answers_to_is_the_one_the_model_casts_with(): void
    {
        $this->assertSame(
            ['active', 'inactive'],
            array_column(StudentStatus::cases(), 'value'),
            'the two values the filter accepts are the two the vocabulary declares — no third option can be'
            .' added to the dropdown without the enum, and the enum is what students.status holds'
        );

        $this->assertSame(
            StudentStatus::class,
            (new Students)->getCasts()['status'],
            'the person record must not be cast with the staff account’s vocabulary'
        );
    }
}
