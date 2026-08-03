<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feetypes', function (Blueprint $table) {
            $table->integer('feeTypeId')->autoIncrement();
            $table->string('feeName', 150);
            $table->decimal('defaultAmount', 10, 2);
            $table->enum('unitBasis', ['perUnit', 'flat']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feetypes');
    }
};
