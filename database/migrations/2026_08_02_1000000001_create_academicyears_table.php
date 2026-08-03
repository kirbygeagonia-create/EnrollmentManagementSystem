<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academicyears', function (Blueprint $table) {
            $table->integer('academicYearId')->autoIncrement();
            $table->string('yearLabel', 150);
            $table->date('startDate');
            $table->date('endDate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academicyears');
    }
};
