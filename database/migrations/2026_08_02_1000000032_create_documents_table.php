<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->integer('documentId')->autoIncrement();
            $table->integer('submissionId');
            $table->text('fileUrl');
            $table->string('fileType', 150);
            $table->date('uploadedDate');
            $table->integer('verifiedBy')->nullable();
            $table->index(['submissionId'], 'fk_documents_submissionid');
            $table->index(['verifiedBy'], 'fk_documents_verifiedby');
            $table->foreign(['submissionId'])->references(['submissionId'])->on('studentrequirementsubmissions')->onUpdate('cascade');
            $table->foreign(['verifiedBy'])->references(['userId'])->on('staffusers')->onDelete('set null')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
