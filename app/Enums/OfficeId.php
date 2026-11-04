<?php

namespace App\Enums;

/**
 * Canonical office IDs (audit §4.1).
 *
 * Policies and seeders previously compared `$user->officeId` against bare
 * integers (2, 11, 22, ...). These IDs come from seed/insert order, so raw
 * literals silently break — or grant the wrong office access — if offices
 * are ever re-seeded or renumbered. All authorization code must reference
 * these named cases instead of bare integers.
 */
enum OfficeId: int
{
    case Registrar = 1;
    case Accounting = 2;
    case Scholarship = 3;
    case Guidance = 4;
    case Blocking = 5;
    case Admission = 6;
    case Academic = 7;
    case Clinic = 11;
    case IdOffice = 22;

    /**
     * The one-enrollment page this office works, when it has one — null where the desk's page is keyed on
     * something a notice about a person does not carry.
     *
     * The owner ruled 2026-11-04 (sheet item 1) that a notice lands on the record, with the queue as the fallback.
     * Four of the nine offices open an `enrollment` route directly: Registrar, Academic (the department's
     * evaluation screen), Clinic and the ID office. The other five do not, and that is a fact about their keys,
     * not an oversight: Accounting's and Scholarship's pages take a fee sheet, Blocking's takes a timetable block,
     * Admission's takes an application, and Guidance's takes a shift request. Inventing a redirect through a join
     * so a notice could name those pages would make the link a second, weaker door — the destination's policy is
     * what makes a deep link protected, so a destination that cannot be reached by the id the notice actually holds
     * gets no record link and the bell keeps the queue.
     */
    public function recordRouteName(): ?string
    {
        return match ($this) {
            self::Registrar => 'registrar.show',
            self::Academic => 'evaluation.show',
            self::Clinic => 'clinic.show',
            self::IdOffice => 'id.show',
            default => null,
        };
    }

    /**
     * The page that holds this office's work, by route NAME rather than by path.
     *
     * A notice has to take a desk somewhere (prompt §8: an intelligible title, action required, record reference,
     * timestamp and protected deep link), and the only destination this repository can state without asking the
     * school is the queue the record is now waiting in — which `WorkflowInbox` already resolves. The name is passed
     * to `route()` by the screen that renders it, so no path is ever taken from a client, and the destination's own
     * policy still refuses a reader who may not open it: a deep link is protected by the gate behind it, not by the
     * link. Every named office has a queue page today; the absence that matters is an `offices` row this enum does
     * not name at all, and `NoticeFacts::link()` answers that with no link rather than a guessed one.
     */
    public function deskRouteName(): string
    {
        return match ($this) {
            self::Registrar => 'registrar.index',
            self::Accounting => 'accounting.index',
            self::Scholarship => 'assessment.index',
            self::Guidance => 'shift.index',
            self::Blocking => 'blocking.index',
            self::Admission => 'admission.index',
            self::Academic => 'evaluation.index',
            self::Clinic => 'clinic.index',
            self::IdOffice => 'id.index',
        };
    }
}
