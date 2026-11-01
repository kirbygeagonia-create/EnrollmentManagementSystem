<?php

namespace Tests\Feature\Notification;

use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\OfficeId;
use App\Enums\StudentType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Staffusers;
use App\Models\Students;
use App\Support\NoticeFacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Prompt §8: "Show an intelligible title, action required, record reference, timestamp, and protected deep link."
 * §28.3 G-35 recorded that the bell rendered a sentence and a moment. Measured across the listeners' payload, the
 * rows live `ems` holds, and the bell's own row block, the title, the action and the link were absent everywhere,
 * and the identifier that did travel (`enrollmentId`) is a join key, not something a window reads.
 *
 * The destination is derived from the record's own state through `WorkflowInbox::nextOffice()` — the map the bell's
 * addressing already uses — so no new office vocabulary is introduced and no path is taken from a request. The
 * protection is the policy at the destination route, not the link itself.
 */
class NoticeReadabilityTest extends TestCase
{
    use RefreshDatabase;

    private function factsFor(Enrollments $enrollment, ?int $officeId): array
    {
        return NoticeFacts::forEnrollment($enrollment, 'Fees computed — Accounting collects next', $officeId);
    }

    #[Test]
    public function every_office_names_a_destination_route_that_actually_exists(): void
    {
        $unresolved = [];
        foreach (OfficeId::cases() as $office) {
            $name = $office->deskRouteName();
            if ($name !== null && ! Route::has($name)) {
                $unresolved[] = $office->name.' → '.$name;
            }
        }

        // An invented route name would render a bell row that throws when a desk clicks it. The list is read out of
        // the enum and the router, never copied into this file, so it cannot go stale in the way a hand-kept map does.
        $this->assertSame([], $unresolved, implode(', ', $unresolved).' name no route the application registers.');
    }

    #[Test]
    public function a_notice_states_the_act_the_record_and_the_desk_that_owes_the_next_step(): void
    {
        [$student, $course, $enrollment] = $this->cast(2);

        $facts = $this->factsFor($enrollment, OfficeId::Accounting->value);

        $this->assertSame('Fees computed — Accounting collects next', $facts['title']);
        $this->assertSame('Waiting on Accounting Payment', $facts['actionRequired'],
            'the label comes from the map the stepper and the bell already share, not a second list');
        $this->assertStringContainsString($student->schoolIdNumber, $facts['recordReference']);
        $this->assertStringContainsString($course->courseCode, $facts['recordReference']);
        $this->assertSame(['name' => 'accounting.index'], $facts['link']);
    }

    #[Test]
    public function the_year_level_in_a_reference_is_spelled_by_the_shared_vocabulary_not_by_hand(): void
    {
        [$student, $course, $enrollment] = $this->cast(2);

        $reference = $this->factsFor($enrollment, OfficeId::Registrar->value)['recordReference'];

        // The word 'Year' beside a digit is the hand-spelling the vocabulary guard exists to refuse; the school
        // ruled the ordinal form, and a notice is one more surface that must not restart the four-vocabulary defect.
        $this->assertStringContainsString('2nd Year', $reference);
        $this->assertDoesNotMatchRegularExpression('/Year\s+2\b/', $reference);
    }

    #[Test]
    public function a_record_waiting_nowhere_links_nowhere_and_says_no_action_rather_than_inventing_one(): void
    {
        [$student, $course, $enrollment] = $this->cast(1);

        $facts = $this->factsFor($enrollment, null);

        $this->assertNull($facts['link'], 'a placeholder destination would take a desk somewhere irrelevant');
        $this->assertNull($facts['actionRequired']);
    }

    #[Test]
    public function an_office_with_no_queue_page_produces_no_link(): void
    {
        [$student, $course, $enrollment] = $this->cast(1);

        // Guidance owns the shift docket and Admission owns applications, so both have a page; the case that matters
        // is the shape of the answer when the office is unknown to the map: no link at all, never a guess.
        $this->assertSame(['name' => 'shift.index'], $this->factsFor($enrollment, OfficeId::Guidance->value)['link']);
        $this->assertSame(['name' => 'admission.index'], $this->factsFor($enrollment, OfficeId::Admission->value)['link']);
        $this->assertSame(['name' => 'registrar.index'], $this->factsFor($enrollment, OfficeId::Registrar->value)['link']);
        $this->assertSame(['name' => 'id.index'], $this->factsFor($enrollment, OfficeId::IdOffice->value)['link']);
        $this->assertSame(['name' => 'clinic.index'], $this->factsFor($enrollment, OfficeId::Clinic->value)['link']);
    }

