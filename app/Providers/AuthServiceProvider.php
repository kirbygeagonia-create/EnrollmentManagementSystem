<?php

namespace App\Providers;

use App\Enums\ExamStage;
use App\Enums\ExamType;
use App\Models\Academicterms;
use App\Models\Admissionrequirements;
use App\Models\Admissions;
use App\Models\Blocks;
use App\Models\Clearanceperiods;
use App\Models\Clearancerequirements;
use App\Models\Clinicrecords;
use App\Models\Courses;
use App\Models\Curriculums;
use App\Models\Curriculumsubjects;
use App\Models\Enrollments;
use App\Models\Examresults;
use App\Models\Feetypes;
use App\Models\Idrequests;
use App\Models\Majors;
use App\Models\Offices;
use App\Models\Payments;
use App\Models\Permissions;
use App\Models\Roles;
use App\Models\Rooms;
use App\Models\Scholarshiptypes;
use App\Models\Settings;
use App\Models\Staffusers;
use App\Models\Studentassessments;
use App\Models\Studentclearances;
use App\Models\Students;
use App\Models\Subjects;
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
        // Reference-data models share the ReferenceDataPolicy (viewAny/manage* abilities)
        Majors::class => ReferenceDataPolicy::class,
        Curriculums::class => ReferenceDataPolicy::class,
        Curriculumsubjects::class => ReferenceDataPolicy::class,
        Subjects::class => ReferenceDataPolicy::class,
        Academicterms::class => ReferenceDataPolicy::class,
        Feetypes::class => ReferenceDataPolicy::class,
        Scholarshiptypes::class => ReferenceDataPolicy::class,
        Offices::class => ReferenceDataPolicy::class,
        Rooms::class => ReferenceDataPolicy::class,
        Blocks::class => ReferenceDataPolicy::class,
        Admissionrequirements::class => ReferenceDataPolicy::class,
        Clearancerequirements::class => ReferenceDataPolicy::class,
        // User-management auxiliary models
        Roles::class => UserManagementPolicy::class,
        Permissions::class => UserManagementPolicy::class,
        Settings::class => UserManagementPolicy::class,
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

        /*
        |--------------------------------------------------------------------------
        | Explicit gates for abilities whose model-policy mapping cannot resolve
        | (multi-policy models, auxiliary models, or class-string first arguments).
        |--------------------------------------------------------------------------
        */

        // Exam recording: ExamPolicy is mapped to Examresults, but recording is
        // authorized against Courses + stage/type enums (BR9/BR10).
        Gate::define('exam.record', function ($user, $course, ExamStage $stage, ExamType $type) {
            // Controllers pass Courses::class (string); resolve the real course so
            // the retention branch (requiresRetentionExam) works.
            if (is_string($course)) {
                $course = Courses::find(request('courseId')) ?? new Courses;
            }

            return app(ExamPolicy::class)->record($user, $course, $stage, $type);
        });

        // Assessment compute acts on the enrollment, not the assessment (AssessmentPolicy
        // owns compute, but Enrollments maps to EvaluationPolicy).
        Gate::define('assessment.compute', function ($user, $enrollment) {
            return app(AssessmentPolicy::class)->compute($user, $enrollment);
        });

        // Payment recording acts on an assessment (PaymentPolicy owns record, but
        // Studentassessments maps to AssessmentPolicy).
        Gate::define('payment.record', function ($user, $assessment) {
            return app(PaymentPolicy::class)->record($user, $assessment);
        });

        // Clinic: view/record act on the enrollment.
        Gate::define('clinic.view', function ($user) {
            return $user->hasPermissionTo('clinic.view');
        });
        Gate::define('clinic.record', function ($user, $enrollment) {
            return app(ClinicPolicy::class)->record($user, $enrollment);
        });

        // ID: create acts on the enrollment; validate/release act on Studentids
        // (which has no model-policy mapping).
        Gate::define('id.view', function ($user) {
            return $user->hasPermissionTo('id.view');
        });
        Gate::define('id.create', function ($user, $enrollment) {
            return app(IDPolicy::class)->create($user, $enrollment);
        });
        Gate::define('id.validate', function ($user, $studentId) {
            return app(IDPolicy::class)->validate($user, $studentId);
        });
        Gate::define('id.release', function ($user, $studentId) {
            return app(IDPolicy::class)->release($user, $studentId);
        });

        // Blocking: Blocks maps to ReferenceDataPolicy (refdata manage), so all
        // blocking-module abilities are explicit gates.
        Gate::define('blocking.viewAny', function ($user) {
            return app(BlockingPolicy::class)->viewAny($user);
        });
        Gate::define('blocking.view', function ($user) {
            return $user->hasPermissionTo('block.view');
        });
        Gate::define('blocking.manageSchedules', function ($user) {
            return app(BlockingPolicy::class)->manageSchedules($user);
        });
        Gate::define('blocking.assignStudents', function ($user) {
            // The controller validates each enrollment's workflow step inside the
            // assignment loop; here we only gate permission + office scope.
            return $user->hasPermissionTo('block.assign') && $user->officeId === 5;
        });
        Gate::define('blocking.printBlockSchedule', function ($user, $block) {
            return app(BlockingPolicy::class)->printBlockSchedule($user, $block);
        });

        // Clearance: slip generation/replacement act on Students + period
        // (Students has no model-policy mapping); approval acts on Clearanceapprovals.
        Gate::define('clearance.generateSlip', function ($user, $student, $period) {
            // Controllers pass class strings before validation; resolve from the request.
            $student = is_string($student) ? Students::find(request('studentId')) ?? new Students : $student;
            $period = is_string($period) ? Clearanceperiods::find(request('clearancePeriodId')) ?? new Clearanceperiods : $period;

            return app(ClearancePolicy::class)->generateSlip($user, $student, $period);
        });
        Gate::define('clearance.replaceLostSlip', function ($user, $student, $period) {
            $student = is_string($student) ? Students::find(request('studentId')) ?? new Students : $student;
            $period = is_string($period) ? Clearanceperiods::find(request('clearancePeriodId')) ?? new Clearanceperiods : $period;

            return app(ClearancePolicy::class)->replaceLostSlip($user, $student, $period);
        });
        Gate::define('clearance.approveRequirement', function ($user, $approval) {
            return app(ClearancePolicy::class)->approveRequirement($user, $approval);
        });

        // Registrar printing (RegistrarPolicy owns these, Enrollments maps to EvaluationPolicy).
        Gate::define('registrar.printCertificate', function ($user, $enrollment) {
            return app(RegistrarPolicy::class)->printCertificate($user, $enrollment);
        });
        Gate::define('registrar.printClassCards', function ($user, $enrollment) {
            return app(RegistrarPolicy::class)->printClassCards($user, $enrollment);
        });
        Gate::define('registrar.printSubjectLoad', function ($user, $enrollment) {
            return app(RegistrarPolicy::class)->printSubjectLoad($user, $enrollment);
        });

        // User management auxiliary models (Roles/Permissions/Settings are mapped to
        // UserManagementPolicy, but the manage* abilities need explicit gates).
        Gate::define('userManagement.manageRoles', function ($user) {
            return app(UserManagementPolicy::class)->manageRoles($user);
        });
        Gate::define('userManagement.managePermissions', function ($user) {
            return app(UserManagementPolicy::class)->managePermissions($user);
        });
        Gate::define('userManagement.manageSettings', function ($user) {
            return app(UserManagementPolicy::class)->manageSettings($user);
        });
    }
}
