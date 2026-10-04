<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The pass slip is confirmed where the department takes the student: Evaluation.
 *
 * Ruling 5 records a fact the Registrar had been asserting without it. A continuing or
 * shifter student's clearance pass slip is *confirmed at Department Evaluation* — the
 * department sees the paper when it issues the form — and the system had no column to
 * say that anyone saw it. The Registrar's gate could therefore pass on a slip nobody at
 * the front of the pipeline had ever acknowledged, and a student who was never confirmed
 * looked identical to one who was.
 *
 * Nullable, because the absence is the point: no confirmation is not a default of
 * "confirmed", and it does not stop the enrollment from being created — it keeps the
 * clearance-passed indicator absent through the later phases and stops the Registrar's
 * approval, which is exactly what the ruling says a missing confirmation must do.
 *
 * Which slip was seen is deliberately not stored: the window the Registrar reads comes
 * from `Clearanceperiods::accepting()`, and a second pointer would be a second answer to
 * "which clearance counts".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            if (! Schema::hasColumn('enrollments', 'clearanceConfirmedBy')) {
                $table->integer('clearanceConfirmedBy')->nullable()->after('dropReason');
            }

            if (! Schema::hasColumn('enrollments', 'clearanceConfirmedAt')) {
                $table->dateTime('clearanceConfirmedAt')->nullable()->after('clearanceConfirmedBy');
            }

            // SQLite cannot attach a foreign key to an existing table, so the reference is
            // declared only where the driver supports it — the same guard the ID-desk
            // alignment migration uses.
            if (DB::connection()->getDriverName() !== 'sqlite') {
                $table->foreign('clearanceConfirmedBy')
                    ->references('userId')->on('staffusers')
                    ->onDelete('set null')->onUpdate('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            if (DB::connection()->getDriverName() !== 'sqlite'
                && Schema::hasColumn('enrollments', 'clearanceConfirmedBy')) {
                $table->dropForeign(['clearanceConfirmedBy']);
            }

            foreach (['clearanceConfirmedAt', 'clearanceConfirmedBy'] as $column) {
                if (Schema::hasColumn('enrollments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
