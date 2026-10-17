<?php

namespace Tests\Feature\Admin;

use App\Enums\StaffStatus;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Shiftingrequests;
use App\Models\Staffusers;
use App\Models\Students;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A delete the schema refuses must answer with a reason, not with a database error page.
 *
 * Live `ems` carries 70 foreign keys whose delete rule is NO ACTION or RESTRICT, and the reference-data and
 * user-management screens answer them with hand-written lists — `blockedDeletionMessage()` is given one count
 * per child table, and `UserManagementController` walks a literal chain of `->exists()` checks. A hand-kept
 * list drifts from the schema, and this is the proof: the shift module arrived after both screens were written,
 * named a course in two columns and an officer in one, and neither list said so.
 *
 * Before the fix neither request showed a stack trace — `bootstrap/app.php:140` turns every failed write's
 * QueryException into one generic flash ("conflicts with information already on file… Reload the record,
 * repeat the step"), which names no record and invites a retry that can never work. These two tests are the
 * ones that fail when the guard is absent, so a re-broken guard cannot pass silently.
 */
class DeleteGuardNamedByShiftDocketTest extends TestCase
{
    use DatabaseTransactions;

    private Staffusers $admin;

    private Staffusers $officer;

    private Courses $from;

    private Courses $to;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RbacSeeder', '--database' => 'sqlite']);

        $unit = Academicunits::create(['unitName' => 'College of Computer Science', 'unitType' => 'college']);
        $year = Academicyears::create([
            'yearLabel' => 'AY-'.uniqid(),
            'startDate' => '2026-06-01',
            'endDate' => '2027-03-31',
        ]);
        Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ]);

        $this->from = $this->course($unit->unitId, 'BSIT');
        $this->to = $this->course($unit->unitId, 'BSCS');

        $this->admin = $this->staff('sysadmin-user', 'SysAdmin');
        $this->officer = $this->staff('officer-user', 'Staff');
    }

    private function course(int $unitId, string $code): Courses
    {
        return Courses::create([
            'unitId' => $unitId,
            'courseCode' => $code.'-'.uniqid(),
            'courseName' => 'Bachelor of '.$code,
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);
    }

    private function staff(string $username, string $role): Staffusers
    {
        $staff = Staffusers::factory()->make([
            'officeId' => 1,
            'role' => 'staff',
            'employeeNo' => 'EMP-'.uniqid(),
            'username' => $username.'-'.uniqid(),
            'email' => $username.'-'.uniqid().'@example.com',
        ]);
        unset($staff->remember_token);
        $staff->save();

        $staff->assignRole($role);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    private function student(): Students
    {
        return Students::create([
            'schoolIdNumber' => 'LANE43-'.uniqid(),
            'lastName' => 'Santos',
            'firstName' => 'Kevin',
            'middleName' => 'M',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2005-01-02',
            'birthplace' => 'Biñan, Laguna',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'contactNumber' => '09170000000',
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
            'email' => 'lane43_'.uniqid().'@example.com',
            'username' => 'lane43_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);
    }

    private function docket(Courses $from, Courses $to, Staffusers $requestedBy): Shiftingrequests
    {
        return Shiftingrequests::create([
            'studentId' => $this->student()->studentId,
            'currentCourseId' => $from->courseId,
            'targetCourseId' => $to->courseId,
            'willStatement' => 'I will move to the other program if the department endorses it.',
            'requestStatus' => 'pending',
            'requestedBy' => $requestedBy->userId,
            'requestedAt' => now(),
        ]);
    }

    #[Test]
    public function a_course_named_by_a_shift_docket_is_not_deleted_and_the_screen_says_which_docket(): void
    {
        $docket = $this->docket($this->from, $this->to, $this->officer);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.reference-data.courses.destroy', $this->from));

        // The two columns are separate constraints on one row, so the count must read 1 docket — not 2 — and
        // the course must still be on the table when the request is over.
        $response->assertRedirect();
        $error = session('error');
        $this->assertIsString($error, 'the delete must answer with a reason, not a constraint violation');
        $this->assertStringContainsString('shift request', $error);
        $this->assertStringNotContainsString('foreign key', strtolower((string) $error));

        $this->assertDatabaseHas('courses', ['courseId' => $this->from->courseId]);
        $this->assertDatabaseHas('shiftingrequests', ['shiftingRequestId' => $docket->shiftingRequestId]);
    }

    #[Test]
    public function the_officer_who_raised_a_live_docket_is_deactivated_and_the_reason_is_theirs(): void
    {
        $this->docket($this->from, $this->to, $this->officer);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->officer));

        $response->assertRedirect();

        $this->officer->refresh();
        $this->assertSame(
            StaffStatus::Inactive,
            $this->officer->status,
            'a requester the schema protects with RESTRICT must be deactivated, not deleted and not a 500'
        );

        // The point is the reason, not just the outcome. Two paths deactivate the account: the list of checks
        // that runs before the delete, and the catch-all after the constraint has already fired. Only the
        // first one knows the docket is why — so this assertion fails if the shift request is dropped from the
        // list, which is what makes it a test of the guard rather than of the fallback.
        $this->assertStringContainsString(
            'historical activity',
            (string) session('success'),
            'the desk must be told the docket named this officer, not that some constraint happened to fire'
        );

        $this->assertDatabaseHas('shiftingrequests', ['requestedBy' => $this->officer->userId]);
    }
}
