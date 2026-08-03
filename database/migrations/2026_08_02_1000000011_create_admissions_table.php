<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->integer('admissionId')->autoIncrement();
            $table->integer('studentId');
            $table->integer('termId');
            $table->integer('courseId');
            $table->enum('applicantType', ['firstYear', 'transferee']);
            $table->enum('admissionStatus', ['pending', 'approved', 'rejected']);
            $table->integer('evaluatedBy')->nullable();
            $table->date('evaluatedDate')->nullable();
            $table->index(['studentId'], 'fk_admissions_studentid');
            $table->index(['termId'], 'fk_admissions_termid');
            $table->index(['courseId'], 'fk_admissions_courseid');
            $table->index(['evaluatedBy'], 'fk_admissions_evaluatedby');
            $table->foreign(['courseId'])->references(['courseId'])->on('courses')->onUpdate('cascade');
            $table->foreign(['evaluatedBy'])->references(['userId'])->on('staffusers')->onUpdate('cascade');
            $table->foreign(['studentId'])->references(['studentId'])->on('students')->onUpdate('cascade');
            $table->foreign(['termId'])->references(['termId'])->on('academicterms')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admissions');
    }
};
