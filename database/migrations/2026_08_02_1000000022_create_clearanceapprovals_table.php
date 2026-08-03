<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clearanceapprovals', function (Blueprint $table) {
            $table->integer('clearanceApprovalId')->autoIncrement();
            $table->integer('studentClearanceId');
            $table->integer('clearanceRequirementId');
            $table->enum('status', ['pending', 'approved', 'rejected', 'waived']);
            $table->integer('approvedBy')->nullable();
            $table->date('approvalDate')->nullable();
            $table->text('remarks');
            $table->index(['studentClearanceId'], 'fk_clearanceapprovals_studentclearanceid');
            $table->index(['clearanceRequirementId'], 'fk_clearanceapprovals_clearancerequirementid');
            $table->index(['approvedBy'], 'fk_clearanceapprovals_approvedby');
            $table->foreign(['approvedBy'])->references(['userId'])->on('staffusers')->onDelete('set null')->onUpdate('cascade');
            $table->foreign(['clearanceRequirementId'])->references(['clearanceRequirementId'])->on('clearancerequirements')->onUpdate('cascade');
            $table->foreign(['studentClearanceId'])->references(['studentClearanceId'])->on('studentclearances')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clearanceapprovals');
    }
};
