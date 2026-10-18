<?php

namespace Tests\Feature;

use App\Models\Payments;
use App\Models\Students;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Lane 50 — the payload contract the calendar-day formatter depends on.
 *
 * `Components/ui/calendarDay.js` reads a school day off the first ten characters of the value it is given, and
 * refuses to put that value through the viewer's zone. That is only correct while the server writes the day the
 * record holds into those characters. These cases pin that: they fail if the application stops answering in UTC
 * (which would make midnight in the school's zone appear as the previous day's 16:00Z) or if a date column
 * starts serialising with a local offset.
 *
 * They cannot pin the JSX decision itself — a PHP test never runs the browser's Date — which is what
 * `storage/app/scratch/lane50-date-day-shift.php` and its mutant harness are for, together with measuring the
 * painted cells at a UTC-11 client.
 */
class CalendarDayPayloadTest extends TestCase
{
    #[Test]
    public function a_date_column_reaches_the_payload_as_the_day_the_record_holds(): void
    {
        $student = new Students(['birthdate' => '2026-05-12']);

        $payload = json_decode($student->toJson(), true);

        $this->assertIsString($payload['birthdate']);
        $this->assertSame('2026-05-12', substr($payload['birthdate'], 0, 10));

        // And the spelling proves why the screen must not use the viewer's zone: this is an instant at 00:00Z,
        // so a client west of UTC would read 5/11/2004 from it.
        $this->assertStringContainsString('T00:00:00', $payload['birthdate']);
    }

    #[Test]
    public function a_datetime_written_by_a_date_only_input_keeps_its_day_in_the_same_ten_characters(): void
    {
        $payment = new Payments(['paymentDate' => '2026-10-12 00:00:00']);

        $payload = json_decode($payment->toJson(), true);

        $this->assertSame('2026-10-12', substr($payload['paymentDate'], 0, 10));
        $this->assertSame('UTC', config('app.timezone'),
            'the day-first reading above holds only while the app serialises in UTC; if this changes, every cell that reads a school day from the payload must be re-measured');
    }
}
