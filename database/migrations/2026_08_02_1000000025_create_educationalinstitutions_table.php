<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('educationalinstitutions', function (Blueprint $table) {
            $table->integer('institutionId')->autoIncrement();
            $table->string('institutionName', 150);
            $table->enum('institutionType', ['elementary', 'juniorHigh', 'seniorHigh', 'vocational', 'college']);
            $table->string('cityMunicipality', 150);
            $table->string('province', 150);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('educationalinstitutions');
    }
};
