<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admissionrequirements', function (Blueprint $table) {
            $table->integer('requirementId')->autoIncrement();
            $table->string('requirementName', 150);
            $table->enum('appliesTo', ['firstYear', 'transferee', 'shifter', 'continuing', 'all']);
            $table->boolean('isRequired');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admissionrequirements');
    }
};
