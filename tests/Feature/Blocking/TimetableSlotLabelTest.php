<?php

namespace Tests\Feature\Blocking;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Two halves of the timetable sheet that the owner read as missing, and both were half-there.
 *
 * §7.9 asks for 30-minute slots from 7:00 AM to 9:30 PM, and the grid has drawn those rows since the
 * span was fixed — but only the whole-hour rows carried a caption, so the 7:30 and 8:30 rows were real
 * and unnamed, which is what a person at the desk reports as "the half hours are missing". The same
 * shape on the other claim: `schedules.instructorId` is NOT NULL with a foreign key and the schedule
 * table below the grid has always listed the teacher, but the grid cell printed the subject code and
 * the room only, so the instructor existed and was invisible exactly where the timetable is read.
 *
 * There is no JavaScript suite in this repository, so each case reads the page's own source and refuses
 * a claim it cannot see — the technique `TimetableGridSpanTest` and the year-level vocabulary guard use.
 */
class TimetableSlotLabelTest extends TestCase
{
    private const SOURCE = 'resources/js/Pages/Blocking/Show.jsx';

    private function source(): string
    {
        $src = (string) file_get_contents(base_path(self::SOURCE));
        $this->assertNotSame('', $src, 'the blocking timetable page was not read at all');

        return $src;
    }

    #[Test]
    public function every_slot_row_prints_its_own_clock_time_not_just_the_hour_rows(): void
    {
        $src = $this->source();

        // The retired shape printed the hour and an empty string for everything else. Asserting the
        // absence of that branch is the assertion that fails the day someone "restores the design".
        // The retired shape printed the hour and an empty string for everything else. The search is scoped
        // to the label branch itself — this page has other `isHourMark` ternaries that legitimately span rows.
        $this->assertDoesNotMatchRegularExpression(
            '/isHourMark\s*\?[^;]{0,160}:\s*\'\'\}/s',
            $src,
            'a slot caption branch prints an empty string, so those rows draw with no time on them'
        );
        $this->assertStringContainsString(
            "\${isHourMark ? '00' : '30'}",
            $src,
            'the minute is not printed for every row, so a 30-minute slot is unnamed on the sheet'
        );
        $this->assertStringContainsString(
            'const hour = START_HOUR + Math.floor(s / 2);',
            $src,
            'the label hour is derived by the row index rather than by the half-hour-aware floor, which is how the caption drifts by an hour'
        );
    }

    #[Test]
    public function the_timetable_cell_that_names_a_subject_also_names_its_instructor(): void
    {
        $src = $this->source();
        $grid = (string) substr($src, (int) strpos($src, '{dayOrder.map((day, d)'));
        $cell = (string) substr($grid, 0, (int) strpos($grid, 'if (cell) {'));

        $this->assertGreaterThan(0, strlen($cell), 'the grid cell block was not located, so this case would prove nothing');
        $this->assertMatchesRegularExpression(
            '/<p[^>]*>\{schedule\.instructor \? `\$\{schedule\.instructor\.firstName\} \$\{schedule\.instructor\.lastName\}` : \'No instructor\'\}<\/p>/',
            $cell,
            'the instructor is not printed in the cell: a schedule row carries a teacher the desk cannot see on the timetable'
        );
        $this->assertStringNotContainsString(
            'title={`',
            $cell,
            'the tooltip was rebuilt inside the cell block, where a hover-only fact reads as an absence on paper'
        );
    }

    #[Test]
    public function an_unassigned_instructor_is_impossible_in_the_data_and_is_still_said_out_loud(): void
    {
        $migration = (string) file_get_contents(base_path('database/migrations/2026_08_02_1000000034_create_schedules_table.php'));

        // The cell falls back to the words "No instructor", so the case asks whether that branch can ever be
        // reached: `instructorId` is NOT NULL with a foreign key, which is why the fallback is a warning and
        // not a normal empty state.
        $this->assertMatchesRegularExpression('/\$table->integer\(\'instructorId\'\);\s*$/m', $migration,
            'the instructor column on a schedule no longer reads as the declaration this case reasons from');
        $this->assertStringContainsString('->references([\'userId\'])->on(\'staffusers\')', $migration,
            'the instructor is no longer a staff account, so the grid cell names a different kind of thing');
    }
}
