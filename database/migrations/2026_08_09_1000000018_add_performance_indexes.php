<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Performance indexes for blocking roster, registrar queue, and block filtering.
     *
     * - enrolledsubjects(blockId, status): block roster queries filter by both
     * - enrollments(enrollmentStatus, enrollmentId): registrar queue uses this composite
     * - blocks(courseId, termId, yearLevel, blockId): blocking index filter + order
     */
    public function up(): void
    {
        Schema::table('enrolledsubjects', function (Blueprint $table) {
            $table->index(['blockId', 'status'], 'idx_enrolledsubjects_block_status');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->index(['enrollmentStatus', 'enrollmentId'], 'idx_enrollments_status_id');
        });

        Schema::table('blocks', function (Blueprint $table) {
            $table->index(['courseId', 'termId', 'yearLevel', 'blockId'], 'idx_blocks_filter_order');
        });
    }

    public function down(): void
    {
        Schema::table('enrolledsubjects', function (Blueprint $table) {
            $table->dropIndex('idx_enrolledsubjects_block_status');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex('idx_enrollments_status_id');
        });

        Schema::table('blocks', function (Blueprint $table) {
            $table->dropIndex('idx_blocks_filter_order');
        });
    }
};
