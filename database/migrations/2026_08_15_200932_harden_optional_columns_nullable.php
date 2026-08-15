<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make all inherently optional text/string columns nullable across all EMS tables.
     * This eliminates MySQL strict-mode error 1364 ("Field 'x' doesn't have a default value")
     * when creating records through the web UI with optional fields left blank.
     */
    public function up(): void
    {
        Schema::table('clearanceapprovals', function (Blueprint $table) {
            $table->text('remarks')->nullable()->change();
        });

        Schema::table('clinicrecords', function (Blueprint $table) {
            $table->text('assessmentNotes')->nullable()->change();
            $table->text('findings')->nullable()->change();
            $table->string('philhealthNumber', 50)->nullable()->change();
        });

        Schema::table('creditedsubjects', function (Blueprint $table) {
            $table->text('remarks')->nullable()->change();
        });

        Schema::table('studentrequirementsubmissions', function (Blueprint $table) {
            $table->text('remarks')->nullable()->change();
        });

        Schema::table('studenteducationalbackgrounds', function (Blueprint $table) {
            $table->string('honorsCertifications')->nullable()->change();
            $table->string('strandTrack')->nullable()->change();
            $table->string('supportingDocumentPath')->nullable()->change();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('middleName', 100)->nullable()->change();
            $table->string('suffix', 20)->nullable()->change();
            $table->string('telephoneNumber', 20)->nullable()->change();
        });

        Schema::table('staffusers', function (Blueprint $table) {
            $table->string('middleName', 100)->nullable()->change();
            $table->string('contactNo', 20)->nullable()->change();
        });

        Schema::table('transferacademicrecords', function (Blueprint $table) {
            $table->decimal('gradeAtOldSchool', 4, 2)->nullable()->change();
        });

        Schema::table('idrequests', function (Blueprint $table) {
            $table->string('cardPhotoPath', 500)->nullable()->change();
            $table->string('producedByVendor', 255)->nullable()->change();
            $table->string('reissueReason', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Safe no-op in reverse
    }
};
