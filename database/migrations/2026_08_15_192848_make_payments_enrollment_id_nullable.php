<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ClearanceController::replaceLostSlip creates a payment with no enrollment
     * (clearance slip replacement fee ₱100). The column must be nullable to
     * avoid MySQL strict-mode error 1364 / FK constraint violation.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('fk_payments_enrollmentid');
            }
            $table->integer('enrollmentId')->nullable()->change();
            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign(['enrollmentId'], 'fk_payments_enrollmentid')
                    ->references(['enrollmentId'])
                    ->on('enrollments')
                    ->onUpdate('cascade')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('fk_payments_enrollmentid');
            }
            $table->integer('enrollmentId')->nullable(false)->change();
            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign(['enrollmentId'], 'fk_payments_enrollmentid')
                    ->references(['enrollmentId'])
                    ->on('enrollments')
                    ->onUpdate('cascade');
            }
        });
    }
};
