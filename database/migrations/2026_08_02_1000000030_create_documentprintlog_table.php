<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentprintlog', function (Blueprint $table) {
            $table->integer('printLogId')->autoIncrement();
            $table->integer('enrollmentId');
            $table->enum('documentType', ['subjectLoad', 'classCard', 'certificate']);
            $table->dateTime('printedDate');
            $table->integer('printedBy');
            $table->integer('documentNumber');
            $table->index(['enrollmentId'], 'fk_documentprintlog_enrollmentid');
            $table->index(['printedBy'], 'fk_documentprintlog_printedby');
            $table->foreign(['enrollmentId'])->references(['enrollmentId'])->on('enrollments')->onUpdate('cascade');
            $table->foreign(['printedBy'])->references(['userId'])->on('staffusers')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentprintlog');
    }
};
