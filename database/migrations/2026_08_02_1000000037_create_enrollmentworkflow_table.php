<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollmentworkflow', function (Blueprint $table) {
            $table->integer('workflowId')->autoIncrement();
            $table->integer('enrollmentId');
            $table->integer('currentStep');
            $table->enum('workflowStatus', ['inProgress', 'completed', 'lost']);
            $table->index(['enrollmentId'], 'fk_enrollmentworkflow_enrollmentid');
            $table->foreign(['enrollmentId'])->references(['enrollmentId'])->on('enrollments')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollmentworkflow');
    }
};
