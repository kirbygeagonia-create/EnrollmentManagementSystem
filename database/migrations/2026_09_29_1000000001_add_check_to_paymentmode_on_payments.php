<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Accounting desk has always offered "Bank Check" on the payment form, and
 * both validators accept `check` — but the enum and this column only allowed
 * cash and online, so recording a check payment died on the way to the database.
 * The storage layer is widened to match the form the cashiers already use.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY COLUMN paymentMode ENUM('cash', 'check', 'online') NOT NULL");
        } else {
            Schema::table('payments', function (Blueprint $table) {
                $table->enum('paymentMode', ['cash', 'check', 'online'])->change();
            });
        }
    }

    public function down(): void
    {
        // Any check already on file has nowhere to go, so the reverse downgrades
        // them to cash first rather than stranding the rows.
        DB::table('payments')->where('paymentMode', 'check')->update(['paymentMode' => 'cash']);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY COLUMN paymentMode ENUM('cash', 'online') NOT NULL");
        } else {
            Schema::table('payments', function (Blueprint $table) {
                $table->enum('paymentMode', ['cash', 'online'])->change();
            });
        }
    }
};
