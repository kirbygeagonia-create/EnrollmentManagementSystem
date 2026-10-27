<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The return record development prompt §6.4 asks for (gap G-10, ruled by the owner 2026-10-26).
 *
 * §6.4 lists six facts every return must carry: the originating office and step, the destination
 * office and step, a reason category and a mandatory explanatory note, the user and the moment, the
 * previous and the new status, and the correction expected with the office that must act next. The
 * state machine already writes five of them into `enrollmentstatushistory` — from, to, who, when and
 * the remarks — but it writes them as *prose* in `remarks`, and a note cannot be counted, filtered,
 * or checked against a destination. So this table stores the same act with each fact in its own
 * column, and the history row still goes through the state machine unchanged: the two are written in
 * one transaction and say the same thing, which is what makes a return auditable instead of narrated.
 *
 * Three decisions worth their reason here.
 *
 * **Origin and destination are office ids, not `offices` foreign keys.** `workflowsteps.officeId` is
 * the same kind of column — a step names the desk that owns it without holding a key into the
 * reference table — and a return row is a statement about a moment, not a child that should veto an
 * office's deletion. The destination is additionally stored as a category-style enum so the two
 * destinations the owner ruled (Admission, Department Evaluation) are the only values a row can hold,
 * and `destinationOfficeId` is derived from that enum in one place rather than typed by the desk.
 *
 * **Step numbers, not step ids.** A workflow's boxes are addressed by `stepOrder` throughout
 * `WorkflowService`, and a returned record's boxes are the same rows; storing the order keeps the row
 * readable even for a workflow built before this table existed. Null when the destination is
 * Admission, because Admission holds no box on the enrollment's own form.
 *
 * **`originOfficeId` is nullable, and `staffusers.officeId` is why.** That column is nullable on the
 * schema (`create_staffusers_table`, and measured on live `ems` as `IS_NULLABLE = YES`), so an
 * administrator seated with no office is a legal account — and a NOT NULL origin would answer the act
 * it performs with a `QueryException` and the generic "conflicts with information already on file"
 * page, which names no record and no column. The alternative — writing some office for it — would put
 * a desk in the audit trail that never signed anything. So the null is kept and the desk that reads
 * the trail is told the return came from an account with no office of its own.
 *
 * **`enrollmentStatus` gains `returnedToAdmission`.** Department Evaluation already had its state
 * from ruling 14; a record sent to a desk that holds no box must not read as though the department
 * had simply not evaluated it yet. The widening is written with `->change()` on *both* drivers,
 * because SQLite compiles `enum()` into a table `CHECK` constraint — a MySQL-only guard would leave
 * the suite unable to write the new value at all.
 */
return new class extends Migration
{
    private const STATUSES = [
        'pending',
        'evaluated',
        'assessed',
        'paid',
        'returnedToEvaluation',
        'returnedToAdmission',
        'enrolled',
        'dropped',
    ];

    private const CATEGORIES = [
        'missingDocuments',
        'examinationFailure',
        'assessmentOrFeeHold',
        'curriculumSectionMismatch',
    ];

    private const DESTINATIONS = ['admission', 'departmentEvaluation'];

    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->enum('enrollmentStatus', self::STATUSES)->change();
        });

        Schema::create('enrollmentreturns', function (Blueprint $table) {
            $table->id('returnId');
            $table->integer('enrollmentId');
            $table->integer('workflowId')->nullable();
            $table->integer('originOfficeId')->nullable();
            $table->integer('originStepOrder')->nullable();
            $table->enum('destination', self::DESTINATIONS);
            $table->integer('destinationOfficeId');
            $table->integer('destinationStepOrder')->nullable();
            $table->enum('returnCategory', self::CATEGORIES);
            $table->string('returnNote', 500);
            $table->string('expectedCorrection', 500);
            $table->string('previousStatus', 30);
            $table->string('newStatus', 30);
            $table->integer('returnedBy')->nullable();
            $table->dateTime('returnedAt');

            $table->index(['enrollmentId', 'returnedAt']);
            $table->index('returnCategory');

            $table->foreign('enrollmentId')->references('enrollmentId')->on('enrollments');
            $table->foreign('workflowId')->references('workflowId')->on('enrollmentworkflow');
            $table->foreign('returnedBy')->references('userId')->on('staffusers')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('enrollmentreturns');
        Schema::enableForeignKeyConstraints();

        Schema::table('enrollments', function (Blueprint $table) {
            $table->enum('enrollmentStatus', array_values(array_diff(self::STATUSES, ['returnedToAdmission'])))->change();
        });
    }
};
