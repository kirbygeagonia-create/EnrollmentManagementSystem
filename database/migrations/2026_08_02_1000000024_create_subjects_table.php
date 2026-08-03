<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->integer('subjectId')->autoIncrement();
            $table->string('subjectCode', 150);
            $table->string('subjectName', 150);
            $table->decimal('lectureUnits', 3, 1);
            $table->decimal('labUnits', 3, 1);
            $table->enum('subjectType', ['lecture', 'lab', 'both']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
