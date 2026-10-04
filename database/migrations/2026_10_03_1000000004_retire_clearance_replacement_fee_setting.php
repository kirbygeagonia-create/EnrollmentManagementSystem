<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One amount, one place.
 *
 * The clearance-slip replacement fee lived twice: the slip's footer read
 * `config('settings.clearanceReplacementFee')` (seeded 100.00) while the charge the desk
 * recorded read the 'Clearance Slip Replacement' row in `feetypes` (§25 P-12). The fee
 * table is the reference data the Registrar maintains, so it keeps the amount and the
 * settings row goes — a key the Registrar can still edit but that no longer decides
 * anything would be the more confusing of the two to leave behind.
 *
 * Reading the fee table is now the only source, and a missing row stops the desk with the
 * fee named instead of charging a remembered figure.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('settingKey', 'clearanceReplacementFee')->delete();
    }

    public function down(): void
    {
        DB::table('settings')->insertOrIgnore([
            'settingKey' => 'clearanceReplacementFee',
            'settingValue' => '100.00',
            'description' => 'PROVISIONAL — printed on the clearance slip footer; Registrar to confirm against the current fee schedule.',
        ]);
    }
};
