<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Widen clinicrecords.status to include the 'reopened' case added by the
 * Clinic re-issue contract.
 *
 * The create migration (0023) was already edited to include 'reopened', but
 * MySQL tables that were migrated before that edit still carry the old ENUM,
 * so a live ALTER is required. SQLite stores enums as plain varchar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE clinicrecords MODIFY COLUMN status ENUM('pending', 'completed', 'reopened') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE clinicrecords MODIFY COLUMN status ENUM('pending', 'completed') NOT NULL DEFAULT 'pending'");
    }
};
