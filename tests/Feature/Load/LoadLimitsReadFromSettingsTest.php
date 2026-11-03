<?php

namespace Tests\Feature\Load;

use App\Enums\UnitType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Blocks;
use App\Models\Courses;
use App\Models\Rooms;
use App\Models\Schedulemeetings;
use App\Models\Schedules;
use App\Models\Settings;
use App\Models\Staffusers;
use App\Models\Subjects;
use App\Providers\SettingsServiceProvider;
use App\Support\LoadLimits;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The two load ceilings prompt §7.3 and §7.5 ask for, measured on the rule rather than on a number.
 *
 * The school has never stated the figures — they are decision-sheet items 10 and 11, still open — so the build was
 * ruled (2026-11-03) to read its ceiling from the `settings` table and enforce nothing while the row is blank. That
 * makes the *absence* the thing worth pinning: a rule that fires on a placeholder refuses every proposal in the
 * school, and a rule that reads a figure nobody entered invents an academic policy in a commit message.
 *
 * The sum helpers are checked against rows written so that each wrong scope answers differently: another term, a
 * dropped subject line, the schedule being edited, a duplicated id, a colleague's rows.
 */
class LoadLimitsReadFromSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function settings(array $values): void
    {
        foreach ($values as $key => $value) {
            $row = Settings::where('settingKey', $key)->first();
            if ($row !== null) {
                $row->forceFill(['settingValue' => $value])->save();

                continue;
            }
            // `settingKey` is not fillable on this model, so an `updateOrCreate` that names it in its attributes
            // array writes a row without the key and dies on the NOT NULL — which is also how the seeder writes them.
            Settings::forceCreate([
                'settingKey' => $key,
                'settingValue' => $value,
                'description' => 'test fixture',
            ]);
        }
        // The bridge runs at boot, so a row written mid-test reaches config the way a Registrar's save reaches it on
        // the next request. Re-running the provider is the only way to prove the chain row -> config -> rule.
        (new SettingsServiceProvider($this->app))->boot();
    }

    private function subject(string $code, float $lecture, float $lab = 0.0): Subjects
    {
        return Subjects::create([
            'subjectCode' => $code,
            'subjectName' => 'Subject '.$code,
            'lectureUnits' => $lecture,
            'labUnits' => $lab,
            'subjectType' => 'lecture',
        ]);
    }

    private function term(string $label, int $start): Academicterms
    {
        $year = Academicyears::create([
            'yearStart' => $start,
            'yearEnd' => $start + 1,
            'yearLabel' => $label,
            'startDate' => $start.'-06-01',
            'endDate' => ($start + 1).'-05-31',
        ]);

        return Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => $start.'-06-01',
            'endDate' => $start.'-10-31',
        ]);
    }

    private function course(string $code): Courses
    {
        $unit = Academicunits::create([
            'unitCode' => 'U'.$code,
            'unitName' => 'Unit '.$code,
            'unitType' => UnitType::College,
        ]);

        return Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => $code,
            'courseName' => 'Course '.$code,
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ]);
    }

    private function block(int $termId, int $courseId, string $section): Blocks
    {
        return Blocks::create([
            'termId' => $termId,
            'courseId' => $courseId,
            'blockName' => 'Block '.$section,
            'maxStudents' => 40,
            'yearLevel' => 1,
        ]);
    }

    private function staff(string $tag): Staffusers
    {
        return Staffusers::factory()->create([
            // A null office keeps the factory's foreign-key hook from inserting an `offices` row this test never
            // asked for — the population read here is the population written here.
            'officeId' => null,
            'employeeNo' => 'EMP-'.$tag.'-'.uniqid(),
            'username' => $tag.'_'.uniqid(),
            'email' => $tag.'_'.uniqid().'@example.com',
        ]);
    }

    private function schedule(Blocks $block, Subjects $subject, Staffusers $teacher, Rooms $room): Schedules
    {
        $schedule = Schedules::create([
            'blockId' => $block->blockId,
            'subjectId' => $subject->subjectId,
            'instructorId' => $teacher->userId,
            'roomId' => $room->roomId,
        ]);
        Schedulemeetings::create([
            'scheduleId' => $schedule->scheduleId,
            'dayOfWeek' => 'Monday',
            'startTime' => '07:30',
            'endTime' => '09:00',
        ]);

        return $schedule;
    }

    #[Test]
    public function no_ceiling_is_enforced_until_the_school_enters_a_figure(): void
    {
        $this->settings([
            LoadLimits::STUDENT_KEY => '',
            LoadLimits::INSTRUCTOR_KEY => '',
        ]);

        $this->assertNull(LoadLimits::studentCeiling(), 'a blank study-load row must not bind a rule');
        $this->assertNull(LoadLimits::instructorCeiling(), 'a blank teaching-load row must not bind a rule');

        // Zero is a placeholder on this screen, not a policy: a ceiling of zero units would refuse every proposal in
        // the institution, which is the difference between an unset rule and an invented one.
        $this->settings([LoadLimits::STUDENT_KEY => '0', LoadLimits::INSTRUCTOR_KEY => '0.0']);
        $this->assertNull(LoadLimits::studentCeiling(), '0 in the field means "not filled in", not "nobody may study"');
        $this->assertNull(LoadLimits::instructorCeiling(), '0.0 on the teaching row is the same placeholder');

        $this->settings([LoadLimits::STUDENT_KEY => 'twenty one']);
        $this->assertNull(LoadLimits::studentCeiling(), 'a figure that is not a number cannot be compared against');
    }

    #[Test]
    public function a_number_typed_on_the_settings_screen_binds_the_rule_through_config(): void
    {
        $this->assertNull(config('settings.'.LoadLimits::STUDENT_KEY), 'the fixture assumes the key starts unset');

        $this->settings([
            LoadLimits::STUDENT_KEY => '21',
            LoadLimits::INSTRUCTOR_KEY => '18.5',
        ]);

        $this->assertSame(21.0, LoadLimits::studentCeiling(), 'the ceiling the desk typed is not the ceiling the rule reads');
        $this->assertSame(18.5, LoadLimits::instructorCeiling());
    }

    #[Test]
    public function a_subject_set_is_counted_once_and_carries_both_kinds_of_units(): void
    {
        $lecture = $this->subject('L1', 3.0);
        $both = $this->subject('B1', 2.0, 1.5);
        $this->subject('ignored', 9.0);

        $this->assertSame(6.5, LoadLimits::unitsFor([$lecture->subjectId, $both->subjectId]),
            'lecture and laboratory units are not both part of a load');
        $this->assertSame(6.5, LoadLimits::unitsFor([$lecture->subjectId, $lecture->subjectId, $both->subjectId]),
            'a duplicated subject id was counted twice, so one proposal could not be re-read safely');
        $this->assertSame(0.0, LoadLimits::unitsFor([]), 'an empty set must answer zero, not null');
        $this->assertSame(0.0, LoadLimits::unitsFor([999999]),
            'an id that names no subject must add nothing rather than 500 the save');
    }

    #[Test]
    public function a_faculty_members_load_is_counted_inside_one_term_and_excludes_the_row_being_edited(): void
    {
        $teacher = $this->staff('tchr');
        $colleague = $this->staff('coll');
        $course = $this->course('BSIT');
        $termA = $this->term('2026-2027', 2026);
        $termB = $this->term('2025-2026', 2025);
        $blockA = $this->block($termA->termId, $course->courseId, 'A');
        $blockA2 = $this->block($termA->termId, $course->courseId, 'A2');
        $blockB = $this->block($termB->termId, $course->courseId, 'B');
        $room = Rooms::create(['roomName' => 'R-1', 'capacity' => 40, 'building' => 'Main']);
        $s1 = $this->subject('S1', 3.0);
        $s2 = $this->subject('S2', 2.0);
        $s3 = $this->subject('S3', 4.0);

        $keep = $this->schedule($blockA, $s1, $teacher, $room);
        $this->schedule($blockA2, $s2, $teacher, $room);
        // Another term's load must not make a free faculty member look full: rooms and officers are reused every
        // year on purpose, which is the discovery that scoped collision detection to one term (the timetable ruling of 2026-10-19).
        $this->schedule($blockB, $s3, $teacher, $room);
        // A colleague's rows are nobody else's ceiling.
        $this->schedule($blockA, $s3, $colleague, $room);

        $this->assertSame(5.0, LoadLimits::instructorUnits((int) $teacher->userId, (int) $termA->termId),
            'the term scope is wrong: a cross-term row was counted, or a same-term row was missed');
        $this->assertSame(2.0, LoadLimits::instructorUnits((int) $teacher->userId, (int) $termA->termId, $keep->scheduleId),
            'editing a schedule counted it twice, so the ceiling crept upward on every re-save');
        $this->assertSame(4.0, LoadLimits::instructorUnits((int) $colleague->userId, (int) $termA->termId),
            'the count ignored which faculty member the schedule belongs to');
        $this->assertSame(4.0, LoadLimits::instructorUnits((int) $teacher->userId, (int) $termB->termId),
            'the other term answered with the first term rows');
    }
}
