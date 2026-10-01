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
     * PROVISIONAL: schoolAddress and schoolPhone are institutional facts this
     * repository does not record, so they are seeded blank for the Registrar to
     * supply — the print letterhead omits them rather than inventing them.
     */
    public function run(): void
    {
        $settings = [
            'schoolName' => [
                'value' => 'Southeast Asian Institute of Technology',
                'description' => 'Letterhead name shown on every printed document.',
            ],
            'schoolAddress' => [
                'value' => '',
                'description' => 'PROVISIONAL — Registrar to supply the official campus address for the print letterhead.',
            ],
            'schoolPhone' => [
                'value' => '',
                'description' => 'PROVISIONAL — Registrar to supply the official contact number for the print letterhead.',
            ],
            'clearanceReplacementFee' => [
                'value' => '100.00',
                'description' => 'PROVISIONAL — printed on the clearance slip footer; Registrar to confirm against the current fee schedule.',
            ],
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
