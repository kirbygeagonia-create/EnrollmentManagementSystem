<?php

namespace Tests\Feature\Admission;

use App\Enums\AdmissionStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\InstitutionType;
use App\Enums\LevelCompleted;
use App\Enums\PassResult;
use App\Enums\StudentType;
use App\Enums\UnitType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Admissions;
use App\Models\Courses;
use App\Models\Educationalinstitutions;
use App\Models\Enrollments;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use App\Models\Subjects;
use App\Models\Transferacademicrecords;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Intake and Department crediting both wrote academic-level values the schema
 * cannot store: `educationalinstitutions.institutionType` and
 * `studenteducationalbackgrounds.levelCompleted` are fixed to elementary,
 * juniorHigh, seniorHigh, vocational and college, but the validation lists let
 * "secondary" and "graduate" through and rejected "juniorHigh" — so a
 * transferee's high-school record could not be saved at all, and the values
 * that did save crashed on read. Crediting also omitted
 * `educationalinstitutions.cityMunicipality` and `.province`, which are NOT
 * NULL, so every credit transfer died in the insert. These tests pin both
 * forms to the schema.
 */
class ApplicantIntakeTest extends TestCase
{
    use DatabaseTransactions;

    private int $courseId;

    private int $termId;

    private int $subjectId;

    private Enrollments $creditingEnrollment;

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
            ['officeId' => 4, 'officeName' => 'Guidance'],
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

