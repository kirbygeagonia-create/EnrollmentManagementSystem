<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A refund has to be its own state, not a borrowed one.
 *
 * Ruling 14 makes a refund reopen the account: the money goes back to the student and an
 * enrollment already `paid` moves back to owing, so the Registrar cannot approve it. The
 * three values `paymentStatus` held could not say that. `pending` already means something
 * else here — it is what a void reads as, the receipt that should never have been filed.
 * Marking a refund `pending` would make a returned payment indistinguishable from a
 * mistake, and the question a cashier is asked is exactly that difference: was this money
 * never collected, or collected and then handed back?
 *
 * `partial` was already in the column but unreachable — `record()` stamped every receipt
 * `paid`, so an installment that left a balance read as a settled account. Making part
 * payments reachable needs no schema, so it is done in the controller.
 */
return new class extends Migration
{
    private const STATUSES = ['paid', 'partial', 'pending', 'refunded'];

    public function up(): void
    {
        // A refund is money going out, so it needs the date, the hand and the reason the
        // receipt itself cannot carry: `paymentDate` stays the day the cash was collected
        // — rewriting it would falsify the receipt — and the cashier's daily sheet has to
        // show the payout on the day it happened.
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'refundedAt')) {
                $table->dateTime('refundedAt')->nullable()->after('paymentStatus');
            }

            if (! Schema::hasColumn('payments', 'refundedBy')) {
                $table->integer('refundedBy')->nullable()->after('refundedAt');
            }

            if (! Schema::hasColumn('payments', 'refundedReason')) {
                $table->string('refundedReason', 500)->nullable()->after('refundedBy');
            }
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreign('refundedBy')
                    ->references('userId')->on('staffusers')
                    ->onDelete('set null')->onUpdate('cascade');
            });

            DB::statement('ALTER TABLE payments MODIFY COLUMN paymentStatus ENUM('.
                implode(', ', array_map(fn (string $status) => "'{$status}'", self::STATUSES)).
                ') NOT NULL');

            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->enum('paymentStatus', self::STATUSES)->change();
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql' && Schema::hasColumn('payments', 'refundedBy')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropForeign(['refundedBy']);
            });
        }

        Schema::table('payments', function (Blueprint $table) {
            foreach (['refundedReason', 'refundedBy', 'refundedAt'] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        // A refunded row has nowhere to go, so it is read back as money held before the
        // column is narrowed — the way the payment-mode downgrade treats a check.
        DB::table('payments')->where('paymentStatus', 'refunded')->update(['paymentStatus' => 'paid']);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY COLUMN paymentStatus ENUM('paid', 'partial', 'pending') NOT NULL");

            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->enum('paymentStatus', ['paid', 'partial', 'pending'])->change();
        });
    }
};
