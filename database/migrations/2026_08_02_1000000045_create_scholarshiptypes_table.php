<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scholarshiptypes', function (Blueprint $table) {
            $table->integer('scholarshipTypeId')->autoIncrement();
            $table->string('scholarshipName', 150);
            $table->enum('coverageType', ['full', 'partial']);
            $table->decimal('coveragePercent', 10, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scholarshiptypes');
    }
};
