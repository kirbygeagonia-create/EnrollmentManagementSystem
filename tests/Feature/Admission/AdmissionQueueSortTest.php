<?php

namespace Tests\Feature\Admission;

use App\Enums\AdmissionStatus;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Admissions;
use App\Models\Courses;
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
 * Prompt §14's sorting control on the Admission queue — the second screen wired to `App\Support\ListSort`
 * (§28.3 G-40, whose sorting half is the work that remains).
 *
 * The queue ordered itself by `admissionId desc` and nothing else, so an officer looking for one applicant among
 * pending cases could only page. The order belongs in the query because the list paginates on the server: a client
 * sort would order only the twenty rows on screen and present that as the queue.
 *
 * The fixture follows the lesson the Registrar queue paid for: the surnames are created in an order that agrees
 * with NEITHER surname-ascending nor id-descending, so a mutant that put the old hard-coded order back cannot pass
 * by coincidence.
 */
class AdmissionQueueSortTest extends TestCase
{
    use RefreshDatabase;

    private Courses $course;

    private Academicterms $term;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        Offices::firstOrCreate(['officeId' => 6], ['officeName' => 'Office of Admissions']);
        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        $unit = Academicunits::create([
            'unitName' => 'College of Computer Studies',
            'unitType' => 'college',
        ]);
        $this->course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSIT',
            'courseName' => 'Information Technology',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);
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
    }

    private function desk(): Staffusers
    {
        $staff = Staffusers::factory()->create([
            'officeId' => 6,
            'employeeNo' => 'EMP-ADMSORT-'.uniqid(),
            'username' => 'adm_sort_'.uniqid(),
            'email' => 'adm_sort_'.uniqid().'@example.com',
        ]);
        $staff->assignRole('AdmissionOfficer');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    /** One pending application under one surname, so the queue's order is the surnames' order. */
    private function application(string $lastName, string $applicantType = 'firstYear'): Admissions
    {
        $student = Students::create([
            'schoolIdNumber' => 'ADMSORT-'.strtoupper(substr(uniqid(), -6)),
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
            'email' => 'adm_sort_'.uniqid().'@example.com',
            'username' => 'adm_sort_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        return Admissions::create([
            'studentId' => $student->studentId,
            'courseId' => $this->course->courseId,
            'termId' => $this->term->termId,
            'applicantType' => $applicantType,
            'admissionStatus' => AdmissionStatus::Pending->value,
        ]);
    }

    /**
     * Created Bautista, Zulueta, Magsaysay: surname-ascending is B, M, Z and id-descending is M, Z, B, so no
     * accidental agreement with the retired order can pass a case meant to catch it.
     */
    private function applicantTrio(): void
    {
        $this->application('Bautista');
        $this->application('Zulueta');
        $this->application('Magsaysay');
    }

    /** @return list<array<string, mixed>> */
    private function rows($response): array
    {
        return $response->viewData('page')['props']['admissions']['data'];
    }

    private function surnames($response): array
    {
        return array_map(fn (array $row): ?string => $row['student']['lastName'] ?? null, $this->rows($response));
    }

    #[Test]
    public function the_queue_can_be_ordered_by_the_applicants_surname(): void
    {
        $this->actingAs($this->desk());
        $this->applicantTrio();

        $response = $this->get(route('admission.index', ['sort' => 'name', 'direction' => 'asc']));

        $response->assertOk();
        // The surname order, not merely "a different order": Bautista before Magsaysay before Zulueta.
        $this->assertSame(['Bautista', 'Magsaysay', 'Zulueta'], $this->surnames($response));
    }

    #[Test]
    public function the_second_request_reverses_the_same_column(): void
    {
        $this->actingAs($this->desk());
        $this->applicantTrio();

        $ascending = $this->get(route('admission.index', ['sort' => 'name', 'direction' => 'asc']));
        $descending = $this->get(route('admission.index', ['sort' => 'name', 'direction' => 'desc']));

        $this->assertSame(['Bautista', 'Magsaysay', 'Zulueta'], $this->surnames($ascending));
        $this->assertSame(
            array_reverse($this->surnames($ascending)),
            $this->surnames($descending),
            'the toggle must reverse the column it was already on, not switch to another one'
        );
        $this->assertSame('desc', $descending->viewData('page')['props']['sort']['direction']);
    }

    #[Test]
    public function a_column_the_handler_never_listed_falls_back_and_never_reaches_the_query(): void
    {
        // The queue with nothing asked of it is the reference the fallback must equal. Comparing the two requests
        // rather than a hand-written list also refuses the trap this file already records: a fixed expectation can
        // agree with the retired order by accident, and then a mutant cannot be caught.
        $this->actingAs($this->desk());
        $this->applicantTrio();

        // `passwordHash` is a real column on a joined table and is exactly what a request must not be able to name:
        // an identifier cannot be bound as a parameter, so the only safe reading of an unlisted key is the fallback.
        $default = $this->get(route('admission.index'));
        $response = $this->get(route('admission.index', ['sort' => 'passwordHash']));

        $sort = $response->viewData('page')['props']['sort'];
        $this->assertSame('admissionId', $sort['column'], 'an unlisted key must fall back, not pass through');
        $this->assertNotContains('passwordHash', $sort['available']);
        $this->assertSame(
            $this->surnames($default),
            $this->surnames($response),
            'a key the handler never listed must order the queue exactly as no request would'
        );
    }

    #[Test]
    public function the_page_is_told_which_order_was_applied_and_which_ones_exist(): void
    {
        $this->actingAs($this->desk());
        $this->applicantTrio();

        $sort = $this->get(route('admission.index', ['sort' => 'status', 'direction' => 'asc']))
            ->viewData('page')['props']['sort'];

        // A sort the server obeyed but never reported is a header that resets while the list stays narrowed — the
        // defect lane 46 owns for filters, arriving with the density control that replaced the complaint.
        $this->assertSame('status', $sort['column']);
        $this->assertSame('asc', $sort['direction']);
        foreach (['name', 'schoolId', 'applicantType', 'status', 'admissionId'] as $key) {
            $this->assertContains($key, $sort['available']);
        }
    }

    #[Test]
    public function a_direction_that_is_not_ascending_or_descending_is_not_passed_to_the_database(): void
    {
        $this->actingAs($this->desk());
        $this->applicantTrio();

        $response = $this->get(route('admission.index', [
            'sort' => 'name',
            'direction' => 'asc; drop table students',
        ]));

        $sort = $response->viewData('page')['props']['sort'];
        $this->assertSame('desc', $sort['direction'], 'anything but asc reduces to desc, and nothing else reaches the order clause');
        $this->assertSame(['Zulueta', 'Magsaysay', 'Bautista'], $this->surnames($response));
    }
}
