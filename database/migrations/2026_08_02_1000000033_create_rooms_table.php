<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->integer('roomId')->autoIncrement();
            $table->string('roomName', 150);
            $table->integer('capacity');
            $table->string('building', 150);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
