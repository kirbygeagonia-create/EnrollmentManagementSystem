<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studentclearances', function (Blueprint $table) {
            $table->integer('studentClearanceId')->autoIncrement();
            $table->integer('studentId');
            $table->integer('clearancePeriodId');
            $table->enum('overallStatus', ['pending', 'approved', 'rejected', 'waived', 'incomplete']);
            $table->date('extendedDeadline')->nullable();
            $table->integer('receivedBy')->nullable();
            $table->dateTime('receivedDate')->nullable();
            $table->unique(['studentId', 'clearancePeriodId'], 'uq_student_clearanceperiod');
            $table->index(['clearancePeriodId'], 'fk_studentclearances_clearanceperiodid');
            $table->index(['receivedBy'], 'fk_studentclearances_receivedby');
            $table->foreign(['clearancePeriodId'])->references(['clearancePeriodId'])->on('clearanceperiods')->onUpdate('cascade');
            $table->foreign(['receivedBy'])->references(['userId'])->on('staffusers');
            $table->foreign(['studentId'])->references(['studentId'])->on('students')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studentclearances');
    }
};
