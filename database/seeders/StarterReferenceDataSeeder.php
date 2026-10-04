<?php

namespace Database\Seeders;

use App\Enums\FeeUnitBasis;
use App\Models\Feetypes;
use App\Models\Religions;
use App\Models\Scholarshiptypes;
use Illuminate\Database\Seeder;

/**
 * Starter rows for the reference lists a desk cannot work around.
 *
 * All three are required by a flow the desk has no other way to complete: religion is
 * mandatory at intake and profile capture, the Scholarship desk can grant aid only
 * against an existing scholarship type, and the clearance desk can charge a lost-slip
 * replacement only against an existing fee type — the slip prints that amount and
 * Accounting records it, so leaving the row out would stop the desk or, worse, let it
 * quote a figure no fee schedule holds.
 *
 * PROVISIONAL: none of these lists is school policy recorded in this repository. These
 * are obvious defaults so the workflow is usable on day one — the Registrar replaces
 * them in Admin → Reference Data, and gap D-2/D-3 stay open until the real registers
 * arrive.
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

        // ---------- Clearance slip replacement fee (P-12, ruling 8) ----------
        // PROVISIONAL: 100.00 is the figure the slip has carried, and it is now a row the
        // Registrar edits in Admin → Reference Data → Fee Types rather than a constant in
        // code. The slip prints this amount and the replacement is charged this amount, so
        // one row answers both. An existing row is never overwritten.
        Feetypes::firstOrCreate(
            ['feeName' => Feetypes::CLEARANCE_SLIP_REPLACEMENT],
            ['defaultAmount' => 100.00, 'unitBasis' => FeeUnitBasis::Flat]
        );
    }
}
