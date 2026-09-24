<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Curriculum versioning (refinement item 7): an enrollment is pinned to the
 * curriculum version the student was admitted under. The evaluator's first
 * subject proposal stamps curriculumId; every later evaluation of that
 * enrollment resolves the SAME curriculum instead of silently drifting to
 * the latest effectiveYear version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->integer('curriculumId')->nullable()->after('majorId');
            $table->foreign('curriculumId')->references('curriculumId')->on('curriculums')->onDelete('set null')->onUpdate('cascade');
            $table->index('curriculumId', 'idx_enrollments_curriculum');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            // SQLite cannot drop constraints/indexes by name after the fact.
            if (DB::connection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['curriculumId']);
                $table->dropIndex('idx_enrollments_curriculum');
            }
            $table->dropColumn('curriculumId');
        });
    }
};
