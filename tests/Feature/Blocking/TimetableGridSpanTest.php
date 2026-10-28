<?php

namespace Tests\Feature\Blocking;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The timetable grid is the shape the development prompt §7.9 asks for, and nothing else checks it.
 *
 * §7.9 does not describe a schedule vaguely: it names Monday through Sunday, 30-minute intervals,
 * and 7:00 AM through 9:30 PM. The grid in `resources/js/Pages/Blocking/Show.jsx` ran to 9:00 PM,
 * so a class meeting until half past nine was drawn as if it ended at nine — the clamp hid the last
 * row rather than refusing it, which is why no screen looked wrong until the span was read against
 * the instruction. There is no JavaScript test suite in this repository, so the same technique the
 * year-level vocabulary uses is the one available here: read the module's source and re-derive the
 * grid from it. A constant in a comment is not evidence; the numbers the code computes with are.
 */
class TimetableGridSpanTest extends TestCase
{
    private const SOURCE = 'resources/js/Pages/Blocking/Show.jsx';

    private function grid(): array
    {
        $src = (string) file_get_contents(base_path(self::SOURCE));

        preg_match('/const dayOrder = \[(?<days>[^\]]*)\]/', $src, $d) || throw new \RuntimeException(
            'REFUSED: the day order is not declared in the form this test reads — re-read '.self::SOURCE
        );
        preg_match('/const START_HOUR = (?<h>\d+);/', $src, $s) || throw new \RuntimeException(
            'REFUSED: the grid start hour is not declared in the form this test reads.'
        );
        preg_match('/const END_HOUR = (?<h>\d+);/', $src, $e) || throw new \RuntimeException(
            'REFUSED: the grid end hour is not declared in the form this test reads.'
        );
        // The half-hour is its own constant: a span that stops at a whole hour cannot express
        // "through 9:30 PM", so the presence of the minute is part of what is being pinned.
        preg_match('/const END_MINUTE = (?<m>\d+);/', $src, $m) || throw new \RuntimeException(
            'REFUSED: the grid has no END_MINUTE, so its span ends on the hour — §7.9 asks for 9:30 PM.'
        );
        preg_match('/const SLOT_MINUTES = (?<i>\d+);/', $src, $i) || throw new \RuntimeException(
            'REFUSED: the slot length is not a named constant, so the interval cannot be checked.'
        );
        preg_match('/const SLOTS = (?<expr>[^;]+);/', $src, $x) || throw new \RuntimeException(
            'REFUSED: the row count is not derived from the constants this test reads.'
        );

        $days = array_values(array_filter(array_map(
            static fn (string $part): string => trim($part, " \t'\""),
            explode(',', $d['days'])
        )));

        return [
            'days' => $days,
            'start' => (int) $s['h'] * 60,
            'end' => (int) $e['h'] * 60 + (int) $m['m'],
            'interval' => (int) $i['i'],
            // The declaration must be arithmetic over the same constants, not a row count typed by hand:
            // a literal would let the grid and the span disagree silently.
            'rows' => $x['expr'],
        ];
    }

    #[Test]
    public function the_week_runs_monday_through_sunday_as_the_instruction_names_it(): void
    {
        $this->assertSame(
            ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            $this->grid()['days'],
            '§7.9 asks for a Sunday-inclusive week; a five-day grid drops the Saturday and Sunday classes the school schedules.'
        );
    }

    #[Test]
    public function the_grid_spans_half_past_nine_at_night_not_nine_o_clock(): void
    {
        $grid = $this->grid();

        $this->assertSame(7 * 60, $grid['start'], '§7.9 begins the day at 7:00 AM.');
        $this->assertSame(21 * 60 + 30, $grid['end'], '§7.9 runs through 9:30 PM — a meeting to half past nine must have a row.');
    }

    #[Test]
    public function the_interval_is_thirty_minutes_and_the_rows_are_derived_from_it(): void
    {
        $grid = $this->grid();

        $this->assertSame(30, $grid['interval'], '§7.9 asks for 30-minute intervals.');

        // The declaration has to be arithmetic over the same constants, so a row count typed by
        // hand cannot drift from the span: substituting the parsed values into the expression and
        // evaluating it would only re-derive from the same numbers, which is circular. Naming the
        // three constants in the expression, and then requiring the count the span implies, is the
        // pair that catches both a literal and a span that moved alone.
        foreach (['END_HOUR', 'START_HOUR', 'SLOT_MINUTES', 'END_MINUTE'] as $name) {
            $this->assertStringContainsString(
                $name,
                $grid['rows'],
                "the row count must be derived from {$name}, not typed — a literal lets the grid and the span disagree silently"
            );
        }

        $this->assertSame(
            intdiv($grid['end'] - $grid['start'], $grid['interval']),
            29,
            'the span divided by the interval is the row count the grid draws'
        );
    }

    #[Test]
    public function the_screen_says_the_span_it_draws(): void
    {
        $src = (string) file_get_contents(base_path(self::SOURCE));

        $this->assertMatchesRegularExpression(
            '/Weekly Timetable \(7:00 AM . 9:30 PM, 30-minute slots\)/u',
            $src,
            'a grid whose heading names a different span than it renders teaches a desk to distrust the heading'
        );
        $this->assertStringNotContainsString('9:00 PM, 30-minute slots', $src);
    }
}
