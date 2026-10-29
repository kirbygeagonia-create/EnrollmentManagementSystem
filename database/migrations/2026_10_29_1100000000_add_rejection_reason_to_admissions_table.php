<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A rejected application carries the reason it was rejected.
 *
 * Prompt §6.5 asks that a denial name its cause. `reject()` wrote only the status word, and the desk
 * posted an empty body, so the reason lived nowhere: the applicant's panel said "Contact the admission
 * office for details" and the next session had nothing to read back. A reason that is not stored is not
 * a decision the school can explain.
 *
 * Nullable on purpose. Every rejection filed before this column stays valid and simply shows no reason,
 * which is the truth about it; a NOT NULL column would either refuse the migration or force the seeder to
 * invent a cause for a decision nobody recorded.
 *
 * `evaluatedDate` is the column the reject act already stamps, and it is a DATE — see §1.1's school-day
 * row. Nothing here changes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            if (! Schema::hasColumn('admissions', 'rejectionReason')) {
                $table->string('rejectionReason', 500)->nullable()->after('admissionStatus');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            if (Schema::hasColumn('admissions', 'rejectionReason')) {
                $table->dropColumn('rejectionReason');
            }
        });
    }
};
