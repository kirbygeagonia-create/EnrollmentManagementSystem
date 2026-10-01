<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The audit trail must be able to name a row that has no number.
 *
 * `auditlogs.entityId` recorded whatever `Model::getKey()` returned, which is an
 * integer for every table in this schema except the ones keyed by a name — the
 * settings table is keyed by `settingKey`. Because the column was a NOT NULL INT,
 * AuditLogObserver had to refuse the write outright, so editing the letterhead,
 * the school address or any other setting left no trace at all: the one screen
 * that answers "who changed this" was blind to exactly the rows an intruder or an
 * honest mistake would touch. A string column holds every key, numeric or not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditlogs', function (Blueprint $table) {
            $table->string('entityId', 191)->change();
        });
    }

    public function down(): void
    {
        // Turning the column back into an INT means every string-keyed row has to
        // go first — there is no number to invent for `schoolName`. They are
        // deleted rather than silently cast, because a cast would file the setting
        // edits under a wrong entity and make the trail lie.
        $nonNumeric = [];
        foreach (DB::table('auditlogs')->pluck('entityId', 'auditId') as $auditId => $entityId) {
            if (! is_numeric($entityId)) {
                $nonNumeric[] = (int) $auditId;
            }
        }

        DB::table('auditlogs')->whereIn('auditId', $nonNumeric)->delete();

        Schema::table('auditlogs', function (Blueprint $table) {
            $table->integer('entityId')->change();
        });
    }
};
