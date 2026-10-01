<?php

namespace Tests\Feature\Admin;

use App\Enums\ClearanceOverallStatus;
use App\Enums\EnrolledSubjectStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\ScholarshipStatus;
use App\Enums\StudentType;
use App\Models\Staffusers;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Reference Data is the vocabulary every desk workflow reads: the course an intake
 * names, the subject a proposal offers, the fee type a charge prices. Its
 * store/update/destroy pairs had no test driving them, and the destroy paths went
 * straight to the database — where the foreign keys either refuse the delete (a raw
 * integrity-constraint 500 on the screen) or null the reference out, un-pinning an
 * enrollment from the course it recorded. These tests drive that CRUD from the
 * request inward and pin both halves: optional fields must survive a strict insert,
 * and a row that something still names must not disappear.
 */
class ReferenceDataCrudTest extends TestCase
{
    use DatabaseTransactions;

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

        DB::table('offices')->insert(['officeId' => 1, 'officeName' => 'Registrar']);
        DB::table('academicunits')->insert([
            'unitId' => 1,
            'unitName' => 'College of Computer Studies',
            'unitType' => 'college',
        ]);
        DB::table('academicyears')->insert([
            'academicYearId' => 1,
            'yearLabel' => '2026-2027',
            'startDate' => '2026-06-01',
            'endDate' => '2027-05-31',
        ]);
    }

    #[Test]
    public function an_unchecked_exam_flag_still_writes_a_value_to_its_not_null_column(): void
    {
        $admin = $this->staffWithRole('SysAdmin');

        // courses.requiresEntranceExam and .requiresRetentionExam are NOT NULL, and an
        // unchecked box is absent from the payload entirely, so the create died in the
        // insert before the flags were read through boolean().
        $this->actingAs($admin)->post(route('admin.reference-data.courses.store'), [
            'unitId' => 1,
            'courseName' => 'Bachelor of Science in Information Systems',
            'courseCode' => 'BSIS',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $course = DB::table('courses')->where('courseCode', 'BSIS')->first();

        $this->assertNotNull($course);
        $this->assertEquals(0, $course->requiresEntranceExam);
        $this->assertEquals(0, $course->requiresRetentionExam);

        $this->actingAs($admin)->patch(route('admin.reference-data.courses.update', $course->courseId), [
            'unitId' => 1,
            'courseName' => 'Bachelor of Science in Information Systems',
            'courseCode' => 'BSIS',
            'requiresEntranceExam' => true,
            'requiresRetentionExam' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $flagged = DB::table('courses')->where('courseId', $course->courseId)->first();

        $this->assertEquals(1, $flagged->requiresEntranceExam);
        $this->assertEquals(1, $flagged->requiresRetentionExam);

        // Unticking both must land the same as sending neither.
        $this->actingAs($admin)->patch(route('admin.reference-data.courses.update', $course->courseId), [
            'unitId' => 1,
            'courseName' => 'Bachelor of Science in Information Systems',
            'courseCode' => 'BSIS',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $cleared = DB::table('courses')->where('courseId', $course->courseId)->first();

        $this->assertEquals(0, $cleared->requiresEntranceExam);
        $this->assertEquals(0, $cleared->requiresRetentionExam);
    }

    #[Test]
    public function an_optional_admission_requirement_is_stored_as_not_required(): void
    {
        $admin = $this->staffWithRole('SysAdmin');

        // admissionrequirements.isRequired is NOT NULL; the box only arrives when checked.
        $this->actingAs($admin)->post(route('admin.reference-data.admission-requirements.store'), [
            'requirementName' => 'Good Moral Certificate',
            'appliesTo' => 'all',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $optional = DB::table('admissionrequirements')->where('requirementName', 'Good Moral Certificate')->first();

        $this->assertNotNull($optional);
        $this->assertEquals(0, $optional->isRequired);

        $this->actingAs($admin)->post(route('admin.reference-data.admission-requirements.store'), [
            'requirementName' => 'Form 137',
            'appliesTo' => 'transferee',
            'isRequired' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals(1, DB::table('admissionrequirements')->where('requirementName', 'Form 137')->first()->isRequired);

        $this->actingAs($admin)->patch(route('admin.reference-data.admission-requirements.update', $optional->requirementId), [
            'requirementName' => 'Good Moral Certificate',
            'appliesTo' => 'transferee',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $updated = DB::table('admissionrequirements')->where('requirementId', $optional->requirementId)->first();

        $this->assertEquals(0, $updated->isRequired);
        $this->assertSame('transferee', $updated->appliesTo);
    }

    #[Test]
    public function a_room_created_without_a_building_is_stored_blank_rather_than_rejected(): void
    {
        $admin = $this->staffWithRole('SysAdmin');

        // rooms.building is NOT NULL while the screen leaves it optional, and
        // ConvertEmptyStringsToNull turns a blank input into an absent value.
        $this->actingAs($admin)->post(route('admin.reference-data.rooms.store'), [
            'roomName' => 'Room 201',
            'capacity' => 40,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $room = DB::table('rooms')->where('roomName', 'Room 201')->first();

        $this->assertNotNull($room);
        $this->assertSame('', $room->building);

        $this->actingAs($admin)->post(route('admin.reference-data.rooms.store'), [
            'roomName' => 'Room 202',
            'capacity' => 40,
            'building' => '',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('', DB::table('rooms')->where('roomName', 'Room 202')->first()->building);

        $this->actingAs($admin)->patch(route('admin.reference-data.rooms.update', $room->roomId), [
            'roomName' => 'Room 201',
            'capacity' => 45,
            'building' => 'Main Building',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $named = DB::table('rooms')->where('roomId', $room->roomId)->first();

        $this->assertSame('Main Building', $named->building);
        $this->assertEquals(45, $named->capacity);
    }

    #[Test]
    public function a_curriculum_subject_returns_to_mandatory_with_its_elective_band_cleared(): void
    {
        $admin = $this->staffWithRole('SysAdmin');
        $courseId = $this->createCourse('BSCS', 'Bachelor of Science in Computer Science');
        $curriculumId = $this->createCurriculum($courseId);
        $subjectId = $this->createSubject('CCS101', 'Introduction to Programming');

        $this->actingAs($admin)->post(route('admin.reference-data.curriculum-subjects.store', $curriculumId), [
            'subjectId' => $subjectId,
            'yearLevel' => 1,
            'semesterOffered' => '1st',
            'is_elective' => true,
            'elective_group' => 'Elective Set A',
            'elective_min_choices' => 2,
            'elective_max_choices' => 3,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $listing = DB::table('curriculumsubjects')->where('curriculumId', $curriculumId)->first();

        $this->assertNotNull($listing);
        $this->assertEquals(1, $listing->is_elective);
        $this->assertSame('Elective Set A', $listing->elective_group);

        // Evaluation reads one band per group, so a subject flipped back to mandatory
        // must not keep a group and a minimum behind for it to trip over.
        $this->actingAs($admin)->patch(route('admin.reference-data.curriculum-subjects.update', $listing->curriculumSubjectId), [
            'subjectId' => $subjectId,
            'yearLevel' => 1,
            'semesterOffered' => '1st',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $mandatory = DB::table('curriculumsubjects')->where('curriculumSubjectId', $listing->curriculumSubjectId)->first();

        $this->assertEquals(0, $mandatory->is_elective);
        $this->assertNull($mandatory->elective_group);
        $this->assertNull($mandatory->elective_min_choices);
    }

    #[Test]
    public function an_elective_band_that_cannot_be_satisfied_is_refused(): void
    {
        $admin = $this->staffWithRole('SysAdmin');
        $courseId = $this->createCourse('BSCS', 'Bachelor of Science in Computer Science');
        $curriculumId = $this->createCurriculum($courseId);
        $subjectId = $this->createSubject('CCS101', 'Introduction to Programming');

        $this->actingAs($admin)->post(route('admin.reference-data.curriculum-subjects.store', $curriculumId), [
            'subjectId' => $subjectId,
            'yearLevel' => 1,
            'semesterOffered' => '1st',
            'is_elective' => true,
            'elective_group' => 'Elective Set A',
            'elective_min_choices' => 4,
            'elective_max_choices' => 2,
        ])->assertSessionHasErrors('elective_max_choices');

        $this->assertSame(0, DB::table('curriculumsubjects')->count());
    }

    #[Test]
    public function a_curriculums_subject_listing_opens_with_the_subjects_it_offers(): void
    {
        $admin = $this->staffWithRole('SysAdmin');
        $courseId = $this->createCourse('BSCS', 'Bachelor of Science in Computer Science');
        $curriculumId = $this->createCurriculum($courseId);
        $first = $this->createSubject('CCS101', 'Introduction to Programming');
        $second = $this->createSubject('CCS102', 'Data Structures');

        // A listing for a curriculum this screen is not for must not leak in.
        $otherCurriculum = $this->createCurriculum($this->createCourse('BSIT', 'Bachelor of Science in Information Technology'));
        $this->addCurriculumSubject($otherCurriculum, $second, 1, '2nd');

        $firstListing = $this->addCurriculumSubject($curriculumId, $first, 1, '1st');
        $secondListing = $this->addCurriculumSubject($curriculumId, $second, 2, '1st', $first);

        $this->actingAs($admin)
            ->get(route('admin.reference-data.curriculum-subjects', $curriculumId))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/ReferenceData/CurriculumSubjects')
                ->where('curriculum.curriculumId', $curriculumId)
                ->where('curriculum.course.courseCode', 'BSCS')
                ->has('subjects', 2)
                ->where('subjects.0.curriculumSubjectId', $firstListing)
                ->where('subjects.0.subject.subjectCode', 'CCS101')
                ->where('subjects.1.curriculumSubjectId', $secondListing)
                // yearLevel 2 sorts after yearLevel 1, and its prerequisite resolves
                ->where('subjects.1.prerequisiteSubject.subjectCode', 'CCS101')
                ->has('allSubjects', 2)
                ->has('semesters', 3)
            );
    }

    #[Test]
    public function deleting_a_curriculum_subject_takes_the_listing_down_but_not_the_seats(): void
    {
        $admin = $this->staffWithRole('SysAdmin');
        $courseId = $this->createCourse('BSCS', 'Bachelor of Science in Computer Science');
        $termId = $this->createTerm();
        $curriculumId = $this->createCurriculum($courseId);
        $subjectId = $this->createSubject('CCS101', 'Introduction to Programming');
        $listingId = $this->addCurriculumSubject($curriculumId, $subjectId, 1, '1st');

        // A student already seated in the subject through this curriculum.
        $studentId = $this->createStudent('2026-00011');
        $enrollmentId = DB::table('enrollments')->insertGetId([
            'studentId' => $studentId,
            'courseId' => $courseId,
            'termId' => $termId,
            'studentType' => StudentType::FirstYear->value,
            'enrollmentStatus' => EnrollmentStatus::Enrolled->value,
            'evaluatedBy' => $admin->userId,
        ]);
        DB::table('enrolledsubjects')->insert([
            'enrollmentId' => $enrollmentId,
            'subjectId' => $subjectId,
            'status' => EnrolledSubjectStatus::Confirmed->value,
        ]);

        // Unlike a curriculum or a block, a listing row is nothing but the catalogue's
        // own opinion about a subject: the seat and the schedule both name the subject,
        // so there is no usage to refuse and no integrity error to catch.
        $this->actingAs($admin)
            ->delete(route('admin.reference-data.curriculum-subjects.destroy', $listingId))
            ->assertRedirect()
            ->assertSessionHas('success', 'Curriculum subject deleted.');

        $this->assertSame(0, DB::table('curriculumsubjects')->where('curriculumSubjectId', $listingId)->count());
        $this->assertSame(1, DB::table('enrolledsubjects')->where('enrollmentId', $enrollmentId)->count());
        $this->assertSame(1, DB::table('subjects')->where('subjectId', $subjectId)->count());
    }

    #[Test]
    public function a_desk_without_the_curriculum_subject_permission_cannot_open_or_delete_a_listing(): void
    {
        $approver = $this->staffWithRole('RegistrarApprover');
        $courseId = $this->createCourse('BSCS', 'Bachelor of Science in Computer Science');
        $curriculumId = $this->createCurriculum($courseId);
        $listingId = $this->addCurriculumSubject($curriculumId, $this->createSubject('CCS101', 'Introduction to Programming'), 1, '1st');

        // RegistrarApprover holds refdata.view, so the hub opens — but the curriculum
        // subject listing and its delete are gated on refdata.curriculumSubjects.manage.
        $this->actingAs($approver)->get(route('admin.reference-data.curriculum-subjects', $curriculumId))->assertForbidden();
        $this->actingAs($approver)->delete(route('admin.reference-data.curriculum-subjects.destroy', $listingId))->assertForbidden();

        $this->assertSame(1, DB::table('curriculumsubjects')->where('curriculumSubjectId', $listingId)->count());
    }

    #[Test]
    public function every_catalog_section_creates_updates_and_deletes_from_the_screen(): void
    {
        $admin = $this->staffWithRole('SysAdmin');
        $courseId = $this->createCourse('BSCS', 'Bachelor of Science in Computer Science');
        $termId = $this->createTerm();

        $sections = [
            ['majors', 'admin.reference-data.majors.store', ['courseId' => $courseId, 'majorName' => 'Software Engineering'], 'Software Engineering'],
            ['subjects', 'admin.reference-data.subjects.store', ['subjectCode' => 'CCS101', 'subjectName' => 'Introduction to Programming', 'lectureUnits' => 3, 'labUnits' => 1, 'subjectType' => 'both'], 'Introduction to Programming'],
            ['academic terms', 'admin.reference-data.terms.store', ['academicYearId' => 1, 'semester' => '2nd', 'startDate' => '2026-11-01', 'endDate' => '2027-03-31'], '2nd'],
            ['fee types', 'admin.reference-data.fee-types.store', ['feeName' => 'Library Fee', 'defaultAmount' => 500, 'unitBasis' => 'flat'], 'Library Fee'],
            ['scholarship types', 'admin.reference-data.scholarship-types.store', ['scholarshipName' => 'Dean, Special', 'coverageType' => 'full', 'coveragePercent' => 100], 'Dean, Special'],
            ['rooms', 'admin.reference-data.rooms.store', ['roomName' => 'Room 101', 'capacity' => 40, 'building' => 'Main Building'], 'Room 101'],
            ['blocks', 'admin.reference-data.blocks.store', ['courseId' => $courseId, 'termId' => $termId, 'yearLevel' => 1, 'blockName' => 'BS-CS-1A', 'maxStudents' => 40], 'BS-CS-1A'],
            ['curriculums', 'admin.reference-data.curriculums.store', ['courseId' => $courseId, 'effectiveYear' => '2026-06-01', 'curriculumName' => '2026 Curriculum'], '2026 Curriculum'],
        ];

        foreach ($sections as [$label, $routeName, $payload, $needle]) {
            $this->actingAs($admin)->post(route($routeName), $payload)->assertRedirect()->assertSessionHasNoErrors();

            $table = $this->tableOf($routeName);
            $row = DB::table($table)->orderByDesc($this->primaryKeyOf($table))->first();

            $this->assertNotNull($row, "The {$label} store route persisted no row.");
            $this->assertContains($needle, (array) $row, "The {$label} store route dropped a value it was given.");
        }

        $this->actingAs($admin)->patch(route('admin.reference-data.majors.update', 1), ['majorName' => 'Information Systems'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Information Systems', DB::table('majors')->where('majorId', 1)->first()->majorName);

        $this->actingAs($admin)->patch(route('admin.reference-data.subjects.update', 1), [
            'subjectCode' => 'CCS101',
            'subjectName' => 'Programming Foundations',
            'lectureUnits' => 2,
            'labUnits' => 2,
            'subjectType' => 'both',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Programming Foundations', DB::table('subjects')->where('subjectId', 1)->first()->subjectName);

        $this->actingAs($admin)->patch(route('admin.reference-data.terms.update', $termId), [
            'academicYearId' => 1,
            'semester' => 'Summer',
            'startDate' => '2027-04-01',
            'endDate' => '2027-05-31',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Summer', DB::table('academicterms')->where('termId', $termId)->first()->semester);

        $this->actingAs($admin)->patch(route('admin.reference-data.fee-types.update', 1), [
            'feeName' => 'Library Fee',
            'defaultAmount' => 600,
            'unitBasis' => 'perUnit',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(600, DB::table('feetypes')->where('feeTypeId', 1)->first()->defaultAmount);

        $this->actingAs($admin)->patch(route('admin.reference-data.scholarship-types.update', 1), [
            'scholarshipName' => 'Dean, Special',
            'coverageType' => 'partial',
            'coveragePercent' => 50,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(50, DB::table('scholarshiptypes')->where('scholarshipTypeId', 1)->first()->coveragePercent);

        $this->actingAs($admin)->patch(route('admin.reference-data.blocks.update', 1), [
            'blockName' => 'BS-CS-1B',
            'maxStudents' => 45,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('BS-CS-1B', DB::table('blocks')->where('blockId', 1)->first()->blockName);

        $this->actingAs($admin)->patch(route('admin.reference-data.curriculums.update', 1), [
            'courseId' => $courseId,
            'effectiveYear' => '2026-06-01',
            'curriculumName' => '2027 Revised Curriculum',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('2027 Revised Curriculum', DB::table('curriculums')->where('curriculumId', 1)->first()->curriculumName);

        // Deleting an unused row really removes it — and in the order the references
        // allow, because a block names the term and a curriculum names the major.
        foreach ([
            ['admin.reference-data.curriculums.destroy', 'curriculums', 'curriculumId'],
            ['admin.reference-data.blocks.destroy', 'blocks', 'blockId'],
            ['admin.reference-data.majors.destroy', 'majors', 'majorId'],
            ['admin.reference-data.subjects.destroy', 'subjects', 'subjectId'],
            ['admin.reference-data.terms.destroy', 'academicterms', 'termId'],
            ['admin.reference-data.fee-types.destroy', 'feetypes', 'feeTypeId'],
            ['admin.reference-data.scholarship-types.destroy', 'scholarshiptypes', 'scholarshipTypeId'],
            ['admin.reference-data.rooms.destroy', 'rooms', 'roomId'],
        ] as [$routeName, $table, $pk]) {
            $this->actingAs($admin)->delete(route($routeName, 1))
                ->assertSessionHas('success');

            $this->assertNull(
                DB::table($table)->where($pk, 1)->first(),
                "The {$table} destroy route left its unused row behind."
            );
        }
    }

    #[Test]
    public function a_semester_the_read_model_cannot_cast_is_refused(): void
    {
        $admin = $this->staffWithRole('SysAdmin');

        // academicterms.semester holds '1st', '2nd' and 'Summer', and the row is read
        // back through the Semester enum. The rule used to list the lowercase
        // spelling, which strict MySQL rejects and every read of the row crashes on.
        $this->actingAs($admin)->post(route('admin.reference-data.terms.store'), [
            'academicYearId' => 1,
            'semester' => 'summer',
            'startDate' => '2026-11-01',
            'endDate' => '2027-03-31',
        ])->assertSessionHasErrors('semester');

        $this->assertSame(0, DB::table('academicterms')->count());
    }

    #[Test]
    public function a_catalog_row_that_records_still_name_cannot_be_deleted(): void
    {
        $admin = $this->staffWithRole('SysAdmin');

        foreach ($this->plantReferencedRows() as [$label, $routeName, $table, $id]) {
            $this->actingAs($admin)->delete(route($routeName, $id))
                ->assertSessionHas('error');

            $this->assertNotNull(
                DB::table($table)->where($this->primaryKeyOf($table), $id)->first(),
                "Deleting a referenced {$label} was allowed: the records naming it would be erased or quietly un-pinned."
            );
        }
    }

    #[Test]
    public function a_desk_without_catalog_authority_cannot_change_the_vocabulary(): void
    {
        $courseId = $this->createCourse('BSCS', 'Bachelor of Science in Computer Science');
        $staff = $this->staffWithRole('Staff');

        $this->actingAs($staff)->post(route('admin.reference-data.courses.store'), [
            'unitId' => 1,
            'courseName' => 'Injected course',
            'courseCode' => 'INJ01',
        ])->assertForbidden();

        $this->actingAs($staff)->delete(route('admin.reference-data.courses.destroy', $courseId))
            ->assertForbidden();

        $this->assertNull(DB::table('courses')->where('courseCode', 'INJ01')->first());
        $this->assertNotNull(DB::table('courses')->where('courseId', $courseId)->first());
    }

    private function staffWithRole(string $roleName): Staffusers
    {
        $staff = Staffusers::factory()->make([
            'officeId' => 1,
            'role' => 'officeHead',
            'employeeNo' => 'EMP-'.uniqid(),
            'username' => 'user'.uniqid(),
            'email' => 'user'.uniqid().'@example.com',
        ]);
        unset($staff->remember_token);
        $staff->save();

        $staff->assignRole($roleName);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $staff;
    }

    private function createCourse(string $code, string $name): int
    {
        return DB::table('courses')->insertGetId([
            'unitId' => 1,
            'courseCode' => $code,
            'courseName' => $name,
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);
    }

    private function createTerm(string $semester = '1st'): int
    {
        return DB::table('academicterms')->insertGetId([
            'academicYearId' => 1,
            'semester' => $semester,
            'startDate' => '2026-06-01',
            'endDate' => '2026-10-31',
        ]);
    }

    private function createSubject(string $code, string $name): int
    {
        return DB::table('subjects')->insertGetId([
            'subjectCode' => $code,
            'subjectName' => $name,
            'lectureUnits' => 3,
            'labUnits' => 0,
            'subjectType' => 'lecture',
        ]);
    }

    private function createCurriculum(int $courseId): int
    {
        return DB::table('curriculums')->insertGetId([
            'courseId' => $courseId,
            'effectiveYear' => '2026-06-01',
            'curriculumName' => '2026 Curriculum',
        ]);
    }

    private function addCurriculumSubject(int $curriculumId, int $subjectId, int $yearLevel, string $semester, ?int $prerequisiteId = null): int
    {
        return DB::table('curriculumsubjects')->insertGetId([
            'curriculumId' => $curriculumId,
            'subjectId' => $subjectId,
            'prerequisiteSubjectId' => $prerequisiteId,
            'yearLevel' => $yearLevel,
            'semesterOffered' => $semester,
        ]);
    }

    private function createStudent(string $number): int
    {
        return DB::table('students')->insertGetId([
            'schoolIdNumber' => $number,
            'lastName' => 'Dela Cruz',
            'firstName' => 'Juan',
            'gender' => 'male',
            'birthdate' => '2008-01-01',
            'birthplace' => 'Biñan',
            'citizenship' => 'Filipino',
            'contactNumber' => '09171234567',
            'email' => "{$number}@example.com",
            'username' => $number,
            'passwordHash' => bcrypt('secret'),
            'status' => 'active',
        ]);
    }

    /**
     * One row in every table that names a reference-data row, so each destroy route
     * is exercised against a parent that is genuinely still in use.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: int}>
     */
    private function plantReferencedRows(): array
    {
        $courseId = $this->createCourse('BSCS', 'Bachelor of Science in Computer Science');
        $termId = $this->createTerm();
        $subjectId = $this->createSubject('CCS101', 'Introduction to Programming');
        $majorId = DB::table('majors')->insertGetId(['courseId' => $courseId, 'majorName' => 'Software Engineering']);
        $studentId = $this->createStudent('2026-00001');
        $instructorId = $this->staffWithRole('Instructor')->userId;

        $enrollmentId = DB::table('enrollments')->insertGetId([
            'studentId' => $studentId,
            'courseId' => $courseId,
            'termId' => $termId,
            'majorId' => $majorId,
            'studentType' => StudentType::FirstYear->value,
            'enrollmentStatus' => EnrollmentStatus::Enrolled->value,
            'evaluatedBy' => $instructorId,
        ]);

        DB::table('enrolledsubjects')->insert([
            'enrollmentId' => $enrollmentId,
            'subjectId' => $subjectId,
            'status' => EnrolledSubjectStatus::Confirmed->value,
        ]);

        $roomId = DB::table('rooms')->insertGetId(['roomName' => 'Room 101', 'capacity' => 40, 'building' => '']);
        $blockId = DB::table('blocks')->insertGetId([
            'courseId' => $courseId,
            'termId' => $termId,
            'yearLevel' => 1,
            'blockName' => 'BS-CS-1A',
            'maxStudents' => 40,
        ]);
        DB::table('schedules')->insert([
            'blockId' => $blockId,
            'subjectId' => $subjectId,
            'instructorId' => $instructorId,
            'roomId' => $roomId,
        ]);

        $feeTypeId = DB::table('feetypes')->insertGetId(['feeName' => 'Library Fee', 'defaultAmount' => 500, 'unitBasis' => 'flat']);
        $assessmentId = DB::table('studentassessments')->insertGetId([
            'enrollmentId' => $enrollmentId,
            'totalAssessedAmount' => 500,
            'totalScholarshipCoverage' => 0,
            'totalWaived' => 0,
            'remainingBalance' => 500,
            'assessmentDate' => '2026-06-01',
        ]);
        DB::table('charges')->insert([
            'assessmentId' => $assessmentId,
            'feeTypeId' => $feeTypeId,
            'amount' => 500,
            'waivedAmount' => 0,
        ]);

        $scholarshipTypeId = DB::table('scholarshiptypes')->insertGetId([
            'scholarshipName' => 'Dean, Special',
            'coverageType' => 'full',
            'coveragePercent' => 100,
        ]);
        DB::table('studentscholarships')->insert([
            'studentId' => $studentId,
            'scholarshipTypeId' => $scholarshipTypeId,
            'termId' => $termId,
            'status' => ScholarshipStatus::Active->value,
            'approvedBy' => $instructorId,
        ]);

        $admissionId = DB::table('admissions')->insertGetId([
            'studentId' => $studentId,
            'termId' => $termId,
            'courseId' => $courseId,
            'applicantType' => 'firstYear',
            'admissionStatus' => 'pending',
        ]);
        $requirementId = DB::table('admissionrequirements')->insertGetId([
            'requirementName' => 'Form 137',
            'appliesTo' => 'all',
            'isRequired' => true,
        ]);
        DB::table('studentrequirementsubmissions')->insert([
            'admissionId' => $admissionId,
            'requirementId' => $requirementId,
            'submissionStatus' => 'submitted',
            'submittedDate' => '2026-06-01',
        ]);

        $clearanceRequirementId = DB::table('clearancerequirements')->insertGetId([
            'officeId' => 1,
            'requirementName' => 'Return borrowed books',
        ]);
        $periodId = DB::table('clearanceperiods')->insertGetId([
            'termId' => $termId,
            'clearanceStartDate' => '2026-06-01',
            'clearanceEndDate' => '2026-08-31',
            'periodStatus' => 'open',
        ]);
        $clearanceId = DB::table('studentclearances')->insertGetId([
            'studentId' => $studentId,
            'clearancePeriodId' => $periodId,
            'overallStatus' => ClearanceOverallStatus::Pending->value,
        ]);
        DB::table('clearanceapprovals')->insert([
            'studentClearanceId' => $clearanceId,
            'clearanceRequirementId' => $clearanceRequirementId,
            'status' => 'pending',
        ]);

        return [
            ['course', 'admin.reference-data.courses.destroy', 'courses', $courseId],
            ['major', 'admin.reference-data.majors.destroy', 'majors', $majorId],
            ['subject', 'admin.reference-data.subjects.destroy', 'subjects', $subjectId],
            ['academic term', 'admin.reference-data.terms.destroy', 'academicterms', $termId],
            ['fee type', 'admin.reference-data.fee-types.destroy', 'feetypes', $feeTypeId],
            ['scholarship type', 'admin.reference-data.scholarship-types.destroy', 'scholarshiptypes', $scholarshipTypeId],
            ['room', 'admin.reference-data.rooms.destroy', 'rooms', $roomId],
            ['block', 'admin.reference-data.blocks.destroy', 'blocks', $blockId],
            ['admission requirement', 'admin.reference-data.admission-requirements.destroy', 'admissionrequirements', $requirementId],
            ['clearance requirement', 'admin.reference-data.clearance-requirements.destroy', 'clearancerequirements', $clearanceRequirementId],
        ];
    }

    private function tableOf(string $routeName): string
    {
        return [
            'admin.reference-data.majors.store' => 'majors',
            'admin.reference-data.subjects.store' => 'subjects',
            'admin.reference-data.terms.store' => 'academicterms',
            'admin.reference-data.fee-types.store' => 'feetypes',
            'admin.reference-data.scholarship-types.store' => 'scholarshiptypes',
            'admin.reference-data.rooms.store' => 'rooms',
            'admin.reference-data.blocks.store' => 'blocks',
            'admin.reference-data.curriculums.store' => 'curriculums',
        ][$routeName];
    }

    private function primaryKeyOf(string $table): string
    {
        return [
            'courses' => 'courseId',
            'majors' => 'majorId',
            'subjects' => 'subjectId',
            'academicterms' => 'termId',
            'curriculums' => 'curriculumId',
            'feetypes' => 'feeTypeId',
            'scholarshiptypes' => 'scholarshipTypeId',
            'rooms' => 'roomId',
            'blocks' => 'blockId',
            'admissionrequirements' => 'requirementId',
            'clearancerequirements' => 'clearanceRequirementId',
        ][$table];
    }
}
