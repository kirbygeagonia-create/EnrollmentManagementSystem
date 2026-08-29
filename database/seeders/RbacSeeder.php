<?php

namespace Database\Seeders;

use App\Models\Staffusers;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RbacSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ===========================================
        // 1. CREATE ALL PERMISSIONS (grouped by module)
        // ===========================================
        $permissions = [
            // Admission module (8)
            'admission' => [
                'admission.view',
                'admission.create',
                'admission.update',
                'admission.approve',
                'admission.reject',
                'admission.delete',
                'admission.requirements.submit',
                'admission.requirements.verify',
            ],

            // Blocking module (6)
            'blocking' => [
                'block.view',
                'block.manage',
                'block.assign',
                'block.schedules.manage',
                'block.capacity.check',
                'print.blockSchedule',
            ],

            // Assessment module (5)
            'assessment' => [
                'assessment.view',
                'assessment.compute',
                'assessment.scholarships.apply',
                'assessment.charges.adjust',
                'assessment.finalize',
            ],

            // Clearance module (6)
            'clearance' => [
                'clearance.view',
                'clearance.periods.manage',
                'clearance.slip.generate',
                'clearance.receipt.record',
                'clearance.approve',
                'clearance.slip.replace',
            ],

            // Clinic module (5)
            'clinic' => [
                'clinic.view',
                'clinic.record',
                'clinic.update',
                'clinic.sign',
                'clinic.reopen',
            ],

            // Evaluation module (10)
            'evaluation' => [
                'evaluation.view',
                'evaluation.create',
                'evaluation.profile.capture',
                'evaluation.profile.capture.any',
                'evaluation.subjects.propose',
                'evaluation.subjects.propose.any',
                'evaluation.credits.process',
                'evaluation.sign',
                'evaluation.sign.dean',
                'enrollment.subjects.confirm',
            ],

            // Exam module (5)
            'exam' => [
                'exam.view',
                'exam.record.general',
                'exam.record.courseSpecific',
                'exam.record.retention',
                'exam.verify.general',
            ],

            // ID module (8)
            'id' => [
                'id.view',
                'id.request.create',
                'id.card.produce',
                'id.validate',
                'id.release',
                'id.sign',
                'id.reissue',
                'id.cancel',
            ],

            // Payment module (4)
            'payment' => [
                'payment.view',
                'payment.record',
                'payment.void',
                'payment.report.daily',
            ],

            // Reference Data module (14)
            'refdata' => [
                'refdata.view',
                'refdata.courses.manage',
                'refdata.majors.manage',
                'refdata.curriculums.manage',
                'refdata.curriculumSubjects.manage',
                'refdata.subjects.manage',
                'refdata.terms.manage',
                'refdata.feeTypes.manage',
                'refdata.scholarshipTypes.manage',
                'refdata.offices.manage',
                'refdata.rooms.manage',
                'refdata.blocks.manage',
                'refdata.admissionRequirements.manage',
                'refdata.clearanceRequirements.manage',
            ],

            // Enrollment/Registrar module (5)
            'enrollment' => [
                'enrollment.approve',
                'print.certificate',
                'print.classCard',
                'print.subjectLoad',
                'enrollment.studentdata.record',
            ],

            // User Management module (12)
            'user' => [
                'user.view',
                'user.create',
                'user.update',
                'user.update.any',
                'user.delete',
                'user.delete.any',
                'user.roles.assign',
                'user.roles.manage',
                'user.permissions.manage',
                'user.status.toggle',
                'audit.view',
                'settings.manage',
            ],

            // Dashboard module (1)
            'dashboard' => [
                'dashboard.view',
            ],
        ];

        $createdPermissions = [];
        foreach ($permissions as $module => $perms) {
            foreach ($perms as $permName) {
                $permission = Permission::firstOrCreate(
                    ['name' => $permName, 'guard_name' => 'web'],
                    ['module' => $module]
                );
                $createdPermissions[$permName] = $permission;
            }
        }

        $this->command->info('Created '.count($createdPermissions).' permissions across '.count($permissions).' modules.');

        // ===========================================
        // 2. CREATE ALL 13 DESK ROLES AND ASSIGN PERMISSIONS
        // ===========================================

        // SysAdmin - ALL permissions
        $sysAdmin = Role::firstOrCreate(
            ['name' => 'SysAdmin', 'guard_name' => 'web'],
            ['description' => 'System Administrator with full access to all modules']
        );
        $sysAdmin->syncPermissions(array_keys($createdPermissions));

        // Admin - ALL permissions
        $admin = Role::firstOrCreate(
            ['name' => 'Admin', 'guard_name' => 'web'],
            ['description' => 'Administrator with full access to all modules']
        );
        $admin->syncPermissions(array_keys($createdPermissions));

        // AdmissionOfficer - Phase 0
        $admissionOfficer = Role::firstOrCreate(
            ['name' => 'AdmissionOfficer', 'guard_name' => 'web'],
            ['description' => 'Admission Officer with intake, requirement verification, and qualification permissions']
        );
        $admissionOfficer->syncPermissions([
            'admission.view', 'admission.create', 'admission.update', 'admission.approve', 'admission.reject',
            'admission.requirements.submit', 'admission.requirements.verify', 'dashboard.view', 'user.view',
        ]);

        // GuidanceStaff - Phase 0.5 & Retention
        $guidanceStaff = Role::firstOrCreate(
            ['name' => 'GuidanceStaff', 'guard_name' => 'web'],
            ['description' => 'Guidance counselor and entrance/retention exam scoring staff']
        );
        $guidanceStaff->syncPermissions([
            'exam.view', 'exam.record.general', 'exam.record.courseSpecific', 'exam.record.retention', 'exam.verify.general',
            'dashboard.view', 'user.view',
        ]);

        // DeptEvaluator - Phase 2
        $deptEvaluator = Role::firstOrCreate(
            ['name' => 'DeptEvaluator', 'guard_name' => 'web'],
            ['description' => 'Academic department evaluator for profile capture, curriculum subject proposals, and transfer credits']
        );
        $deptEvaluator->syncPermissions([
            'evaluation.view', 'evaluation.create', 'evaluation.profile.capture', 'evaluation.profile.capture.any',
            'evaluation.subjects.propose', 'evaluation.subjects.propose.any', 'evaluation.credits.process',
            'evaluation.sign', 'exam.view', 'admission.view', 'dashboard.view', 'user.view', 'enrollment.subjects.confirm',
        ]);

        // Dean - Phase 2 & Academic Department Head
        $dean = Role::firstOrCreate(
            ['name' => 'Dean', 'guard_name' => 'web'],
            ['description' => 'College Dean with evaluation sign-off, curriculum review, and enrollment confirmation']
        );
        $dean->syncPermissions([
            'admission.view', 'admission.create', 'admission.update', 'admission.approve', 'admission.reject',
            'admission.requirements.submit', 'admission.requirements.verify',
            'evaluation.view', 'evaluation.create', 'evaluation.profile.capture', 'evaluation.profile.capture.any',
            'evaluation.subjects.propose', 'evaluation.subjects.propose.any', 'evaluation.credits.process',
            'evaluation.sign', 'evaluation.sign.dean',
            'exam.view', 'refdata.view', 'user.view', 'dashboard.view', 'enrollment.subjects.confirm',
        ]);

        // ProgramHead - Phase 2
        $programHead = Role::firstOrCreate(
            ['name' => 'ProgramHead', 'guard_name' => 'web'],
            ['description' => 'Program Head with evaluation, subject proposal, and confirmation permissions']
        );
        $programHead->syncPermissions([
            'admission.view', 'evaluation.view', 'evaluation.create', 'evaluation.credits.process',
            'evaluation.subjects.propose', 'evaluation.sign', 'exam.view', 'dashboard.view', 'enrollment.subjects.confirm',
        ]);

        // ScholarshipOfficer - Phase 3
        $scholarshipOfficer = Role::firstOrCreate(
            ['name' => 'ScholarshipOfficer', 'guard_name' => 'web'],
            ['description' => 'Scholarship & Assessment officer for grant verification and fee computation']
        );
        $scholarshipOfficer->syncPermissions([
            'assessment.view', 'assessment.compute', 'assessment.scholarships.apply', 'assessment.charges.adjust',
            'assessment.finalize', 'dashboard.view', 'user.view',
        ]);

        // AccountingStaff - Phase 4
        $accountingStaff = Role::firstOrCreate(
            ['name' => 'AccountingStaff', 'guard_name' => 'web'],
            ['description' => 'Cashier and accounting staff for payment collection, OR recording, and daily collection reports']
        );
        $accountingStaff->syncPermissions([
            'payment.view', 'payment.record', 'payment.void', 'payment.report.daily',
            'assessment.view', 'dashboard.view', 'user.view',
        ]);

        // RegistrarDesk - Phase 1
        $registrarDesk = Role::firstOrCreate(
            ['name' => 'RegistrarDesk', 'guard_name' => 'web'],
            ['description' => 'Registrar desk staff for clearance receipt recording and student verification']
        );
        $registrarDesk->syncPermissions([
            'clearance.view', 'clearance.receipt.record', 'clearance.slip.generate', 'dashboard.view', 'user.view',
        ]);

        // RegistrarApprover - Phase 5
        $registrarApprover = Role::firstOrCreate(
            ['name' => 'RegistrarApprover', 'guard_name' => 'web'],
            ['description' => 'Registrar officer for final enrollment approval, subject confirmation, certificate and class card printing']
        );
        $registrarApprover->syncPermissions([
            'enrollment.approve', 'print.certificate', 'print.classCard', 'print.subjectLoad', 'enrollment.studentdata.record',
            'clearance.view', 'payment.view', 'evaluation.view', 'assessment.view', 'dashboard.view', 'user.view',
        ]);

        // BlockingCoordinator - Phase 6
        $blockingCoordinator = Role::firstOrCreate(
            ['name' => 'BlockingCoordinator', 'guard_name' => 'web'],
            ['description' => 'Blocking coordinator for block section assignment, schedule management, and capacity verification']
        );
        $blockingCoordinator->syncPermissions([
            'block.view', 'block.manage', 'block.assign', 'block.schedules.manage', 'block.capacity.check',
            'print.blockSchedule', 'dashboard.view', 'user.view',
        ]);

        // ClinicStaff - Phase 7
        $clinicStaff = Role::firstOrCreate(
            ['name' => 'ClinicStaff', 'guard_name' => 'web'],
            ['description' => 'School clinic health assessment and PhilHealth registration staff']
        );
        $clinicStaff->syncPermissions([
            'clinic.view', 'clinic.record', 'clinic.update', 'clinic.sign', 'clinic.reopen',
            'dashboard.view', 'user.view',
        ]);

        // IdOfficer - Phase 8
        $idOfficer = Role::firstOrCreate(
            ['name' => 'IdOfficer', 'guard_name' => 'web'],
            ['description' => 'ID Office staff for ID requests, photo validation, QR generation, and card release']
        );
        $idOfficer->syncPermissions([
            'id.view', 'id.request.create', 'id.card.produce', 'id.validate', 'id.release', 'id.sign', 'id.reissue', 'id.cancel',
            'dashboard.view', 'user.view',
        ]);

        // OfficeHead - all view permissions + module action permissions (excluding admin-only)
        $officeHead = Role::firstOrCreate(
            ['name' => 'OfficeHead', 'guard_name' => 'web'],
            ['description' => 'Office Head with all view permissions and full module action permissions']
        );
        $officeHead->syncPermissions([
            'admission.view', 'exam.view', 'evaluation.view', 'assessment.view',
            'payment.view', 'clearance.view', 'enrollment.approve', 'block.view',
            'clinic.view', 'id.view', 'refdata.view', 'user.view', 'audit.view', 'dashboard.view',
            'block.manage', 'block.assign', 'block.schedules.manage',
            'clearance.periods.manage', 'clearance.slip.generate',
            'clearance.receipt.record', 'clearance.approve',
            'clinic.record', 'clinic.update', 'clinic.sign', 'clinic.reopen',
            'id.request.create', 'id.card.produce', 'id.validate', 'id.release', 'id.sign', 'id.reissue', 'id.cancel',
            'payment.record', 'payment.report.daily',
            'assessment.compute', 'assessment.finalize',
            'exam.record.general', 'exam.record.courseSpecific', 'exam.record.retention', 'exam.verify.general',
            'evaluation.create', 'evaluation.profile.capture', 'evaluation.subjects.propose', 'evaluation.credits.process', 'evaluation.sign',
            'admission.create', 'admission.update', 'admission.approve', 'admission.reject', 'admission.requirements.submit', 'admission.requirements.verify',
            'print.certificate', 'print.classCard', 'print.subjectLoad', 'enrollment.studentdata.record',
        ]);

        // Staff - view permissions
        $staff = Role::firstOrCreate(
            ['name' => 'Staff', 'guard_name' => 'web'],
            ['description' => 'Staff with view-only access across all modules']
        );
        $staff->syncPermissions([
            'admission.view', 'exam.view', 'evaluation.view', 'assessment.view',
            'payment.view', 'clearance.view', 'block.view', 'clinic.view',
            'id.view', 'refdata.view', 'user.view', 'audit.view', 'dashboard.view',
        ]);

        // Instructor - Phase 2 & Advising
        $instructor = Role::firstOrCreate(
            ['name' => 'Instructor', 'guard_name' => 'web'],
            ['description' => 'College Faculty Instructor with evaluation, subject proposal, and schedule viewing permissions']
        );
        $instructor->syncPermissions([
            'evaluation.view', 'evaluation.create', 'evaluation.subjects.propose', 'evaluation.profile.capture',
            'print.classCard', 'print.subjectLoad', 'block.view', 'dashboard.view', 'user.view',
        ]);

        $this->command->info('Created all 14 functional desk and faculty roles with module permissions.');

        // ===========================================
        // 3. ASSIGN REALISTIC DESK ROLES TO STAFF USERS
        // ===========================================
        foreach (Staffusers::all() as $user) {
            $rolesToSync = [];
            $roleVal = $user->role?->value ?? 'staff';

            if ($roleVal === 'admin') {
                $rolesToSync = ['SysAdmin', 'Admin'];
            } elseif ($roleVal === 'dean') {
                $rolesToSync = ['Dean', 'DeptEvaluator'];
            } elseif ($roleVal === 'programHead') {
                $rolesToSync = ['ProgramHead', 'DeptEvaluator'];
            } elseif ($roleVal === 'instructor') {
                $rolesToSync = ['Instructor', 'DeptEvaluator'];
            } elseif ($roleVal === 'officeHead') {
                $rolesToSync = match ($user->officeId) {
                    1 => ['RegistrarApprover', 'OfficeHead'],
                    2 => ['AccountingStaff', 'OfficeHead'],
                    3 => ['ScholarshipOfficer', 'OfficeHead'],
                    4 => ['GuidanceStaff', 'OfficeHead'],
                    5 => ['BlockingCoordinator', 'OfficeHead'],
                    6 => ['AdmissionOfficer', 'OfficeHead'],
                    7 => ['OfficeHead'],
                    11 => ['ClinicStaff', 'OfficeHead'],
                    22 => ['IdOfficer', 'OfficeHead'],
                    default => ['OfficeHead'],
                };
            } else { // regular staff
                $rolesToSync = match ($user->officeId) {
                    1 => ['RegistrarDesk', 'Staff'],
                    2 => ['AccountingStaff', 'Staff'],
                    3 => ['ScholarshipOfficer', 'Staff'],
                    4 => ['GuidanceStaff', 'Staff'],
                    5 => ['BlockingCoordinator', 'Staff'],
                    6 => ['AdmissionOfficer', 'Staff'],
                    7 => ['Staff'],
                    11 => ['ClinicStaff', 'Staff'],
                    22 => ['IdOfficer', 'Staff'],
                    default => ['Staff'],
                };
            }

            $user->syncRoles($rolesToSync);
        }

        $this->command->info('RbacSeeder completed successfully!');
    }
}
