<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollmentstatushistory', function (Blueprint $table) {
            $table->integer('historyId')->autoIncrement();
            $table->integer('enrollmentId');
            $table->string('fromStatus', 30);
            $table->string('toStatus', 30);
            $table->integer('changedBy')->nullable();
            $table->string('remarks', 500)->nullable();
            $table->dateTime('changedAt')->useCurrent();
            $table->index(['enrollmentId'], 'fk_enrollmentstatushistory_enrollment');
            $table->index(['changedBy'], 'fk_enrollmentstatushistory_changedby');
            $table->foreign(['changedBy'])->references(['userId'])->on('staffusers');
            $table->foreign(['enrollmentId'])->references(['enrollmentId'])->on('enrollments');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollmentstatushistory');
    }
};
