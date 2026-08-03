<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studentscholarships', function (Blueprint $table) {
            $table->integer('studentScholarshipId')->autoIncrement();
            $table->integer('studentId');
            $table->integer('scholarshipTypeId');
            $table->integer('termId');
            $table->enum('status', ['active', 'revoked', 'expired']);
            $table->integer('approvedBy');
            $table->boolean('awardedBeforeEnrollment')->default(0);
            $table->unique(['studentId', 'scholarshipTypeId', 'termId'], 'uq_student_scholarship_term');
            $table->index(['scholarshipTypeId'], 'fk_studentscholarships_scholarshiptypeid');
            $table->index(['termId'], 'fk_studentscholarships_termid');
            $table->index(['approvedBy'], 'fk_studentscholarships_approvedby');
            $table->index(['studentId', 'termId'], 'idx_studentscholarships_lookup');
            $table->foreign(['approvedBy'])->references(['userId'])->on('staffusers')->onUpdate('cascade');
            $table->foreign(['scholarshipTypeId'])->references(['scholarshipTypeId'])->on('scholarshiptypes')->onUpdate('cascade');
            $table->foreign(['studentId'])->references(['studentId'])->on('students')->onUpdate('cascade');
            $table->foreign(['termId'])->references(['termId'])->on('academicterms')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studentscholarships');
    }
};
