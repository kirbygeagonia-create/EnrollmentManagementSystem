<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §28.3 G-36: one notice per act per recipient, enforced by the server.
 *
 * Both notification listeners are queued (`SendEnrollmentNotification.php:9`, `SendWorkflowNotification.php:9`), so a
 * worker that takes the job again writes the bell row a second time. Until now nothing in the schema objected: the
 * `notifications` table carried only the composite non-unique index on (notifiable_type, notifiable_id) that Laravel
 * ships with. The key is on the ACT and the ADDRESSEE together, because the same act legitimately produces one row
 * per staff member in the office the notice is addressed to, and one office legitimately rings twice about two
 * different signings.
 *
 * Nullable on purpose: a hand-built event with no history row behind it — the shape two cases in
 * `NotificationBellTest.php` construct — has no act identity, and inventing one would make the key a guess. NULLs do
 * not collide in a MySQL unique index and do not collide in SQLite's either, so an unkeyed notice is always delivered
 * and a keyed one is delivered once. Both drivers were consulted before this was written, because a guard whose
 * duplicate error looks different on each surface is the spent-counter family reached from the write side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('dedupeKey', 191)->nullable();
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->unique(['dedupeKey'], 'uq_notifications_dedupe');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique('uq_notifications_dedupe');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('dedupeKey');
        });
    }
};
