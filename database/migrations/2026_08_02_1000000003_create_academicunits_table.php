<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academicunits', function (Blueprint $table) {
            $table->integer('unitId')->autoIncrement();
            $table->string('unitName', 150);
            $table->enum('unitType', ['college', 'department']);
            $table->integer('parentUnitId')->nullable();
            $table->index(['parentUnitId'], 'fk_academicunits_parentunitid');
            $table->foreign(['parentUnitId'])->references(['unitId'])->on('academicunits')->onDelete('set null')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academicunits');
    }
};
