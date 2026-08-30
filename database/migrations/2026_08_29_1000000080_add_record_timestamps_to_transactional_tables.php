<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit (low-prio): add record-level timestamps to core transactional tables.
 *
 * Most transactional tables shipped with $timestamps = false and no
 * created_at/updated_at columns. Status-change history is tracked separately
 * (Enrollmentstatushistory etc.), but basic record timestamps are cheap and
 * invaluable for debugging/auditing. Columns are nullable so pre-existing
 * rows and raw SQL inserts (tests, migrations) remain unaffected.
 */
return new class extends Migration
{
    /**
     * Tables receiving created_at / updated_at columns.
     */
    private array $tables = [
        'students',
        'admissions',
        'enrollments',
        'payments',
        'studentassessments',
        'studentclearances',
        'clinicrecords',
        'idrequests',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'created_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'created_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropTimestamps();
                });
            }
        }
    }
};
