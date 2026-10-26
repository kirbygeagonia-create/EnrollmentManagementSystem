<?php

namespace Tests\Feature\Admission;

use App\Enums\AdmissionStatus;
use App\Enums\UnitType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Addresses;
use App\Models\Admissionrequirements;
use App\Models\Admissions;
use App\Models\Courses;
use App\Models\Guardians;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Studentrequirementsubmissions;
use App\Models\Students;
use App\Policies\AdmissionPolicy;
use App\Support\SchoolTime;
use App\Support\StudentRecordDefaults;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Draft intake (development prompt §7.1: "Incomplete intake can be saved as a Draft / Incomplete record and
 * resumed later without re-entering all data"; §14 makes it an acceptance case).
 *
 * Before this, the first save was already a submitted case: `admissionStatus` held only pending/approved/
 * rejected, and store() wrote Pending and seeded the requirement rows in the same transaction — so a walk-in
 * applicant who did not have their Form 137 had nowhere to stop.
 *
 * The halves a reader cannot infer are pinned here: what a draft still owes (the identity and program fields,
 * because students.* are NOT NULL and a placeholder would be invented data), and which acts the desk is
 * *offered* — the page used to decide that from the submission's own status word, so it offered Verify on a
 * draft, and on an approved case, where verifyRequirements() answers 403.
 */
class DraftIntakeTest extends TestCase
{
    use DatabaseTransactions;

    private int $courseId;

    private int $termId;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite']);
        $this->seedReferenceData();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RbacSeeder', '--database' => 'sqlite']);
    }

    private function seedReferenceData(): void
    {
        Offices::insert([
            ['officeId' => 1, 'officeName' => 'Registrar'],
            ['officeId' => 6, 'officeName' => 'Admission'],
        ]);

        Religions::create(['religionId' => 1, 'religionName' => 'Roman Catholic']);

        $unit = Academicunits::create([
            'unitCode' => 'CCS',
            'unitName' => 'College of Computer Studies',
            'unitType' => UnitType::College,
        ]);

        $this->courseId = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCS',
            'courseName' => 'Bachelor of Science in Computer Science',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ])->courseId;

        $year = Academicyears::create([
            'yearStart' => 2026,
            'yearEnd' => 2027,
            'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01',
            'endDate' => '2027-05-31',
        ]);

        $this->termId = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ])->termId;
    }

    private function staff(int $officeId, string $role): Staffusers
    {
        $staff = Staffusers::factory()->make([
            'officeId' => $officeId,
            'role' => 'staff',
            'employeeNo' => 'EMP-'.uniqid(),
            'username' => 'user_'.uniqid(),
            'email' => 'user_'.uniqid().'@example.com',
        ]);
        unset($staff->remember_token);
        $staff->save();
        $staff->assignRole($role);

        return $staff;
    }

    private function requirement(string $name, string $appliesTo): Admissionrequirements
    {
        return Admissionrequirements::create([
            'requirementName' => $name,
            'appliesTo' => $appliesTo,
            'isRequired' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function intakePayload(array $overrides = []): array
    {
        $tag = uniqid();

        return array_merge([
            'schoolIdNumber' => 'LRN-'.$tag,
            'lastName' => 'Dela Cruz',
            'firstName' => 'Juan',
            'middleName' => 'M',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2008-01-01',
            'birthplace' => 'Biñan',
            'citizenship' => 'Filipino',
            'religionId' => 1,
            'civilStatus' => 'single',
            'contactNumber' => '09171234567',
            'email' => "applicant_{$tag}@example.com",
            'username' => "applicant_{$tag}",
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'courseId' => $this->courseId,
            'termId' => $this->termId,
            'applicantType' => 'firstYear',
            'addresses' => [[
                'addressType' => 'home',
                'houseBuildingNo' => '12',
                'street' => 'Mabini',
                'barangay' => 'Poblacion',
                'cityMunicipality' => 'Biñan',
                'province' => 'Laguna',
                'country' => 'Philippines',
            ]],
            'guardians' => [[
                'relationship' => 'mother',
                'fullName' => 'Maria Dela Cruz',
                'contactNumber' => '09171234568',
                'isEmergencyContact' => true,
            ]],
        ], $overrides);
    }

    /**
     * The wizard's own untouched rows. It pre-selects the picker (addressType home, relationship mother) and
     * pre-fills the country, then posts every row it was given — so this is what a real "Save as draft" click
     * sends, and it is the shape that a bare `nullable|array` rule would still refuse.
     *
     * @return array<string, mixed>
     */
    private function blankSections(): array
    {
        return [
            'addresses' => [[
                'addressType' => 'home',
                'houseBuildingNo' => '',
                'street' => '',
                'sitioPurok' => '',
                'barangay' => '',
                'cityMunicipality' => '',
                'district' => '',
                'province' => '',
                'region' => '',
                'zipCode' => '',
                'country' => 'Philippines',
            ]],
            'guardians' => [[
                'relationship' => 'mother',
                'fullName' => '',
                'contactNumber' => '',
                'email' => '',
                'isEmergencyContact' => false,
                'isAuthorizedToActOnBehalf' => false,
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function props(string $url): array
    {
        return $this->get($url)->viewData('page')['props'];
    }

    #[Test]
    public function a_draft_is_saved_without_the_sections_the_applicant_has_not_brought_yet(): void
    {
        $this->actingAs($this->staff(6, 'AdmissionOfficer'));

        $this->post(route('admission.store'), $this->intakePayload(
            array_merge($this->blankSections(), ['intakeAction' => 'draft'])
        ))->assertSessionHasNoErrors();

        $admission = Admissions::sole();

        $this->assertEquals(AdmissionStatus::Draft, $admission->admissionStatus);
        $this->assertSame(SchoolTime::today(), $admission->draftedDate?->toDateString());
        $this->assertSame(0, Addresses::count());
        $this->assertSame(0, Guardians::count());

        // The person is still named: students.* are NOT NULL without a default, so a draft defers the address
        // and guardian sections, never the identity.
        $this->assertSame('Dela Cruz', Students::sole()->lastName);
    }

    #[Test]
    public function a_half_written_address_is_kept_and_named_rather_than_dropped(): void
    {
        $this->actingAs($this->staff(6, 'AdmissionOfficer'));

        $row = $this->blankSections()['addresses'][0];
        $row['barangay'] = 'Poblacion';

        // The desk typed into the row, so the draft must not silently discard their work: the rule that wants
        // cityMunicipality and province still bites.
        $this->post(route('admission.store'), $this->intakePayload([
            'intakeAction' => 'draft',
            'addresses' => [$row],
            'guardians' => $this->blankSections()['guardians'],
        ]))->assertSessionHasErrors([
            'addresses.0.cityMunicipality',
            'addresses.0.province',
        ]);

        $this->assertSame(0, Admissions::count());
    }

    #[Test]
    public function the_same_form_without_the_draft_act_is_still_a_submitted_case(): void
    {
        $this->actingAs($this->staff(6, 'AdmissionOfficer'));

        $this->post(route('admission.store'), $this->intakePayload())->assertSessionHasNoErrors();

        $admission = Admissions::sole();

        $this->assertEquals(AdmissionStatus::Pending, $admission->admissionStatus);
        $this->assertNull($admission->draftedDate);
        $this->assertSame(1, Addresses::count());
    }

    #[Test]
    public function a_draft_is_not_in_the_queue_but_is_counted_and_filterable_as_a_draft(): void
    {
        $this->actingAs($this->staff(6, 'AdmissionOfficer'));
        $this->makeDraft();

        $stats = $this->props(route('admission.index'))['stats'];
        $this->assertSame(1, $stats['draft']);
        $this->assertSame(0, $stats['pending']);
        $this->assertSame(1, $stats['total'], 'A draft is an admission record; the queue tiles count it under its own word.');

        // The two keys the desk's new control offers must reach the handler.
        $this->assertSame(1, count($this->props(route('admission.index', ['status' => 'draft']))['admissions']['data']));
        $this->assertSame(0, count($this->props(route('admission.index', ['status' => 'pending']))['admissions']['data']));
    }

    #[Test]
    public function submitting_a_draft_names_the_sections_still_missing_and_leaves_it_a_draft(): void
    {
        $this->actingAs($this->staff(6, 'AdmissionOfficer'));
        $this->makeDraft();

        $admission = Admissions::sole();

        $this->post(route('admission.submit-draft', $admission))
            ->assertSessionHasErrors('intake');

        $message = session()->get('errors')->getBag('default')->first('intake');
        $this->assertStringContainsString('an address', $message);
        $this->assertStringContainsString('a guardian or emergency contact', $message);

        $this->assertEquals(AdmissionStatus::Draft, $admission->fresh()->admissionStatus);
    }

    #[Test]
    public function a_completed_draft_is_submitted_and_holds_one_checklist_row_per_requirement(): void
    {
        $this->actingAs($this->staff(6, 'AdmissionOfficer'));

        $this->requirement('Form 137', 'firstYear');
        $this->requirement('Certificate of Good Moral Character', 'all');

        $this->makeDraft();
        $admission = Admissions::sole();

        // The checklist exists from the draft itself, so a paper handed over at the counter next week has
        // something to attach to — this is the act §7.1's "resumed later" is for.
        $this->assertSame(2, $admission->requirementSubmissions()->count());

        $this->seatTheMissingSections($admission);

        $this->post(route('admission.submit-draft', $admission))->assertSessionHasNoErrors();

        $this->assertEquals(AdmissionStatus::Pending, $admission->fresh()->admissionStatus);

        // Two rows, not four: submitDraft() seeds again, and studentrequirementsubmissions has no unique key
        // on (admissionId, requirementId) — measured on live `ems` — so only the helper's own check stops a
        // duplicate.
        $this->assertSame(2, $admission->requirementSubmissions()->count());
        $this->assertSame(2, Studentrequirementsubmissions::count());
    }

    #[Test]
    public function a_requirement_added_while_the_case_sat_as_a_draft_is_picked_up_on_submit(): void
    {
        $this->actingAs($this->staff(6, 'AdmissionOfficer'));

        $this->requirement('Form 137', 'firstYear');
        $this->makeDraft();

        $admission = Admissions::sole();
        $this->assertSame(1, $admission->requirementSubmissions()->count());

        $this->requirement('Barcode Assessment Result', 'firstYear');
        $this->seatTheMissingSections($admission);

        $this->post(route('admission.submit-draft', $admission))->assertSessionHasNoErrors();

        $this->assertSame(2, $admission->requirementSubmissions()->count());
    }

    #[Test]
    public function a_document_is_received_on_a_draft_but_the_draft_cannot_be_verified_approved_or_rejected(): void
    {
        Storage::fake('documents');
        $this->actingAs($this->staff(6, 'AdmissionOfficer'));

        $requirement = $this->requirement('Form 137', 'firstYear');
        $this->makeDraft();
        $admission = Admissions::sole();

        $this->post(route('admission.requirements.submit', [$admission, $requirement]), [
            'file' => UploadedFile::fake()->create('form137.pdf', 40),
        ])->assertSessionHasNoErrors();

        $this->assertSame('submitted', $requirement->studentrequirementsubmissions()->sole()->submissionStatus->value);

        // Verification is a review of a submitted case, not of a parked one.
        $this->post(route('admission.requirements.verify', [$admission, $requirement]), ['approved' => true])
            ->assertForbidden();
        $this->post(route('admission.approve', $admission))->assertForbidden();
        $this->post(route('admission.reject', $admission))->assertForbidden();

        // And the readiness list says so in the desk's own words rather than letting the officer click into it.
        $this->assertContains(
            'Only a pending application can be approved — this one is draft.',
            app(AdmissionPolicy::class)->approvalBlockers($admission)
        );
    }

    #[Test]
    public function the_desk_is_offered_only_the_acts_the_policy_will_accept(): void
    {
        $this->actingAs($this->staff(6, 'AdmissionOfficer'));

        $this->requirement('Form 137', 'firstYear');
        $this->makeDraft();
        $draft = Admissions::sole();

        $onDraft = $this->props(route('admission.show', $draft))['deskActions'];
        $this->assertTrue($onDraft['submitRequirements']);
        $this->assertFalse($onDraft['verifyRequirements']);
        $this->assertTrue($onDraft['submitDraft']);

        $this->seatTheMissingSections($draft);
        $this->post(route('admission.submit-draft', $draft))->assertSessionHasNoErrors();

        $onPending = $this->props(route('admission.show', $draft))['deskActions'];
        $this->assertTrue($onPending['verifyRequirements']);
        $this->assertFalse($onPending['submitDraft'], 'Only a draft offers the draft-submit act.');

        // An approved case holds no verification work either. The page used to show the button from the
        // submission's own status word, so it offered an act the route refused.
        $approved = Admissions::create([
            'studentId' => $draft->studentId,
            'courseId' => $this->courseId,
            'termId' => $this->termId,
            'applicantType' => 'firstYear',
            'admissionStatus' => AdmissionStatus::Approved->value,
        ]);

        $onApproved = $this->props(route('admission.show', $approved))['deskActions'];
        $this->assertFalse($onApproved['verifyRequirements']);
        $this->assertFalse($onApproved['submitRequirements']);
        $this->assertFalse($onApproved['submitDraft']);
    }

    #[Test]
    public function a_view_only_account_is_offered_nothing_and_refused_the_submit(): void
    {
        $this->actingAs($this->staff(6, 'AdmissionOfficer'));
        $this->requirement('Form 137', 'firstYear');
        $this->makeDraft();
        $admission = Admissions::sole();

        // `Staff` holds admission.view and none of the three intake rights. OfficeHead is not the right
        // counter-example here: RbacSeeder grants it admission.create, .update, .requirements.submit and
        // .requirements.verify on purpose, so its flags are all true and would prove nothing.
        $this->actingAs($this->staff(6, 'Staff'));

        $actions = $this->props(route('admission.show', $admission))['deskActions'];
        $this->assertFalse($actions['submitDraft']);
        $this->assertFalse($actions['submitRequirements']);
        $this->assertFalse($actions['verifyRequirements']);

        $this->post(route('admission.submit-draft', $admission))->assertForbidden();
        $this->assertEquals(AdmissionStatus::Draft, $admission->fresh()->admissionStatus);
    }

    #[Test]
    public function a_submitted_case_cannot_be_pushed_back_through_the_draft_submit_act(): void
    {
        $this->actingAs($this->staff(6, 'AdmissionOfficer'));

        $this->post(route('admission.store'), $this->intakePayload())->assertSessionHasNoErrors();
        $admission = Admissions::sole();

        $this->post(route('admission.submit-draft', $admission))->assertSessionHasErrors('admissionStatus');
        $this->assertEquals(AdmissionStatus::Pending, $admission->fresh()->admissionStatus);
    }

    private function makeDraft(): void
    {
        $this->post(route('admission.store'), $this->intakePayload(
            array_merge($this->blankSections(), ['intakeAction' => 'draft'])
        ))->assertSessionHasNoErrors();
    }

    private function seatTheMissingSections(Admissions $admission): void
    {
        $studentId = $admission->studentId;

        Addresses::create(StudentRecordDefaults::address([
            'studentId' => $studentId,
            'addressType' => 'home',
            'barangay' => 'Poblacion',
            'cityMunicipality' => 'Biñan',
            'province' => 'Laguna',
        ]));
        Guardians::create(StudentRecordDefaults::guardian([
            'studentId' => $studentId,
            'relationship' => 'mother',
            'fullName' => 'Maria Dela Cruz',
            'contactNumber' => '09171234568',
            'isEmergencyContact' => true,
        ]));
    }
}
