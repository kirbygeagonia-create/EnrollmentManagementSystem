<?php

namespace App\Providers;

use App\Models\Admissions;
use App\Models\Clinicrecords;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Examresults;
use App\Models\Idrequests;
use App\Models\Payments;
use App\Models\Staffusers;
use App\Models\Studentassessments;
use App\Models\Studentclearances;
use App\Policies\AdmissionPolicy;
use App\Policies\AssessmentPolicy;
use App\Policies\BlockingPolicy;
use App\Policies\ClearancePolicy;
use App\Policies\ClinicPolicy;
use App\Policies\EvaluationPolicy;
use App\Policies\ExamPolicy;
use App\Policies\IDPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ReferenceDataPolicy;
use App\Policies\RegistrarPolicy;
use App\Policies\UserManagementPolicy;
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
        Admissions::class => AdmissionPolicy::class,
        Examresults::class => ExamPolicy::class,
        Studentclearances::class => ClearancePolicy::class,
        Enrollments::class => EvaluationPolicy::class,
        Studentassessments::class => AssessmentPolicy::class,
        Payments::class => PaymentPolicy::class,
        Clinicrecords::class => ClinicPolicy::class,
        Idrequests::class => IDPolicy::class,
        Courses::class => ReferenceDataPolicy::class,
        Staffusers::class => UserManagementPolicy::class,
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

        // Registrar & Blocking policies act on Enrollments but are not the default
        // model-policy mapping (EvaluationPolicy holds that slot). Register them as
        // explicit gates so controllers can call authorize('registrar.approve', $enrollment).
        Gate::define('registrar.approve', function ($user, $enrollment) {
            return app(RegistrarPolicy::class)->approve($user, $enrollment);
        });
        Gate::define('block.manage', function ($user) {
            return app(BlockingPolicy::class)->manageBlocks($user);
        });
    }
}
