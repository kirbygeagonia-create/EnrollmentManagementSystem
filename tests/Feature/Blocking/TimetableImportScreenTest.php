<?php

namespace Tests\Feature\Blocking;

use App\Console\Commands\ImportTimetable;
use App\Enums\UnitType;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Blocks;
use App\Models\Courses;
use App\Models\Rooms;
use App\Models\Schedulemeetings;
use App\Models\Schedules;
use App\Models\Staffusers;
use App\Models\Subjects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Blocking desk's import screen (prompt §7.9's preview-and-validation contract, at a window rather than a
 * terminal).
 *
 * The rule itself belongs to `ems:import:timetable` and is pinned there; these cases pin what only the screen can
 * get wrong: that a preview writes nothing, that a publish writes the accepted rows and spends the sheet, that a
 * desk without the right is refused at both doors, that a photograph of a timetable is refused rather than read,
 * and that the file never lands on a served folder.
 *
 * The last two cases compare the two surfaces on purpose. A screen that recomputed the verdicts would be a second
 * definition of "does this row collide" — the defect this repository has refused in every lane that pairs a page
 * with a handler — so the equality is asserted in both directions rather than trusted.
 */
class TimetableImportScreenTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = 'block_name,subject_code,instructor,room,day_of_week,start_time,end_time';

    private int $termId;

    private int $blockId;

    private Staffusers $desk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RbacSeeder', '--database' => 'sqlite']);

        $unit = Academicunits::create([
            'unitCode' => 'CCS',
            'unitName' => 'College of Computer Studies',
            'unitType' => UnitType::College,
        ]);

        $courseId = Courses::create([
            'unitId' => $unit->unitId,
            'courseCode' => 'BSCS',
            'courseName' => 'Bachelor of Science in Computer Science',
            'requiresEntranceExam' => false,
            'requiresRetentionExam' => false,
        ])->courseId;

        $year = Academicyears::create([
            'yearStart' => 2024,
            'yearEnd' => 2025,
            'yearLabel' => '2024-2025',
            'startDate' => '2024-06-01',
            'endDate' => '2025-05-31',
        ]);

        $this->termId = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2024-06-01',
            'endDate' => '2024-10-31',
        ])->termId;

        Subjects::create([
            'subjectCode' => 'CS101',
            'subjectName' => 'Introduction to Programming',
            'lectureUnits' => 3,
            'labUnits' => 0,
            'subjectType' => 'lecture',
        ]);

        Rooms::create(['roomName' => 'Room 101', 'capacity' => 40, 'building' => 'Main']);

        $this->blockId = Blocks::create([
            'courseId' => $courseId,
            'termId' => $this->termId,
            'yearLevel' => 1,
            'blockName' => 'BSIT 1-A',
            'maxStudents' => 40,
            'scheduleStatus' => 'draft',
        ])->blockId;

        // `officeId` stays null: the account exists to hold a right, and the staff factory would otherwise
        // ensure an office row to satisfy a foreign key, which changes the population a count is measuring.
        $this->desk = Staffusers::factory()->create([
            'firstName' => 'Mel',
            'lastName' => 'Cruz',
            'middleName' => null,
            'username' => 'desk.blocking',
            'email' => 'desk.blocking@seait.edu.ph',
            'officeId' => null,
        ]);
        $this->desk->assignRole('SysAdmin');
    }

    #[Test]
    public function a_desk_without_the_import_right_is_refused_at_both_doors(): void
    {
        $outsider = Staffusers::factory()->create([
            'firstName' => 'Nina',
            'lastName' => 'Bayani',
            'middleName' => null,
            'username' => 'desk.registrar',
            'email' => 'desk.registrar@seait.edu.ph',
            'officeId' => null,
        ]);
        $outsider->assignRole('RegistrarApprover');

        // An ability is not a permission name: `block.import` is the right, `blocking.importSheet` the ability,
        // so the refusal below is the policy answering and not Spatie's Gate::before granting by collision.
        $this->actingAs($outsider)->get(route('blocking.import'))->assertForbidden();
        $this->actingAs($outsider)->post(route('blocking.import.preview'), [
            'termId' => $this->termId,
            'sheet' => UploadedFile::fake()->createWithContent('week.csv', $this->csv([
                $this->row('BSIT 1-A', 'CS101', 'desk.blocking', 'Room 101', 'Monday', '08:00', '09:30'),
            ])),
        ])->assertForbidden();

        // The publish door is the one that writes, so it is asked too: guarding the page and the preview while
        // the act stands open is the half-applied authorization this repository keeps refusing.
        $this->actingAs($outsider)->post(route('blocking.import.publish'), [
            'key' => str_repeat('a', 32),
            'termId' => $this->termId,
        ])->assertForbidden();

        $this->assertEmpty(session('timetableImport'), 'A refused preview still left a sheet behind.');
        $this->assertSame(0, Schedules::count());
    }

    #[Test]
    public function the_preview_reports_the_rows_and_writes_nothing(): void
    {
        $response = $this->actingAs($this->desk)->from(route('blocking.import'))->post(route('blocking.import.preview'), [
            'termId' => $this->termId,
            'sheet' => UploadedFile::fake()->createWithContent('week.csv', $this->csv([
                $this->row('BSIT 1-A', 'CS101', 'desk.blocking', 'Room 101', 'Monday', '08:00', '09:30'),
                $this->row('BSIT 1-A', 'CS101', 'desk.blocking', 'Room 101', 'Tuesday', '08:00', '09:30'),
            ])),
        ]);

        $response->assertRedirect(route('blocking.import'));
        $this->assertSame(0, Schedules::count(), 'A preview that writes is not a preview.');
        $this->assertSame(0, Schedulemeetings::count());

        $stored = (array) session('timetableImport');
        $preview = (array) ($stored['preview'] ?? []);

        $this->assertNotEmpty($preview, 'The screen was sent no payload, so it would show an empty table over a sheet that read fine.');
        $this->assertSame(2, $preview['read']);
        $this->assertSame(0, $preview['refused']);
        $this->assertSame(1, $preview['schedules']);
        $this->assertSame(2, $preview['meetings']);
        $this->assertSame('clean', $preview['outcome']);
        $this->assertNotEmpty($stored['key'] ?? null, 'The publish act is addressed by the key the preview minted.');

        // Named fields, not positions: a page that rebuilt the verdict from the console table's cell order
        // breaks the day a column is added, and breaks silently.
        $this->assertSame('BSIT 1-A', $preview['rows'][0]['block']);
        $this->assertSame('CS101', $preview['rows'][0]['subject']);
        $this->assertSame('Monday 08:00-09:30', $preview['rows'][0]['when']);
    }

    #[Test]
    public function publishing_writes_the_rows_and_spends_the_sheet_so_a_second_click_writes_nothing(): void
    {
        $this->actingAs($this->desk)->post(route('blocking.import.preview'), [
            'termId' => $this->termId,
            'sheet' => UploadedFile::fake()->createWithContent('week.csv', $this->csv([
                $this->row('BSIT 1-A', 'CS101', 'desk.blocking', 'Room 101', 'Monday', '08:00', '09:30'),
            ])),
        ]);

        $key = (string) session('timetableImport.key');
        $this->assertNotSame('', $key);

        $this->actingAs($this->desk)->post(route('blocking.import.publish'), [
            'key' => $key,
            'termId' => $this->termId,
        ])->assertRedirect(route('blocking.import'));

        $this->assertSame(1, Schedules::count());
        $this->assertSame(1, Schedulemeetings::count());
        $this->assertSame('published', session('timetableImport')['preview']['outcome'] ?? null);

        // The second click is the state an idempotency question is really about. The sheet is gone, so the act
        // has no material to repeat — and the answer says that instead of writing a second week.
        $this->actingAs($this->desk)->post(route('blocking.import.publish'), [
            'key' => $key,
            'termId' => $this->termId,
        ]);

        $this->assertSame(1, Schedules::count(), 'A repeat published a second copy of the week.');
        $this->assertSame(1, Schedulemeetings::count());
        $this->assertStringContainsString('no longer here', (string) session('timetableImport')['preview']['message']);
    }

    #[Test]
    public function the_screen_and_the_terminal_answer_the_same_question_about_the_same_sheet(): void
    {
        $csv = $this->csv([
            $this->row('BSIT 1-A', 'CS101', 'desk.blocking', 'Room 101', 'Monday', '08:00', '09:30'),
            // A second row in the same hour and room: the within-sheet pass is the only one that can see it.
            $this->row('BSIT 1-A', 'CS101', 'desk.blocking', 'Room 101', 'Monday', '09:00', '10:30'),
        ]);

        $this->actingAs($this->desk)->post(route('blocking.import.preview'), [
            'termId' => $this->termId,
            'sheet' => UploadedFile::fake()->createWithContent('week.csv', $csv),
        ]);
        $screen = (array) session('timetableImport')['preview'];

        $path = storage_path('app/testing-timetable-'.$this->termId.'.csv');
        file_put_contents($path, $csv);
        $json = storage_path('app/testing-timetable.json');
        Artisan::call('ems:import:timetable', [
            '--csv' => $path, '--term' => (string) $this->termId, '--json' => $json,
        ]);
        $console = json_decode((string) file_get_contents($json), true);
        @unlink($path);
        @unlink($json);

        foreach (['read', 'refused', 'schedules', 'meetings'] as $field) {
            $this->assertSame($console[$field], $screen[$field], "The two surfaces disagree about {$field}.");
        }
        $this->assertSame($console['skippedForCollision'], $screen['skippedForCollision']);
        $this->assertNotEmpty($screen['reasons'], 'A collision the terminal names must reach the screen as a reason.');
    }

    #[Test]
    public function a_photograph_of_a_timetable_is_refused_because_only_a_sheet_is_read(): void
    {
        $this->actingAs($this->desk)->from(route('blocking.import'))
            ->post(route('blocking.import.preview'), [
                'termId' => $this->termId,
                'sheet' => UploadedFile::fake()->create('timetable.png', 8, 'image/png'),
            ])
            ->assertSessionHasErrors('sheet');

        $this->assertSame(0, Schedules::count());
        $this->assertArrayNotHasKey('preview', (array) session('timetableImport'));
    }

    #[Test]
    public function the_sheet_waits_on_the_private_disk_and_never_on_a_served_folder(): void
    {
        $this->actingAs($this->desk)->post(route('blocking.import.preview'), [
            'termId' => $this->termId,
            'sheet' => UploadedFile::fake()->createWithContent('week.csv', $this->csv([
                $this->row('BSIT 1-A', 'CS101', 'desk.blocking', 'Room 101', 'Monday', '08:00', '09:30'),
            ])),
        ]);

        $key = (string) session('timetableImport.key');
        $stored = Storage::disk('local')->path('timetable-imports/'.$key.'.csv');

        $this->assertFileExists($stored);
        // The `local` disk roots at storage/app/private, which no route serves since the `serve` flag came off
        // on 2026-10-31; a file that landed under public/ would be readable by URL, and the extension would be
        // the only hint. So the claim is about the path the bytes actually sit on.
        $this->assertStringStartsWith(realpath(storage_path('app/private')) ?: storage_path('app/private'), realpath($stored) ?: $stored);
        $this->assertStringNotContainsString(realpath(public_path('storage')) ?: 'storage/', strtolower(realpath($stored) ?: $stored));
    }

    #[Test]
    public function the_screen_shows_the_column_map_and_the_terms_the_command_will_accept(): void
    {
        $props = $this->actingAs($this->desk)->get(route('blocking.import'))->viewData('page')['props'];

        // One definition of the map: the line a desk copies must be the line the tool reads, or an author follows
        // whichever of the two they were shown.
        $this->assertSame(ImportTimetable::headerLine(), $props['columns']);
        $this->assertSame($this->termId, (int) $props['terms'][0]['termId']);
        $this->assertStringContainsString('2024-2025', $props['terms'][0]['label']);
    }

    /** @param  list<string>  $rows */
    private function csv(array $rows): string
    {
        return self::HEADERS."\n".implode("\n", $rows)."\n";
    }

    private function row(string $block, string $subject, string $instructor, string $room, string $day, string $start, string $end): string
    {
        return implode(',', func_get_args());
    }
}
