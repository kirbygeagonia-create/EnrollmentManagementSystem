<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinicrecords', function (Blueprint $table) {
            $table->integer('clinicRecordId')->autoIncrement();
            $table->integer('enrollmentId');
            $table->decimal('heightCm', 5, 1)->default(0.0);
            $table->decimal('weightKg', 5, 1)->default(0.0);
            $table->string('bloodPressure', 20)->default('');
            $table->string('philhealthNumber', 20)->default('');
            $table->boolean('philhealthRegistered')->default(0);
            $table->text('assessmentNotes');
            $table->text('findings');
            $table->integer('clinicStaffId')->nullable();
            $table->date('assessmentDate')->nullable();
            $table->enum('status', ['pending', 'completed'])->default('pending');
            $table->index(['enrollmentId'], 'fk_clinicrecords_enrollmentid');
            $table->index(['clinicStaffId'], 'fk_clinicrecords_clinicstaffid');
            $table->foreign(['clinicStaffId'])->references(['userId'])->on('staffusers')->onDelete('set null')->onUpdate('cascade');
            $table->foreign(['enrollmentId'])->references(['enrollmentId'])->on('enrollments')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinicrecords');
    }
};
