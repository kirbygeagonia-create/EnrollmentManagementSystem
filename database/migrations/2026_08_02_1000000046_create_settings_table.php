<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('settingKey', 100)->primary();
            $table->text('settingValue');
            $table->string('description', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
