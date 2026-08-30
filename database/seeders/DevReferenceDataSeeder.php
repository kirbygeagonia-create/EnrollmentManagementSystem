<?php

namespace Database\Seeders;

use App\Enums\FeeUnitBasis;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Admissionrequirements;
use App\Models\Clearanceperiods;
use App\Models\Clearancerequirements;
use App\Models\Courses;
use App\Models\Feetypes;
use App\Models\Offices;
use App\Models\Religions;
use App\Models\Rooms;
use App\Models\Staffusers;
use App\Models\Subjects;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DEV-ONLY reconstruction of the local `ems` reference dataset.
 *
 * The original hand-built synthetic dataset (30k students) was not committed
 * to the repo and is not recoverable from git. This seeder recreates the
 * reference data the E2E walkthrough and AdminAccessSmoke tests read from the
 * live MySQL database (see AGENTS.md / audit §3.2). It is idempotent and is
 * NOT wired into DatabaseSeeder (CI uses empty test databases).
 *
 * Run on the dev database only:
 *
 *   php artisan db:seed --class=DevReferenceDataSeeder --force
 *   php artisan db:seed --force   # RbacSeeder + NotificationSeeder
 */
class DevReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        // ---------- Offices (fixed IDs are part of the domain model) ----------
        $offices = [
            1 => 'Registrar',
            2 => 'Accounting',
            3 => 'Scholarship',
            4 => 'Guidance',
            5 => 'Blocking',
            6 => 'Admission',
            7 => 'Academic Department',
            8 => 'Clearance',
            11 => 'Clinic',
            22 => 'ID Office',
        ];
        foreach ($offices as $id => $name) {
            Offices::firstOrCreate(['officeId' => $id], ['officeName' => $name]);
        }

        // ---------- Academic units (explicit IDs 1-6 so course refs stay stable) ----------
        $units = [
            1 => ['College of Agriculture and Fisheries', 'college'],
            2 => ['College of Criminal Justice Education', 'college'],
            3 => ['College of Business and Governance', 'college'],
            4 => ['College of Information and Computing Technology', 'college'],
            5 => ['College of Engineering', 'college'],
            6 => ['College of Teacher Education', 'college'],
        ];
        foreach ($units as $id => [$name, $type]) {
            Academicunits::firstOrCreate(
                ['unitId' => $id],
                ['unitName' => $name, 'unitType' => $type]
            );
        }

        // ---------- Religions ----------
        Religions::firstOrCreate(['religionId' => 1], ['religionName' => 'Roman Catholic']);

        // ---------- Academic years + terms (E2E expects term 18 = Summer) ----------
        $year1 = Academicyears::firstOrCreate(
            ['yearLabel' => '2024-2025'],
            ['startDate' => '2024-06-01', 'endDate' => '2025-05-31']
        );
        $year2 = Academicyears::firstOrCreate(
            ['yearLabel' => '2025-2026'],
            ['startDate' => '2025-06-01', 'endDate' => '2026-05-31']
        );
        $terms = [
            [1, $year1->academicYearId, '1st', '2024-06-01', '2024-10-31'],
            [2, $year1->academicYearId, '2nd', '2024-11-01', '2025-03-31'],
            [3, $year1->academicYearId, 'Summer', '2025-04-01', '2025-05-31'],
            [10, $year2->academicYearId, '1st', '2025-06-01', '2025-10-31'],
            [11, $year2->academicYearId, '2nd', '2025-11-01', '2026-03-31'],
            [18, $year2->academicYearId, 'Summer', '2026-04-01', '2026-05-31'],
        ];
        foreach ($terms as [$id, $yid, $sem, $s, $e]) {
            Academicterms::firstOrCreate(
                ['termId' => $id],
                ['academicYearId' => $yid, 'semester' => $sem, 'startDate' => $s, 'endDate' => $e]
            );
        }

        // ---------- Clearance periods (E2E reads the open period for term 18) ----------
        Clearanceperiods::firstOrCreate(
            ['termId' => 18],
            ['clearanceStartDate' => '2026-04-01', 'clearanceEndDate' => '2026-05-31', 'periodStatus' => 'open']
        );

        // ---------- Courses (E2E uses ids 1, 3, 5) ----------
        $courses = [
            [1, 4, 'BSIT', 'Bachelor of Science in Information Technology', false, false],
            [2, 4, 'BSCS', 'Bachelor of Science in Computer Science', false, false],
            [3, 2, 'BSCrim', 'Bachelor of Science in Criminology', true, false],
            [4, 1, 'BSA', 'Bachelor of Science in Agriculture', false, false],
            [5, 3, 'BSBA', 'Bachelor of Science in Business Administration', false, false],
            [6, 6, 'BSEd', 'Bachelor of Secondary Education', false, false],
        ];
        foreach ($courses as [$id, $unitId, $code, $name, $exam, $retention]) {
            Courses::firstOrCreate(
                ['courseId' => $id],
                [
                    'unitId' => $unitId,
                    'courseCode' => $code,
                    'courseName' => $name,
                    'requiresEntranceExam' => $exam,
                    'requiresRetentionExam' => $retention,
                ]
            );
        }

        // ---------- Subjects ----------
        $subjects = [
            ['subjCode' => 'GEN101', 'subjName' => 'General Education 1', 'subjType' => 'lecture', 'units' => 3],
            ['subjCode' => 'ENG101', 'subjName' => 'English 1', 'subjType' => 'lecture', 'units' => 3],
            ['subjCode' => 'MATH101', 'subjName' => 'Mathematics 1', 'subjType' => 'lecture', 'units' => 3],
            ['subjCode' => 'CRIM101', 'subjName' => 'Introduction to Criminology', 'subjType' => 'lecture', 'units' => 3],
            ['subjCode' => 'IT101', 'subjName' => 'Introduction to Computing', 'subjType' => 'lecture', 'units' => 3],
            ['subjCode' => 'COM101', 'subjName' => 'Communication Skills', 'subjType' => 'lecture', 'units' => 3],
        ];
        foreach ($subjects as $s) {
            Subjects::firstOrCreate(
                ['subjectCode' => $s['subjCode']],
                [
                    'subjectName' => $s['subjName'],
                    'subjectType' => $s['subjType'],
                    'lectureUnits' => $s['units'],
                    'labUnits' => 0,
                ]
            );
        }

        // ---------- Rooms ----------
        Rooms::firstOrCreate(
            ['roomName' => 'Room 101'],
            ['capacity' => 40, 'building' => 'Main Building']
        );

        // ---------- Fee types ----------
        $fees = [
            ['Tuition Fee (per unit)', 1250.00, FeeUnitBasis::PerUnit],
            ['Miscellaneous Fee', 1500.00, FeeUnitBasis::Flat],
            ['Laboratory Fee', 500.00, FeeUnitBasis::PerUnit],
            ['Library Fee', 250.00, FeeUnitBasis::Flat],
        ];
        foreach ($fees as [$name, $amount, $basis]) {
            Feetypes::firstOrCreate(
                ['feeName' => $name],
                ['defaultAmount' => $amount, 'unitBasis' => $basis->value]
            );
        }

        // ---------- Admission requirements ----------
        $admissionReqs = [
            ['PSA Birth Certificate', 'firstYear', true],
            ['Form 138 / Report Card', 'firstYear', true],
            ['Transfer Credentials / Honorable Dismissal', 'transferee', true],
            ['Certificate of Good Moral Character', 'all', true],
        ];
        foreach ($admissionReqs as [$name, $appliesTo, $required]) {
            Admissionrequirements::firstOrCreate(
                ['requirementName' => $name],
                ['appliesTo' => $appliesTo, 'isRequired' => $required]
            );
        }

        // ---------- Clearance requirements (one row per office; table only has officeId) ----------
        foreach ([1, 2, 3, 4, 5, 6, 7, 8, 11, 22] as $officeId) {
            Clearancerequirements::firstOrCreate(['officeId' => $officeId]);
        }

        // ---------- Staff (staff8 = admin for AdminAccessSmoke; office heads) ----------
        Staffusers::firstOrCreate(
            ['username' => 'staff8'],
            [
                'employeeNo' => 'EMP-00008',
                'firstName' => 'System',
                'middleName' => '',
                'lastName' => 'Administrator',
                'email' => 'staff8@seait.edu.ph',
                'passwordHash' => Hash::make('password'),
                'officeId' => 1,
                'contactNo' => '',
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        $officeHeadRoles = [
            1 => 'Registrar Head', 2 => 'Accounting Head', 3 => 'Scholarship Head',
            4 => 'Guidance Head', 5 => 'Blocking Head', 6 => 'Admission Head',
            7 => 'Academic Head', 8 => 'Clearance Head', 11 => 'Clinic Head', 22 => 'ID Head',
        ];
        $i = 1;
        foreach ($officeHeadRoles as $officeId => $displayName) {
            $username = "office{$officeId}_head";
            Staffusers::firstOrCreate(
                ['username' => $username],
                [
                    'employeeNo' => 'EMP-'.str_pad((string) (100 + $i), 5, '0', STR_PAD_LEFT),
                    'firstName' => $displayName,
                    'middleName' => '',
                    'lastName' => 'Staff',
                    'email' => "{$username}@seait.edu.ph",
                    'passwordHash' => Hash::make('password'),
                    'officeId' => $officeId,
                    'contactNo' => '',
                    'role' => 'officeHead',
                    'status' => 'active',
                ]
            );
            $i++;
        }

        $this->command?->info(class_basename($this).': done.');
    }
}
