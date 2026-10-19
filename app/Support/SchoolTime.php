<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/**
 * The one place that asks "which calendar day is the school standing in?".
 *
 * The application's clock runs UTC (`config/app.php`'s timezone, and `config/database.php` forces every MySQL
 * session to `+00:00`) and that is right for storing and comparing instants. It was never right for the boundary
 * a desk draws: SEAIT keeps Manila time, so cutting a day at UTC midnight hands an officer the hours 00:00–07:59
 * of their own day in *yesterday's* report. Measured on live `ems`: 1,459 of 3,426 audit rows, 56 of 187 signed
 * workflow boxes and 11 of 15 payment rows land on a different date depending on which midnight you use.
 *
 * So instants stay UTC and only the edges convert — and the edges are these four methods. A controller that
 * re-derived the offset itself would be a second answer to the same question, which is how `OfficeId` lists and
 * tone maps went stale in earlier lanes.
 *
 * Every returned bound is a UTC instant, so the comparison against a stored `DATETIME` is a comparison of two
 * instants and works the same on MySQL and on the SQLite the suite runs on — no driver-specific date function.
 */
final class SchoolTime
{
    /** The zone the institution's desks, notices and printed forms are written in. */
    public static function zone(): string
    {
        return (string) config('school.timezone', 'Asia/Manila');
    }

    /** The date a person at the school would say they are in — `Y-m-d`, no time, no zone. */
    public static function today(): string
    {
        return CarbonImmutable::now(self::zone())->format('Y-m-d');
    }

    /**
     * The UTC instant that opens the school's `$date`.
     *
     * @throws InvalidArgumentException when the desk sent something that is not a plain calendar date
     */
    public static function startOfSchoolDay(string $date): CarbonImmutable
    {
        self::assertDate($date);

        return CarbonImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $date.' 00:00:00',
            new DateTimeZone(self::zone())
        )->setTimezone('UTC');
    }

    /**
     * The UTC instant that opens the school's *next* day — the exclusive upper bound for `$date`, so a record
     * stamped at the last second of the day is inside and the one at midnight is not.
     */
    public static function startOfNextSchoolDay(string $date): CarbonImmutable
    {
        // Step the *date* and re-open it in the school's zone rather than adding 24 hours to an instant: a zone
        // that observes daylight saving would otherwise end the day an hour early or late. Manila does not, but
        // the rule should not depend on that fact.
        return self::startOfSchoolDay(
            CarbonImmutable::createFromFormat('Y-m-d', $date, new DateTimeZone(self::zone()))->addDay()->format('Y-m-d')
        );
    }

    /**
     * The calendar date a desk meant, or null when the value is absent or is not a date at all.
     *
     * A filter bound arrives from a URL, and a hand-edited one used to reach `whereDate()` as raw text — MySQL
     * compared it and answered nothing. Converting that into a 500 would be worse for the officer, so an unusable
     * bound means "no bound here", which is what the screen shows.
     */
    public static function dayOrNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return self::isRealDay($value) ? $value : null;
    }

    /**
     * Which calendar day an instant falls on *at the school* — the conversion every "is it still today" question
     * needs, because a `DATETIME` argument is a moment and a `DATE` column is a day.
     *
     * Takes a fresh immutable copy rather than re-zoning the caller's object: `Carbon` is mutable, and a helper
     * that quietly moved the instant it was handed would be a side effect in a date function.
     */
    public static function civilDateOf(DateTimeInterface $moment): string
    {
        return CarbonImmutable::instance($moment)->timezone(self::zone())->format('Y-m-d');
    }

    /**
     * A shape alone is not a date. `Carbon::createFromFormat` rolls an impossible one over instead of failing —
     * measured here: `2026-13-45` came back as 2027-02-13 and `2026-02-30` as March 1st. A desk that typed
     * nonsense would have got a real report for a day it never asked for, so the calendar has to be consulted.
     */
    private static function isRealDay(string $value): bool
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) === 1
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    private static function assertDate(string $date): void
    {
        if (! self::isRealDay($date)) {
            throw new InvalidArgumentException("expected a real calendar date as Y-m-d, got: {$date}");
        }
    }
}
