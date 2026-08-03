<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardians', function (Blueprint $table) {
            $table->integer('guardianId')->autoIncrement();
            $table->integer('studentId');
            $table->enum('relationship', ['mother', 'father', 'guardian', 'other']);
            $table->string('fullName', 150);
            $table->string('contactNumber', 20);
            $table->string('email', 150);
            $table->boolean('isEmergencyContact');
            $table->boolean('isAuthorizedToActOnBehalf');
            $table->index(['studentId'], 'fk_guardians_studentid');
            $table->foreign(['studentId'])->references(['studentId'])->on('students')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};
