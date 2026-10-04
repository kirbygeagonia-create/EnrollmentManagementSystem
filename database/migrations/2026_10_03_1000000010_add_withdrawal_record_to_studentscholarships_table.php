<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A withdrawn grant has to say why it was withdrawn.
 *
 * Ruling 15 gives the Scholarship desk two acts the column never allowed it to perform:
 * revoke a grant (the eligibility was mistaken or has been forfeited) or expire one (the
 * grant ran out mid-term), and in both cases recompute the assessment and un-pay — the
 * student owes again even after the Registrar approved, and "the trail says why".
 *
 * `studentscholarships.status` already carried `revoked` and `expired`, but nothing in the
 * application could write them, so both were dead vocabulary (§28 C-11) — a status a panel
 * can point at but no desk can reach is not a feature. These three columns are what make
 * the state mean something: the same audit row that records that the status changed cannot
 * carry the reason the desk typed, and a reason stored only in an audit log is a reason the
 * Scholarship screen cannot show the student who asks.
 *
 * Nullable, and the pair is written together: a revoked or expired row with no actor and no
 * reason is exactly the unexplained decision this migration exists to prevent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studentscholarships', function (Blueprint $table) {
            if (! Schema::hasColumn('studentscholarships', 'statusChangedBy')) {
                $table->integer('statusChangedBy')->nullable()->after('status');
            }

            if (! Schema::hasColumn('studentscholarships', 'statusChangedAt')) {
                $table->dateTime('statusChangedAt')->nullable()->after('statusChangedBy');
            }

            if (! Schema::hasColumn('studentscholarships', 'statusReason')) {
                $table->string('statusReason', 500)->nullable()->after('statusChangedAt');
            }
        });

        // SQLite cannot attach a foreign key to an existing table.
        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('studentscholarships', function (Blueprint $table) {
                $table->foreign('statusChangedBy')
                    ->references('userId')->on('staffusers')
                    ->onDelete('set null')->onUpdate('cascade');
            });
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite' && Schema::hasColumn('studentscholarships', 'statusChangedBy')) {
            Schema::table('studentscholarships', function (Blueprint $table) {
                $table->dropForeign(['statusChangedBy']);
            });
        }

        Schema::table('studentscholarships', function (Blueprint $table) {
            foreach (['statusReason', 'statusChangedAt', 'statusChangedBy'] as $column) {
                if (Schema::hasColumn('studentscholarships', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
