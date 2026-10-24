<?php

namespace Tests\Feature\Print;

use App\Enums\AddressType;
use App\Enums\EnrolledSubjectStatus;
use App\Enums\EnrollmentType;
use App\Enums\Semester;
use App\Enums\StudentType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Addresses;
use App\Models\Courses;
use App\Models\Enrolledsubjects;
use App\Models\Enrollments;
use App\Models\Majors;
use App\Models\Staffusers;
use App\Models\Students;
use App\Models\Subjects;
use App\Support\YearLevel;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * P-2. The enrollment form's paper used to carry no student identity, program, year level or academic
 * period, and the row waited weeks on a blank physical form that never arrived. Ruled 2026-10-23: build
 * it from the designs the school's own papers already show, because the data was never the obstacle.
 * The identification block is now on the blade, and this class pins the three things that made the wait
 * worth something: the desk's eager-load list, the cost of reading the facts, and the printed page.
 *
 * The desk path (`PrintService::printEnrollmentForm`) and the fidelity command
 * (`PrintFidelitySamples`) each write their own eager-load list. This test copies the desk's list and
 * then reads it back out of that file, so the two cannot drift without going red: if a relation is
 * dropped from the desk path, the PDF the Registrar hands a student loses a line silently while
 * `ems:print-fidelity` — which renders from its own list — keeps printing it.
 *
 * P-18 is the review that followed, and it is the same document stating three things wrongly: Part 2
 * counted proposed and dropped subjects as the term's load, the home/current box compared an enum cast
 * with `===` to a string so it could never print, and the print's own number was minted after the page
 * was drawn and appeared nowhere on it. Every one of those survived a green suite, because a filter
 * dropped from a blade still renders, a dead `@if` still renders nothing, and a late `recordIssue()`
 * still writes a log row.
 */
class PrintEnrollmentFormPayloadTest extends TestCase
{
    use RefreshDatabase;

    /** The eight relations `PrintService::printEnrollmentForm()` loads. Kept in one place for the drift check below. */
    private const DESK_RELATIONS = [
        'student.addresses',
        'student.guardians',
        'course',
        'major',
        'term.academicYear',
        'enrolledSubjects.subject',
        'evaluatedByUser',
        'registrarProcessedByUser',
    ];

    private function issuedEnrollment(): Enrollments
    {
        // religionId pinned to 1: RefreshDatabase rolls back but AUTO_INCREMENT does not, so an
        // unpinned insert lands on a fresh id on MySQL while the student fixture asks for 1.
        try {
            DB::table('religions')->insert(['religionId' => 1, 'religionName' => 'Roman Catholic']);
        } catch (QueryException) {
            // Already present on a schema the seeders filled — the pinned id is what matters.
        }

        $unit = Academicunits::create(['unitName' => 'College of Criminology', 'unitType' => 'college']);
        $yearId = DB::table('academicyears')->insertGetId([
            'yearLabel' => '2025-2026',
            'startDate' => '2025-08-01',
            'endDate' => '2026-05-31',
        ]);
        $term = Academicterms::create([
            'academicYearId' => $yearId,
            'semester' => Semester::Second,
            'startDate' => '2026-01-05',
            'endDate' => '2026-05-15',
        ]);
        $course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCCR',
            'courseName' => 'Bachelor of Science in Criminology',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);
        $major = Majors::create(['courseId' => $course->courseId, 'majorName' => 'Criminology']);

        $student = Students::create([
            'schoolIdNumber' => 'P2-PAYLOAD-'.uniqid(),
            'lastName' => 'Dela Cruz',
            'firstName' => 'Juan',
            'middleName' => 'M',
            'suffix' => 'N/A',
            'gender' => 'male',
            'birthdate' => '2004-05-12',
            'birthplace' => 'General Santos',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'status' => 'active',
            'religionId' => 1,
            'contactNumber' => '09171234567',
            'telephoneNumber' => null,
            'semestersCompleted' => 1,
            'yearsInInstitution' => 1,
            'email' => 'p2_payload_'.uniqid().'@example.com',
            'username' => 'p2_payload_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
        ]);

