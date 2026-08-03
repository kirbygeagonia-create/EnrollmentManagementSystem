<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idrequests', function (Blueprint $table) {
            $table->integer('idRequestId')->autoIncrement();
            $table->integer('enrollmentId');
            $table->enum('requestReason', ['newStudent', 'shifted', 'lost', 'replaced', 'renewed']);
            $table->string('emergencyContactName', 150);
            $table->string('emergencyContactNumber', 20);
            $table->string('bloodType', 150);
            $table->string('cardPhotoPath', 255);
            $table->string('producedByVendor', 150);
            $table->date('requestDate');
            $table->enum('status', ['pending', 'cardProduced']);
            $table->index(['enrollmentId'], 'fk_idrequests_enrollmentid');
            $table->foreign(['enrollmentId'])->references(['enrollmentId'])->on('enrollments')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idrequests');
    }
};
