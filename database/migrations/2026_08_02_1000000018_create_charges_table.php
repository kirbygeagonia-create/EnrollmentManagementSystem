<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charges', function (Blueprint $table) {
            $table->integer('chargeId')->autoIncrement();
            $table->integer('assessmentId');
            $table->integer('feeTypeId');
            $table->decimal('amount', 10, 2);
            $table->decimal('waivedAmount', 10, 2);
            $table->index(['assessmentId'], 'fk_charges_assessmentid');
            $table->index(['feeTypeId'], 'fk_charges_feetypeid');
            $table->foreign(['assessmentId'])->references(['assessmentId'])->on('studentassessments')->onUpdate('cascade');
            $table->foreign(['feeTypeId'])->references(['feeTypeId'])->on('feetypes')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};
