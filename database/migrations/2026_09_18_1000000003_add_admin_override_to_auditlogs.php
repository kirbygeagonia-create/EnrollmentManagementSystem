<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item 3 admin write-boundary: the Admin role is read-everywhere (no direct
 * record mutation by permission). Any write an Admin account makes is an
 * oversight-role override — flagged on the same audit row by the existing
 * AuditLogObserver (no parallel log).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditlogs', function (Blueprint $table) {
            $table->boolean('adminOverride')->default(false)->after('action');
            $table->index('adminOverride', 'idx_auditlogs_admin_override');
        });
    }

    public function down(): void
    {
        Schema::table('auditlogs', function (Blueprint $table) {
            // SQLite cannot drop indexes by name after the fact.
            if (DB::connection()->getDriverName() !== 'sqlite') {
                $table->dropIndex('idx_auditlogs_admin_override');
            }
            $table->dropColumn('adminOverride');
        });
    }
};
