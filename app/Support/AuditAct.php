<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * The named act an audit row belongs to.
 *
 * `AuditLogObserver` watches every model, so on its own it can only say `created`, `updated` or `deleted` —
 * which is a fact about a row, not about what a desk did. Development prompt §9 is titled "Admin, Access
 * Control, Audit Logs, and Archiving" and its *Audit logs* subsection enumerates seven families of high-impact
 * action whose history must be kept — intake and requirement decisions, examination confirmation, evaluation and
 * crediting, workflow signing, money, the per-office outcomes (clearance, clinic, ID), and role or account
 * changes — each event carrying actor, time, action, entity, reason and safe before/after detail. The domain
 * tables already record most of that with actor, date and often a reason; what did not exist was one column to
 * ask "who did this kind of thing", across the whole log.
 *
 * So an act stamps its own name at the place the act is written, and the observer copies that name into
 * `auditlogs.action` for every row the act touches — falling back to the generic word for a plain edit that
 * belongs to no named act. `auditlogs.action` is a varchar(100), so this is a write, not a migration.
 *
 * The holder is static and RESTORED rather than an injected service, for one reason that is not taste: this class
 * would have to reach every writer, and several of them are constructed with `new` in `tools/`, in tests and in
 * other services, so a constructor argument would ripple through all of them to buy nothing. The scope is
 * bracketed — `run()` restores the previous label in a `finally` — so a second act in one request cannot inherit
 * the first one's name, and a write outside any act keeps the generic word.
 * An unknown word is refused at the call site because the vocabulary lives here and nowhere else — a list the
 * audit writes but no one reads is how two spellings of one act enter a table.
 */
final class AuditAct
{
    /** §9's seven families, each named by at least one word below, plus the two the project's own rulings added. */
    public const ISSUE = 'enrollment.issue';

    public const DROP = 'enrollment.drop';

    public const RETURN = 'enrollment.return';

    public const REQUIREMENT_VERIFY = 'requirement.verify';

    public const CLEARANCE_DECIDE = 'clearance.decide';

    public const CREDIT_DECIDE = 'credit.decide';

    public const EXAM_VERIFY = 'exam.verify';

    public const PAYMENT_RECORD = 'payment.record';

    public const PAYMENT_VOID = 'payment.void';

    public const PAYMENT_REFUND = 'payment.refund';

    public const STEP_SIGN = 'step.sign';

    public const ID_VALIDATE = 'id.validate';

    public const ROLE_CHANGE = 'role.change';

    public const ACCOUNT_DEACTIVATE = 'account.deactivate';

    /** @var list<string> */
    public const LIST = [
        self::ISSUE, self::DROP, self::RETURN, self::REQUIREMENT_VERIFY, self::CLEARANCE_DECIDE,
        self::CREDIT_DECIDE, self::EXAM_VERIFY, self::PAYMENT_RECORD, self::PAYMENT_VOID,
        self::PAYMENT_REFUND, self::STEP_SIGN, self::ID_VALIDATE, self::ROLE_CHANGE, self::ACCOUNT_DEACTIVATE,
    ];

    /** The words the observer writes when no named act is running. */
    public const EVENTS = ['created', 'updated', 'deleted'];

    /**
     * Every word `auditlogs.action` can hold: the three event words, then the named acts.
     *
     * The audit desk's filter list is built from this and from nothing else. It used to be thirteen hand-typed
     * words, of which nine — `viewed`, `printed`, `approved`, `rejected`, `status_changed`, `assigned`,
     * `unassigned`, `login`, `logout` — were written by no code in the repository (measured on live `ems`: the
     * column held only `created`, `updated` and `deleted`), so a desk could choose a filter that could never
     * match and read the empty list as a quiet period rather than as an option that does not exist.
     *
     * @return list<string>
     */
    public static function vocabulary(): array
    {
        return array_merge(self::EVENTS, self::LIST);
    }

    private static ?string $current = null;

    /**
     * The act each desk route performs, keyed by route NAME.
     *
     * Naming the act where the request enters it — rather than at each write — is what makes the trail complete
     * for one act: a single desk decision writes the record itself, the workflow box behind it, the status
     * history and the notice, and a wrapper at the write would have to be repeated per statement to catch them
     * all. Two acts are deliberately NOT in this map because no route owns them: `enrollment.issue` is named by
     * `EnrollmentIssuer::issue()` itself, since intake approval, the Registrar desk and the demo pipeline all
     * reach that one method, and an act performed by a console command or a seeder has no route and keeps the
     * event word — the observer still records the row, and the domain table still carries the actor and date.
     *
     * A name no longer in the route table is a refusal in `AuditActNamingTest`, not a silent gap: that is the
     * only way a retired route stops leaving its act unnamed.
     *
     * @var array<string, string>
     */
    public const ROUTE_ACTS = [
        'admission.requirements.verify' => self::REQUIREMENT_VERIFY,
        'clearance.approve' => self::CLEARANCE_DECIDE,
        'enrollment.return' => self::RETURN,
        'evaluation.credits.decide' => self::CREDIT_DECIDE,
        'evaluation.credits.process' => self::CREDIT_DECIDE,
        'evaluation.sign' => self::STEP_SIGN,
        'exam.verify' => self::EXAM_VERIFY,
        'accounting.payment.record' => self::PAYMENT_RECORD,
        'accounting.payment.void' => self::PAYMENT_VOID,
        'accounting.payment.refund' => self::PAYMENT_REFUND,
        'id.validate' => self::ID_VALIDATE,
        'admin.users.roles.assign' => self::ROLE_CHANGE,
        'admin.users.roles.update' => self::ROLE_CHANGE,
        'admin.users.roles.destroy' => self::ROLE_CHANGE,
        'admin.users.status.toggle' => self::ACCOUNT_DEACTIVATE,
        'registrar.drop' => self::DROP,
    ];

    /** The act a route performs, or null when a request performs no named act. */
    public static function forRoute(?string $routeName): ?string
    {
        return $routeName === null ? null : (self::ROUTE_ACTS[$routeName] ?? null);
    }

    /**
     * Run one act, naming it for every audit row written inside it.
     *
     * @template T
     *
     * @param  callable():T  $act
     * @return T
     */
    public static function run(string $name, callable $act): mixed
    {
        if (! in_array($name, self::LIST, true)) {
            throw new InvalidArgumentException(
                "Audit act [{$name}] is not one of the named acts: ".implode(', ', self::LIST).'.'
            );
        }

        $previous = self::$current;
        self::$current = $name;

        try {
            return $act();
        } finally {
            self::$current = $previous;
        }
    }

    /** The act being performed right now, or null when a write belongs to no named act. */
    public static function current(): ?string
    {
        return self::$current;
    }

    /**
     * The word to store for one row: the act's own name when one is running, else the generic event word.
     */
    public static function labelFor(string $generic): string
    {
        return self::$current ?? $generic;
    }
}