        $this->subjectId = Subjects::create([
            'subjectCode' => 'CCS101',
            'subjectName' => 'Introduction to Programming',
            'lectureUnits' => 3,
            'labUnits' => 1,
            'subjectType' => 'both',
        ])->subjectId;
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
            'addresses' => [
                [
                    'addressType' => 'home',
                    'houseBuildingNo' => '12',
                    'street' => 'Mabini',
                    'barangay' => 'Poblacion',
                    'cityMunicipality' => 'Biñan',
                    'province' => 'Laguna',
                    'country' => 'Philippines',
                ],
            ],
            'guardians' => [
                [
                    'relationship' => 'mother',
                    'fullName' => 'Maria Dela Cruz',
                    'contactNumber' => '09171234568',
                    'isEmergencyContact' => true,
                ],
            ],
        ], $overrides);
    }

    #[Test]
    public function an_applicant_cannot_be_registered_as_continuing_or_shifter(): void
    {
        $this->actingAs($this->staff(6, 'AdmissionOfficer'));

        // admissions.applicantType only holds firstYear and transferee; the old
        // rule accepted four values and the extra two crashed on write.
        foreach (['continuing', 'shifter'] as $type) {
            $this->post(route('admission.store'), $this->intakePayload(['applicantType' => $type]))
                ->assertSessionHasErrors('applicantType');
        }

        $this->assertSame(0, Admissions::count());
    }

    #[Test]
    public function both_supported_applicant_types_are_registered(): void
    {
        $this->actingAs($this->staff(6, 'AdmissionOfficer'));

        foreach (['firstYear', 'transferee'] as $type) {
            $this->post(route('admission.store'), $this->intakePayload(['applicantType' => $type]))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(2, Admissions::count());
        $this->assertEqualsCanonicalizing(
            ['firstYear', 'transferee'],
            Admissions::pluck('applicantType')->map(fn ($v) => $v->value)->all()
        );
        $this->assertEquals(AdmissionStatus::Pending, Admissions::first()->admissionStatus);
    }

    #[Test]
    public function the_junior_high_record_the_form_used_to_reject_is_now_stored(): void
    {
        $this->actingAs($this->staff(6, 'AdmissionOfficer'));

        $background = [
            'institutionName' => 'Biñan National High School',
            'institutionType' => 'juniorHigh',
            'cityMunicipality' => 'Biñan',
            'province' => 'Laguna',
            'levelCompleted' => 'juniorHigh',
            'yearCompleted' => '2024-03-31',
            'strandTrack' => 'N/A',
        ];

        $this->post(route('admission.store'), $this->intakePayload([
            'applicantType' => 'firstYear',
            'educationalBackgrounds' => [$background],
        ]))->assertSessionHasNoErrors();

        $student = Students::orderByDesc('studentId')->first();

        // Reading back is where the old mismatch surfaced as a 500.
        $this->assertEquals(
            InstitutionType::JuniorHigh,
            Educationalinstitutions::where('institutionName', $background['institutionName'])->firstOrFail()->institutionType
        );
        $this->assertEquals(
            LevelCompleted::JuniorHigh,
            $student->educationalBackgrounds()->firstOrFail()->levelCompleted
        );
    }

    #[Test]
    public function academic_levels_outside_the_schema_are_refused_with_a_validation_error(): void
    {
        $this->actingAs($this->staff(6, 'AdmissionOfficer'));

        foreach (['secondary', 'graduate'] as $level) {
            $this->post(route('admission.store'), $this->intakePayload([
                'educationalBackgrounds' => [[
                    'institutionName' => 'Some School',
                    'institutionType' => $level,
                    'cityMunicipality' => 'Biñan',
                    'province' => 'Laguna',
                    'levelCompleted' => $level,
                ]],
            ]))->assertSessionHasErrors([
                'educationalBackgrounds.0.institutionType',
                'educationalBackgrounds.0.levelCompleted',
            ]);
        }
    }

    #[Test]
    public function intake_refuses_a_background_missing_a_not_null_column(): void
    {
        $this->actingAs($this->staff(6, 'AdmissionOfficer'));

        $complete = [
            'institutionName' => 'Biñan NHS',
            'institutionType' => 'juniorHigh',
            'cityMunicipality' => 'Biñan',
            'province' => 'Laguna',
            'levelCompleted' => 'juniorHigh',
            'yearCompleted' => '2024-03-31',
        ];

        // The intake wizard drops empty fields from the request, so before these
        // rules the omitted key reached the insert as NULL and the submit 500ed.
        foreach (['cityMunicipality', 'province', 'yearCompleted'] as $missing) {
            $row = $complete;
            unset($row[$missing]);

            $this->post(route('admission.store'), $this->intakePayload([
                'educationalBackgrounds' => [$row],
            ]))->assertSessionHasErrors('educationalBackgrounds.0.'.$missing);
        }

        $this->assertSame(0, Admissions::count());
    }

    #[Test]
    public function crediting_records_the_previous_institution_at_every_stored_level(): void
    {
        $enrollment = $this->creditingTestSetup();

        $credit = [
            'previousSubjectName' => 'Computer Programming 1',
            'creditedToSubjectId' => $this->subjectId,
            'creditedUnits' => 4,
            'institutionName' => 'Laguna College',
            'cityMunicipality' => 'Santa Cruz',
            'province' => 'Laguna',
            'grade' => 1.5,
        ];

        // The old rule list refused vocational and juniorHigh outright.
        foreach (['vocational', 'juniorHigh', 'college'] as $type) {
            $this->post(route('evaluation.credits.process', $enrollment), [
                'credits' => [array_merge($credit, ['institutionType' => $type])],
            ])->assertSessionHasNoErrors();
        }

        $institution = Educationalinstitutions::where('institutionName', 'Laguna College')->firstOrFail();

        $this->assertEquals(InstitutionType::Vocational, $institution->institutionType);
        $this->assertSame('Santa Cruz', $institution->cityMunicipality);
        $this->assertSame('Laguna', $institution->province);
        $this->assertSame(3, $enrollment->fresh()->creditedsubjects()->count());
    }

    #[Test]
    public function crediting_refuses_an_institution_without_the_location_the_table_requires(): void
    {
        $this->creditingTestSetup();

        // cityMunicipality and province are NOT NULL columns; before they were
        // validated the write blew up as a 500 instead of a form error.
        foreach (['cityMunicipality', 'province'] as $missing) {
            $payload = [
                'previousSubjectName' => 'Missing Location Subject',
                'creditedToSubjectId' => $this->subjectId,
                'creditedUnits' => 3,
                'institutionName' => 'Nowhere College',
                'institutionType' => 'college',
                'cityMunicipality' => 'Biñan',
                'province' => 'Laguna',
            ];
            unset($payload[$missing]);

            $this->post(route('evaluation.credits.process', $this->creditingEnrollment), [
                'credits' => [$payload],
            ])->assertSessionHasErrors('credits.0.'.$missing);
        }
    }

    #[Test]
    public function crediting_refuses_a_level_the_institution_table_cannot_hold(): void
    {
        $enrollment = $this->creditingTestSetup();

        $this->post(route('evaluation.credits.process', $enrollment), [
            'credits' => [[
                'previousSubjectName' => 'Old Subject',
                'creditedToSubjectId' => $this->subjectId,
                'creditedUnits' => 3,
                'institutionName' => 'Graduate School',
                'institutionType' => 'graduate',
                'cityMunicipality' => 'Biñan',
                'province' => 'Laguna',
            ]],
        ])->assertSessionHasErrors('credits.0.institutionType');
    }

    #[Test]
    public function crediting_refuses_a_grade_outside_the_stored_scale(): void
    {
        $enrollment = $this->creditingTestSetup();

        // transferacademicrecords.gradeAtOldSchool is decimal(3,2) and the desk
        // records the Philippine 1.00–5.00 scale, not a percentage.
        $this->post(route('evaluation.credits.process', $enrollment), [
            'credits' => [[
                'previousSubjectName' => 'Percentage Grade Subject',
                'creditedToSubjectId' => $this->subjectId,
                'creditedUnits' => 3,
                'institutionName' => 'Laguna College',
                'institutionType' => 'college',
                'cityMunicipality' => 'Biñan',
                'province' => 'Laguna',
                'grade' => 88,
            ]],
        ])->assertSessionHasErrors('credits.0.grade');
    }

    #[Test]
    public function a_prior_subject_the_student_failed_stays_on_the_record_but_is_never_credited(): void
    {
        // The crediting panel wrote passResult = 'passed' for every row it was
        // given, so a 4.50 from the previous institution subtracted a mandatory
        // subject and satisfied a prerequisite here — a credit the student had not
        // earned. The prior school's record is still kept (a full academic history
        // is retained, passed or failed); only the credit is refused.
        $enrollment = $this->creditingTestSetup();

        $this->post(route('evaluation.credits.process', $enrollment), [
            'credits' => [[
                'previousSubjectName' => 'Discrete Mathematics',
                'creditedToSubjectId' => $this->subjectId,
                'creditedUnits' => 3,
                'institutionName' => 'Failed College',
                'institutionType' => 'college',
                'cityMunicipality' => 'Biñan',
                'province' => 'Laguna',
                'grade' => 4.50,
            ]],
        ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'not credited'));

        $record = Transferacademicrecords::where('subjectNameAtOldSchool', 'Discrete Mathematics')->sole();
        $this->assertEquals(PassResult::Failed, $record->passResult);
        $this->assertSame(
            0,
            $enrollment->fresh()->creditedsubjects()->count(),
            'A failed subject must not subtract a mandatory subject from the load.'
        );
    }

    #[Test]
    public function a_prior_subject_the_student_passed_is_credited_and_marked_passed(): void
    {
        $enrollment = $this->creditingTestSetup();

        $this->post(route('evaluation.credits.process', $enrollment), [
            'credits' => [[
                'previousSubjectName' => 'Introduction to Programming',
                'creditedToSubjectId' => $this->subjectId,
                'creditedUnits' => 3,
                'institutionName' => 'Passed College',
                'institutionType' => 'college',
                'cityMunicipality' => 'Biñan',
                'province' => 'Laguna',
                'grade' => 1.75,
            ]],
        ])->assertSessionHasNoErrors();

        $record = Transferacademicrecords::where('subjectNameAtOldSchool', 'Introduction to Programming')->sole();
        $this->assertEquals(PassResult::Passed, $record->passResult);
        $this->assertSame(1, $enrollment->fresh()->creditedsubjects()->count());
    }

    private function creditingTestSetup(): Enrollments
    {
        $evaluator = $this->staff(4, 'DeptEvaluator');
        $this->actingAs($evaluator);

        $student = Students::create([
            'schoolIdNumber' => 'TF-'.uniqid(),
            'lastName' => 'Reyes',
            'firstName' => 'Bo',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2003-01-01',
            'birthplace' => 'Biñan',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234570',
            'email' => 'bo_'.uniqid().'@example.com',
            'username' => 'bo_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->creditingEnrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $this->courseId,
            'termId' => $this->termId,
            'yearLevel' => 2,
            'studentType' => StudentType::Transferee,
            'enrollmentType' => 'new',
            'academicStanding' => 'irregular',
            'enrollmentStatus' => EnrollmentStatus::Pending,
            'evaluatedBy' => $evaluator->userId,
        ]);

        return $this->creditingEnrollment;
    }
}
