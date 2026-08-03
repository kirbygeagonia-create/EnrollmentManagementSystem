<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrolledsubjects', function (Blueprint $table) {
            $table->integer('enrolledSubjectId')->autoIncrement();
            $table->integer('enrollmentId');
            $table->integer('subjectId');
            $table->integer('blockId');
            $table->integer('scheduleId')->nullable();
            $table->decimal('grade', 3, 2)->nullable();
            $table->enum('status', ['proposed', 'confirmed', 'dropped']);
            $table->index(['enrollmentId'], 'fk_enrolledsubjects_enrollmentid');
            $table->index(['subjectId'], 'fk_enrolledsubjects_subjectid');
            $table->index(['blockId'], 'fk_enrolledsubjects_blockid');
            $table->index(['scheduleId'], 'fk_enrolledsubjects_scheduleid');
            $table->foreign(['blockId'])->references(['blockId'])->on('blocks')->onUpdate('cascade');
            $table->foreign(['enrollmentId'])->references(['enrollmentId'])->on('enrollments')->onUpdate('cascade');
            $table->foreign(['scheduleId'])->references(['scheduleId'])->on('schedules')->onDelete('set null')->onUpdate('cascade');
            $table->foreign(['subjectId'])->references(['subjectId'])->on('subjects')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrolledsubjects');
    }
};
