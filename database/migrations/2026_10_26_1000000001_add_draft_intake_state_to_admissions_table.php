<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Draft intake state (development prompt §7.1, gap G-2 of the requirement matrix).
 *
 * §7.1 asks that "incomplete intake can be saved as a Draft / Incomplete record and resumed later without
 * re-entering all data", and §14 makes it an acceptance case. Before this the first save was already a
 * submitted case: `admissionStatus` held pending/approved/rejected, and AdmissionController::store() wrote
 * Pending and seeded every applicable requirement row in the same transaction — so a walk-in applicant who
 * arrived without a document had nowhere to stop.
 *
 * Both engines enforce this column, and they do it differently, which is why a driver guard that only widens
 * MySQL leaves the suite unable to write the new state: SQLite compiles `enum()` to
 * `varchar CHECK ("admissionStatus" in ('pending',…))` (SQLiteGrammar::typeEnum), so the constraint travels
 * with the table and has to be re-made. `payments.paymentStatus` was widened the same way for the refund state.
 */
return new class extends Migration
{
    private const STATUSES = ['draft', 'pending', 'approved', 'rejected'];

    public function up(): void
    {
        // The moment a draft was last touched, so a desk can order the resumable cases by who has been idle
        // longest. Nullable on purpose: an admission that was never a draft has no such moment.
        Schema::table('admissions', function (Blueprint $table) {
            $table->date('draftedDate')->nullable()->after('admissionStatus');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE admissions MODIFY COLUMN admissionStatus ENUM('.
                implode(', ', array_map(fn (string $status) => "'{$status}'", self::STATUSES)).
                ') NOT NULL');

            return;
        }

        Schema::table('admissions', function (Blueprint $table) {
            $table->enum('admissionStatus', self::STATUSES)->change();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('admissions', 'draftedDate')) {
            Schema::table('admissions', function (Blueprint $table) {
                $table->dropColumn('draftedDate');
            });
        }

        // A draft has nowhere to go. `pending` is the state it was heading for and the one a desk can still
        // act on, so the row is read back before the column narrows — the way the refund downgrade reads a
        // `refunded` receipt back as money held. Narrowing over a draft row would be the loud failure MySQL
        // gives, and this is the downgrade path, not a desk act.
        DB::table('admissions')->where('admissionStatus', 'draft')->update(['admissionStatus' => 'pending']);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE admissions MODIFY COLUMN admissionStatus ENUM('pending', 'approved', 'rejected') NOT NULL");

            return;
        }

        Schema::table('admissions', function (Blueprint $table) {
            $table->enum('admissionStatus', ['pending', 'approved', 'rejected'])->change();
        });
    }
};
