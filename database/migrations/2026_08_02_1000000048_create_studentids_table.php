<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studentids', function (Blueprint $table) {
            $table->integer('idId')->autoIncrement();
            $table->integer('studentId');
            $table->integer('idRequestId');
            $table->string('qrCode', 255);
            $table->date('issueDate');
            $table->enum('validationStatus', ['pendingValidation', 'active', 'lost', 'replaced']);
            $table->string('securityPhotoPath', 255);
            $table->integer('validatedBy')->nullable();
            $table->dateTime('validatedDate')->nullable();
            $table->unique(['qrCode'], 'uq_studentids_qrcode');
            $table->index(['studentId'], 'fk_studentids_studentid');
            $table->index(['idRequestId'], 'fk_studentids_idrequestid');
            $table->index(['validatedBy'], 'fk_studentids_validatedby');
            $table->foreign(['idRequestId'])->references(['idRequestId'])->on('idrequests')->onUpdate('cascade');
            $table->foreign(['studentId'])->references(['studentId'])->on('students')->onUpdate('cascade');
            $table->foreign(['validatedBy'])->references(['userId'])->on('staffusers')->onDelete('set null')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studentids');
    }
};
