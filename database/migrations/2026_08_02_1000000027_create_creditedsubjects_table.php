<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creditedsubjects', function (Blueprint $table) {
            $table->integer('creditedId')->autoIncrement();
            $table->integer('enrollmentId');
            $table->integer('transferRecordId')->nullable();
            $table->string('previousSubjectName', 150);
            $table->integer('creditedToSubjectId')->nullable();
            $table->decimal('creditedUnits', 3, 1);
            $table->text('remarks');
            $table->index(['enrollmentId'], 'fk_creditedsubjects_enrollmentid');
            $table->index(['transferRecordId'], 'fk_creditedsubjects_transferrecordid');
            $table->index(['creditedToSubjectId'], 'fk_creditedsubjects_creditedtosubjectid');
            $table->foreign(['creditedToSubjectId'])->references(['subjectId'])->on('subjects')->onDelete('set null')->onUpdate('cascade');
            $table->foreign(['enrollmentId'])->references(['enrollmentId'])->on('enrollments')->onUpdate('cascade');
            $table->foreign(['transferRecordId'])->references(['transferRecordId'])->on('transferacademicrecords')->onDelete('set null')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creditedsubjects');
    }
};
