<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculumsubjects', function (Blueprint $table) {
            $table->integer('curriculumSubjectId')->autoIncrement();
            $table->integer('curriculumId');
            $table->integer('subjectId');
            $table->integer('prerequisiteSubjectId')->nullable();
            $table->integer('yearLevel');
            $table->enum('semesterOffered', ['1st', '2nd', 'Summer']);
            $table->index(['curriculumId'], 'fk_curriculumsubjects_curriculumid');
            $table->index(['subjectId'], 'fk_curriculumsubjects_subjectid');
            $table->index(['prerequisiteSubjectId'], 'fk_curriculumsubjects_prerequisitesubjectid');
            $table->foreign(['curriculumId'])->references(['curriculumId'])->on('curriculums')->onUpdate('cascade');
            $table->foreign(['prerequisiteSubjectId'])->references(['subjectId'])->on('subjects')->onDelete('set null')->onUpdate('cascade');
            $table->foreign(['subjectId'])->references(['subjectId'])->on('subjects')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculumsubjects');
    }
};
