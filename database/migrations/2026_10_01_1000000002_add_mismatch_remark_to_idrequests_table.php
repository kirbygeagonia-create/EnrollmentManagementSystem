<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ID desk could only ever say yes.
 *
 * `validate()` set the request to Validated and there was no other outcome: no
 * way to record that the face in front of the window did not match the file, no
 * way to say which detail was wrong, and no trace of who noticed (§28 C-5, and
 * concerns #41/#48/#49). The obvious fix — a `held` or `rejected` status — would
 * invent a workflow state the enrollment form cannot represent: box 22 is signed
 * or it is not, and a request parked in a state no desk acts on is how records
 * go quiet rather than how they get fixed.
 *
 * So the mismatch is recorded as a remark on the request the officer is holding.
 * Null by default and nullable: nothing that is on file today changes meaning.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('idrequests', 'mismatchRemark')) {
            Schema::table('idrequests', function (Blueprint $table) {
                $table->string('mismatchRemark', 255)->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('idrequests', 'mismatchRemark')) {
            Schema::table('idrequests', function (Blueprint $table) {
                $table->dropColumn('mismatchRemark');
            });
        }
    }
};
