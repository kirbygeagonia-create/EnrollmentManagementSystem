<?php

namespace Database\Seeders;

use App\Models\Offices;
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

            // Clinic module (4)
            'clinic' => [
                'clinic.view',
                'clinic.record',
                'clinic.update',
                'clinic.sign',
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

            // ID module (6)
            'id' => [
                'id.view',
                'id.request.create',
                'id.card.produce',
                'id.validate',
                'id.release',
                'id.sign',
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
        // 2. CREATE ROLES AND ASSIGN PERMISSIONS
        // ===========================================

        // SysAdmin - ALL permissions
        $sysAdmin = Role::firstOrCreate(
            ['name' => 'SysAdmin', 'guard_name' => 'web'],
            ['description' => 'System Administrator with full access to all modules']
        );
        $sysAdmin->syncPermissions(array_keys($createdPermissions));
        $this->command->info('Role "SysAdmin" created with '.$sysAdmin->permissions->count().' permissions.');

        // Admin - ALL permissions (mirror of SysAdmin; role enum 'admin' maps here)
        $admin = Role::firstOrCreate(
            ['name' => 'Admin', 'guard_name' => 'web'],
            ['description' => 'Administrator with full access to all modules']
        );
        $admin->syncPermissions(array_keys($createdPermissions));
        $this->command->info('Role "Admin" created with '.$admin->permissions->count().' permissions.');

        // Dean - specific permissions
        $dean = Role::firstOrCreate(
            ['name' => 'Dean', 'guard_name' => 'web'],
            ['description' => 'Dean with access to admission, evaluation, exam, refdata, user view, dashboard, enrollment confirmation']
        );
        $deanPerms = [
            'admission.view', 'admission.create', 'admission.update', 'admission.approve', 'admission.reject',
            'admission.requirements.submit', 'admission.requirements.verify',
            'evaluation.view', 'evaluation.create', 'evaluation.profile.capture', 'evaluation.profile.capture.any',
            'evaluation.subjects.propose', 'evaluation.subjects.propose.any', 'evaluation.credits.process',
            'evaluation.sign', 'evaluation.sign.dean',
            'exam.view',
            'refdata.view',
            'user.view',
            'dashboard.view',
            'enrollment.subjects.confirm',
        ];
        $dean->syncPermissions($deanPerms);
        $this->command->info('Role "Dean" created with '.$dean->permissions->count().' permissions.');

        // ProgramHead - specific permissions
        $programHead = Role::firstOrCreate(
            ['name' => 'ProgramHead', 'guard_name' => 'web'],
            ['description' => 'Program Head with access to admission view, evaluation, exam view, dashboard, enrollment confirmation']
        );
        $programHeadPerms = [
            'admission.view',
            'evaluation.view', 'evaluation.create', 'evaluation.credits.process',
            'evaluation.subjects.propose', 'evaluation.sign',
            'exam.view',
            'dashboard.view',
            'enrollment.subjects.confirm',
        ];
        $programHead->syncPermissions($programHeadPerms);
        $this->command->info('Role "ProgramHead" created with '.$programHead->permissions->count().' permissions.');

        // OfficeHead - all view permissions + module action permissions
        $officeHead = Role::firstOrCreate(
            ['name' => 'OfficeHead', 'guard_name' => 'web'],
            ['description' => 'Office Head with all view permissions and full module action permissions']
        );
        $officeHeadPerms = [
            // All view permissions
            'admission.view', 'exam.view', 'evaluation.view', 'assessment.view',
            'payment.view', 'clearance.view', 'enrollment.approve', 'block.view',
            'clinic.view', 'id.view', 'refdata.view', 'user.view', 'audit.view', 'dashboard.view',
            // Module action permissions
            'block.manage', 'block.assign', 'block.schedules.manage',
            'clearance.periods.manage', 'clearance.slip.generate',
            'clearance.receipt.record', 'clearance.approve',
            'clinic.record', 'clinic.update', 'clinic.sign',
            'id.request.create', 'id.card.produce', 'id.validate', 'id.release', 'id.sign',
            'payment.record', 'payment.report.daily',
            'assessment.compute', 'assessment.finalize',
            'exam.record.general', 'exam.record.courseSpecific', 'exam.record.retention', 'exam.verify.general',
            'evaluation.create', 'evaluation.profile.capture', 'evaluation.subjects.propose', 'evaluation.credits.process', 'evaluation.sign',
            'admission.create', 'admission.approve', 'admission.reject', 'admission.requirements.submit', 'admission.requirements.verify',
            'enrollment.approve', 'print.certificate', 'print.classCard', 'print.subjectLoad', 'enrollment.studentdata.record',
        ];
        $officeHead->syncPermissions($officeHeadPerms);
        $this->command->info('Role "OfficeHead" created with '.$officeHead->permissions->count().' permissions.');

        // Staff - view permissions only
        $staff = Role::firstOrCreate(
            ['name' => 'Staff', 'guard_name' => 'web'],
            ['description' => 'Staff with view-only access across all modules']
        );
        $staffPerms = [
            'admission.view', 'exam.view', 'evaluation.view', 'assessment.view',
            'payment.view', 'clearance.view', 'block.view', 'clinic.view',
            'id.view', 'refdata.view', 'user.view', 'audit.view', 'dashboard.view',
        ];
        $staff->syncPermissions($staffPerms);
        $this->command->info('Role "Staff" created with '.$staff->permissions->count().' permissions.');

        // ===========================================
        // 3. ASSIGN SysAdmin/Admin ROLES TO ADMIN USERS
        // ===========================================
        $adminUsers = Staffusers::where('role', 'admin')->get();
        foreach ($adminUsers as $user) {
            // Assign both SysAdmin and Admin roles (SysAdmin triggers Gate::before)
            $user->assignRole('SysAdmin');
            $user->assignRole('Admin');
            $this->command->info("Assigned SysAdmin and Admin roles to user: {$user->username} (ID: {$user->userId})");
        }

        // ===========================================
        // 4. CREATE MISSING OFFICES
        // ===========================================
        $offices = [
            1 => 'System Administration', // Keep existing
            2 => 'Accounting Office',
            3 => 'Assessment Office',
            4 => 'Department Evaluation',
            5 => 'Blocking and Scheduling',
            6 => 'Admission Office',
            7 => 'Guidance / Entrance Exam',
            8 => 'Clearance Office',
            11 => 'Clinic',
            22 => 'ID Office',
        ];

        foreach ($offices as $officeId => $officeName) {
            $office = Offices::firstOrCreate(
                ['officeId' => $officeId],
                ['officeName' => $officeName]
            );
            $this->command->info("Office ensured: ID={$office->officeId}, Name={$office->officeName}");
        }

        // ===========================================
        // 5. POPULATE role_permissions PIVOT FOR UI DISPLAY
        // ===========================================
        // The custom pivot table 'role_permissions' is used by UserManagementController
        // Spatie's syncPermissions already handles role_has_permissions (which maps to role_permissions per config)
        // So this is already done via the syncPermissions calls above.
        $this->command->info('Role-permission pivot (role_permissions) populated via Spatie syncPermissions.');

        $this->command->info('RbacSeeder completed successfully!');
    }
}
