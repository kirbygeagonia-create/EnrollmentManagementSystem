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
                $this->dropPaymentsEnrollmentForeignKey();
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
                $this->dropPaymentsEnrollmentForeignKey();
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

    /**
     * Drop the payments.enrollmentId foreign key using its actual constraint
     * name. Fresh migrations give it Laravel's generated name
     * (payments_enrollmentid_foreign), while the legacy hand-built database
     * used fk_payments_enrollmentid — hard-coding either one breaks the other
     * (audit §3.2 CI MySQL).
     */
    private function dropPaymentsEnrollmentForeignKey(): void
    {
        $database = DB::connection()->getDatabaseName();

        $fk = DB::select(
            'SELECT constraint_name AS constraint_name
             FROM information_schema.key_column_usage
             WHERE constraint_schema = ? AND table_name = ?
               AND column_name = ? AND referenced_table_name = ?
             LIMIT 1',
            [$database, 'payments', 'enrollmentId', 'enrollments']
        );

        if ($fk) {
            DB::statement("ALTER TABLE `payments` DROP FOREIGN KEY `{$fk[0]->constraint_name}`");
        }
    }
};
