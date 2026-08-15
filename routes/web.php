<?php

use App\Http\Controllers\Accounting\AccountingController;
use App\Http\Controllers\Admin\ReferenceDataController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admission\AdmissionController;
use App\Http\Controllers\Assessment\AssessmentController;
use App\Http\Controllers\Blocking\BlockingController;
use App\Http\Controllers\Clearance\ClearanceController;
use App\Http\Controllers\Clinic\ClinicController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Evaluation\EvaluationController;
use App\Http\Controllers\Exam\ExamController;
use App\Http\Controllers\ID\IDController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Registrar\RegistrarController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;

// Root: authenticated users go straight to the dashboard, guests to the
// branded login page. The legacy Welcome landing page was archived
// (see Documentation/ArchivedPages/Welcome.jsx).
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', DashboardController::class)->middleware(['auth'])->name('dashboard');
Route::get('/dashboard/queue-counts', [DashboardController::class, 'queueCounts'])->middleware(['auth'])->name('dashboard.queue-counts');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Business Routes (auth middleware)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    /* ==================== Admission ==================== */
    Route::get('/admission', [AdmissionController::class, 'index'])->name('admission.index');
    Route::get('/admission/create', [AdmissionController::class, 'create'])->name('admission.create');
    Route::post('/admission', [AdmissionController::class, 'store'])->name('admission.store');
    Route::get('/admission/{admission}', [AdmissionController::class, 'show'])->name('admission.show');
    Route::post('/admission/{admission}/requirements/{requirement}/submit', [AdmissionController::class, 'submitRequirement'])->name('admission.requirements.submit');
    Route::post('/admission/{admission}/requirements/{requirement}/verify', [AdmissionController::class, 'verifyRequirement'])->name('admission.requirements.verify');
    Route::post('/admission/{admission}/approve', [AdmissionController::class, 'approve'])->name('admission.approve');
    Route::post('/admission/{admission}/reject', [AdmissionController::class, 'reject'])->name('admission.reject');

    /* ==================== Exam ==================== */
    Route::get('/exam', [ExamController::class, 'index'])->name('exam.index');
    Route::get('/exam/create', [ExamController::class, 'create'])->name('exam.create');
    Route::get('/exam/students', [ExamController::class, 'students'])->name('exam.students');
    Route::post('/exam/general', [ExamController::class, 'recordGeneral'])->name('exam.general.record');
    Route::post('/exam/course-specific', [ExamController::class, 'recordCourseSpecific'])->name('exam.course-specific.record');
    Route::post('/exam/retention', [ExamController::class, 'recordRetention'])->name('exam.retention.record');
    Route::get('/exam/results', [ExamController::class, 'results'])->name('exam.results');

    /* ==================== Evaluation ==================== */
    Route::get('/evaluation', [EvaluationController::class, 'index'])->name('evaluation.index');
    Route::get('/evaluation/{enrollment}', [EvaluationController::class, 'show'])->name('evaluation.show');
    Route::put('/evaluation/{enrollment}/profile', [EvaluationController::class, 'captureProfile'])->name('evaluation.profile.capture');
    Route::post('/evaluation/{enrollment}/subjects', [EvaluationController::class, 'proposeSubjects'])->name('evaluation.subjects.propose');
    Route::post('/evaluation/{enrollment}/credits', [EvaluationController::class, 'processCredits'])->name('evaluation.credits.process');
    Route::post('/evaluation/{enrollment}/sign', [EvaluationController::class, 'sign'])->name('evaluation.sign');

    /* ==================== Assessment ==================== */
    Route::get('/assessment', [AssessmentController::class, 'index'])->name('assessment.index');
    Route::get('/assessment/{assessment}', [AssessmentController::class, 'show'])->name('assessment.show');
    Route::post('/assessment/compute/{enrollment}', [AssessmentController::class, 'compute'])->name('assessment.compute');
    Route::post('/assessment/{assessment}/scholarships', [AssessmentController::class, 'applyScholarship'])->name('assessment.scholarships.apply');
    Route::patch('/assessment/{assessment}/charges', [AssessmentController::class, 'adjustCharges'])->name('assessment.charges.adjust');
    Route::post('/assessment/{assessment}/finalize', [AssessmentController::class, 'finalize'])->name('assessment.finalize');

    /* ==================== Accounting ==================== */
    Route::get('/accounting', [AccountingController::class, 'index'])->name('accounting.index');
    Route::get('/accounting/{assessment}', [AccountingController::class, 'show'])->name('accounting.show');
    Route::post('/accounting/{assessment}/payment', [AccountingController::class, 'record'])->name('accounting.payment.record');
    Route::get('/accounting/daily-report', [AccountingController::class, 'dailyReport'])->name('accounting.daily-report');
    Route::post('/accounting/payments/{payment}/void', [AccountingController::class, 'void'])->name('accounting.payment.void');

    /* ==================== Clearance ==================== */
    Route::get('/clearance', [ClearanceController::class, 'index'])->name('clearance.index');
    Route::get('/clearance/periods', [ClearanceController::class, 'periods'])->name('clearance.periods');
    Route::post('/clearance/periods', [ClearanceController::class, 'storePeriod'])->name('clearance.periods.store');
    Route::patch('/clearance/periods/{period}', [ClearanceController::class, 'updatePeriod'])->name('clearance.periods.update');
    Route::post('/clearance/slip/generate', [ClearanceController::class, 'generateSlip'])->name('clearance.slip.generate');
    Route::post('/clearance/{clearance}/receipt', [ClearanceController::class, 'recordReceipt'])->name('clearance.receipt.record');
    Route::post('/clearance/approvals/{approval}', [ClearanceController::class, 'approveRequirement'])->name('clearance.approve');
    Route::post('/clearance/slip/replace', [ClearanceController::class, 'replaceLostSlip'])->name('clearance.slip.replace');
    Route::get('/clearance/{clearance}/print', [ClearanceController::class, 'printSlip'])->name('clearance.print-slip');

    /* ==================== Blocking ==================== */
    Route::get('/blocking', [BlockingController::class, 'index'])->name('blocking.index');
    Route::get('/blocking/{block}', [BlockingController::class, 'show'])->name('blocking.show');
    Route::post('/blocking', [BlockingController::class, 'store'])->name('blocking.store');
    Route::patch('/blocking/{block}', [BlockingController::class, 'update'])->name('blocking.update');
    Route::delete('/blocking/{block}', [BlockingController::class, 'destroy'])->name('blocking.destroy');
    Route::post('/blocking/{block}/schedules', [BlockingController::class, 'storeSchedule'])->name('blocking.schedules.store');
    Route::patch('/blocking/schedules/{schedule}', [BlockingController::class, 'updateSchedule'])->name('blocking.schedules.update');
    Route::delete('/blocking/schedules/{schedule}', [BlockingController::class, 'destroySchedule'])->name('blocking.schedules.destroy');
    Route::post('/blocking/{block}/assign', [BlockingController::class, 'assignStudents'])->name('blocking.assign');
    Route::post('/blocking/{block}/unassign', [BlockingController::class, 'unassignStudents'])->name('blocking.unassign');
    Route::get('/blocking/{block}/print', [BlockingController::class, 'printBlockSchedule'])->name('blocking.print-schedule');

    /* ==================== Registrar ==================== */
    Route::get('/registrar', [RegistrarController::class, 'index'])->name('registrar.index');
    Route::get('/registrar/{enrollment}', [RegistrarController::class, 'show'])->name('registrar.show');
    Route::post('/registrar/{enrollment}/approve', [RegistrarController::class, 'approve'])->name('registrar.approve');
    Route::get('/registrar/{enrollment}/print/certificate', [RegistrarController::class, 'printCertificate'])->name('registrar.print-certificate');
    Route::get('/registrar/{enrollment}/print/class-cards', [RegistrarController::class, 'printClassCards'])->name('registrar.print-class-cards');
    Route::get('/registrar/{enrollment}/print/subject-load', [RegistrarController::class, 'printSubjectLoad'])->name('registrar.print-subject-load');

    /* ==================== Clinic ==================== */
    Route::get('/clinic', [ClinicController::class, 'index'])->name('clinic.index');
    Route::get('/clinic/{enrollment}', [ClinicController::class, 'show'])->name('clinic.show');
    Route::post('/clinic/{enrollment}', [ClinicController::class, 'record'])->name('clinic.record');
    Route::patch('/clinic/records/{clinic}', [ClinicController::class, 'update'])->name('clinic.update');
    Route::post('/clinic/records/{clinic}/reopen', [ClinicController::class, 'reopen'])->name('clinic.reopen');

    /* ==================== ID ==================== */
    Route::get('/id', [IDController::class, 'index'])->name('id.index');
    Route::get('/id/{enrollment}', [IDController::class, 'show'])->name('id.show');
    Route::post('/id/{enrollment}', [IDController::class, 'create'])->name('id.create');
    Route::post('/id/requests/{idRequest}/produce', [IDController::class, 'produceCard'])->name('id.produce');
    Route::post('/id/cards/{studentId}/validate', [IDController::class, 'validate'])->name('id.validate');
    Route::post('/id/cards/{studentId}/release', [IDController::class, 'release'])->name('id.release');
    Route::post('/id/requests/{idRequest}/reissue', [IDController::class, 'reissue'])->name('id.reissue');
    Route::post('/id/requests/{idRequest}/cancel', [IDController::class, 'cancel'])->name('id.cancel');

    /* ==================== Admin / Reference Data ==================== */
    Route::get('/admin/reference-data', [ReferenceDataController::class, 'index'])->name('admin.reference-data.index');

    // Courses
    Route::get('/admin/reference-data/courses', [ReferenceDataController::class, 'courses'])->name('admin.reference-data.courses');
    Route::post('/admin/reference-data/courses', [ReferenceDataController::class, 'storeCourse'])->name('admin.reference-data.courses.store');
    Route::patch('/admin/reference-data/courses/{course}', [ReferenceDataController::class, 'updateCourse'])->name('admin.reference-data.courses.update');
    Route::delete('/admin/reference-data/courses/{course}', [ReferenceDataController::class, 'destroyCourse'])->name('admin.reference-data.courses.destroy');

    // Majors
    Route::get('/admin/reference-data/majors', [ReferenceDataController::class, 'majors'])->name('admin.reference-data.majors');
    Route::post('/admin/reference-data/majors', [ReferenceDataController::class, 'storeMajor'])->name('admin.reference-data.majors.store');
    Route::patch('/admin/reference-data/majors/{major}', [ReferenceDataController::class, 'updateMajor'])->name('admin.reference-data.majors.update');
    Route::delete('/admin/reference-data/majors/{major}', [ReferenceDataController::class, 'destroyMajor'])->name('admin.reference-data.majors.destroy');

    // Curriculums
    Route::get('/admin/reference-data/curriculums', [ReferenceDataController::class, 'curriculums'])->name('admin.reference-data.curriculums');
    Route::post('/admin/reference-data/curriculums', [ReferenceDataController::class, 'storeCurriculum'])->name('admin.reference-data.curriculums.store');
    Route::patch('/admin/reference-data/curriculums/{curriculum}', [ReferenceDataController::class, 'updateCurriculum'])->name('admin.reference-data.curriculums.update');
    Route::delete('/admin/reference-data/curriculums/{curriculum}', [ReferenceDataController::class, 'destroyCurriculum'])->name('admin.reference-data.curriculums.destroy');

    // Curriculum Subjects
    Route::get('/admin/reference-data/curriculums/{curriculum}/subjects', [ReferenceDataController::class, 'curriculumSubjects'])->name('admin.reference-data.curriculum-subjects');
    Route::post('/admin/reference-data/curriculums/{curriculum}/subjects', [ReferenceDataController::class, 'storeCurriculumSubject'])->name('admin.reference-data.curriculum-subjects.store');
    Route::patch('/admin/reference-data/curriculum-subjects/{cs}', [ReferenceDataController::class, 'updateCurriculumSubject'])->name('admin.reference-data.curriculum-subjects.update');
    Route::delete('/admin/reference-data/curriculum-subjects/{cs}', [ReferenceDataController::class, 'destroyCurriculumSubject'])->name('admin.reference-data.curriculum-subjects.destroy');

    // Subjects
    Route::get('/admin/reference-data/subjects', [ReferenceDataController::class, 'subjects'])->name('admin.reference-data.subjects');
    Route::post('/admin/reference-data/subjects', [ReferenceDataController::class, 'storeSubject'])->name('admin.reference-data.subjects.store');
    Route::patch('/admin/reference-data/subjects/{subject}', [ReferenceDataController::class, 'updateSubject'])->name('admin.reference-data.subjects.update');
    Route::delete('/admin/reference-data/subjects/{subject}', [ReferenceDataController::class, 'destroySubject'])->name('admin.reference-data.subjects.destroy');

    // Terms
    Route::get('/admin/reference-data/terms', [ReferenceDataController::class, 'terms'])->name('admin.reference-data.terms');
    Route::post('/admin/reference-data/terms', [ReferenceDataController::class, 'storeTerm'])->name('admin.reference-data.terms.store');
    Route::patch('/admin/reference-data/terms/{term}', [ReferenceDataController::class, 'updateTerm'])->name('admin.reference-data.terms.update');
    Route::delete('/admin/reference-data/terms/{term}', [ReferenceDataController::class, 'destroyTerm'])->name('admin.reference-data.terms.destroy');

    // Fee Types
    Route::get('/admin/reference-data/fee-types', [ReferenceDataController::class, 'feeTypes'])->name('admin.reference-data.fee-types');
    Route::post('/admin/reference-data/fee-types', [ReferenceDataController::class, 'storeFeeType'])->name('admin.reference-data.fee-types.store');
    Route::patch('/admin/reference-data/fee-types/{feeType}', [ReferenceDataController::class, 'updateFeeType'])->name('admin.reference-data.fee-types.update');
    Route::delete('/admin/reference-data/fee-types/{feeType}', [ReferenceDataController::class, 'destroyFeeType'])->name('admin.reference-data.fee-types.destroy');

    // Scholarship Types
    Route::get('/admin/reference-data/scholarship-types', [ReferenceDataController::class, 'scholarshipTypes'])->name('admin.reference-data.scholarship-types');
    Route::post('/admin/reference-data/scholarship-types', [ReferenceDataController::class, 'storeScholarshipType'])->name('admin.reference-data.scholarship-types.store');
    Route::patch('/admin/reference-data/scholarship-types/{type}', [ReferenceDataController::class, 'updateScholarshipType'])->name('admin.reference-data.scholarship-types.update');
    Route::delete('/admin/reference-data/scholarship-types/{type}', [ReferenceDataController::class, 'destroyScholarshipType'])->name('admin.reference-data.scholarship-types.destroy');

    // Offices
    Route::get('/admin/reference-data/offices', [ReferenceDataController::class, 'offices'])->name('admin.reference-data.offices');
    Route::post('/admin/reference-data/offices', [ReferenceDataController::class, 'storeOffice'])->name('admin.reference-data.offices.store');
    Route::patch('/admin/reference-data/offices/{office}', [ReferenceDataController::class, 'updateOffice'])->name('admin.reference-data.offices.update');
    Route::delete('/admin/reference-data/offices/{office}', [ReferenceDataController::class, 'destroyOffice'])->name('admin.reference-data.offices.destroy');

    // Rooms
    Route::get('/admin/reference-data/rooms', [ReferenceDataController::class, 'rooms'])->name('admin.reference-data.rooms');
    Route::post('/admin/reference-data/rooms', [ReferenceDataController::class, 'storeRoom'])->name('admin.reference-data.rooms.store');
    Route::patch('/admin/reference-data/rooms/{room}', [ReferenceDataController::class, 'updateRoom'])->name('admin.reference-data.rooms.update');
    Route::delete('/admin/reference-data/rooms/{room}', [ReferenceDataController::class, 'destroyRoom'])->name('admin.reference-data.rooms.destroy');

    // Blocks
    Route::get('/admin/reference-data/blocks', [ReferenceDataController::class, 'blocks'])->name('admin.reference-data.blocks');
    Route::post('/admin/reference-data/blocks', [ReferenceDataController::class, 'storeBlock'])->name('admin.reference-data.blocks.store');
    Route::patch('/admin/reference-data/blocks/{block}', [ReferenceDataController::class, 'updateBlock'])->name('admin.reference-data.blocks.update');
    Route::delete('/admin/reference-data/blocks/{block}', [ReferenceDataController::class, 'destroyBlock'])->name('admin.reference-data.blocks.destroy');

    // Admission Requirements
    Route::get('/admin/reference-data/admission-requirements', [ReferenceDataController::class, 'admissionRequirements'])->name('admin.reference-data.admission-requirements');
    Route::post('/admin/reference-data/admission-requirements', [ReferenceDataController::class, 'storeAdmissionRequirement'])->name('admin.reference-data.admission-requirements.store');
    Route::patch('/admin/reference-data/admission-requirements/{req}', [ReferenceDataController::class, 'updateAdmissionRequirement'])->name('admin.reference-data.admission-requirements.update');
    Route::delete('/admin/reference-data/admission-requirements/{req}', [ReferenceDataController::class, 'destroyAdmissionRequirement'])->name('admin.reference-data.admission-requirements.destroy');

    // Clearance Requirements
    Route::get('/admin/reference-data/clearance-requirements', [ReferenceDataController::class, 'clearanceRequirements'])->name('admin.reference-data.clearance-requirements');
    Route::post('/admin/reference-data/clearance-requirements', [ReferenceDataController::class, 'storeClearanceRequirement'])->name('admin.reference-data.clearance-requirements.store');
    Route::delete('/admin/reference-data/clearance-requirements/{req}', [ReferenceDataController::class, 'destroyClearanceRequirement'])->name('admin.reference-data.clearance-requirements.destroy');

    /* ==================== Admin / User Management ==================== */
    Route::get('/admin/users', [UserManagementController::class, 'index'])->name('admin.users.index');
    Route::post('/admin/users', [UserManagementController::class, 'store'])->name('admin.users.store');
    Route::patch('/admin/users/{user}', [UserManagementController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [UserManagementController::class, 'destroy'])->name('admin.users.destroy');
    Route::post('/admin/users/{user}/roles', [UserManagementController::class, 'assignRoles'])->name('admin.users.roles.assign');
    Route::post('/admin/users/{user}/toggle-status', [UserManagementController::class, 'toggleStatus'])->name('admin.users.status.toggle');
    Route::get('/admin/users/roles', [UserManagementController::class, 'roles'])->name('admin.users.roles');
    Route::post('/admin/users/roles', [UserManagementController::class, 'storeRole'])->name('admin.users.roles.store');
    Route::patch('/admin/users/roles/{role}', [UserManagementController::class, 'updateRole'])->name('admin.users.roles.update');
    Route::delete('/admin/users/roles/{role}', [UserManagementController::class, 'destroyRole'])->name('admin.users.roles.destroy');
    Route::get('/admin/users/permissions', [UserManagementController::class, 'permissions'])->name('admin.users.permissions');
    Route::post('/admin/users/permissions', [UserManagementController::class, 'storePermission'])->name('admin.users.permissions.store');
    Route::patch('/admin/users/permissions/{permission}', [UserManagementController::class, 'updatePermission'])->name('admin.users.permissions.update');
    Route::delete('/admin/users/permissions/{permission}', [UserManagementController::class, 'destroyPermission'])->name('admin.users.permissions.destroy');
    Route::get('/admin/users/settings', [UserManagementController::class, 'settings'])->name('admin.users.settings');
    Route::patch('/admin/users/settings/{setting}', [UserManagementController::class, 'updateSetting'])->name('admin.users.settings.update');
    Route::get('/admin/users/audit-logs', [UserManagementController::class, 'auditLogs'])->name('admin.users.audit-logs');

    /* ==================== Student 360 ==================== */
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');

    /* ==================== Notifications ==================== */
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

});

require __DIR__.'/auth.php';
