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
        public string $remarks = ''
    ) {}
}