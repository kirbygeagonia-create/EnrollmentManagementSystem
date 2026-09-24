<?php

namespace Tests\Feature\Admin;

use App\Models\Courses;
use App\Models\Curriculums;
use App\Models\Curriculumsubjects;
use App\Models\Majors;
use App\Models\Subjects;
use Database\Seeders\DevReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Reference Data Seeder Test
 *
 * Guards the official SEAIT program catalog (SEAIT_Official_Information.md
 * §4.A) that DevReferenceDataSeeder reconstructs: the six academic units use
 * the official names, the degree programs match the documented offering, the
 * documented specializations exist as majors, and the board courses carry the
 * SCAT exam requirements. Also proves the seeder is idempotent.
 */
class ReferenceDataSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function seeder_reconstructs_the_official_program_catalog(): void
    {
        $this->seed(DevReferenceDataSeeder::class);

        // 16 degree programs across the six official academic units.
        $this->assertSame(16, Courses::count());

        // Official unit names (§4.A) — CICT is "Communication", and the
        // engineering unit is the Department of Civil & Electrical Engineering.
        $this->assertSame(
            'College of Information and Communication Technology',
            Courses::where('courseCode', 'BSIT')->first()?->unit?->unitName
        );
        $this->assertSame(
            'Department of Civil & Electrical Engineering',
            Courses::where('courseCode', 'BSCE')->first()?->unit?->unitName
        );

        // Board courses carry the SCAT requirements: BSCrim explicitly
        // (§4.A.2), BSSW as the board program (§8 Step 3 + BR10).
        foreach (['BSCrim', 'BSSW'] as $boardCode) {
            $board = Courses::where('courseCode', $boardCode)->first();
            $this->assertNotNull($board, "{$boardCode} must exist in the catalog");
            $this->assertTrue((bool) $board->requiresEntranceExam, "{$boardCode} requires the entrance exam");
            $this->assertTrue((bool) $board->requiresRetentionExam, "{$boardCode} requires the retention exam");
        }

        // BSCS is not part of the official SEAIT offering.
        $this->assertNull(Courses::where('courseCode', 'BSCS')->first(),
            'BSCS must NOT exist — it is not part of the official SEAIT offering');

        // Documented specializations exist as majors (course = degree program).
        $this->assertSame(12, Majors::count());
        $bsa = Courses::where('courseCode', 'BSA')->first();
        $this->assertSame(4, $bsa->majors()->count(), 'BSA has four documented majors');
        $this->assertSame(['Animal Science', 'Crop Science', 'Horticulture', 'Plant Breeding and Genetics'],
            $bsa->majors()->orderBy('majorId')->pluck('majorName')->all());
        $bsit = Courses::where('courseCode', 'BSIT')->first();
        $this->assertSame(['Business Analytics'], $bsit->majors()->pluck('majorName')->all(),
            'Business Analytics is a BSIT major, not a separate course');
        $this->assertNull(Courses::where('courseCode', 'BSIT-BA')->first(),
            'BSIT-BA must NOT exist as a course — Business Analytics is a BSIT major');

        // Catalog expansion: every subject carries its CHED description.
        $this->assertSame(0, Subjects::whereNull('subjectDesc')->count(),
            'Every subject must carry a description');
    }

    #[Test]
    public function every_course_has_a_baseline_curriculum_with_subjects(): void
    {
        $this->seed(DevReferenceDataSeeder::class);

        // One curriculum per program so the Evaluation flow has subjects to
        // propose for every course.
        $this->assertSame(16, Curriculums::count());
        $this->assertSame(0, Courses::whereNotIn('courseId', Curriculums::select('courseId'))->count());

        // The shared CHED GE/minor pool sits in EVERY curriculum.
        $gePool = ['UT1', 'TC1', 'MM1', 'PC1', 'RH1', 'ET1', 'ST1', 'RZ1', 'PE1', 'PE2', 'PE3', 'PE4', 'NSTP1', 'NSTP2', 'AA1'];
        foreach (Curriculums::with('course')->get() as $curriculum) {
            $codes = Curriculumsubjects::where('curriculumId', $curriculum->curriculumId)
                ->with('subject:subjectId,subjectCode')->get()
                ->map(fn ($cs) => $cs->subject?->subjectCode)->all();
            foreach ($gePool as $ge) {
                $this->assertContains($ge, $codes,
                    "{$curriculum->course->courseCode} curriculum must include the shared GE subject {$ge}");
            }
        }

        $curriculumIdOf = fn (string $code) => Courses::where('courseCode', $code)
            ->first()->curriculums()->first()->curriculumId;
        $pairsOf = fn (string $code) => Curriculumsubjects::where('curriculumId', $curriculumIdOf($code))
            ->with('subject:subjectId,subjectCode', 'prerequisiteSubject:subjectId,subjectCode')->get()
            ->map(fn ($cs) => [$cs->subject?->subjectCode, $cs->prerequisiteSubject?->subjectCode])->all();

        // BSCrim: CMO 5 s. 2018 major subjects with the CRIM 1 → CRIM 2
        // prerequisite chain intact.
        $bscrimPairs = $pairsOf('BSCrim');
        $this->assertContains(['CRIM2', 'CRIM101'], $bscrimPairs,
            'CRIM 2 must list Introduction to Criminology as its prerequisite');
        $this->assertContains(['CDI2', 'CDI1'], $bscrimPairs);
        $this->assertContains(['LEA3', 'LEA2'], $bscrimPairs);
        $this->assertSame(34, count($bscrimPairs), '15 shared GE + 19 BSCrim program subjects');

        // BSIT: UE CIT2019 subjects with the programming prereq chain and
        // Business Analytics as a BSIT major subject.
        $bsitPairs = $pairsOf('BSIT');
        $this->assertContains(['CCP1102', 'CCP1101'], $bsitPairs);
        $this->assertContains(['CDT1101', null], $bsitPairs,
            'Business Analytics (CDT1101) is a BSIT major subject');
        $this->assertSame(36, count($bsitPairs), '15 shared GE + 21 BSIT program subjects');

        // CTE programs carry the CMO 75 s. 2017 Education core.
        $bsedCodes = Curriculumsubjects::where('curriculumId', $curriculumIdOf('BSEd'))
            ->with('subject:subjectId,subjectCode')->get()
            ->map(fn ($cs) => $cs->subject?->subjectCode)->all();
        $this->assertContains('ED101', $bsedCodes, 'BSEd carries the Education core');
        $this->assertContains('ED106', $bsedCodes);
        $this->assertContains('EN1', $bsedCodes, 'BSEd carries its specialization subjects');

        // Program subject sets DIFFER — no program carries another's majors.
        $paSubjectId = Subjects::where('subjectCode', 'PA1')->value('subjectId');
        $bsitSubjectIds = Curriculumsubjects::where('curriculumId', $curriculumIdOf('BSIT'))->pluck('subjectId')->all();
        $this->assertNotContains($paSubjectId, $bsitSubjectIds, 'BSIT must not carry BPA major subjects');
        $itSubjectId = Subjects::where('subjectCode', 'CDT1101')->value('subjectId');
        $bshmSubjectIds = Curriculumsubjects::where('curriculumId', $curriculumIdOf('BSHM'))->pluck('subjectId')->all();
        $this->assertNotContains($itSubjectId, $bshmSubjectIds, 'BSHM must not carry BSIT major subjects');
    }

    #[Test]
    public function seeder_is_idempotent(): void
    {
        $this->seed(DevReferenceDataSeeder::class);
        $this->seed(DevReferenceDataSeeder::class);

        $this->assertSame(16, Courses::count());
        $this->assertSame(12, Majors::count());
        $this->assertSame(16, Curriculums::count());

        // The seeder owns the curriculum-subject content: re-running replaces
        // it in place without duplicating rows.
        $csCount = Curriculumsubjects::count();
        $this->seed(DevReferenceDataSeeder::class);
        $this->assertSame($csCount, Curriculumsubjects::count(),
            'Re-running must not duplicate curriculum subjects');
    }
}
