<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offices', function (Blueprint $table) {
            $table->integer('officeId')->autoIncrement();
            $table->string('officeName', 150);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offices');
    }
};
