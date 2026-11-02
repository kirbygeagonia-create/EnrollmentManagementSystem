<?php

namespace App\Support;

use App\Enums\AdmissionStatus;
use App\Enums\ClearanceOverallStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\OfficeId;
use App\Enums\WorkflowStepStatus;
use App\Models\Admissions;
use App\Models\Blocks;
use App\Models\Enrollments;
use App\Models\Staffusers;
use App\Models\Studentassessments;
use App\Models\Studentclearances;
use Illuminate\Database\Eloquent\Builder;

/**
 * One definition of each dashboard queue, so a badge and the list underneath it cannot disagree.
 *
 * Prompt §8: "Queue counts, status badges, filters, and visible records must use consistent business filters. A
 * dashboard count must not contradict the list underneath it." Until now `DashboardController::queueCounts()`
 * restated each desk's filters as its own query and drifted on three counts: `admission` and `evaluation` were
 * institution-wide while the pages behind them are bounded by the desk's college, `evaluation` counted only
 * `Pending` while the desk also lists what the Registrar sent back, and `assessment`/`accounting`/`id` counted
 * enrollments or requests where their pages list assessment sheets and enrollment rows.
 *
 * These builders carry the *population* only: no eager loading, no search, no ordering, no pagination. The
 * dashboard's badges are built from them, and so now are seven desk lists — `AssessmentController`,
 * `RegistrarController`, `AccountingController` (its queue base), `EvaluationController`, `ClinicController`,
 * `IDController` and `BlockingController` each call the method below that names their rows, so the card and the list
 * are one call rather than two copies that a test merely watches. `AdmissionController` and `ClearanceController`
 * still write their own
 * queries and the docblock of each method below says why: their cards name a SUBSET (`Pending` applications,
 * `Pending` clearances) while the desk lists rows it may still act on beyond that subset, so unifying them would
 * hide records rather than align numbers. `DashboardQueueCountMatchesDeskTest` compares those two badges to the
 * desk's own tile for exactly that reason — the card counts a queue, the list counts rows. Nothing is left to move
 * except by that rule: `blocking` joined on 2026-11-02, when the owner ruled that its card counts the blocks its own
 * page lists rather than the enrollments waiting at that office.
 *
 * **Only two of these take a desk, and that is the boundary as it is built, not an oversight.** `admission` and
 * `evaluation` honour `CollegeScope` because `AdmissionController` and `EvaluationController` bound their own
 * lists with it. The other seven carry no parameter because no list behind them is bounded: `AssessmentController`,
 * `AccountingController`, `RegistrarController`, `ClinicController`, `IDController`, `ClearanceController` and
 * `BlockingController` contain no `CollegeScope` call and their policies ask none of the desk. A badge bounded while
 * its list is not
 * would be the same §8 contradiction in the opposite direction, so the seven stay institution-wide. Measured on
 * live `ems`: the only college-bound accounts are six `office7` teachers (`dept.caf`, `dept.cjc`, `dept.cbg`,
 * `dept.ctic`, `dept.cee`, `dept.cte`) holding evaluation and admission rights alone, so today no bounded account
 * can open any of the seven desks — the question *should those desks be college-bounded* is dormant, not live, and
 * is recorded as G-46's second half rather than answered by a signature that implies a scope nobody applies.
 *
 * That is the shape the workflow monitor already proves: `WorkflowInbox::nextOffice()` is asked by both the list
 * and the bell, so that pair cannot disagree.
 */
class DeskQueues
{
    /**
     * Applications awaiting the Admission desk, inside the college boundary the desk's own list honours.
     *
     * The list does NOT come from here, and that is a decision rather than debt: this call counts `Pending` while
     * `AdmissionController::index()` shows the submitted cases *and* the saved drafts of every status, because a
     * draft is work the desk has not finished rather than a row it may ignore. Building the page from this builder
     * would hide rows from the desk — §8 with the sign flipped, and the same reason `clearance()` is left alone.
     *
     * @return Builder<Admissions>
     */
    public static function admission(?Staffusers $desk): Builder
    {
        return CollegeScope::ofAdmissions(
            Admissions::query(),
            $desk
        )->where('admissionStatus', AdmissionStatus::Pending->value);
    }

    /**
     * Enrollments this desk must evaluate — fresh ones and the ones the Registrar sent back, which the desk has
     * always listed and the badge never counted.
     *
     * `EvaluationController::index()` builds its queue from this call, so the card and the list are one predicate
     * rather than two copies a test merely watches (§28.3 G-46).
     *
     * @return Builder<Enrollments>
     */
    public static function evaluation(?Staffusers $desk): Builder
    {
        return CollegeScope::ofEnrollments(
            Enrollments::query(),
            $desk
        )->whereIn('enrollmentStatus', [
            EnrollmentStatus::Pending->value,
            EnrollmentStatus::ReturnedToEvaluation->value,
        ]);
    }

    /**
     * The fee sheets the Assessment desk works, which is a different set from "enrollments evaluated": an
     * evaluated enrollment with no sheet yet is the `pendingEvaluations` list on that same page.
     *
     * @return Builder<Studentassessments>
     */
    public static function assessment(): Builder
    {
        return self::assessmentsFor(EnrollmentStatus::Evaluated);
    }

