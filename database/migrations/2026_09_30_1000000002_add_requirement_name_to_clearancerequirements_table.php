<?php

use App\Support\ProvisionalClearanceRequirements;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A clearance line has to say what the student owes, not only who collects it.
 *
 * `clearancerequirements` was two columns: an id and an officeId. A requirement was
 * therefore an office, so the slip printed "Registrar" where the obligation should
 * read, an approving officer signed against no text, and a real line such as "Return
 * borrowed books" could not be expressed without adding an office. The name lands on
 * the row instead; the backfill only fills rows that are still empty, so a school
 * that has written its own wording is not overwritten by the upgrade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clearancerequirements', function (Blueprint $table) {
            $table->string('requirementName', 150)->nullable()->after('officeId');
        });

        foreach (ProvisionalClearanceRequirements::NAMES as $officeId => $name) {
            DB::table('clearancerequirements')
                ->where('officeId', $officeId)
                ->whereNull('requirementName')
                ->update(['requirementName' => $name]);
        }
    }

    public function down(): void
    {
        Schema::table('clearancerequirements', function (Blueprint $table) {
            $table->dropColumn('requirementName');
        });
    }
};
