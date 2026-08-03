<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clearancerequirements', function (Blueprint $table) {
            $table->integer('clearanceRequirementId')->autoIncrement();
            $table->integer('officeId');
            $table->index(['officeId'], 'fk_clearancerequirements_officeid');
            $table->foreign(['officeId'])->references(['officeId'])->on('offices')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clearancerequirements');
    }
};
