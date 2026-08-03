<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->integer('paymentId')->autoIncrement();
            $table->integer('enrollmentId');
            $table->string('orNumber', 150);
            $table->decimal('amount', 10, 2);
            $table->dateTime('paymentDate');
            $table->enum('paymentMode', ['cash', 'online']);
            $table->integer('processedBy')->nullable();
            $table->enum('paymentStatus', ['paid', 'partial', 'pending']);
            $table->unique(['orNumber'], 'uq_payments_ornumber');
            $table->index(['enrollmentId'], 'fk_payments_enrollmentid');
            $table->index(['processedBy'], 'fk_payments_processedby');
            $table->foreign(['enrollmentId'])->references(['enrollmentId'])->on('enrollments')->onUpdate('cascade');
            $table->foreign(['processedBy'])->references(['userId'])->on('staffusers')->onDelete('set null')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
