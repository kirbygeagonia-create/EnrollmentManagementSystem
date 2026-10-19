/**
 * A school date is a calendar day, not an instant. It reaches the browser either as "2026-05-12" (a DATE column
 * uncast) or as "2026-05-12T00:00:00.000000Z" (a `date` cast, or a midnight the app's UTC zone stamped). In both
 * spellings the day the record holds is the first ten characters — and putting the value through `new Date()`
 * and then a local formatter moves it into the *viewer's* zone, which shows the day before the stored one for
 * anyone west of UTC. So read the day from the text and format it in UTC, where no client zone can touch it.
 *
 * An instant — a signature, a receipt, a refund — is the other half, and lane 51 settled how it is shown: an
 * institutional record is read at the school, so it is rendered in the school's own zone rather than the
 * browser's. `new Date(value).toLocale…` made the same signature say one thing to a Manila desk and another to
 * anyone else, and on this machine (UTC-11) it said a third thing. The zone below is the same one
 * `App\Support\SchoolTime` draws every server-side day boundary in, and a test pins the two together.
 */

const DAY = /^(\d{4})-(\d{2})-(\d{2})/;

/** SEAIT's civil time. Kept in step with `config/school.php` by `tests/Feature/SchoolTimeTest.php`. */
export const SCHOOL_TIMEZONE = 'Asia/Manila';

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

function momentOf(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const moment = new Date(value);

    return Number.isNaN(moment.getTime()) ? null : moment;
}

/** An instant with its clock time — when the desk signed the box, when the money left the drawer. */
export function formatSchoolMoment(value, options = {}) {
    const moment = momentOf(value);

    return moment === null ? '—' : moment.toLocaleString('en-PH', { ...options, timeZone: SCHOOL_TIMEZONE });
}

/** An instant shown as a date only, which is still the school's date: 18:00Z is tomorrow in Manila. */
export function formatSchoolDay(value, options = {}) {
    const moment = momentOf(value);

    return moment === null ? '—' : moment.toLocaleDateString('en-PH', { ...options, timeZone: SCHOOL_TIMEZONE });
}
