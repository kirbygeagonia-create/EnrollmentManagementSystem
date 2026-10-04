<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A drop has to say why.
 *
 * Ruling 17 makes the Registrar the only desk that can drop an enrollment, because a drop
 * undoes what the other desks signed — and an act that erases four signatures is the one
 * act the record cannot leave unexplained. `returnReason` set the shape for this in item
 * 8: the reason lives beside the record so the screen a panelist opens answers the
 * question without a join.
 *
 * Who dropped it and when are deliberately NOT copied here. The state machine already
 * writes `enrollmentstatushistory` with changedBy, changedAt and the remarks on every
 * transition, and the audit observer writes the row change; a third place holding the
 * same facts is a third place they can disagree.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('enrollments', 'dropReason')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->string('dropReason', 500)->nullable()->after('returnReason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('enrollments', 'dropReason')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->dropColumn('dropReason');
            });
        }
    }
};
