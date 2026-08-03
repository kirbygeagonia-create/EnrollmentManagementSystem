<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->integer('enrollmentId')->autoIncrement();
            $table->integer('studentId');
            $table->integer('courseId');
            $table->integer('majorId')->nullable();
            $table->integer('termId');
            $table->integer('yearLevel')->default(1);
            $table->integer('admissionId')->nullable();
            $table->enum('studentType', ['firstYear', 'continuing', 'transferee', 'shifter']);
            $table->enum('enrollmentType', ['new', 'old'])->default('old');
            $table->enum('academicStanding', ['regular', 'irregular']);
            $table->enum('enrollmentStatus', ['pending', 'evaluated', 'assessed', 'paid', 'enrolled', 'dropped']);
            $table->integer('evaluatedBy');
            $table->integer('registrarProcessedBy')->nullable();
            $table->date('enrolledDate')->nullable();
            $table->date('formIssuedDate')->nullable();
            $table->date('formSignedDate')->nullable();
            $table->index(['studentId'], 'fk_enrollments_studentid');
            $table->index(['courseId'], 'fk_enrollments_courseid');
            $table->index(['majorId'], 'fk_enrollments_majorid');
            $table->index(['termId'], 'fk_enrollments_termid');
            $table->index(['admissionId'], 'fk_enrollments_admissionid');
            $table->index(['evaluatedBy'], 'fk_enrollments_evaluatedby');
            $table->index(['registrarProcessedBy'], 'fk_enrollments_registrarprocessedby');
            $table->foreign(['admissionId'])->references(['admissionId'])->on('admissions')->onDelete('set null')->onUpdate('cascade');
            $table->foreign(['courseId'])->references(['courseId'])->on('courses')->onUpdate('cascade');
            $table->foreign(['evaluatedBy'])->references(['userId'])->on('staffusers')->onUpdate('cascade');
            $table->foreign(['majorId'])->references(['majorId'])->on('majors')->onDelete('set null')->onUpdate('cascade');
            $table->foreign(['registrarProcessedBy'])->references(['userId'])->on('staffusers')->onUpdate('cascade');
            $table->foreign(['studentId'])->references(['studentId'])->on('students')->onUpdate('cascade');
            $table->foreign(['termId'])->references(['termId'])->on('academicterms')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
