<?php

namespace App\Support;

use App\Models\Notifications;
use Illuminate\Database\QueryException;

/**
 * The one writer of bell rows, so a retried queued listener cannot deliver the same notice twice (§28.3 G-36).
 *
 * Both listeners are `ShouldQueue`, and `WorkflowStepSigned` / `EnrollmentStatusChanged` are fired inside the
 * transaction that records the act — so a job that runs twice is a duplicate notice for a desk that has already been
 * told. The pre-check answers the ordinary retry without touching the database's error path; the unique key
 * `uq_notifications_dedupe` answers the race where two workers check at once, because a check-then-write is not a
 * guard until the server refuses the second write.
 *
 * A null key is not "unknown, assume duplicate" — it means the act carries no identity (a hand-built event with no
 * history row behind it), and the notice is delivered. Silently swallowing an unkeyed delivery would turn the guard
 * into a way of losing bells, which is the opposite of what prompt §8 asks.
 */
class NoticeDelivery
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function once(?string $key, array $attributes): ?Notifications
    {
        if ($key === null) {
            return Notifications::create($attributes);
        }

        if (Notifications::query()->where('dedupeKey', $key)->exists()) {
            return null;
        }

        try {
            return Notifications::create($attributes + ['dedupeKey' => $key]);
        } catch (QueryException $e) {
            if (self::isDuplicateKey($e)) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * The same refusal arrives differently on each driver: MySQL hands back errno 1062, and the suite's SQLite answers
     * "UNIQUE constraint failed". Either is treated as "already delivered"; anything else is a real fault and rethrows,
     * because a guard that swallows every QueryException hides a broken column as a duplicate notice.
     */
    private static function isDuplicateKey(QueryException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062
            || str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }
}
