<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staffusers', function (Blueprint $table) {
            $table->integer('userId')->autoIncrement();
            $table->integer('officeId');
            $table->integer('unitId')->nullable();
            $table->string('employeeNo', 150);
            $table->string('firstName', 150);
            $table->string('middleName', 150);
            $table->string('lastName', 150);
            $table->string('username', 150);
            $table->string('passwordHash', 150);
            $table->string('remember_token', 100)->nullable();
            $table->enum('role', ['staff', 'officeHead', 'dean', 'programHead', 'admin']);
            $table->string('email', 150);
            $table->string('contactNo', 20);
            $table->enum('status', ['active', 'inactive']);
            $table->unique(['employeeNo'], 'uq_staff_employeeno');
            $table->unique(['username'], 'uq_staff_username');
            $table->index(['officeId'], 'fk_staffusers_officeid');
            $table->index(['unitId'], 'fk_staffusers_unitid');
            $table->foreign(['officeId'])->references(['officeId'])->on('offices')->onUpdate('cascade');
            $table->foreign(['unitId'])->references(['unitId'])->on('academicunits')->onDelete('set null')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staffusers');
    }
};
