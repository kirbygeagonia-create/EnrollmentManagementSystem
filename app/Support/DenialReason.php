<?php

namespace App\Support;

use Throwable;

/**
 * The one question a refusal answers: did the code that refused the desk actually *say why*?
 *
 * Two kinds of 403 reach the officer. A policy or gate denial carries Laravel's placeholder string, and
 * nothing more is knowable — the page's own copy is the whole truth. A boundary that refused one named
 * record (`abort_unless(..., 403, "Program BSCE belongs to another college")`) holds the thing the desk
 * can act on, and for years it was thrown away: `resources/views/errors/403.blade.php` interpolated
 * nothing, so every reason-bearing refusal printed the same bare "Access denied".
 *
 * Both surfaces now ask this instead of each deciding on its own — the page prints the reason, and an
 * endpoint called by `fetch()` gets it back as JSON for the screen to render. Keeping the test for
 * "is this a real reason" in one place is the point: the placeholder string is the framework's, and a
 * second copy of it drifts the next time Laravel rewords it.
 */
final class DenialReason
{
    /** `Illuminate\Auth\Access\AuthorizationException`'s default message: a denial, but not an explanation. */
    private const PLACEHOLDER = 'This action is unauthorized.';

    /**
     * The reason the desk was given, or null when the refusal said nothing the desk can use.
     */
    public static function of(?Throwable $e): ?string
    {
        $message = trim((string) $e?->getMessage());

        return $message === '' || $message === self::PLACEHOLDER ? null : $message;
    }

    /**
     * What a closed account is told, by the two surfaces that close it.
     *
     * The login screen has said this sentence since the account-status gap was first worked; the mid-session guard
     * added on 2026-11-02 (§28.3 G-42's second half) says the same one, because a person who is refused at the door
     * and refused differently inside should not have to learn two explanations of the same state. Appended here
     * rather than written at either call site for the reason this class exists at all: one place that decides what a
     * refusal says, so the page and the JSON answer cannot drift.
     */
    public static function deactivated(): string
    {
        return 'Your staff account is currently deactivated. Please contact the administrator.';
    }
}