    /**
     * Accounting's default queue: the assessed accounts that still owe. The page's other tab holds the settled
     * ones, and its own toggle counts both from this same base.
     *
     * @return Builder<Studentassessments>
     */
    public static function accounting(): Builder
    {
        return self::assessmentsFor(EnrollmentStatus::Assessed)
            ->where('remainingBalance', '>', 0);
    }

    /**
     * What the Registrar approves: payment collected or settled, both of which the desk lists together.
     *
     * @return Builder<Enrollments>
     */
    public static function registrar(): Builder
    {
        return Enrollments::query()->whereIn('enrollmentStatus', [
            EnrollmentStatus::Assessed->value,
            EnrollmentStatus::Paid->value,
        ]);
    }

    /**
     * Enrollments whose first open box is the clinic. "First" is not decoration: an enrollment with both the
     * blocking and the clinic box open belongs to whichever desk is ahead, never to both.
     *
     * @return Builder<Enrollments>
     */
    public static function clinic(): Builder
    {
        return self::waitingAtFirstPendingBox(OfficeId::Clinic);
    }

    /**
     * Enrollments whose first open box is the ID desk — the same set the page's own "Awaiting validation" tab
     * counts, which is why the badge previously read higher than the list it opens.
     *
     * @return Builder<Enrollments>
     */
    public static function id(): Builder
    {
        return self::waitingAtFirstPendingBox(OfficeId::IdOffice);
    }

    /**
     * Clearance rows awaiting any office, the set the desk's own status tiles break down.
     *
     * @return Builder<Studentclearances>
     */
    public static function clearance(): Builder
    {
        return Studentclearances::query()
            ->where('overallStatus', ClearanceOverallStatus::Pending->value);
    }

    /**
     * Assessment sheets whose enrollment sits in one status. Shared by the two desks that read the same table
     * from opposite ends, and PUBLIC because those desks now build their own lists from here too: until this batch
     * the agreement between a badge and the list under it was only ever *tested*, and two copies of one predicate
     * drift the way `DashboardController::queueCounts()` did (§28.3 G-46). The fee-sheet desk asks for `Evaluated`
     * and the cashier asks for `Assessed`; the cashier's own queue base is this call without the balance filter,
     * because its page holds both the owing tab and the settled one and its badge names only the first.
     *
     * @return Builder<Studentassessments>
     */
    public static function assessmentsFor(EnrollmentStatus $status): Builder
    {
        return Studentassessments::query()
            ->whereHas('enrollment', fn ($q) => $q->where('enrollmentStatus', $status->value));
    }

    /**
     * Enrollments at the first open box of one office.
     *
     * Public because the Clinic and ID desks build their own queues from this same rule, and the dashboard card
     * that opens one of those desks must be the desk's set rather than a second copy of it. The `blocking` badge
     * called this method until 2026-11-02, when the owner ruled that its card names the blocks its page lists;
     * the office's waiting enrollments remain what the bell addresses, so the call stays here for the two desks
     * that list them.
     *
     * The raw subquery is portable SQL on purpose: the suite runs this on SQLite and the desk runs it on MySQL.
     *
     * @return Builder<Enrollments>
     */
    public static function waitingAtFirstPendingBox(OfficeId $office): Builder
    {
        return Enrollments::query()
            ->where('enrollmentStatus', EnrollmentStatus::Enrolled->value)
            ->whereHas('enrollmentworkflow.workflowsteps', fn ($q) => $q
                ->where('stepStatus', WorkflowStepStatus::Pending->value)
                ->where('officeId', $office->value)
                ->whereRaw('stepOrder = (SELECT MIN(ws.stepOrder) FROM workflowsteps ws WHERE ws.workflowId = workflowsteps.workflowId AND ws.stepStatus = ?)', [WorkflowStepStatus::Pending->value])
            );
    }

    /**
     * The timetable blocks the Blocking desk lists.
     *
     * Ruled 2026-11-02 as item 9 of the 2026-10-29 sheet: the card counts **what its own page lists**. Until then
     * the badge counted `waitingAtFirstPendingBox(OfficeId::Blocking)` — enrollments whose first open box is that
     * office — while `BlockingController::index()` lists blocks with their capacity and seats. The two numbers
     * answer different questions, and the one a coordinator read before clicking was the one that did not match the
     * rows waiting to be acted on. This is now the ninth card to obey the rule the other eight already do.
     *
     * No filter and no `CollegeScope`, because the page applies neither: its only narrowing comes from the desk's
     * own search, course, term and year pickers, and `blocking.viewAny` bounds the *card* rather than the rows.
     * A badge narrowed while its list is not is §8 with the sign flipped, which is the same reason `assessment` and
     * `accounting` carry no desk parameter.
     *
     * The enrollments figure has not been deleted. `waitingAtFirstPendingBox(OfficeId::Blocking)` is still what the
     * workflow bell addresses and what `WorkflowInbox::nextOffice()` walks, so it stays where it is read rather than
     * being painted on a card whose page shows blocks.
     *
     * @return Builder<Blocks>
     */
    public static function blocking(): Builder
    {
        return Blocks::query();
    }
}