        // `enrollments.evaluatedBy` is NOT NULL, and both signature relations are in the desk's load
        // list, so the fixture seats real staff rows rather than leaving them null.
        $deskStaff = fn (int $officeId, string $tag): Staffusers => Staffusers::factory()->create([
            'officeId' => $officeId,
            'role' => 'officeHead',
            'employeeNo' => $tag.'-'.uniqid(),
            'username' => $tag.'_'.uniqid(),
            'email' => $tag.'_'.uniqid().'@example.com',
        ]);

        $enrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $course->courseId,
            'majorId' => $major->majorId,
            'termId' => $term->termId,
            'yearLevel' => 1,
            'admissionId' => null,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => EnrollmentType::New,
            'academicStanding' => 'regular',
            'enrollmentStatus' => 'enrolled',
            'evaluatedBy' => $deskStaff(4, 'p2_eval')->userId,
            'registrarProcessedBy' => $deskStaff(1, 'p2_reg')->userId,
            'enrolledDate' => now(),
            'formIssuedDate' => now()->toDateString(),
        ]);

        return $enrollment;
    }

    #[Test]
    public function test_the_desk_loads_the_relations_the_form_would_need(): void
    {
        $service = (string) file_get_contents(base_path('app/Services/PrintService.php'));

        $start = strpos($service, 'function printEnrollmentForm');
        $this->assertNotFalse($start, 'PrintService::printEnrollmentForm() is gone — the form’s print path has moved and this test’s contract no longer describes it.');

        $body = substr($service, (int) $start, (int) strpos($service, 'function ', $start + 10) - $start);
        preg_match("/->load\(\s*\[([^\]]*)\]/s", $body, $m);
        $this->assertNotEmpty($m[1] ?? '', 'The desk method no longer holds an eager-load list to compare against.');

        preg_match_all("/'([a-zA-Z.]+)'/", $m[1], $found);
        $loaded = $found[1];
        sort($loaded);
        $expected = self::DESK_RELATIONS;
        sort($expected);

        $this->assertSame(
            $expected,
            $loaded,
            'The eager-load list pinned by this test drifted from PrintService::printEnrollmentForm(). '
                .'Update the payload it proves, not just this list: every relation dropped is a line the '
                .'desk’s paper loses while ems:print-fidelity keeps rendering it from its own list.'
        );

        $fidelity = (string) file_get_contents(base_path('app/Console/Commands/PrintFidelitySamples.php'));
        preg_match("/'enrollment-form' => fn \(\) => view\('prints\.enrollment-form', \['enrollment' => \\\$enrollment->load\(\[([^\]]*)\]/", $fidelity, $fm);
        $this->assertNotEmpty($fm[1] ?? '', 'ems:print-fidelity no longer renders the enrollment form through a load list this test can read.');
        preg_match_all("/'([a-zA-Z.]+)'/", $fm[1], $ff);
        $fromFidelity = $ff[1];
        sort($fromFidelity);
        $this->assertSame($expected, $fromFidelity,
            'The fidelity command and the desk now load different relations, so a PDF that proves the paper ‘8/8 rendered’ '
            .'is no longer the same document the Registrar hands over.');
    }

    #[Test]
    public function test_the_form_payload_carries_every_identity_fact_the_block_prints(): void
    {
        $enrollment = $this->issuedEnrollment()->load(self::DESK_RELATIONS);

        $this->assertNotEmpty($enrollment->student->schoolIdNumber, 'The student ID has no value to print.');
        $this->assertNotEmpty($enrollment->course->courseName, 'The program has no name to print.');
        $this->assertNotNull($enrollment->yearLevel, 'The year level has no value to print.');
        $this->assertNotEmpty($enrollment->term->academicYear->yearLabel, 'The school year has no label to print.');
        $this->assertSame(
            Semester::Second,
            $enrollment->term->semester,
            'The term’s semester is not the enum the blade must ask a label from — a raw cast would print nothing.'
        );
    }

    /**
     * The facts above are reachable, which is why closing P-2 was a layout edit rather than plumbing.
     * But reaching them through the relation graph the desk loads is not the same as reaching them
     * cheaply: this asserts the identity line does not need a query the print path did not already run,
     * so a future blade edit cannot turn a print into an N+1 and blame the paper.
     */
    #[Test]
    public function test_reading_those_facts_issues_no_further_query(): void
    {
        $enrollment = $this->issuedEnrollment()->load(self::DESK_RELATIONS);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $rendered = collect([
            $enrollment->student->schoolIdNumber,
            $enrollment->course->courseName,
            $enrollment->yearLevel,
            $enrollment->term->academicYear->yearLabel,
            $enrollment->term->semester->value,
        ])->map(fn ($v) => (string) $v)->implode('|');

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertNotSame('', $rendered, 'The identity line built itself empty.');
        $this->assertSame(0, $queries,
            "Reading the four identity facts issued {$queries} query(ies) after the desk’s own load list — a blade that prints them would be doing an N+1 on every print."
        );
    }

    /**
     * P-2 closed: the identification block is on the paper, not merely reachable from the payload.
     * The document's `<title>` carried the student number all along and never reached the printed
     * page, so this reads the body only — an assertion that passed on a head element is the same
     * sleep `lane62-print-paper-probe.php` found in the register's "verified on paper" sentence.
     */
    #[Test]
    public function test_the_form_prints_the_identification_block_on_its_face(): void
    {
        $enrollment = $this->issuedEnrollment()->load(self::DESK_RELATIONS);

        $html = view('prints.enrollment-form', ['enrollment' => $enrollment])->render();
        $body = substr($html, (int) strpos($html, '<body'));

        $this->assertNotSame($html, $body, 'The form renders no <body>, so there is nothing to read a printed line from.');

        foreach ([
            'Student ID' => $enrollment->student->schoolIdNumber,
            'School Year' => $enrollment->term->academicYear->yearLabel,
            'Course' => $enrollment->course->courseName,
            'Year Level' => YearLevel::format($enrollment->yearLevel),
            'Semester' => $enrollment->term->semester->value,
        ] as $label => $value) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($label, '/').':?<\/span>.{0,200}?'.preg_quote((string) $value, '/').'/su',
                $body,
                'The form prints no "'.$label.'" line carrying '.$value.' within the same field, so a page that leaves this desk cannot be tied back to the file it belongs in — which is what P-2 was opened for.'
            );
        }
    }

    /**
     * P-18's first half: the form's Part 2 table is the registered load of the term, so a proposed or
     * dropped row may not appear in it or be counted in its TOTAL. The Subject Load has always filtered
     * this way; the form did not, and on live `ems` all 18 enrollments holding a non-confirmed row
     * hold no confirmed row at all — each printed a full subject table for a load nobody confirmed.
     */
    #[Test]
    public function test_part_2_prints_only_the_subjects_the_term_confirmed(): void
    {
        $enrollment = $this->issuedEnrollment()->load(self::DESK_RELATIONS);

        $confirmed = $this->subject('P18CONF', 3, 1);
        $proposed = $this->subject('P18PROP', 5, 0);
        $dropped = $this->subject('P18DROP', 4, 0);
        foreach ([[$confirmed, EnrolledSubjectStatus::Confirmed], [$proposed, EnrolledSubjectStatus::Proposed], [$dropped, EnrolledSubjectStatus::Dropped]] as [$subject, $status]) {
            Enrolledsubjects::create([
                'enrollmentId' => $enrollment->enrollmentId,
                'subjectId' => $subject->subjectId,
                'status' => $status,
            ]);
        }

        $body = $this->printedBody($enrollment->fresh()->load(self::DESK_RELATIONS));

        $this->assertStringContainsString('P18CONF', $body, 'The confirmed subject did not reach the paper.');
        $this->assertStringNotContainsString('P18PROP', $body, 'A proposed subject printed on the enrollment form, so the paper states a subject the department only intended.');
        $this->assertStringNotContainsString('P18DROP', $body, 'A dropped subject printed on the enrollment form, so the paper states a subject the Registrar removed.');

        // 3 + 1 from the confirmed row only; the other two would have added nine more. The unit
        // columns are decimal-cast, so the pattern accepts "3" and "3.0" alike.
        $this->assertMatchesRegularExpression('/TOTAL<\/td>\s*<td>3(?:\.0+)?<\/td>\s*<td>1(?:\.0+)?<\/td>\s*<td>4(?:\.0+)?<\/td>/u', $body,
            'The TOTAL row did not add up to the confirmed load alone — the table on the face of the sheet and its own total disagree.');
    }

    /**
     * The same filter, seen from the other paper: the form and the Subject Load must state one load for
     * one enrollment. This is the claim a reader holding two sheets would assume, and nothing checked.
     */
    #[Test]
    public function test_the_form_and_the_subject_load_state_the_same_load(): void
    {
        $enrollment = $this->issuedEnrollment()->load(self::DESK_RELATIONS);

        Enrolledsubjects::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'subjectId' => $this->subject('P18BOTH', 2, 2)->subjectId,
            'status' => EnrolledSubjectStatus::Proposed,
        ]);

        $withLoad = $enrollment->fresh()->load(array_merge(self::DESK_RELATIONS, ['enrolledSubjects.schedule']));

        $form = $this->printedBody($withLoad);
        $load = (string) view('prints.subject-load', ['enrollment' => $withLoad])->render();

        $this->assertStringNotContainsString('P18BOTH', $form, 'The enrollment form prints a subject the Subject Load withholds.');
        $this->assertStringNotContainsString('P18BOTH', $load, 'The fixture stopped holding its proposed row, so this no longer tests the two papers agreeing.');
        $this->assertStringContainsString('No confirmed subjects for this term.', $load, 'The Subject Load stopped stating an empty load, so the pair below is no longer compared.');
        $this->assertStringContainsString('No confirmed subjects for this term.', $form,
            'An enrollment with nothing confirmed printed a bare TOTAL 0 instead of saying so — the two papers now have to state the same absence in the same words.');
    }

    /**
     * P-18's second half: `addressType` is cast to a backed enum, so `=== 'home'` and
     * `where('addressType', 'current')` are each false — the box existed in the template and could
     * never print. It also now compares every line a courier reads, not the street alone.
     */
    #[Test]
    public function test_the_home_address_says_whether_the_current_address_is_the_same(): void
    {
        $enrollment = $this->issuedEnrollment()->load(self::DESK_RELATIONS);
        $student = $enrollment->student;

        $this->address($student->studentId, 'home', '12', 'Rizal', 'Norla', '9505');
        $this->address($student->studentId, 'current', '12', 'Rizal', 'Norla', '9505');

        $same = $this->printedBody($enrollment->fresh()->load(self::DESK_RELATIONS));

        $this->assertStringContainsString('Current address is the same as this home address', $same,
            'The home/current line never printed at all: an enum cast compared with === to a string is false, which is how the box was dead.');
        $this->assertMatchesRegularExpression('/type="checkbox"[^>]*checked/u', $same,
            'Two identical addresses left the box unticked, so the paper denies a fact the record states.');

        Addresses::where('studentId', $student->studentId)->where('addressType', AddressType::Current->value)
            ->update(['barangay' => 'Lagao']);

        $different = $this->printedBody($enrollment->fresh()->load(self::DESK_RELATIONS));

        $this->assertStringContainsString('Current address is the same as this home address', $different,
            'The line disappeared when the addresses differ; it should print unticked.');
        $this->assertStringNotContainsString('type="checkbox" id="sameAsCurrentAddress" checked', $different,
            'A different barangay still ticked the box, so the paper claimed one address where the record holds two.');
    }

    /**
     * P-18's third half: every desk print writes a `documentprintlog` row, and the slip and the
     * certificate print their number so a sheet traces back to its issuance. The form minted its row
     * after rendering and printed nothing, so its number existed only in the database.
     */
    #[Test]
    public function test_the_form_prints_the_number_of_the_print_that_issued_it(): void
    {
        $enrollment = $this->issuedEnrollment()->load(self::DESK_RELATIONS);

        $body = $this->printedBody($enrollment, 'EF-2026-0007');

        $this->assertMatchesRegularExpression('/Document No\.:?<\/span>.{0,200}?EF-2026-0007/su', $body,
            'The issuance number was not printed beside its own label, so a page on a desk cannot be tied to the print that drew it.');

        $service = (string) file_get_contents(base_path('app/Services/PrintService.php'));
        $start = (int) strpos($service, 'function printEnrollmentForm');
        $method = substr($service, $start, (int) strpos($service, 'function ', $start + 10) - $start);
        $this->assertLessThan(
            (int) strpos($method, 'generatePdf('),
            (int) strpos($method, 'recordIssue('),
            'printEnrollmentForm() renders before it mints the number again, which is the ordering that leaves the paper without one.'
        );
    }

    /**
     * One enum value, one spelling, on every paper. The certificate and the Subject Load use
     * `Str::headline()`; the form used `ucfirst()`, which prints "FirstYear".
     */
    #[Test]
    public function test_student_type_is_spelled_the_way_the_other_papers_spell_it(): void
    {
        $enrollment = $this->issuedEnrollment()->load(self::DESK_RELATIONS);

        $body = $this->printedBody($enrollment);

        $this->assertStringContainsString('First Year', $body,
            'The form did not print the student type the way the certificate and the Subject Load print the same enum value.');
        $this->assertStringNotContainsString('FirstYear', $body, 'The raw camel-case enum value reached the paper.');
    }

    /**
     * The number that reaches the paper is the one the issuance carries. Asserted against the
     * service's own source rather than by calling it: `generatePdf()` renders through Browsershot and
     * needs a real Chrome binary, which this box has and the runner does not — a test that printed a
     * PDF here would be red there, which is the trap this repository has been caught by twice.
     */
    #[Test]
    public function test_the_desk_passes_its_own_issuance_number_to_the_paper(): void
    {
        $service = (string) file_get_contents(base_path('app/Services/PrintService.php'));
        $start = (int) strpos($service, 'function printEnrollmentForm');
        $method = substr($service, $start, (int) strpos($service, 'function ', $start + 10) - $start);

        $this->assertMatchesRegularExpression(
            "/recordIssue\(\s*\\\$enrollment->enrollmentId,\s*DocumentType::EnrollmentForm,/",
            $method,
            'printEnrollmentForm() no longer records its own issuance, so the paper has nothing honest to print.'
        );
        $this->assertStringContainsString(
            "'documentNumber' => \$printLog->documentNumber,",
            $method,
            'The desk stopped handing the render the number of its own log row, so the sheet and documentprintlog drift.'
        );
    }

    private function printedBody(Enrollments $enrollment, ?string $documentNumber = null): string
    {
        $data = ['enrollment' => $enrollment];
        if ($documentNumber !== null) {
            $data['documentNumber'] = $documentNumber;
        }

        $html = (string) view('prints.enrollment-form', $data)->render();

        return substr($html, (int) strpos($html, '<body'));
    }

    private function subject(string $code, int $lecture, int $lab): Subjects
    {
        return Subjects::create([
            'subjectCode' => $code,
            'subjectName' => $code.' Subject',
            'lectureUnits' => $lecture,
            'labUnits' => $lab,
            'subjectType' => 'lecture',
        ]);
    }

    private function address(int $studentId, string $type, string $house, string $street, string $barangay, string $zip): Addresses
    {
        return Addresses::create([
            'studentId' => $studentId,
            'addressType' => $type,
            'houseBuildingNo' => $house,
            'street' => $street,
            'sitioPurok' => 'Purok 3',
            'barangay' => $barangay,
            'cityMunicipality' => 'Tupi',
            'district' => 'SOCCSKSARGEN',
            'province' => 'South Cotabato',
            'region' => 'Region XII',
            'country' => 'Philippines',
            'zipCode' => $zip,
        ]);
    }
}
