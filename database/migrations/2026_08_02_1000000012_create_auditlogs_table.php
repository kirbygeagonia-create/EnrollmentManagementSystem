<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditlogs', function (Blueprint $table) {
            $table->integer('auditId')->autoIncrement();
            $table->integer('userId')->nullable();
            $table->string('action', 100);
            $table->string('entityTable', 150);
            $table->integer('entityId');
            $table->json('oldValues')->nullable();
            $table->json('newValues')->nullable();
            $table->string('ipAddress', 45)->nullable();
            $table->dateTime('createdAt')->useCurrent();
            $table->index(['userId'], 'fk_auditlogs_user');
            $table->foreign(['userId'])->references(['userId'])->on('staffusers');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditlogs');
    }
};
