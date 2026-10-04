<?php

namespace Database\Seeders;

use App\Enums\OfficeId;
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
                // Ruling 15: taking a grant back is its own act, with its own reason.
                'assessment.scholarships.withdraw',
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

            // Evaluation module (11)
            'evaluation' => [
                'evaluation.view',
                'evaluation.create',
                'evaluation.profile.capture',
                'evaluation.profile.capture.any',
                'evaluation.subjects.propose',
                'evaluation.subjects.propose.any',
                'evaluation.credits.process',
                'evaluation.clearance.confirm',
                'evaluation.sign',
                'evaluation.sign.dean',
                'enrollment.subjects.confirm',
            ],

            // Shift requests module (3) — G-7, ruling 11. A SEAIT student changing
            // program is not an applicant, so the paper moves on its own rights:
            // filed at Academic Department Evaluation, endorsed by the dean or program
            // head, decided by the Guidance Councillor.
            'shift' => [
                'shift.request.create',
                'shift.sign.department',
                'shift.grant',
            ],

            // Exam module (5)
            'exam' => [
                'exam.view',
                'exam.record.general',
                'exam.record.courseSpecific',
                'exam.record.retention',
                'exam.verify.general',
            ],

            // ID module (4) — validation-only flow (card-making removed)
            'id' => [
                'id.view',
                'id.request.create',
                'id.validate',
                'id.sign',
            ],

            // Payment module (4)
            'payment' => [
                'payment.view',
                'payment.record',
                'payment.void',
                'payment.refund',
                'payment.report.daily',
            ],

            // Reference Data module (15)
            'refdata' => [
                'refdata.view',
                'refdata.courses.manage',
                'refdata.majors.manage',
                'refdata.curriculums.manage',
                'refdata.curriculumSubjects.manage',
                'refdata.subjects.manage',
                'refdata.terms.manage',
                'refdata.feeTypes.manage',
                'refdata.gradeScale.manage',
                'refdata.scholarshipTypes.manage',
                'refdata.offices.manage',
                'refdata.rooms.manage',
                'refdata.blocks.manage',
                'refdata.admissionRequirements.manage',
                'refdata.clearanceRequirements.manage',
            ],

            // Enrollment/Registrar module (6)
            'enrollment' => [
                'enrollment.approve',
                'enrollment.drop',
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

            // Student directory module (1) — Student 360 / global quick-search
            'students' => [
                'students.view',
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

        // Retire permissions this seeder no longer declares (e.g. `id.release`,
        // dropped when card production and handover were removed). Without
        // this, an old row survives a reseed and still appears in the Roles
        // permission matrix, where nothing can grant it meaningfully again.
        $obsolete = Permission::where('guard_name', 'web')
            ->whereNotIn('name', array_keys($createdPermissions))
            ->pluck('name');
        if ($obsolete->isNotEmpty()) {
            Permission::where('guard_name', 'web')->whereNotIn('name', array_keys($createdPermissions))->delete();
            $this->command->info('Retired '.$obsolete->count().' obsolete permission(s): '.$obsolete->implode(', '));
        }

        // ===========================================
        // 2. CREATE ALL DESK AND FACULTY ROLES AND ASSIGN PERMISSIONS
        // ===========================================

        // SysAdmin - ALL permissions
        $sysAdmin = Role::firstOrCreate(
            ['name' => 'SysAdmin', 'guard_name' => 'web'],
            ['description' => 'System Administrator with full access to all modules']
        );
        $sysAdmin->syncPermissions(array_keys($createdPermissions));

        // Admin - ALL permissions
        // Item 3 write-boundary: Admin is read-everywhere — it holds every
        // view permission but NO mutation permissions, so it can oversee all
        // desks without directly touching other offices' records. SysAdmin
        // remains the only full-power role. Any write an Admin account makes
        // is flagged `adminOverride` by the AuditLogObserver.
        $admin = Role::firstOrCreate(
            ['name' => 'Admin', 'guard_name' => 'web'],
            ['description' => 'Institutional overseer with read-everywhere access (no direct record mutation)']
        );
        $admin->syncPermissions(
            collect($createdPermissions)
                ->filter(fn ($p) => str_ends_with($p->name, '.view'))
                ->pluck('name')
                ->all()
        );

        // AdmissionOfficer - Phase 0
        $admissionOfficer = Role::firstOrCreate(
            ['name' => 'AdmissionOfficer', 'guard_name' => 'web'],
            ['description' => 'Admission Officer with intake, requirement verification, and qualification permissions']
        );
        $admissionOfficer->syncPermissions([
            'admission.view', 'admission.create', 'admission.update', 'admission.approve', 'admission.reject',
            'admission.requirements.submit', 'admission.requirements.verify', 'dashboard.view', 'user.view',
            'students.view',
        ]);

        // GuidanceStaff - Item 4: the School Entrance Examination ONLY. Guidance
        // records it, sees all of its results (pass and failed), and transfers
        // the passers to the academic departments. Course-specific and retention
        // exams are handled and viewed only by the owning department.
        $guidanceStaff = Role::firstOrCreate(
            ['name' => 'GuidanceStaff', 'guard_name' => 'web'],
            ['description' => 'Guidance counselor — School Entrance Examination scoring (Stage 1, BR9): all results, passer transfer']
        );
        $guidanceStaff->syncPermissions([
            'exam.view', 'exam.record.general', 'exam.verify.general',
            // Ruling 11: the Guidance Councillor's signature is the final call on a shift.
            'shift.grant',
            'dashboard.view', 'user.view', 'students.view',
        ]);

        // DeptEvaluator - Phase 2. Item 4: the owning academic department also
        // handles and views the course-specific entrance exam (BR9 Stage 2) and
        // the retention exam (BR10 — recorded in the Academic Evaluation area).
        $deptEvaluator = Role::firstOrCreate(
            ['name' => 'DeptEvaluator', 'guard_name' => 'web'],
            ['description' => 'Academic department evaluator — profile capture, subject proposals, transfer credits, course-specific and retention exams']
        );
        $deptEvaluator->syncPermissions([
            'evaluation.view', 'evaluation.create', 'evaluation.profile.capture', 'evaluation.profile.capture.any',
            'evaluation.subjects.propose', 'evaluation.subjects.propose.any', 'evaluation.credits.process',
            'evaluation.sign', 'exam.view', 'exam.record.courseSpecific', 'exam.record.retention',
            // Ruling 5: the desk that takes the student is the desk that sees the pass slip.
            'evaluation.clearance.confirm',
            // Ruling 11: the shift paper is filed at this desk.
            'shift.request.create',
            'admission.view', 'dashboard.view', 'user.view', 'enrollment.subjects.confirm',
            'students.view',
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
            'evaluation.clearance.confirm',
            // Ruling 11: the dean endorses the shift, and may file it for their department.
            'shift.request.create', 'shift.sign.department',
            'exam.view', 'exam.record.courseSpecific', 'exam.record.retention',
            'refdata.view', 'user.view', 'dashboard.view', 'enrollment.subjects.confirm',
            'students.view',
        ]);

        // ProgramHead - Phase 2
        $programHead = Role::firstOrCreate(
            ['name' => 'ProgramHead', 'guard_name' => 'web'],
            ['description' => 'Program Head with evaluation, subject proposal, and confirmation permissions']
        );
        $programHead->syncPermissions([
            'admission.view', 'evaluation.view', 'evaluation.create', 'evaluation.credits.process',
            'evaluation.subjects.propose', 'evaluation.sign', 'evaluation.clearance.confirm', 'exam.view',
            // Ruling 11: the program head is one of the two hands that may endorse.
            'shift.request.create', 'shift.sign.department',
            'exam.record.courseSpecific', 'exam.record.retention',
            'dashboard.view', 'enrollment.subjects.confirm',
            'students.view',
        ]);

        // ScholarshipOfficer - Phase 3
        $scholarshipOfficer = Role::firstOrCreate(
            ['name' => 'ScholarshipOfficer', 'guard_name' => 'web'],
            ['description' => 'Scholarship & Assessment officer for grant verification and fee computation']
        );
        $scholarshipOfficer->syncPermissions([
            'assessment.view', 'assessment.compute', 'assessment.scholarships.apply', 'assessment.charges.adjust',
            'assessment.scholarships.withdraw',
            'assessment.finalize', 'dashboard.view', 'user.view', 'students.view',
        ]);

        // AccountingStaff - Phase 4
        $accountingStaff = Role::firstOrCreate(
            ['name' => 'AccountingStaff', 'guard_name' => 'web'],
            ['description' => 'Cashier and accounting staff for payment collection, OR recording, and daily collection reports']
        );
        $accountingStaff->syncPermissions([
            'payment.view', 'payment.record', 'payment.void', 'payment.report.daily',
            // Ruling 14: handing money back is the cashier's act, not a void.
            'payment.refund',
            // Ruling 8 (BR33): a lost clearance slip is reissued only against a replacement
            // fee, and the fee is recorded here — the act files the OR as it reissues.
            'clearance.slip.replace',
            'assessment.view', 'dashboard.view', 'user.view', 'students.view',
        ]);

        // RegistrarDesk - Phase 1
        $registrarDesk = Role::firstOrCreate(
            ['name' => 'RegistrarDesk', 'guard_name' => 'web'],
            ['description' => 'Registrar desk staff for clearance receipt recording and student verification']
        );
        $registrarDesk->syncPermissions([
            'clearance.view', 'clearance.receipt.record', 'clearance.slip.generate',
            // Ruling 8 names Clearance alongside Accounting for the replacement, and
            // ruling 12 folds the legacy Clearance office into the Registrar — so this is
            // the desk role that takes the lost-slip request at the counter.
            'clearance.slip.replace',
            'dashboard.view', 'user.view',
            'students.view',
        ]);

        // RegistrarApprover - Phase 5
        $registrarApprover = Role::firstOrCreate(
            ['name' => 'RegistrarApprover', 'guard_name' => 'web'],
            ['description' => 'Registrar officer for final enrollment approval, subject confirmation, certificate and class card printing']
        );
        $registrarApprover->syncPermissions([
            'enrollment.approve', 'print.certificate', 'print.classCard', 'print.subjectLoad', 'enrollment.studentdata.record',
            // Ruling 17: dropping is the Registrar's alone. It erases what the other
            // desks signed, so the desk that holds the final signature is the one that
            // answers for it — and OfficeHead is deliberately not in this list.
            'enrollment.drop',
            'clearance.view', 'payment.view', 'evaluation.view', 'assessment.view', 'dashboard.view', 'user.view',
            'students.view',
            // The Registrar finalizes academic standing, so the grade scale those
            // standings are derived against is maintained here rather than by a
            // seeder edit. refdata.view opens the catalog hub; the hub then lists
            // only the catalogs this role actually manages (grade scale).
            'refdata.view', 'refdata.gradeScale.manage',
        ]);

        // BlockingCoordinator - Phase 6
        $blockingCoordinator = Role::firstOrCreate(
            ['name' => 'BlockingCoordinator', 'guard_name' => 'web'],
            ['description' => 'Blocking coordinator for block section assignment, schedule management, and capacity verification']
        );
        $blockingCoordinator->syncPermissions([
            'block.view', 'block.manage', 'block.assign', 'block.schedules.manage', 'block.capacity.check',
            'print.blockSchedule', 'dashboard.view', 'user.view', 'students.view',
        ]);

        // ClinicStaff - Phase 7
        $clinicStaff = Role::firstOrCreate(
            ['name' => 'ClinicStaff', 'guard_name' => 'web'],
            ['description' => 'School clinic health assessment and PhilHealth registration staff']
        );
        $clinicStaff->syncPermissions([
            'clinic.view', 'clinic.record', 'clinic.update', 'clinic.sign', 'clinic.reopen',
            'dashboard.view', 'user.view', 'students.view',
        ]);

        // IdOfficer - Phase 8
        $idOfficer = Role::firstOrCreate(
            ['name' => 'IdOfficer', 'guard_name' => 'web'],
            ['description' => 'ID Office staff for ID requests and face-photo validation']
        );
        $idOfficer->syncPermissions([
            'id.view', 'id.request.create', 'id.validate', 'id.sign',
            'dashboard.view', 'user.view', 'students.view',
        ]);

        // OfficeHead - all view permissions + the module actions a desk head answers
        // for. Item 4: exam recording is NOT a desk-head permission — the School
        // Entrance Examination is Guidance-only, and course-specific and retention
        // exams belong to the owning academic department (DeptEvaluator).
        //
        // Ruling 7 (X-4) splits the signature rights out of this role. A head used to be
        // able to sign at any desk in the building — approve an enrollment, approve an
        // application, sign the evaluation, the clinic and the ID boxes — which made the
        // signature on a record a question of role name rather than of office custody.
        // Each of those five now sits with the desk that owns the box: enrollment.approve
        // with RegistrarApprover, admission.approve with AdmissionOfficer, evaluation.sign
        // with DeptEvaluator (and Dean/ProgramHead), clinic.sign with ClinicStaff,
        // id.sign with IdOfficer. Nothing else moved: the view rights, the reference-data
        // rights and the counters a head actually works (blocking, clearance windows,
        // payment recording, assessment computing) stay broad on purpose.
        //
        // `clearance.approve` is the one listed right that STAYS. It is not a workflow
        // signature: ClearancePolicy::approveRequirement scopes it to the office that owns
        // the requirement row, so it means "this office answers for its own clearance line".
        // No desk role holds `clearance.view` for another office's queue, so moving it to a
        // desk role would leave the act with nobody who can reach it — see the ruling's own
        // "keep view rights broad" for why the head is the right holder here.
        $officeHead = Role::firstOrCreate(
            ['name' => 'OfficeHead', 'guard_name' => 'web'],
            ['description' => 'Office Head with all view permissions and module action permissions (exam recording is Guidance/department-only)']
        );
        $officeHead->syncPermissions([
            'admission.view', 'exam.view', 'evaluation.view', 'assessment.view',
            'payment.view', 'clearance.view', 'block.view',
            'clinic.view', 'id.view', 'refdata.view', 'user.view', 'audit.view', 'dashboard.view',
            'students.view',
            'block.manage', 'block.assign', 'block.schedules.manage',
            'clearance.periods.manage', 'clearance.slip.generate',
            'clearance.receipt.record', 'clearance.approve',
            'clinic.record', 'clinic.update', 'clinic.reopen',
            'id.request.create', 'id.validate',
            'payment.record', 'payment.report.daily',
            'assessment.compute', 'assessment.finalize',
            'evaluation.create', 'evaluation.profile.capture', 'evaluation.subjects.propose', 'evaluation.credits.process',
            'admission.create', 'admission.update', 'admission.reject', 'admission.requirements.submit', 'admission.requirements.verify',
            'print.certificate', 'print.classCard', 'print.subjectLoad', 'enrollment.studentdata.record',
        ]);

        // Staff - view permissions.
        // Audit follow-up §A1: audit.view is deliberately NOT granted to the
        // base Staff role — the audit log exposes full model snapshots (incl.
        // student PII) and would bypass the students.view scoping.
        $staff = Role::firstOrCreate(
            ['name' => 'Staff', 'guard_name' => 'web'],
            ['description' => 'Staff with view-only access across all modules']
        );
        $staff->syncPermissions([
            'admission.view', 'exam.view', 'evaluation.view', 'assessment.view',
            'payment.view', 'clearance.view', 'block.view', 'clinic.view',
            'id.view', 'refdata.view', 'user.view', 'dashboard.view',
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

        $this->command->info('Created '.Role::count().' desk and faculty roles with module permissions.');

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
                    OfficeId::Registrar->value => ['RegistrarApprover', 'OfficeHead'],
                    OfficeId::Accounting->value => ['AccountingStaff', 'OfficeHead'],
                    OfficeId::Scholarship->value => ['ScholarshipOfficer', 'OfficeHead'],
                    OfficeId::Guidance->value => ['GuidanceStaff', 'OfficeHead'],
                    OfficeId::Blocking->value => ['BlockingCoordinator', 'OfficeHead'],
                    OfficeId::Admission->value => ['AdmissionOfficer', 'OfficeHead'],
                    // Item 4: the Academic desk is the owning department's desk —
                    // its head evaluates, so pair DeptEvaluator (course-specific
                    // and retention exams are handled and viewed here).
                    OfficeId::Academic->value => ['DeptEvaluator', 'OfficeHead'],
                    OfficeId::Clinic->value => ['ClinicStaff', 'OfficeHead'],
                    OfficeId::IdOffice->value => ['IdOfficer', 'OfficeHead'],
                    default => ['OfficeHead'],
                };
            } else { // regular staff
                $rolesToSync = match ($user->officeId) {
                    OfficeId::Registrar->value => ['RegistrarDesk', 'Staff'],
                    OfficeId::Accounting->value => ['AccountingStaff', 'Staff'],
                    OfficeId::Scholarship->value => ['ScholarshipOfficer', 'Staff'],
                    OfficeId::Guidance->value => ['GuidanceStaff', 'Staff'],
                    OfficeId::Blocking->value => ['BlockingCoordinator', 'Staff'],
                    OfficeId::Admission->value => ['AdmissionOfficer', 'Staff'],
                    OfficeId::Academic->value => ['Staff'],
                    OfficeId::Clinic->value => ['ClinicStaff', 'Staff'],
                    OfficeId::IdOffice->value => ['IdOfficer', 'Staff'],
                    default => ['Staff'],
                };
            }

            $user->syncRoles($rolesToSync);
        }

        $this->command->info('RbacSeeder completed successfully!');
    }
}
