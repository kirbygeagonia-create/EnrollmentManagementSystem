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
     * The address and the landline are not invented here. All three values are recorded in
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
     *
     * The name is the sheet's Official Name, spelled as §1 records it. It used to be
     * "Southeast Asian Institute of Technology" — one word, no suffix — which is what every
     * printed letterhead said while the login screen, the desk screens and this repository's
     * own documentation said the other (P-16). Ruled on 2026-10-23 in the school's favour,
     * and confirmed by the physical papers: the Subject Load's letterhead reads
     * "South East Asian Institute of Technology, Inc." Print templates fall back to the
     * upper-case form of the same string, so a fresh install cannot print a different name
     * from one that has been edited.
     */
    public function run(): void
    {
        $settings = [
            'schoolName' => [
                'value' => 'South East Asian Institute of Technology, Inc.',
                'description' => 'Letterhead name shown on every printed document; SEAIT_Official_Information.md §1 records it.',
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
