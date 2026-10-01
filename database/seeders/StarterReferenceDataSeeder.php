<?php

namespace Database\Seeders;

use App\Models\Religions;
use App\Models\Scholarshiptypes;
use Illuminate\Database\Seeder;

/**
 * Starter rows for the two reference lists a desk cannot work around.
 *
 * Both are required choices on their forms: religion is mandatory at intake and
 * profile capture, and the Scholarship desk can grant aid only against an existing
 * scholarship type. Left empty, a fresh install cannot register an applicant.
 *
 * PROVISIONAL: neither list is school policy recorded in this repository. These are
 * obvious defaults so the workflow is usable on day one — the Registrar replaces them
 * in Admin → Reference Data, and gap D-2/D-3 stay open until the real registers arrive.
 */
class StarterReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        // ---------- Religions (gap D-2) ----------
        // Roman Catholic keeps id 1 so the historical row is found, not duplicated.
        $religions = [
            'Roman Catholic',
            'Iglesia ni Cristo',
            'Born Again Christian / Evangelical',
            'Seventh-day Adventist',
            'Philippine Independent Church (Aglipay)',
            'Islam',
            'Buddhism',
            'Other',
        ];

        foreach ($religions as $religion) {
            Religions::firstOrCreate(['religionName' => $religion]);
        }

        // ---------- Scholarship types (gap D-3) ----------
        $scholarships = [
            ['scholarshipName' => 'Full Scholarship', 'coverageType' => 'full', 'coveragePercent' => 100.00],
            ['scholarshipName' => 'Partial Scholarship', 'coverageType' => 'partial', 'coveragePercent' => 50.00],
        ];

        foreach ($scholarships as $scholarship) {
            Scholarshiptypes::firstOrCreate(
                ['scholarshipName' => $scholarship['scholarshipName']],
                $scholarship
            );
        }
    }
}
