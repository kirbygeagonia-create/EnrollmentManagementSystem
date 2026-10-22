<?php

namespace Database\Seeders;

use App\Models\Settings;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Seed the setting keys the print templates read through config('settings.*').
     *
     * Idempotent and non-destructive: an existing key is never overwritten, so a
     * Registrar's edits in Admin → User Management → Settings survive re-seeding.
     *
     * The address and the landline are not invented here. Both are recorded in
     * Documentation/SEAIT_Official_Information.md §1, compiled from the official
     * portal (seait.edu.ph), and that sheet entered this repository on 2026-08-28
     * (e35fe3d) — before this seeder was written on 2026-10-01 (29b5532), whose
     * header declared these to be "institutional facts this repository does not
     * record" and seeded both blank for twenty days. The declaration was false the
     * day it was committed; see §25.5 and P-7. The phone is the Registrar's
     * published landline, the number the institution answers the public with, and
     * the sheet lists a second line beside it. A school that prefers another
     * changes it on the Admin screen, and `required` validation means it cannot
     * then be blanked again.
     */
    public function run(): void
    {
        $settings = [
            'schoolName' => [
                'value' => 'Southeast Asian Institute of Technology',
                'description' => 'Letterhead name shown on every printed document.',
            ],
            'schoolAddress' => [
                'value' => 'National Highway, Purok 7, Crossing Rubber, Tupi, South Cotabato, Philippines, 9505',
                'description' => 'Main campus address, from SEAIT_Official_Information.md §1 (compiled from seait.edu.ph).',
            ],
            'schoolPhone' => [
                'value' => '(083) 226-1602',
                'description' => 'Registrar landline, from SEAIT_Official_Information.md §1. The sheet also lists 226-1203.',
            ],
            // The clearance replacement fee is deliberately not a setting: the amount a
            // student is charged and the amount the slip prints both come from the
            // 'Clearance Slip Replacement' fee type in Reference Data, and a second place
            // holding the same figure is how the two drifted apart (P-12, ruling 8).
        ];

        foreach ($settings as $key => $setting) {
            if (Settings::where('settingKey', $key)->exists()) {
                continue;
            }

            Settings::forceCreate([
                'settingKey' => $key,
                'settingValue' => $setting['value'],
                'description' => $setting['description'],
            ]);
        }
    }
}
