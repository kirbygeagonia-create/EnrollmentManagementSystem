<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * Two ID-card states no desk could reach.
 *
 * `released` and `cancelled` are what the ID Office's vocabulary looked like while it
 * also made and handed out cards. Card production and hand-over were removed end to end
 * (2026-09-30), so `IdRequestStatus` no longer carries either case, nothing in `app/`
 * writes them, and a row holding one would be a record the desk cannot read.
 *
 * Ruling 13 retired them from the column. Live `ems` was counted first — `idrequests`
 * holds only `validated` (2) and `pending` (1) — so this narrows an empty set. The count
 * is re-checked here rather than trusted from memory: MySQL rejects a narrowing that
 * strands rows, and silently rewriting a stranded row into `pending` would invent a desk
 * action that never happened. If a database does hold either value, this stops and names
 * it instead (§28 C-11).
 */
return new class extends Migration
{
    private const KEPT = ['pending', 'validated'];

    private const RETIRED = ['released', 'cancelled'];

    public function up(): void
    {
        $stranded = DB::table('idrequests')
            ->whereIn('status', self::RETIRED)
            ->count();

        if ($stranded > 0) {
            $kept = implode(', ', self::KEPT);
            $retired = implode(' or ', self::RETIRED);

            throw new RuntimeException(
                "idrequests.status cannot narrow to {$kept}: {$stranded} row(s) still hold {$retired}. "
                ."No desk can act on those states, so retire them deliberately — not by a migration that rewrites them behind the record's back."
            );
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE idrequests MODIFY COLUMN status ENUM('.
                implode(', ', array_map(fn (string $v) => "'{$v}'", self::KEPT)).
                ') NOT NULL');

            return;
        }

        Schema::table('idrequests', function (Blueprint $table) {
            $table->enum('status', self::KEPT)->change();
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE idrequests MODIFY COLUMN status ENUM('pending', 'validated', 'released', 'cancelled') NOT NULL");

            return;
        }

        Schema::table('idrequests', function (Blueprint $table) {
            $table->enum('status', ['pending', 'validated', 'released', 'cancelled'])->change();
        });
    }
};
