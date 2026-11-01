<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADS guide problem 5 (prompt part 43.C's concurrency group): two desks holding the same student record must not
 * silently overwrite one another.
 *
 * `students.rowVersion` is the counter an optimistic lock compares. It is deliberately additive and defaulted: every
 * row already on file becomes version 1, so no existing record is invalidated and no desk has to know about the
 * column before the page that edits the person is re-read.
 *
 * Two choices worth stating, because the repository has been burned by both.
 *
 * **Portable, unlike the withdrawn active-seat key.** A plain `unsignedInteger` with a default compiles on MySQL and
 * SQLite alike, so the suite exercises the same rule CI does. The seat rule was a generated column, SQLite could not
 * build it, and the whole local suite went green over a constraint it had never enforced
 * (`2026_11_01_1600000000_withdraw_the_active_seat_key…`). A guard that only one driver can see is not a guard.
 *
 * **Not in `$fillable`, on purpose.** `Students::$fillable` is what a desk payload may write, and a version column
 * the client can set is a version column the client can forge: a stale form could simply post a high number and
 * walk past the check. The bump is a direct property assignment inside the write, which Eloquent persists because it
 * made the attribute dirty — the field travels *from* the record the page was rendered from, never *into* it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->unsignedInteger('rowVersion')->default(1)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn('rowVersion');
        });
    }
};
