<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Widen idrequests.status to the new IdRequestStatus cases added by the
 * re-issue contract (validated, released, reissuePending, cancelled).
 *
 * MySQL enforces ENUM columns strictly, so the widened enum must be applied
 * to the live table; SQLite stores enums as plain varchar (no constraint),
 * so it needs no change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE idrequests MODIFY COLUMN status ENUM('pending', 'cardProduced', 'validated', 'released', 'reissuePending', 'cancelled') NOT NULL");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE idrequests MODIFY COLUMN status ENUM('pending', 'cardProduced') NOT NULL");
    }
};
