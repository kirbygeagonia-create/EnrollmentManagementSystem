<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Academic standing is no longer known at admission (remediation item 16).
 *
 * The Admission desk used to stamp every new enrollment as `regular` before the
 * student had been evaluated, which made the label an unverified default rather
 * than a decision: Evaluation derives it from the grades on file and the
 * Registrar confirms it at approval. Until somebody decides, the honest value
 * is "not yet decided" — so the column becomes nullable instead of pretending.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE enrollments MODIFY COLUMN academicStanding ENUM('regular', 'irregular') NULL DEFAULT NULL");
        } else {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->enum('academicStanding', ['regular', 'irregular'])->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Restoring NOT NULL would strand every undecided enrollment, so the
        // reverse only tightens the column once the rows are filled in again.
        DB::table('enrollments')->whereNull('academicStanding')->update(['academicStanding' => 'regular']);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE enrollments MODIFY COLUMN academicStanding ENUM('regular', 'irregular') NOT NULL");
        } else {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->enum('academicStanding', ['regular', 'irregular'])->nullable(false)->change();
            });
        }
    }
};
