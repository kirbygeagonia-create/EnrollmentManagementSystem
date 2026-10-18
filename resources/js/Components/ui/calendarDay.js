/**
 * A school date is a calendar day, not an instant. It reaches the browser either as "2026-05-12" (a DATE column
 * uncast) or as "2026-05-12T00:00:00.000000Z" (a `date` cast, or a midnight the app's UTC zone stamped). In both
 * spellings the day the record holds is the first ten characters — and putting the value through `new Date()`
 * and then a local formatter moves it into the *viewer's* zone, which shows the day before the stored one for
 * anyone west of UTC. So read the day from the text and format it in UTC, where no client zone can touch it.
 *
 * An instant — a signature, a receipt, a refund — is different: there the viewer's own zone is the right thing
 * to render, and those cells must keep using `new Date(value).toLocale…`.
 */

const DAY = /^(\d{4})-(\d{2})-(\d{2})/;

export function formatCalendarDay(value, options = {}) {
    const match = DAY.exec(String(value ?? ''));

    if (! match) {
        return '—';
    }

    const utc = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])));

    if (Number.isNaN(utc.getTime())) {
        return '—';
    }

    return utc.toLocaleDateString('en-PH', { ...options, timeZone: 'UTC' });
}

/** A `YYYY` column is a year, and `getFullYear()` on a January 1st value reads 2023 at UTC-11. */
export function calendarYear(value) {
    const match = DAY.exec(String(value ?? ''));

    return match ? Number(match[1]) : null;
}
