<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->integer('studentId')->autoIncrement();
            $table->string('schoolIdNumber', 150);
            $table->string('lastName', 150);
            $table->string('firstName', 150);
            $table->string('middleName', 150);
            $table->string('suffix', 150);
            $table->enum('gender', ['male', 'female']);
            $table->date('birthdate');
            $table->string('birthplace', 150);
            $table->string('citizenship', 150);
            $table->enum('civilStatus', ['single', 'married', 'widowed', 'separated'])->default('single');
            $table->integer('religionId')->nullable();
            $table->string('contactNumber', 20);
            $table->string('telephoneNumber', 20)->nullable();
            $table->integer('semestersCompleted')->nullable();
            $table->integer('yearsInInstitution')->nullable();
            $table->string('email', 150);
            $table->string('username', 150);
            $table->string('passwordHash', 150);
            $table->enum('status', ['active', 'inactive']);
            $table->unique(['schoolIdNumber'], 'uq_students_schoolid');
            $table->unique(['username'], 'uq_students_username');
            $table->index(['religionId'], 'fk_students_religionid');
            $table->index(['lastName', 'firstName'], 'idx_students_names');
            $table->foreign(['religionId'])->references(['religionId'])->on('religions')->onDelete('set null')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
