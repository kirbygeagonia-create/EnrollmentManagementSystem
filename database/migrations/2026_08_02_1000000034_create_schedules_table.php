<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->integer('scheduleId')->autoIncrement();
            $table->integer('blockId');
            $table->integer('subjectId');
            $table->integer('instructorId');
            $table->integer('roomId');
            $table->index(['subjectId'], 'fk_schedules_subjectid');
            $table->index(['instructorId'], 'fk_schedules_instructorid');
            $table->index(['roomId'], 'fk_schedules_roomid');
            $table->index(['blockId', 'subjectId'], 'idx_schedules_lookup');
            $table->index(['blockId'], 'fk_schedules_blockid');
            $table->foreign(['blockId'])->references(['blockId'])->on('blocks')->onUpdate('cascade');
            $table->foreign(['instructorId'])->references(['userId'])->on('staffusers')->onUpdate('cascade');
            $table->foreign(['roomId'])->references(['roomId'])->on('rooms')->onUpdate('cascade');
            $table->foreign(['subjectId'])->references(['subjectId'])->on('subjects')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
