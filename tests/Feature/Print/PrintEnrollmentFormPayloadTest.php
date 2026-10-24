<?php

namespace Tests\Feature\Print;

use App\Enums\EnrollmentType;
use App\Enums\Semester;
use App\Enums\StudentType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Majors;
use App\Models\Staffusers;
use App\Models\Students;
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
            'Year Level' => 'Year '.$enrollment->yearLevel,
            'Semester' => $enrollment->term->semester->value,
        ] as $label => $value) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($label, '/').':?<\/span>.{0,200}?'.preg_quote((string) $value, '/').'/su',
                $body,
                'The form prints no "'.$label.'" line carrying '.$value.' within the same field, so a page that leaves this desk cannot be tied back to the file it belongs in — which is what P-2 was opened for.'
            );
        }
    }
}
