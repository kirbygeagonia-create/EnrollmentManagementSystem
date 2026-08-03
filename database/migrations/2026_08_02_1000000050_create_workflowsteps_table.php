<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflowsteps', function (Blueprint $table) {
            $table->integer('workflowStepId')->autoIncrement();
            $table->integer('workflowId');
            $table->integer('officeId');
            $table->integer('stepOrder');
            $table->enum('stepStatus', ['pending', 'completed', 'skipped']);
            $table->integer('signedBy')->nullable();
            $table->dateTime('signedDate')->nullable();
            $table->index(['workflowId'], 'fk_workflowsteps_workflowid');
            $table->index(['officeId'], 'fk_workflowsteps_officeid');
            $table->index(['signedBy'], 'fk_workflowsteps_signedby');
            $table->foreign(['officeId'])->references(['officeId'])->on('offices')->onUpdate('cascade');
            $table->foreign(['signedBy'])->references(['userId'])->on('staffusers')->onDelete('set null')->onUpdate('cascade');
            $table->foreign(['workflowId'])->references(['workflowId'])->on('enrollmentworkflow')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflowsteps');
    }
};
