<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registrar hold state (refinement pass item 8): the registrar can return a
 * paid enrollment back to Department Evaluation with a required reason.
 *
 * - Widens enrollments.enrollmentStatus with the returnedToEvaluation case
 *   (MySQL enforces ENUM columns strictly; SQLite stores enums as plain
 *   varchar, so it needs no change).
 * - Adds the nullable returnReason the registrar states on return — it is
 *   shown to the evaluating department and mirrored into
 *   enrollmentstatushistory.remarks by the state machine.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE enrollments MODIFY COLUMN enrollmentStatus ENUM('pending', 'evaluated', 'assessed', 'paid', 'returnedToEvaluation', 'enrolled', 'dropped') NOT NULL");
        }

        Schema::table('enrollments', function (Blueprint $table) {
            $table->string('returnReason', 500)->nullable()->after('registrarProcessedBy');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('returnReason');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE enrollments MODIFY COLUMN enrollmentStatus ENUM('pending', 'evaluated', 'assessed', 'paid', 'enrolled', 'dropped') NOT NULL");
        }
    }
};
