<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * The bounded retry for the two MySQL conditions that mean "another transaction held this row first".
 *
 * ADS problem 7 asks for prevention, detection and a retry that is safe. Prevention is a lock order (the block,
 * then the room, then the enrollment rows in id order — see `BlockingController::assignStudents()`); this class is
 * the detection and the retry. It is deliberately narrow:
 *
 *  - Only 1213 (deadlock) and 1205 (lock wait timeout) are retried. A duplicate key (1062), a lost connection or a
 *    constraint violation is a different fact, and swallowing it here would turn a real refusal into a silent loop.
 *  - The act it wraps must be one transaction. A retry is safe because the failed attempt rolled back completely;
 *    retrying an act that already committed part of its work would duplicate it, which is why problem 17's request key
 *    is the thing that makes a re-submit safe rather than this helper.
 *  - Attempts and the delay are bounded, with jitter so two desks that lost together do not collide again on the
 *    same tick.
 *  - The SQLite suite cannot deadlock, so no code path here is reached by a normal test run; its cases drive the
 *    exceptions directly instead of pretending the driver produced them.
 */
class RetryOnDeadlock
{
    public const DEADLOCK = 1213;

    public const LOCK_WAIT_TIMEOUT = 1205;

    /**
     * Run `$work`, retrying only a deadlock or a lock-wait timeout.
     *
     * @param  callable():mixed  $work  one complete transaction
     * @param  int  $attempts  total tries, including the first
     * @param  int  $baseDelayMs  multiplied by the attempt number, then jittered
     */
    public static function run(callable $work, int $attempts = 3, int $baseDelayMs = 25): mixed
    {
        if ($attempts < 1) {
            throw new \InvalidArgumentException('attempts must be at least 1');
        }

        for ($try = 1; ; $try++) {
            try {
                return $work();
            } catch (QueryException $e) {
                $code = self::codeOf($e);

                if (! self::isRetryable($code)) {
                    throw $e;
                }

                if ($try >= $attempts) {
                    throw ValidationException::withMessages([
                        'capacity' => 'Another desk was writing this record at the same moment and '
                            .$attempts.' attempts could not get past it. Nothing was saved — please try again.',
                    ]);
                }

                if ($baseDelayMs > 0) {
                    usleep(($baseDelayMs * $try * 1000) + random_int(0, $baseDelayMs * 1000));
                }
            }
        }
    }

    /** MySQL's driver code, read the way both servers hand it over. */
    public static function codeOf(QueryException $e): int
    {
        $info = is_array($e->errorInfo ?? null) ? $e->errorInfo : [];

        return (int) ($info[1] ?? $e->getCode());
    }

    public static function isRetryable(int $code): bool
    {
        return $code === self::DEADLOCK || $code === self::LOCK_WAIT_TIMEOUT;
    }
}
