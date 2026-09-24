<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Align idrequests with the validation-only ID flow: card production
 * (QR encoding, PVC card records, vendor intake, re-issue) is removed from
 * the system — the ID Office is a validation desk (verify identity, capture
 * the face photo, mark validated, release the physical card).
 *
 * - validatedBy/validatedDate move onto the request itself (previously
 *   tracked on the produced studentids row, which no longer gets written).
 * - The pure production states (cardProduced, reissuePending) are retired:
 *   existing rows remap to pending (they still await the desk's validation)
 *   before the MySQL ENUM narrows to the kept vocabulary — MySQL rejects an
 *   ALTER whose enum list drops values still present in the data. SQLite
 *   stores enums as plain varchar, so only the data cleanup applies there.
 * - The re-issue columns are dropped outright (pure production machinery);
 *   producedByVendor/cardPhotoPath are already nullable and simply stop
 *   being written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idrequests', function (Blueprint $table) {
            if (Schema::hasColumn('idrequests', 'is_reissue')) {
                $table->dropColumn('is_reissue');
            }
            if (Schema::hasColumn('idrequests', 'reissueReason')) {
                $table->dropColumn('reissueReason');
            }
        });

        Schema::table('idrequests', function (Blueprint $table) {
            $table->integer('validatedBy')->nullable()->after('status');
            $table->dateTime('validatedDate')->nullable()->after('validatedBy');
            $table->foreign('validatedBy')->references('userId')->on('staffusers')->onDelete('set null')->onUpdate('cascade');
        });

        // Retire the production states (both drivers — SQLite rows keep stale
        // strings that the trimmed PHP enum could no longer hydrate).
        DB::statement("UPDATE idrequests SET status = 'pending' WHERE status IN ('cardProduced', 'reissuePending')");

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE idrequests MODIFY COLUMN status ENUM('pending', 'validated', 'released', 'cancelled') NOT NULL");
        }
    }

    public function down(): void
    {
        Schema::table('idrequests', function (Blueprint $table) {
            if (DB::connection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['validatedBy']);
            }
            $table->dropColumn(['validatedBy', 'validatedDate']);

            if (! Schema::hasColumn('idrequests', 'reissueReason')) {
                $table->string('reissueReason', 255)->nullable()->after('status');
            }
            if (! Schema::hasColumn('idrequests', 'is_reissue')) {
                $table->boolean('is_reissue')->default(false)->after('reissueReason');
            }
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE idrequests MODIFY COLUMN status ENUM('pending', 'cardProduced', 'validated', 'released', 'reissuePending', 'cancelled') NOT NULL");
        }
    }
};
