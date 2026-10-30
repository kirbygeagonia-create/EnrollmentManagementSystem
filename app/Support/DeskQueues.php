<?php

namespace App\Support;

use App\Enums\AdmissionStatus;
use App\Enums\ClearanceOverallStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\OfficeId;
use App\Enums\WorkflowStepStatus;
use App\Models\Admissions;
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
 * dashboard's badges are built from them; the desk screens still build their own lists inline, and what keeps the
 * two honest is `tests/Feature/Dashboard/DashboardQueueCountMatchesDeskTest.php`, which reads each desk's own
 * printed number and requires the badge to equal it — including the college case, which is the half an
 * unbounded account cannot see. Moving each list onto these methods is the next step, and until then the equality
 * is a tested agreement, not a shared call.
 *
 * **Only two of these take a desk, and that is the boundary as it is built, not an oversight.** `admission` and
 * `evaluation` honour `CollegeScope` because `AdmissionController` and `EvaluationController` bound their own
 * lists with it. The other six carry no parameter because no list behind them is bounded: `AssessmentController`,
 * `AccountingController`, `RegistrarController`, `ClinicController`, `IDController` and `ClearanceController`
 * contain no `CollegeScope` call and their policies ask none of the desk. A badge bounded while its list is not
 * would be the same §8 contradiction in the opposite direction, so the six stay institution-wide. Measured on
 * live `ems`: the only college-bound accounts are six `office7` teachers (`dept.caf`, `dept.cjc`, `dept.cbg`,
 * `dept.ctic`, `dept.cee`, `dept.cte`) holding evaluation and admission rights alone, so today no bounded account
 * can open any of the six desks — the question *should those desks be college-bounded* is dormant, not live, and
 * is recorded as G-46's second half rather than answered by a signature that implies a scope nobody applies.
 *
 * That is the shape the workflow monitor already proves: `WorkflowInbox::nextOffice()` is asked by both the list
 * and the bell, so that pair cannot disagree.
 *
 * The `blocking` queue deliberately has no method here. Its badge counts enrollments whose first open box is
 * Blocking, while `BlockingController::index()` lists timetable blocks — two different subjects, and which one the
 * card should name is the school's question, not a filter to unify. It is item 9 on the 2026-10-29 decision sheet;
 * the docx register does not carry that question yet, and this comment does not claim it does.
 */
class DeskQueues
{
    /**
     * Applications awaiting the Admission desk, inside the college boundary the desk's own list honours.
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
     * from opposite ends.
     *
     * @return Builder<Studentassessments>
     */
    private static function assessmentsFor(EnrollmentStatus $status): Builder
    {
        return Studentassessments::query()
            ->whereHas('enrollment', fn ($q) => $q->where('enrollmentStatus', $status->value));
    }

    /**
     * Enrollments at the first open box of one office.
     *
     * Public because the Clinic and ID desks build their own queues from this same rule, and the dashboard card
     * that opens one of those desks must be the desk's set rather than a second copy of it. The `blocking`
     * badge is the one caller outside those two desks that asks this question of its own office; its card is a
     * school question (sheet item 9), but the predicate it is waiting on is not.
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
}
