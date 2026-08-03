<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gradescale', function (Blueprint $table) {
            $table->integer('gradeScaleId')->autoIncrement();
            $table->decimal('minGrade', 3, 2);
            $table->decimal('maxGrade', 3, 2);
            $table->boolean('isPassing');
            $table->string('description', 150);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gradescale');
    }
};
