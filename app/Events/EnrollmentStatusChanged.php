<?php

namespace App\Events;

use App\Models\Enrollments;
use App\Models\Staffusers;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EnrollmentStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Enrollments $enrollment,
        public string $fromStatus,
        public string $toStatus,
        public ?Staffusers $changedBy,
        public string $remarks = '',
        // The `enrollmentstatushistory` row this transition wrote. It is the act's identity: the bell's dedupe key
        // (§28.3 G-36) is built from it, because a retried queued listener must not ring the next desk twice for one
        // move, while a later move through the SAME pair of statuses is a different act and must ring. A hand-built
        // event has no history row, so this is null and the notice is delivered unkeyed rather than swallowed.
        public ?int $historyId = null
    ) {}
}
