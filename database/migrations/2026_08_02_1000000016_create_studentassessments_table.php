<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studentassessments', function (Blueprint $table) {
            $table->integer('assessmentId')->autoIncrement();
            $table->integer('enrollmentId');
            $table->decimal('totalAssessedAmount', 10, 2);
            $table->decimal('totalScholarshipCoverage', 10, 2);
            $table->decimal('totalWaived', 10, 2);
            $table->decimal('remainingBalance', 10, 2);
            $table->date('assessmentDate');
            $table->index(['enrollmentId'], 'fk_studentassessments_enrollmentid');
            $table->foreign(['enrollmentId'])->references(['enrollmentId'])->on('enrollments')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studentassessments');
    }
};
