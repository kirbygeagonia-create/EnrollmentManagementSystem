<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studentrequirementsubmissions', function (Blueprint $table) {
            $table->integer('submissionId')->autoIncrement();
            $table->integer('admissionId');
            $table->integer('requirementId');
            $table->enum('submissionStatus', ['submitted', 'verified', 'rejected', 'incomplete', 'pending']);
            $table->date('submittedDate');
            $table->text('remarks');
            $table->index(['admissionId'], 'fk_studentrequirementsubmissions_admissionid');
            $table->index(['requirementId'], 'fk_studentrequirementsubmissions_requirementid');
            $table->foreign(['admissionId'])->references(['admissionId'])->on('admissions')->onUpdate('cascade');
            $table->foreign(['requirementId'])->references(['requirementId'])->on('admissionrequirements')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studentrequirementsubmissions');
    }
};
