<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->integer('addressId')->autoIncrement();
            $table->integer('studentId');
            $table->enum('addressType', ['home', 'current', 'permanent']);
            $table->string('houseBuildingNo', 150);
            $table->string('street', 150);
            $table->string('sitioPurok', 150);
            $table->string('barangay', 150);
            $table->string('cityMunicipality', 150);
            $table->string('district', 150)->nullable();
            $table->string('province', 150);
            $table->string('region', 150);
            $table->string('country', 150)->default('Philippines');
            $table->string('zipCode', 20);
            $table->index(['studentId'], 'fk_addresses_studentid');
            $table->foreign(['studentId'])->references(['studentId'])->on('students')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
