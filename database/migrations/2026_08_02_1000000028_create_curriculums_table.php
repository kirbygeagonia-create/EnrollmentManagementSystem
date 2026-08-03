<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculums', function (Blueprint $table) {
            $table->integer('curriculumId')->autoIncrement();
            $table->integer('courseId');
            $table->integer('majorId')->nullable();
            $table->date('effectiveYear');
            $table->string('curriculumName', 150);
            $table->index(['courseId'], 'fk_curriculums_courseid');
            $table->index(['majorId'], 'fk_curriculums_majorid');
            $table->foreign(['courseId'])->references(['courseId'])->on('courses')->onUpdate('cascade');
            $table->foreign(['majorId'])->references(['majorId'])->on('majors')->onDelete('set null')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculums');
    }
};
