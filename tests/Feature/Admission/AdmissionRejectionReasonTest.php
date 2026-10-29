<?php

namespace Tests\Feature\Admission;

use App\Enums\AdmissionStatus;
use App\Enums\SubmissionStatus;
use App\Enums\UnitType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Admissionrequirements;
use App\Models\Admissions;
use App\Models\Courses;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Studentrequirementsubmissions;
use App\Models\Students;
use App\Support\SchoolTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A rejection names its cause (development prompt §6.5, matrix gap G33).
 *
 * Before this, `reject()` wrote only the status word and the desk posted an empty body, so the reason a
 * applicant was denied lived in nobody's record: the page's own rejected panel read "Contact the admission
 * office for details". The requirement-level refusal was worse, because the screen supplied a canned
 * sentence when the officer typed nothing — which read as a reason on the file while being nobody's words.
 *
 * The half a reader cannot infer from the column: an *approval* still needs no remark. Requiring text on
 * every line would push the desk to write something about a document that was simply fine.
 */
class AdmissionRejectionReasonTest extends TestCase
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

        $this->seed('Database\Seeders\RbacSeeder');
    }

    private function desk(): Staffusers
    {
        $staff = Staffusers::factory()->make([
            'officeId' => 6,
            'role' => 'staff',
            'employeeNo' => 'EMP-'.uniqid(),
            'username' => 'admission_'.uniqid(),
            'email' => 'admission_'.uniqid().'@example.com',
        ]);
        unset($staff->remember_token);
        $staff->save();
        $staff->assignRole('AdmissionOfficer');

        return $staff;
    }

    private function application(array $overrides = []): Admissions
    {
        $student = Students::create([
            'schoolIdNumber' => 'ADM-'.uniqid(),
            'lastName' => 'Applicant',
            'firstName' => 'Testing',
            'middleName' => 'Q',
            'suffix' => 'N/A',
            'gender' => 'female',
            'birthdate' => '2005-01-10',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
            'email' => 'applicant_'.uniqid().'@example.com',
            'username' => 'applicant_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        return Admissions::create(array_merge([
            'studentId' => $student->studentId,
            'courseId' => $this->courseId,
            'termId' => $this->termId,
            'applicantType' => 'firstYear',
            'admissionStatus' => AdmissionStatus::Pending->value,
        ], $overrides));
    }

    private function submittedRequirement(Admissions $admission, string $name = 'Form 137'): Studentrequirementsubmissions
    {
        $requirement = Admissionrequirements::create([
            'requirementName' => $name,
            'appliesTo' => 'firstYear',
            'isRequired' => true,
        ]);

        return Studentrequirementsubmissions::create([
            'admissionId' => $admission->admissionId,
            'requirementId' => $requirement->requirementId,
            'submissionStatus' => 'submitted',
            // submittedDate is NOT NULL and a DATE column, so the fixture dates it the way the desk does.
            'submittedDate' => SchoolTime::today(),
        ]);
    }

    private function props(string $url): array
    {
        return $this->get($url)->viewData('page')['props'];
    }

    #[Test]
    public function a_rejection_with_no_reason_written_is_refused_and_the_application_stays_pending(): void
    {
        $this->actingAs($this->desk());
        $admission = $this->application();

        $this->post(route('admission.reject', $admission), [])
            ->assertSessionHasErrors('rejectionReason');

        $this->assertSame(AdmissionStatus::Pending, $admission->fresh()->admissionStatus);
        $this->assertNull($admission->fresh()->rejectionReason);
    }

    #[Test]
    public function a_reason_shorter_than_the_rule_names_the_field_it_refused(): void
    {
        $this->actingAs($this->desk());
        $admission = $this->application();

        // Seven characters: under the ten the rule asks for, so the refusal is about the length and the
        // application stays pending. A reason this short would not explain the decision to anybody.
        $this->post(route('admission.reject', $admission), ['rejectionReason' => 'Denied.'])
            ->assertSessionHasErrors('rejectionReason');

        $this->assertSame(AdmissionStatus::Pending, $admission->fresh()->admissionStatus);
    }

    #[Test]
    public function a_rejection_stores_the_officers_own_words_and_files_them_on_the_schools_day(): void
    {
        $this->actingAs($this->desk());
        $admission = $this->application();

        $reason = 'Missing the third-year laboratory prerequisite on the transcript.';

        // Frozen at 18:00Z, when Manila is already on the next civil day and the application's UTC clock is
        // not. Without the freeze the two clocks agree for most of the day and the case passes on either
        // stamp — which is exactly how a mutant that puts `now()` back survived this file's own harness.
        CarbonImmutable::setTestNow('2026-10-29 18:00:00');

        try {
            $this->assertNotSame(CarbonImmutable::now()->toDateString(), SchoolTime::today(),
                'the frozen instant must put the two clocks in disagreement, or this case proves nothing');

            $this->post(route('admission.reject', $admission), ['rejectionReason' => $reason])
                ->assertSessionHasNoErrors()
                ->assertRedirect();
        } finally {
            CarbonImmutable::setTestNow();
        }

        $fresh = $admission->fresh();
        $this->assertSame(AdmissionStatus::Rejected, $fresh->admissionStatus);
        $this->assertSame($reason, $fresh->rejectionReason);
        // evaluatedDate is a DATE column: a Carbon written from the app clock is formatted in UTC, so the row
        // would read 2026-10-29 while the school was already on the 30th.
        $this->assertSame('2026-10-30', $fresh->evaluatedDate?->toDateString());
    }

    #[Test]
    public function the_rejected_panel_shows_the_stored_reason_to_the_desk(): void
    {
        $staff = $this->desk();
        $this->actingAs($staff);
        $admission = $this->application();

        $reason = 'The registrar could not verify the issued transcript presented.';
        $this->post(route('admission.reject', $admission), ['rejectionReason' => $reason])
            ->assertSessionHasNoErrors();

        $shared = $this->props(route('admission.show', $admission))['admission'];
        $this->assertSame($reason, $shared['rejectionReason']);
    }

    #[Test]
    public function a_rejected_document_row_without_a_remark_is_refused(): void
    {
        $this->actingAs($this->desk());
        $admission = $this->application();
        $submission = $this->submittedRequirement($admission);

        $this->post(
            route('admission.requirements.verify', ['admission' => $admission, 'requirement' => $submission->requirementId]),
            ['approved' => false, 'remarks' => '']
        )->assertSessionHasErrors('remarks');

        $this->assertSame(SubmissionStatus::Submitted, $submission->fresh()->submissionStatus);
    }

    #[Test]
    public function a_rejected_document_row_keeps_the_remark_the_desk_wrote_rather_than_a_canned_sentence(): void
    {
        $this->actingAs($this->desk());
        $admission = $this->application();
        $submission = $this->submittedRequirement($admission);

        $remark = 'The image is unreadable at the seal, so the office cannot verify it.';
        $this->post(
            route('admission.requirements.verify', ['admission' => $admission, 'requirement' => $submission->requirementId]),
            ['approved' => false, 'remarks' => $remark]
        )->assertSessionHasNoErrors();

        $fresh = $submission->fresh();
        $this->assertSame(SubmissionStatus::Rejected, $fresh->submissionStatus);
        $this->assertSame($remark, $fresh->remarks);
    }

    #[Test]
    public function an_approved_document_row_needs_no_remark_because_there_is_nothing_to_explain(): void
    {
        $this->actingAs($this->desk());
        $admission = $this->application();
        $submission = $this->submittedRequirement($admission);

        $this->post(
            route('admission.requirements.verify', ['admission' => $admission, 'requirement' => $submission->requirementId]),
            ['approved' => true, 'remarks' => '']
        )->assertSessionHasNoErrors();

        $this->assertSame(SubmissionStatus::Verified, $submission->fresh()->submissionStatus);
        $this->assertNull($submission->fresh()->remarks);
    }
}
