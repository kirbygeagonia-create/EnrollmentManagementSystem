<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->integer('courseId')->autoIncrement();
            $table->integer('unitId');
            $table->string('courseName', 150);
            $table->string('courseCode', 150);
            $table->boolean('requiresEntranceExam');
            $table->boolean('requiresRetentionExam');
            $table->index(['unitId'], 'fk_courses_unitid');
            $table->foreign(['unitId'])->references(['unitId'])->on('academicunits')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
