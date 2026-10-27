<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * §7.2's Registrar confirmation, and the two evidence columns it signs.
 *
 * Prompt §7.2 asks for "the Registrar's final confirmation of a passing/failing
 * examination result". Before this migration the recorded pass was final the moment it
 * was typed: `examresults` had no status between "the office scored it" and "the school
 * stands behind it", and no column could say who closed that gap or when. The owner ruled
 * 2026-10-26 that the recording desk's entry stays provisional and the Registrar confirms
 * it official, so every downstream gate asks the confirmed value.
 *
 * `resultStatus` is a separate column from `examResult` rather than a third value inside
 * it, because the two answer different questions: `examResult` is pass or fail and is
 * **nullable** (an unmarked paper is neither, and `exam.results` counts only scored rows
 * in a pass rate), while the status says whether the school has adopted whatever that
 * mark is. Folding them would make a confirmed null read as a result.
 *
 * `resultStatus` defaults to `provisional` because that is what a desk's own entry IS the
 * moment it is written — a default of `official` would make the confirmation the exception
 * rather than the rule. The five rows already on file are therefore backfilled explicitly,
 * not by the default: an applicant whose admission was approved on last month's pass would
 * otherwise find their enrollment reading as unexamined the next time a desk opened it.
 * The backfill is visible rather than disguised as a signature — `verifiedBy` and
 * `verifiedDate` stay null on those rows, and no act performed from the confirmation screen
 * can leave both blank.
 *
 * The two evidence columns copy the shape §7.3's crediting decision uses: who signed, when
 * the school's own civil day said, both nullable so a value can never be invented by the
 * schema. `verifiedDate` is written from `App\Support\SchoolTime::today()`, never from
 * `now()`, because the day a Registrar confirms a result is the school's day (lane 51) and
 * this box's clock is not the world's (lane 74).
 *
 * No `after()` anchors: MySQL resolves `AFTER` inside one multi-add ALTER against the
 * column list as the statement began, so chained anchors land the new columns in reverse
 * declaration order (measured on live `ems` while building §7.3). These three append, and
 * the order they sit in is not a claim anything reads.
 */
return new class extends Migration
{
    private const STATUSES = ['provisional', 'official'];

    public function up(): void
    {
        Schema::table('examresults', function (Blueprint $table) {
            if (! Schema::hasColumn('examresults', 'resultStatus')) {
                $table->enum('resultStatus', self::STATUSES)->default('provisional');
            }

            if (! Schema::hasColumn('examresults', 'verifiedBy')) {
                $table->integer('verifiedBy')->nullable();
            }

            if (! Schema::hasColumn('examresults', 'verifiedDate')) {
                $table->date('verifiedDate')->nullable();
            }
        });

        DB::table('examresults')
            ->where('resultStatus', 'provisional')
            ->whereNull('verifiedBy')
            ->update(['resultStatus' => 'official']);

        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table('examresults', function (Blueprint $table) {
                $table->foreign('verifiedBy')
                    ->references('userId')->on('staffusers')
                    ->onDelete('set null')->onUpdate('cascade');
            });

            return;
        }

        // SQLite cannot add a foreign key to an existing table at all, so the suite's
        // schema carries the column without the constraint — the same trade §7.3's
        // `decidedBy` made, and `lane43-delete-guards.php` counts the MySQL keys.
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql' && Schema::hasColumn('examresults', 'verifiedBy')) {
            Schema::table('examresults', function (Blueprint $table) {
                $table->dropForeign(['verifiedBy']);
            });
        }

        Schema::table('examresults', function (Blueprint $table) {
            foreach (['verifiedDate', 'verifiedBy', 'resultStatus'] as $column) {
                if (Schema::hasColumn('examresults', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
