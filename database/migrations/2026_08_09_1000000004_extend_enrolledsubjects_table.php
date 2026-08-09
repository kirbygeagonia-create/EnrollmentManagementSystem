<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrolledsubjects', function (Blueprint $table) {
            if (! Schema::hasColumn('enrolledsubjects', 'attempt_number')) {
                $table->unsignedTinyInteger('attempt_number')->default(1)->after('status');
            }
            if (! Schema::hasColumn('enrolledsubjects', 'original_enrolled_subject_id')) {
                $table->integer('original_enrolled_subject_id')->nullable()->after('attempt_number');
            }
        });

        // Add foreign key for original_enrolled_subject_id
        Schema::table('enrolledsubjects', function (Blueprint $table) {
            if (Schema::hasColumn('enrolledsubjects', 'original_enrolled_subject_id')) {
                $table->foreign('original_enrolled_subject_id')
                    ->references('enrolledSubjectId')
                    ->on('enrolledsubjects')
                    ->onDelete('set null')
                    ->onUpdate('cascade');
            }
        });

        // Add unique index on (enrollmentId, subjectId, attempt_number)
        Schema::table('enrolledsubjects', function (Blueprint $table) {
            $table->unique(['enrollmentId', 'subjectId', 'attempt_number'], 'uq_enrollment_subject_attempt');
        });
    }

    public function down(): void
    {
        Schema::table('enrolledsubjects', function (Blueprint $table) {
            // Drop unique index first
            if (Schema::hasIndex('enrolledsubjects', 'uq_enrollment_subject_attempt')) {
                $table->dropUnique('uq_enrollment_subject_attempt');
            }
            // Drop foreign key
            if (Schema::hasColumn('enrolledsubjects', 'original_enrolled_subject_id')) {
                $table->dropForeign(['original_enrolled_subject_id']);
            }
            // Drop columns
            if (Schema::hasColumn('enrolledsubjects', 'original_enrolled_subject_id')) {
                $table->dropColumn('original_enrolled_subject_id');
            }
            if (Schema::hasColumn('enrolledsubjects', 'attempt_number')) {
                $table->dropColumn('attempt_number');
            }
        });
    }
};
