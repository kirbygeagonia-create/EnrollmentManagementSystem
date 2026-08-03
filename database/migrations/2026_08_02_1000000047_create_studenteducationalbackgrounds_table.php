<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studenteducationalbackgrounds', function (Blueprint $table) {
            $table->integer('backgroundId')->autoIncrement();
            $table->integer('studentId');
            $table->integer('institutionId');
            $table->enum('levelCompleted', ['elementary', 'juniorHigh', 'seniorHigh', 'vocational', 'college']);
            $table->string('strandTrack', 150);
            $table->date('yearCompleted');
            $table->string('honorsCertifications', 150);
            $table->string('supportingDocumentPath', 255);
            $table->index(['studentId'], 'fk_studenteducationalbackgrounds_studentid');
            $table->index(['institutionId'], 'fk_studenteducationalbackgrounds_institutionid');
            $table->foreign(['institutionId'])->references(['institutionId'])->on('educationalinstitutions')->onUpdate('cascade');
            $table->foreign(['studentId'])->references(['studentId'])->on('students')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studenteducationalbackgrounds');
    }
};
