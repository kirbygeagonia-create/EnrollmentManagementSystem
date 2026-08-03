<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedulemeetings', function (Blueprint $table) {
            $table->integer('meetingId')->autoIncrement();
            $table->integer('scheduleId');
            $table->enum('dayOfWeek', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);
            $table->time('startTime');
            $table->time('endTime');
            $table->index(['scheduleId'], 'fk_schedulemeetings_scheduleid');
            $table->foreign(['scheduleId'])->references(['scheduleId'])->on('schedules')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedulemeetings');
    }
};
