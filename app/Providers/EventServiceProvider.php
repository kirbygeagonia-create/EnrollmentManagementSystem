<?php

namespace App\Providers;

use App\Events\EnrollmentStatusChanged;
use App\Events\WorkflowStepSigned;
use App\Listeners\SendEnrollmentNotification;
use App\Listeners\SendWorkflowNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        EnrollmentStatusChanged::class => [
            SendEnrollmentNotification::class,
        ],
        WorkflowStepSigned::class => [
            SendWorkflowNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }
}
