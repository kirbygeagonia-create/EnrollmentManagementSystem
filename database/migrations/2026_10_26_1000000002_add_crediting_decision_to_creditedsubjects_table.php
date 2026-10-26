<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A transferred subject is credited only when the department has said so, row by row.
 *
 * Prompt §7.3 asks for a formal crediting review with a decision on each prior subject.
 * Until now the system had no decision column: `processCredits()` created a
 * `creditedsubjects` row for every prior subject whose recorded grade fell inside the
 * passing band, and that row immediately subtracted a mandatory subject from the study
 * load (`EvaluationController.php`'s mandatory-block diff) and satisfied a prerequisite
 * (the same controller's `satisfiedPrerequisites()` and `EnrollmentReadiness`'s copy of
 * it). A matching title was therefore enough — which is exactly what §7.3 forbids: "Do
 * not automatically credit a course based only on a matching title."
 *
 * Ruled 2026-10-26 by the owner: the department decides. The account that records credits
 * already holds `evaluation.credits.process`, so the decision needs no new permission and
 * no second desk — it names the person who made it, the day it was made, and, for a
 * refusal, why. A row that is not approved stops counting everywhere while staying on the
 * record, because the prior subject and its grade belong to
 * `transferacademicrecords`, not to this table.
 *
 * `creditDecision` defaults to `approved` so the rows already on file keep their effect:
 * a decision column that silently switched twenty-nine live credits to "not counted" would
 * retract study loads the Registrar has already approved. Those rows are distinguishable
 * — `decidedBy`/`decidedDate` are null on a row this column backfilled, and no act
 * performed from here can leave both blank — so the backfill is visible rather than
 * disguised as a decision somebody signed.
 *
 * The three evidence columns on `transferacademicrecords` are §7.3's own list: a subject
 * code, the term or year it was taken, and the syllabus reference the equivalency was
 * argued from. All three are nullable — a school that has not produced the paper is not
 * made to invent one — and ruled into this batch rather than deferred, because the panel
 * that asks for a decision is the place that should show the evidence beside it.
 */
return new class extends Migration
{
    private const DECISIONS = ['approved', 'rejected'];

    public function up(): void
    {
        Schema::table('creditedsubjects', function (Blueprint $table) {
            if (! Schema::hasColumn('creditedsubjects', 'creditDecision')) {
                $table->enum('creditDecision', self::DECISIONS)
                    ->default('approved')
                    ->after('remarks');
            }

            if (! Schema::hasColumn('creditedsubjects', 'decidedBy')) {
                $table->integer('decidedBy')->nullable()->after('creditDecision');
            }

            if (! Schema::hasColumn('creditedsubjects', 'decidedDate')) {
                $table->date('decidedDate')->nullable()->after('decidedBy');
            }

            if (! Schema::hasColumn('creditedsubjects', 'decisionReason')) {
                $table->string('decisionReason', 500)->nullable()->after('decidedDate');
            }
        });

        Schema::table('transferacademicrecords', function (Blueprint $table) {
            foreach (['previousSubjectCode' => 50, 'termTaken' => 100, 'syllabusReference' => 255] as $column => $length) {
                if (! Schema::hasColumn('transferacademicrecords', $column)) {
                    $table->string($column, $length)->nullable()->after('subjectNameAtOldSchool');
                }
            }
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table('creditedsubjects', function (Blueprint $table) {
                $table->foreign('decidedBy')
                    ->references('userId')->on('staffusers')
                    ->onDelete('set null')->onUpdate('cascade');
            });

            return;
        }

        // SQLite cannot add a foreign key to an existing table at all, so the suite's
        // schema carries the column without the constraint — the same trade the refund
        // columns made, and `lane43-delete-guards.php` counts the MySQL keys.
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql' && Schema::hasColumn('creditedsubjects', 'decidedBy')) {
            Schema::table('creditedsubjects', function (Blueprint $table) {
                $table->dropForeign(['decidedBy']);
            });
        }

        Schema::table('creditedsubjects', function (Blueprint $table) {
            foreach (['decisionReason', 'decidedDate', 'decidedBy', 'creditDecision'] as $column) {
                if (Schema::hasColumn('creditedsubjects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('transferacademicrecords', function (Blueprint $table) {
            foreach (['syllabusReference', 'termTaken', 'previousSubjectCode'] as $column) {
                if (Schema::hasColumn('transferacademicrecords', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
