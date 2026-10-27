<?php

namespace Tests\Feature\Console;

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
use App\Support\TimetableConflicts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * G-5, prompt §7.9: a timetable sheet is previewed against the Blocking rules and published only when
 * every row was accepted. These cases pin the three promises the gap register names.
 *
 * **A preview really writes nothing.** That claim is only safe if a case tries to break it, so every
 * read-only case ends on `assertNothingWritten()`, which compares the two tables it would touch against
 * whatever the fixtures in that same case already put there.
 *
 * **One malformed row refuses the whole sheet — including under `--commit`.** The command prints a
 * "would publish" verdict beside the refusal for the clean rows, so a reader could otherwise take the
 * accepted half as good and re-submit it; the commit case is what proves the transaction never opens.
 *
 * **A collision is named before publication, by the rule the desk already uses.** The sentences are
 * asserted on `TimetableConflicts` itself, because that is where they are built: a console table
 * word-wraps a long cell, and an assertion that spans the wrap point fails on the *rendering*, not on
 * the rule. The command's own cases then assert the tallies, the exit status, and the short tokens.
 */
class TimetableImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = 'block_name,subject_code,instructor,room,day_of_week,start_time,end_time';

    private int $termA;

    private int $termB;

    private int $blockA;

    private int $blockB;

    private int $courseId;

    /** What the two timetable tables hold before the case runs, so fixtures do not look like writes. */
    private array $expect = [0, 0];

    private int $lastStatus = -1;

    /** @var array<int, string> */
    private array $paths = [];

    protected function setUp(): void
    {
        parent::setUp();

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
            'yearStart' => 2024,
            'yearEnd' => 2025,
            'yearLabel' => '2024-2025',
            'startDate' => '2024-06-01',
            'endDate' => '2025-05-31',
        ]);

        // Two terms, because the point of `--term` is that rooms and officers are reused across them on
        // purpose, so a sheet has to say which one it is booking.
        $this->termA = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '1st',
            'startDate' => '2024-06-01',
            'endDate' => '2024-10-31',
        ])->termId;

        $this->termB = Academicterms::create([
            'academicYearId' => $year->academicYearId,
            'semester' => '2nd',
            'startDate' => '2024-11-01',
            'endDate' => '2025-05-31',
        ])->termId;

        foreach (['CS101' => 'Introduction to Programming', 'CS102' => 'Data Structures'] as $code => $name) {
            Subjects::create([
                'subjectCode' => $code,
                'subjectName' => $name,
                'lectureUnits' => 3,
                'labUnits' => 0,
                'subjectType' => 'lecture',
            ]);
        }

        Rooms::create(['roomName' => 'Room 101', 'capacity' => 40, 'building' => 'Main']);
        Rooms::create(['roomName' => 'Room 9', 'capacity' => 10, 'building' => 'Annex']);

        $this->staff('Juan', 'Salazar', 'instr.salazar');
        $this->staff('Maria', 'Salazar', 'instr.mendoza');
        // A second account under the same full name: live `ems` measured exactly such a pair on
        // 2026-10-27, which is why a sheet keyed on a name is refused rather than guessed at.
        $this->staff('Maria', 'Salazar', 'instr.twice');

        $this->blockA = $this->block('BSIT 1-A', $this->termA, 40, 'draft');
        $this->blockB = $this->block('BSBA 2-A', $this->termA, 30, 'draft');
        $this->block('BSCrim 1-A', $this->termA, 40, 'final');
        $this->block('DUP 1-A', $this->termA, 40, 'draft');
        $this->block('DUP 1-A', $this->termA, 40, 'draft');
        $this->block('BSIT 1-A', $this->termB, 40, 'draft');
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            @unlink($path);
        }

        $this->paths = [];

        parent::tearDown();
    }

    #[Test]
    public function the_template_lists_every_column_the_tool_reads_and_writes_nothing(): void
    {
        $output = $this->import(['--template' => true]);
        $this->assertSame(0, $this->lastStatus);

        foreach (explode(',', self::HEADERS) as $column) {
            $this->assertStringContainsString($column, $output, "The template does not list {$column}, so a sheet author cannot know it is required.");
        }

        $this->assertStringContainsString('block_name,subject_code,instructor,room,day_of_week,start_time,end_time', $output, 'The template must also print a header row to copy.');
        $this->assertStringContainsString('--term=<academicterms.termId>', $output, 'The template must send the operator to --term, because a sheet cannot be read without naming its term.');
        $this->assertNothingWritten();
    }

    #[Test]
    public function a_clean_sheet_previews_and_writes_nothing_without_commit(): void
    {
        $output = $this->sheetRun($this->rows([
            $this->line('BSIT 1-A', 'CS101', 'instr.salazar', 'Room 101', 'Monday', '08:00', '09:30'),
            $this->line('BSIT 1-A', 'CS101', 'instr.salazar', 'Room 101', 'Tuesday', '08:00', '09:30'),
        ]));

        $this->assertSame(0, $this->lastStatus);
        $this->assertStringContainsString('Rows read: 2 accepted: 2 refused: 0 would create: 1 schedule(s) over 2 meeting line(s)', $output);
        $this->assertStringContainsString('Nothing was written.', $output);
        // The word `refuse` appears in the tally itself (`refused: 0`), so the verdict column is what
        // has to be absent — a cell that reads `refuse` is the only way a row says it was refused.
        $this->assertStringNotContainsString('| refuse |', $output);
        $this->assertNothingWritten();
    }

    #[Test]
    public function commit_publishes_one_schedule_carrying_both_meetings(): void
    {
        $output = $this->sheetRun($this->rows([
            $this->line('BSIT 1-A', 'CS101', 'instr.salazar', 'Room 101', 'Monday', '08:00', '09:30'),
            $this->line('BSIT 1-A', 'CS101', 'instr.salazar', 'Room 101', 'Tuesday', '8:00', '09:30'),
        ]), ['--commit' => true]);

        $this->assertSame(0, $this->lastStatus);
        $this->assertStringContainsString('Published: 2 meeting line(s) over 1 schedule(s).', $output);

        $rows = $this->scheduleRows();
        $this->assertCount(1, $rows, 'Two rows sharing block, subject, instructor and room are one schedule with two meetings, not two schedules.');
        $this->assertSame([$this->blockA], array_column($rows, 'blockId'));
        $this->assertSame([1], array_column($rows, 'subjectId'));

        $meetings = Schedulemeetings::orderBy('meetingId')->get();
        // `8:00` is accepted and stored padded: the shared comparison is byte order, and an un-padded
        // value in this column would sort against the file's own rows the wrong way.
        $this->assertSame(['Monday', 'Tuesday'], $meetings->pluck('dayOfWeek')->map(fn ($d): string => $d->value)->all());
        $this->assertSame(['08:00', '08:00'], $meetings->pluck('startTime')->all());
        $this->assertSame(['09:30', '09:30'], $meetings->pluck('endTime')->all());
    }

    #[Test]
    public function commit_publishes_two_schedules_for_two_subjects_of_one_block(): void
    {
        $this->sheetRun($this->rows([
            $this->line('BSIT 1-A', 'CS101', 'instr.salazar', 'Room 101', 'Monday', '08:00', '09:30'),
            $this->line('BSIT 1-A', 'CS102', 'instr.salazar', 'Room 101', 'Tuesday', '08:00', '09:30'),
        ]), ['--commit' => true]);

        $rows = $this->scheduleRows();
        $this->assertCount(2, $rows);
        $this->assertSame([1, 2], array_column($rows, 'subjectId'));
        $this->assertSame(2, Schedulemeetings::count());
    }

    #[Test]
    public function one_malformed_row_refuses_the_whole_sheet_even_under_commit(): void
    {
        $output = $this->sheetRun($this->rows([
            $this->line('BSIT 1-A', 'CS101', 'instr.salazar', 'Room 101', 'Monday', '08:00', '09:30'),
            $this->line('BSBA 2-A', 'CS102', 'instr.mendoza', 'Room 101', 'Tuesdae', '08:00', '09:30'),
        ]), ['--commit' => true]);

        $this->assertSame(1, $this->lastStatus, 'A refused sheet must not look like a successful run to the shell.');
        $this->assertStringContainsString('The sheet is refused as a whole: 1 of 2 row(s) carry a reason.', $output);
        $this->assertStringContainsString('--commit was given, and nothing was written.', $output);
        $this->assertStringContainsString("day_of_week 'Tuesdae' is not one of Monday, Tuesday, Wednesday, Thursday, Friday, Saturday, Sunday", $output);
        $this->assertNothingWritten();
    }

    #[Test]
    public function the_sheet_itself_cannot_double_book_a_room_and_both_rows_are_named(): void
    {
        $output = $this->sheetRun($this->rows([
            $this->line('BSIT 1-A', 'CS101', 'instr.salazar', 'Room 101', 'Friday', '13:00', '14:30'),
            $this->line('BSBA 2-A', 'CS102', 'instr.mendoza', 'Room 101', 'Friday', '14:00', '15:30'),
        ]));

        $this->assertSame(1, $this->lastStatus);
        $this->assertSame(2, substr_count($output, 'Room conflict on Friday 13:00-14:30 between rows 2 and 3 of this file'),
            'Neither row may be blamed alone: a reader who fixes only one re-books the other.');
        $this->assertStringNotContainsString('Instructor conflict', $output, 'The two rows name different officers, so only the room is shared.');
        $this->assertStringNotContainsString('Block conflict', $output);
        $this->assertNothingWritten();
    }

    #[Test]
    public function a_row_colliding_with_a_meeting_already_on_file_is_refused_by_the_database_pass(): void
    {
        // Different block, different officer, same room and hour: only the room branch may fire.
        $this->onFile($this->blockA, 'CS101', 'instr.salazar', 'Room 101', 'Monday', '08:00:00', '09:30:00');

        $output = $this->sheetRun($this->rows([
            $this->line('BSBA 2-A', 'CS102', 'instr.mendoza', 'Room 101', 'Monday', '08:00', '09:30'),
        ]));

        $this->assertSame(1, $this->lastStatus);
        $this->assertStringContainsString('Room conflict on Monday 08:00-09:30 — that hour is already on file for the same officer, room or block', $output);
        $this->assertStringNotContainsString('Instructor conflict', $output);
        $this->assertStringNotContainsString('Block conflict', $output);
        $this->assertNothingWritten();
    }

    #[Test]
    public function a_meeting_in_another_term_does_not_refuse_the_term_being_built(): void
    {
        // Rooms and officers are reused every year on purpose. `schedules` carries no term — it lives on
        // the block — so the collision query reaches through the block to tell the two apart, and a
        // schedule whose block is missing has no term to scope by and the filter is dropped instead.
        $this->onFile($this->block('BSIT 1-A', $this->termB, 40, 'draft'), 'CS101', 'instr.salazar', 'Room 101', 'Monday', '08:00', '09:30');

        $output = $this->sheetRun($this->rows([
            $this->line('BSIT 1-A', 'CS101', 'instr.salazar', 'Room 101', 'Monday', '08:00', '09:30'),
        ]), ['--term' => (string) $this->termA]);

        $this->assertSame(0, $this->lastStatus);
        $this->assertStringContainsString('refused: 0', $output);
        $this->assertStringNotContainsString('conflict', $output);
        $this->assertNothingWritten();
    }

    #[Test]
    public function committing_a_sheet_and_running_it_again_refuses_every_row(): void
    {
        $rows = $this->rows([
            $this->line('BSIT 1-A', 'CS101', 'instr.salazar', 'Room 101', 'Monday', '08:00', '09:30'),
        ]);

        $this->sheetRun($rows, ['--commit' => true]);
        $published = $this->counts();
        $this->assertSame([1, 1], $published);

        $second = $this->sheetRun($rows);
        $this->assertSame(1, $this->lastStatus);
        $this->assertStringContainsString('refused: 1', $second);
        $this->assertStringContainsString('Instructor conflict', $second);
        $this->assertStringContainsString('Room conflict', $second);
        $this->assertStringContainsString('Block conflict', $second);
        $this->assertSame($published, $this->counts(), 'A re-run may not double-book the room, the officer and the block it just published.');
    }

    #[Test]
    public function an_ambiguous_or_unknown_name_is_refused_with_the_candidates_it_saw(): void
    {
        $output = $this->sheetRun($this->rows([
            $this->line('DUP 1-A', 'CS101', 'instr.salazar', 'Room 101', 'Monday', '08:00', '09:30'),
            $this->line('No Such Block', 'CS101', 'instr.salazar', 'Room 101', 'Tuesday', '08:00', '09:30'),
            $this->line('BSIT 1-A', 'ZZZ999', 'instr.salazar', 'Room 101', 'Wednesday', '08:00', '09:30'),
            $this->line('BSIT 1-A', 'CS101', 'Maria Salazar', 'Room 101', 'Thursday', '08:00', '09:30'),
            $this->line('BSIT 1-A', 'CS101', 'Juan Salazar', 'Room 999', 'Friday', '08:00', '09:30'),
        ]));

        $this->assertSame(1, $this->lastStatus);
        $this->assertStringContainsString('matches 2 blocks in this term', $output, 'A block name that is not unique inside the term must print both ids rather than pick one.');
        $this->assertStringContainsString('names no block in the term asked for', $output);
        $this->assertStringContainsString('matches no subjects.subjectCode', $output);
        $this->assertStringContainsString('is the name of 2 accounts (instr.mendoza, instr.twice)', $output);
        $this->assertStringContainsString('matches no rooms.roomName', $output);
        $this->assertStringContainsString('refused: 5', $output);
        $this->assertNothingWritten();
    }

    #[Test]
    public function a_unique_full_name_resolves_and_an_account_name_always_can(): void
    {
        $output = $this->sheetRun($this->rows([
            $this->line('BSIT 1-A', 'CS101', 'Juan Salazar', 'Room 101', 'Monday', '08:00', '09:30'),
            $this->line('BSBA 2-A', 'CS102', 'instr.mendoza', 'Room 101', 'Monday', '09:30', '11:00'),
        ]));

        $this->assertSame(0, $this->lastStatus);
        $this->assertStringContainsString('refused: 0', $output);
        $this->assertStringNotContainsString('matches no staffusers.username', $output);
        $this->assertNothingWritten();
    }

    #[Test]
    public function a_finalized_block_refuses_its_rows_because_the_desk_would(): void
    {
        $output = $this->sheetRun($this->rows([
            $this->line('BSCrim 1-A', 'CS101', 'instr.salazar', 'Room 101', 'Monday', '08:00', '09:30'),
        ]));

        $this->assertSame(1, $this->lastStatus);
        $this->assertStringContainsString('is finalized, and Blocking accepts no schedule onto a finalized timetable', $output);
        $this->assertNothingWritten();
    }

    #[Test]
    public function a_clock_that_cannot_be_ordered_or_an_end_before_its_start_is_refused(): void
    {
        $output = $this->sheetRun($this->rows([
            $this->line('BSIT 1-A', 'CS101', 'instr.salazar', 'Room 101', 'Monday', '8.00', '09:30'),
            $this->line('BSIT 1-A', 'CS102', 'instr.salazar', 'Room 101', 'Tuesday', '09:00', '09:00'),
            $this->line('BSBA 2-A', 'CS101', 'instr.mendoza', 'Room 101', 'Wednesday', '14:00', '13:00'),
        ]));

        $this->assertStringContainsString("start_time '8.00' is not a 24-hour time", $output);
        $this->assertStringContainsString("end_time '09:00' is not after start_time '09:00'", $output);
        $this->assertStringContainsString("end_time '13:00' is not after start_time '14:00'", $output);
        $this->assertStringContainsString('refused: 3', $output);
        $this->assertNothingWritten();
    }

    #[Test]
    public function a_row_with_the_wrong_field_count_is_refused_naming_both_numbers(): void
    {
        // The unquoted comma is what a real spreadsheet produces, and it silently shifts every column
        // after it, so the row is refused rather than read with its day where its room should be.
        $output = $this->sheetRun($this->rows([
            $this->line('BSIT 1-A', 'CS101', 'instr.salazar', 'Room 101, Annex', 'Monday', '08:00', '09:30'),
        ]));

        $this->assertStringContainsString('the row carries 8 fields where the header declares 7', $output);
        $this->assertStringContainsString('shifts every column after it', $output);
        $this->assertNothingWritten();
    }

    #[Test]
    public function a_missing_or_forged_header_is_refused_before_any_row_is_read(): void
    {
        $missing = $this->import([
            '--csv' => $this->write("block_name,subject_code,instructor,room,day_of_week,start_time\nBSIT 1-A,CS101,instr.salazar,Room 101,Monday,08:00\n"),
            '--term' => (string) $this->termA,
        ]);
        $this->assertSame(1, $this->lastStatus);
        $this->assertStringContainsString("Missing required column: 'end_time'", $missing);

        $extra = $this->import([
            '--csv' => $this->write(self::HEADERS.',notes'."\n".$this->line('BSIT 1-A', 'CS101', 'instr.salazar', 'Room 101', 'Monday', '08:00', '09:30').',bring a laptop'."\n"),
            '--term' => (string) $this->termA,
        ]);
        $this->assertStringContainsString("Unknown column: 'notes'", $extra);
        $this->assertNothingWritten();
    }

    #[Test]
    public function the_term_is_required_and_an_unknown_id_prints_the_terms_on_file(): void
    {
        $header = $this->write(self::HEADERS."\n");

        $missing = $this->import(['--csv' => $header]);
        $this->assertSame(1, $this->lastStatus);
        $this->assertStringContainsString('No term named.', $missing);
        $this->assertStringContainsString('2024-2025', $missing, 'A reader told to name a term must be able to see which terms exist.');
        $this->assertStringContainsString('1st', $missing);
        $this->assertStringContainsString('2nd', $missing);

        $forged = $this->import(['--csv' => $header, '--term' => '9999']);
        $this->assertStringContainsString('No academicterm answers termId 9999', $forged);

        $notAnId = $this->import(['--csv' => $header, '--term' => '2024-2025 1st']);
        $this->assertStringContainsString("--term must be the term's id, not '2024-2025 1st'", $notAnId);
        $this->assertNothingWritten();
    }

    #[Test]
    public function limit_says_what_was_read_rather_than_what_the_file_holds(): void
    {
        $rows = $this->rows([
            $this->line('BSIT 1-A', 'CS101', 'instr.salazar', 'Room 101', 'Monday', '08:00', '09:30'),
            $this->line('BSIT 1-A', 'CS102', 'instr.salazar', 'Room 101', 'Tuesday', '08:00', '09:30'),
            $this->line('BSBA 2-A', 'CS101', 'instr.mendoza', 'Room 101', 'Wednesday', '08:00', '09:30'),
        ]);

        $whole = $this->sheetRun($rows);
        $this->assertStringContainsString('Rows read: 3', $whole);

        $capped = $this->sheetRun($rows, ['--limit' => '1']);
        $this->assertStringContainsString('Rows read: 1', $capped, '--limit is a promise about what was judged, so it has to change the tally.');
        $this->assertNothingWritten();
    }

    #[Test]
    public function a_room_too_small_for_the_block_warns_without_refusing_the_row(): void
    {
        // The hard cap is enforced at assignment time (`room_capacity`), so a too-small room must not
        // silently veto a legal schedule. The desk warns and proceeds, and so does the preview.
        $output = $this->sheetRun($this->rows([
            $this->line('BSBA 2-A', 'CS102', 'instr.mendoza', 'Room 9', 'Monday', '08:00', '09:30'),
        ]));

        $this->assertSame(0, $this->lastStatus);
        $this->assertStringContainsString('Would still be published — assignments to this room will be capped', $output);
        $this->assertStringContainsString('BSBA 2-A holds 30 students and room Room 9 seats 10', $output);
        $this->assertStringContainsString('refused: 0', $output);
        $this->assertNothingWritten();
    }

    #[Test]
    public function an_empty_sheet_and_an_unreadable_path_are_refused_rather_than_reported_clean(): void
    {
        $empty = $this->import(['--csv' => $this->write(self::HEADERS."\n"), '--term' => (string) $this->termA]);
        $this->assertSame(1, $this->lastStatus);
        $this->assertStringContainsString('The sheet carries no data rows beside its header.', $empty);

        $absent = $this->import(['--csv' => 'C:/no/such/sheet.csv']);
        $this->assertSame(1, $this->lastStatus);
        $this->assertStringContainsString("No readable CSV at 'C:/no/such/sheet.csv'", $absent);
        $this->assertStringContainsString('--template', $absent);
        $this->assertNothingWritten();
    }

    #[Test]
    public function the_shared_overlap_rule_names_the_pairing_and_holds_the_half_open_boundary(): void
    {
        // These sentences are the desk's and the sheet's only vocabulary for a collision, and they are
        // asserted here because a console table wraps them.
        $roomOnly = TimetableConflicts::withinSheet([
            ['row' => 2, 'blockId' => 1, 'instructorId' => 7, 'roomId' => 3, 'dayOfWeek' => 'Friday', 'startTime' => '13:00', 'endTime' => '14:30'],
            ['row' => 3, 'blockId' => 2, 'instructorId' => 8, 'roomId' => 3, 'dayOfWeek' => 'Friday', 'startTime' => '14:00', 'endTime' => '15:30'],
        ])->all();
        $this->assertSame(['Room conflict on Friday 13:00-14:30 between rows 2 and 3 of this file'], $roomOnly);

        $backToBack = TimetableConflicts::withinSheet([
            ['row' => 2, 'blockId' => 1, 'instructorId' => 7, 'roomId' => 3, 'dayOfWeek' => 'Monday', 'startTime' => '08:00', 'endTime' => '09:30'],
            ['row' => 3, 'blockId' => 2, 'instructorId' => 7, 'roomId' => 3, 'dayOfWeek' => 'Monday', 'startTime' => '09:30', 'endTime' => '11:00'],
        ])->all();
        $this->assertSame([], $backToBack, 'A meeting that starts as another ends is back-to-back, not a collision.');

        $mixedShapes = TimetableConflicts::withinSheet([
            ['row' => 2, 'blockId' => 1, 'instructorId' => 7, 'roomId' => 3, 'dayOfWeek' => 'Monday', 'startTime' => '08:00:00', 'endTime' => '09:30:00'],
            ['row' => 3, 'blockId' => 2, 'instructorId' => 7, 'roomId' => 3, 'dayOfWeek' => 'Monday', 'startTime' => '09:30', 'endTime' => '11:00'],
        ])->all();
        $this->assertSame([], $mixedShapes, "The table holds both spellings — a desk form leaves '09:30' on SQLite while MySQL and every seeder write '09:30:00' — and comparing them unpadded would sort '09:30:00' above '09:30' and invent a collision.");

        $threeWays = TimetableConflicts::withinSheet([
            ['row' => 2, 'blockId' => 1, 'instructorId' => 7, 'roomId' => 3, 'dayOfWeek' => 'Monday', 'startTime' => '08:00', 'endTime' => '09:30'],
            ['row' => 3, 'blockId' => 1, 'instructorId' => 7, 'roomId' => 3, 'dayOfWeek' => 'Monday', 'startTime' => '09:00', 'endTime' => '10:00'],
        ])->all();
        $this->assertSame([
            'Instructor conflict on Monday 08:00-09:30 between rows 2 and 3 of this file',
            'Room conflict on Monday 08:00-09:30 between rows 2 and 3 of this file',
            'Block conflict on Monday 08:00-09:30 between rows 2 and 3 of this file — one group cannot be in two places at once',
        ], $threeWays);

        $otherDay = TimetableConflicts::withinSheet([
            ['row' => 2, 'blockId' => 1, 'instructorId' => 7, 'roomId' => 3, 'dayOfWeek' => 'Monday', 'startTime' => '08:00', 'endTime' => '17:00'],
            ['row' => 3, 'blockId' => 1, 'instructorId' => 7, 'roomId' => 3, 'dayOfWeek' => 'Tuesday', 'startTime' => '08:00', 'endTime' => '17:00'],
        ])->all();
        $this->assertSame([], $otherDay, 'The same officer and room on a different day is a normal week.');
    }

    #[Test]
    public function the_database_half_of_the_same_rule_reads_the_stored_shape_without_inventing_a_collision(): void
    {
        // MySQL and every seeder store `08:00:00`; a desk form on SQLite leaves `08:00`. The proposed
        // meeting always arrives as `HH:MM`, so the bind is padded before it reaches the column — and
        // both halves of the boundary are asked here, on the driver the suite actually runs.
        foreach (['08:00:00', '08:00'] as $stored) {
            $this->runDatabaseBoundary($stored);
        }
    }

    /**
     * @param  array<string, string|bool>  $options
     */
    private function import(array $options): string
    {
        $this->lastStatus = Artisan::call('ems:import:timetable', $options);

        // Captured output carries ANSI codes (lane 73b's lesson) and a console table word-wraps, so the
        // assertions read a normalised form: escape sequences out, whitespace runs collapsed.
        return preg_replace('/\s+/', ' ', preg_replace('/\e\[[0-9;]*m/', '', Artisan::output())) ?? '';
    }

    /**
     * @param  array<string, string|bool>  $extra
     */
    private function sheetRun(string $body, array $extra = []): string
    {
        return $this->import([
            '--csv' => $this->write(self::HEADERS."\n".$body."\n"),
            '--term' => (string) $this->termA,
        ] + $extra);
    }

    private function runDatabaseBoundary(string $stored): void
    {
        // Meetings first: `schedulemeetings.scheduleId` is a foreign key into `schedules`.
        Schedulemeetings::query()->delete();
        Schedules::query()->delete();
        $this->expect = [0, 0];

        // Both cells of the stored row get the SAME shape, because that is what each surface writes:
        // MySQL returns `08:00:00`/`09:30:00` for a TIME column and a desk form on SQLite leaves
        // `08:00`/`09:30`. Padding one side of the fixture would hide the very mismatch this pins.
        $this->onFile($this->blockA, 'CS101', 'instr.salazar', 'Room 101', 'Monday', $stored, $this->shift($stored, 90, $stored));

        $other = (int) Staffusers::where('username', 'instr.mendoza')->value('userId');
        $schedule = new Schedules(['blockId' => $this->blockB, 'subjectId' => 2, 'instructorId' => $other, 'roomId' => 1]);
        $schedule->setRelation('block', Blocks::find($this->blockB));
        $schedule->setRelation('meetings', collect([
            new Schedulemeetings(['dayOfWeek' => 'Monday', 'startTime' => '09:30', 'endTime' => '11:00']),
            new Schedulemeetings(['dayOfWeek' => 'Monday', 'startTime' => '09:00', 'endTime' => '11:00']),
        ]));

        $conflicts = TimetableConflicts::againstDatabase($schedule)->all();

        $this->assertSame(
            ['Room conflict on Monday 09:00-11:00'],
            $conflicts,
            "Stored as '{$stored}': the first meeting is back-to-back and must pass, the second overlaps by half an hour and must refuse."
        );
    }

    private function shift(string $clock, int $minutes, string $shape): string
    {
        $parts = array_map('intval', array_pad(explode(':', $clock), 3, 0));
        $total = $parts[0] * 60 + $parts[1] + $minutes;

        return str_contains($shape, ':') && strlen($shape) === 8
            ? sprintf('%02d:%02d:00', intdiv($total, 60), $total % 60)
            : sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }

    private function write(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ems-timetable-').'.csv';
        file_put_contents($path, $contents);
        $this->paths[] = $path;

        return $path;
    }

    /**
     * @param  list<string>  $rows
     */
    private function rows(array $rows): string
    {
        return implode("\n", $rows);
    }

    private function line(string $block, string $subject, string $instructor, string $room, string $day, string $start, string $end): string
    {
        return implode(',', func_get_args());
    }

    /**
     * @return list<array{blockId: int, subjectId: int, instructorId: int, roomId: int}>
     */
    private function scheduleRows(): array
    {
        return Schedules::query()->orderBy('scheduleId')->get()
            ->map(fn (Schedules $s): array => [
                'blockId' => (int) $s->blockId,
                'subjectId' => (int) $s->subjectId,
                'instructorId' => (int) $s->instructorId,
                'roomId' => (int) $s->roomId,
            ])
            ->all();
    }

    /** @return array{0: int, 1: int} */
    private function counts(): array
    {
        return [Schedules::count(), Schedulemeetings::count()];
    }

    private function assertNothingWritten(): void
    {
        $this->assertSame($this->expect, $this->counts(), 'The command printed a verdict and wrote a timetable anyway.');
    }

    private function staff(string $first, string $last, string $username): Staffusers
    {
        return Staffusers::factory()->create([
            'firstName' => $first,
            'lastName' => $last,
            'middleName' => null,
            'username' => $username,
            'email' => $username.'@seait.edu.ph',
        ]);
    }

    private function block(string $name, int $termId, int $maxStudents, string $status): int
    {
        return Blocks::create([
            'courseId' => $this->courseId,
            'termId' => $termId,
            'yearLevel' => 1,
            'blockName' => $name,
            'maxStudents' => $maxStudents,
            'scheduleStatus' => $status,
        ])->blockId;
    }

    /**
     * A meeting already on the timetable, written the way each surface writes it: the seconds-bearing
     * shape is MySQL's and the seeder's, the short one is what a desk form leaves on SQLite.
     */
    private function onFile(int $blockId, string $subjectCode, string $username, string $roomName, string $day, string $start, string $end): void
    {
        $schedule = Schedules::create([
            'blockId' => $blockId,
            'subjectId' => Subjects::where('subjectCode', $subjectCode)->value('subjectId'),
            'instructorId' => Staffusers::where('username', $username)->value('userId'),
            'roomId' => Rooms::where('roomName', $roomName)->value('roomId'),
        ]);

        Schedulemeetings::create([
            'scheduleId' => $schedule->scheduleId,
            'dayOfWeek' => $day,
            'startTime' => $start,
            'endTime' => $end,
        ]);

        $this->expect = [$this->expect[0] + 1, $this->expect[1] + 1];
    }
}
