<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Composite indexes for the heavy desk screens at synthetic-data volume.
     *
     * - enrollments(termId, enrollmentStatus): registrar approval queue,
     *   evaluation queue, dashboard queue counts
     * - enrolledsubjects(enrollmentId, status): block roster + subject-load
     *   screens filter by both columns per enrollment
     * - workflowsteps(workflowId, stepOrder): workflow stepper reads steps in
     *   order for a single workflow (avoids filesort)
     * - clearanceapprovals(studentClearanceId, status): clearance detail screen
     *   per student clearance
     */
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->index(['termId', 'enrollmentStatus'], 'idx_enrollments_term_status');
        });

        Schema::table('enrolledsubjects', function (Blueprint $table) {
            $table->index(['enrollmentId', 'status'], 'idx_enrolledsubjects_enrollment_status');
        });

        Schema::table('workflowsteps', function (Blueprint $table) {
            $table->index(['workflowId', 'stepOrder'], 'idx_workflowsteps_workflow_order');
        });

        Schema::table('clearanceapprovals', function (Blueprint $table) {
            $table->index(['studentClearanceId', 'status'], 'idx_clearanceapprovals_clearance_status');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex('idx_enrollments_term_status');
        });

        Schema::table('enrolledsubjects', function (Blueprint $table) {
            $table->dropIndex('idx_enrolledsubjects_enrollment_status');
        });

        Schema::table('workflowsteps', function (Blueprint $table) {
            $table->dropIndex('idx_workflowsteps_workflow_order');
        });

        Schema::table('clearanceapprovals', function (Blueprint $table) {
            $table->dropIndex('idx_clearanceapprovals_clearance_status');
        });
    }
};
