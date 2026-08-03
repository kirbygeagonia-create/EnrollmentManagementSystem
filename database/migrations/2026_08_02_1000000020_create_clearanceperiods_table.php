<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clearanceperiods', function (Blueprint $table) {
            $table->integer('clearancePeriodId')->autoIncrement();
            $table->integer('termId');
            $table->date('clearanceStartDate');
            $table->date('clearanceEndDate');
            $table->enum('periodStatus', ['open', 'closed', 'extended']);
            $table->index(['termId'], 'fk_clearanceperiods_termid');
            $table->foreign(['termId'])->references(['termId'])->on('academicterms')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clearanceperiods');
    }
};
