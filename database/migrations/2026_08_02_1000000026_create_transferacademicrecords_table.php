<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transferacademicrecords', function (Blueprint $table) {
            $table->integer('transferRecordId')->autoIncrement();
            $table->integer('studentId');
            $table->integer('institutionId');
            $table->string('subjectNameAtOldSchool', 150);
            $table->decimal('unitsAtOldSchool', 3, 1);
            $table->decimal('gradeAtOldSchool', 3, 2);
            $table->enum('passResult', ['passed', 'failed']);
            $table->index(['studentId'], 'fk_transferacademicrecords_studentid');
            $table->index(['institutionId'], 'fk_transferacademicrecords_institutionid');
            $table->foreign(['institutionId'])->references(['institutionId'])->on('educationalinstitutions')->onUpdate('cascade');
            $table->foreign(['studentId'])->references(['studentId'])->on('students')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transferacademicrecords');
    }
};
