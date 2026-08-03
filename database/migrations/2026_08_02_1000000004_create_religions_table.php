<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('religions', function (Blueprint $table) {
            $table->integer('religionId')->autoIncrement();
            $table->string('religionName', 150);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('religions');
    }
};
