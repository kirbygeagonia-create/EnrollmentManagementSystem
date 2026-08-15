<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Update documentprintlog.documentType ENUM to include all DocumentType enum cases:
     * subjectLoad, classCard, certificate, clearanceSlip, blockSchedule, enrollmentForm
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE documentprintlog MODIFY COLUMN documentType ENUM('subjectLoad', 'classCard', 'certificate', 'clearanceSlip', 'blockSchedule', 'enrollmentForm') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE documentprintlog MODIFY COLUMN documentType ENUM('subjectLoad', 'classCard', 'certificate') NOT NULL");
        }
    }
};