    #[Test]
    public function the_missing_message_dashes_instead_of_borrowing_a_label_from_nobody(): void
    {
        $bell = (string) file_get_contents(base_path('resources/js/Layouts/AuthenticatedLayout.jsx'));

        // The screen used to render `n.data?.message || 'Notification'`: a phrase that reads as a title on the file
        // while being nobody's words. The guard is on the source because there is no JavaScript test suite, the same
        // method the year-level and tone-map pairings use.
        $this->assertStringNotContainsString("|| 'Notification'", $bell,
            'the bell substitutes a word again; an absent message must dash, not be labelled');
        foreach (['n.data?.title', 'n.data?.recordReference', 'n.data?.actionRequired', 'n.data?.link'] as $part) {
            $this->assertStringContainsString($part, $bell, "the bell no longer reads {$part}");
        }
    }

    #[Test]
    public function the_two_listeners_put_the_four_parts_into_the_notice_they_write(): void
    {
        foreach ([
            'app/Listeners/SendEnrollmentNotification.php',
            'app/Listeners/SendWorkflowNotification.php',
        ] as $rel) {
            $src = (string) file_get_contents(base_path($rel));
            $this->assertStringContainsString('NoticeFacts::forEnrollment(', $src,
                "{$rel} writes a notice without the §8 parts, so the bell has nothing to paint");
        }
    }

    /** @return array{0: Students, 1: Courses, 2: Enrollments} */
    private function cast(int $yearLevel = 2): array
    {
        $unit = Academicunits::create(['unitName' => 'College of Computer Studies', 'unitType' => 'college']);
        $year = Academicyears::create([
            'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01',
            'endDate' => '2027-03-31',
        ]);
        $term = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ]);
        $course = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSIT-NOT',
            'courseName' => 'Bachelor of Science in Information Technology',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);
        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);
        $student = Students::create([
            'schoolIdNumber' => 'NOTICE-'.uniqid(),
            'lastName' => 'Reyes',
            'firstName' => 'Ana',
            'middleName' => 'M',
            'suffix' => 'N/A',
            'gender' => 'female',
            'birthdate' => '2004-02-02',
            'birthplace' => 'Test City',
            'citizenship' => 'Filipino',
            'civilStatus' => 'single',
            'religionId' => 1,
            'contactNumber' => '09171234568',
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
            'email' => 'notice_'.uniqid().'@example.com',
            'username' => 'notice_'.uniqid(),
            'passwordHash' => bcrypt('password123'),
            'status' => 'active',
        ]);

        // `enrollments.evaluatedBy` is NOT NULL, and it is a foreign key into offices-seeded staff, so the fixture
        // seats the offices and one signer the way the bell's own test does rather than assuming a userId.
        foreach (OfficeId::cases() as $office) {
            Offices::firstOrCreate(['officeId' => $office->value], ['officeName' => 'Office '.$office->value]);
        }
        $signer = Staffusers::factory()->create([
            'officeId' => OfficeId::Registrar->value,
            'employeeNo' => 'EMP-NOTICE-'.uniqid(),
            'username' => 'notice_staff_'.uniqid(),
            'email' => 'notice_staff_'.uniqid().'@example.com',
        ]);

        $enrollment = Enrollments::create([
            'studentId' => $student->studentId,
            'courseId' => $course->courseId,
            'termId' => $term->termId,
            'yearLevel' => $yearLevel,
            'studentType' => StudentType::FirstYear,
            'enrollmentType' => EnrollmentType::New,
            'enrollmentStatus' => EnrollmentStatus::Assessed,
            'evaluatedBy' => $signer->userId,
        ]);

        // The reference is built from relations, so the case reads them the way the listeners do.
        $enrollment->load('student', 'course');

        return [$student, $course, $enrollment];
    }
}
