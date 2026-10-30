<?php

namespace Tests\Feature\Notification;

use App\Listeners\SendEnrollmentNotification;
use App\Listeners\SendWorkflowNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Prompt §8's duplicate-and-after-commit clause, the half that needed no school ruling.
 *
 * Both notification listeners are queued, and both events they answer are fired inside a database transaction —
 * the signature's and the state transition's. Before this, `afterCommit` appeared nowhere in `app/`, `config/` or
 * `database/`, so on a queued connection a request that rolled back still left a job that rang the next desk for an
 * act that never happened, and a retried job re-delivered the bell. The other half of §8's clause — a unique key so
 * a re-run cannot insert a second row — waits on the decision to spend a migration and is registered as such.
 *
 * Why this case reads the objects rather than driving the queue: `RefreshDatabase` wraps every test in a
 * transaction that is never committed, so a correctly deferred job is never observable as a push — a behavioural
 * assertion here would pass on a broken listener and fail on a working one. Asserting the property and the interface
 * is the promise that can actually be pinned: the deferral is on, and it is not "fixed" by making the listener
 * synchronous, which would run it mid-transaction instead.
 */
class NotificationAfterCommitTest extends TestCase
{
    /** @return array<string, array{0: class-string}> */
    public static function listeners(): array
    {
        return [
            'the workflow signature bell' => [SendWorkflowNotification::class],
            'the enrollment status bell' => [SendEnrollmentNotification::class],
        ];
    }

    #[Test]
    #[DataProvider('listeners')]
    public function a_queued_listener_is_deferred_until_the_transaction_commits(string $listener): void
    {
        $instance = new $listener;

        $this->assertTrue(
            (bool) ($instance->afterCommit ?? false),
            $listener.' is queued but fires regardless of whether the act it reports was committed'
        );
    }

    #[Test]
    #[DataProvider('listeners')]
    public function the_deferral_is_not_purchased_by_making_the_listener_synchronous(string $listener): void
    {
        $this->assertTrue(
            is_a($listener, ShouldQueue::class, true),
            $listener.' no longer implements ShouldQueue — running it inline would place the notification inside the '
                .'transaction that may still be rolled back, which is the same defect in the opposite direction'
        );
    }
}
