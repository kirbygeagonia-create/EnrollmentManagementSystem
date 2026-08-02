<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\Admissions::class => \App\Policies\AdmissionPolicy::class,
        \App\Models\Examresults::class => \App\Policies\ExamPolicy::class,
        \App\Models\Studentclearances::class => \App\Policies\ClearancePolicy::class,
        \App\Models\Enrollments::class => \App\Policies\EvaluationPolicy::class,
        \App\Models\Studentassessments::class => \App\Policies\AssessmentPolicy::class,
        \App\Models\Payments::class => \App\Policies\PaymentPolicy::class,
        \App\Models\Enrollments::class => \App\Policies\RegistrarPolicy::class,
        \App\Models\Enrollments::class => \App\Policies\BlockingPolicy::class,
        \App\Models\Clinicrecords::class => \App\Policies\ClinicPolicy::class,
        \App\Models\Idrequests::class => \App\Policies\IDPolicy::class,
        \App\Models\Courses::class => \App\Policies\ReferenceDataPolicy::class,
        \App\Models\Staffusers::class => \App\Policies\UserManagementPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Implicitly grant "SysAdmin" role all permissions
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('SysAdmin')) {
                return true;
            }
        });
    }
}