<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academicterms', function (Blueprint $table) {
            $table->integer('termId')->autoIncrement();
            $table->integer('academicYearId');
            $table->enum('semester', ['1st', '2nd', 'Summer']);
            $table->date('startDate');
            $table->date('endDate');
            $table->index(['academicYearId'], 'fk_academicterms_academicyearid');
            $table->foreign(['academicYearId'])->references(['academicYearId'])->on('academicyears')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academicterms');
    }
};
