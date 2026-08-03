<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('examresults', function (Blueprint $table) {
            $table->integer('examId')->autoIncrement();
            $table->integer('studentId');
            $table->integer('courseId');
            $table->integer('termId');
            $table->enum('examStage', ['entrance', 'retention']);
            $table->enum('examType', ['general', 'courseSpecific'])->default('general');
            $table->enum('examResult', ['pass', 'fail'])->nullable();
            $table->date('examDate');
            $table->index(['studentId', 'courseId'], 'idx_examresults_student');
            $table->index(['studentId'], 'fk_examresults_studentid');
            $table->index(['courseId'], 'fk_examresults_courseid');
            $table->index(['termId'], 'fk_examresults_termid');
            $table->foreign(['courseId'])->references(['courseId'])->on('courses')->onUpdate('cascade');
            $table->foreign(['studentId'])->references(['studentId'])->on('students')->onUpdate('cascade');
            $table->foreign(['termId'])->references(['termId'])->on('academicterms')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examresults');
    }
};
