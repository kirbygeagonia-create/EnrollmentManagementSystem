<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One process stamps a record, so the database must not have an opinion of its own.
 *
 * Ruled by the owner on 2026-10-13, after measuring the split: this install's MySQL session resolves to
 * `SYSTEM`, and `system_time_zone` reports UTC-11, while `config/app.php` carries `timezone => 'UTC'`.
 * `NOW()` answered `2026-10-13 03:52` for the same instant PHP stamped `2026-10-13 14:52` — eleven hours
 * apart, and a plain DATETIME stores no zone, so whichever process wrote the row decides the hours a
 * desk reads. Both tables here had a `CURRENT_TIMESTAMP` default, which means the answer was the
 * database's.
 *
 * Measured before removing it, because the writers are not alike: `AuditLogObserver` and
 * `EnrollmentStateMachine` already pass `now()`, while the two `Auditlogs::create` calls in
 * `UserManagementController` did not — they relied on the default, and this migration would have turned
 * a role assignment into a "Field 'createdAt' doesn't have a default value" error. So the guarantee moved
 * into the models: `Auditlogs::booted()` and `Enrollmentstatushistory::booted()` fill the column with
 * `now()` when a write omits it. The suite proved that was the right place by failing on a third writer
 * nobody had counted — a test fixture inserting a `login` row — which no amount of patching call sites
 * one at a time would have prevented. On this install the difference had not reached the data: the audit
 * table holds 2,747 `created`, 166 `updated` and 1 `deleted` rows and **no** `assigned` row, so nothing
 * stored was ever DB-stamped.
 *
 * `jobs.failed_at` keeps its `useCurrent()`: it belongs to the framework's own queue schema, Laravel
 * writes it, and touching framework migrations buys a conflict on the next framework update.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditlogs', function (Blueprint $table) {
            $table->dateTime('createdAt')->change();
        });

        Schema::table('enrollmentstatushistory', function (Blueprint $table) {
            $table->dateTime('changedAt')->change();
        });
    }

    public function down(): void
    {
        Schema::table('auditlogs', function (Blueprint $table) {
            $table->dateTime('createdAt')->useCurrent()->change();
        });

        Schema::table('enrollmentstatushistory', function (Blueprint $table) {
            $table->dateTime('changedAt')->useCurrent()->change();
        });
    }
};
