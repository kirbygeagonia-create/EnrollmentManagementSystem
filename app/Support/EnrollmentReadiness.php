<?php

namespace App\Support;

use App\Enums\ClearanceOverallStatus;
use App\Enums\EnrolledSubjectStatus;
use App\Enums\StudentType;
use App\Enums\SubmissionStatus;
use App\Models\Clearanceperiods;
use App\Models\Creditedsubjects;
use App\Models\Curriculums;
use App\Models\Curriculumsubjects;
use App\Models\Enrolledsubjects;
use App\Models\Enrollments;
use App\Models\Gradescale;
use App\Models\Studentclearances;

/**
 * The gates the Registrar reads before it signs, computed in one place.
 *
 * The desk checked that the department had signed, that a fee sheet existed, that
 * Accounting had been paid and that clearance was received — but not that the
 * applicant's required documents were actually verified, nor that the load being
 * approved is one the student is entitled to take. Both were computed elsewhere
 * (the Admission desk for documents, Department Evaluation for prerequisites) and
 * enforced only at those desks, so a record could arrive at the Registrar with an
 * unverified certificate and an unmet prerequisite and still be approvable.
 *
 * These are the predicates for those gates, shared by the policy that withholds
 * approval and the checklist that explains why, so the desk is never shown a green
 * light the server will not grant.
 */
final class EnrollmentReadiness
{
    /**
     * The clearance a continuing or shifter student must hold before the Registrar
     * signs, and — when it does not — the one sentence that says what is missing.
     *
     * Ruling 4 closes the silence that used to pass this gate: with no accepting
     * clearance window the check returned true, so a continuing student could be
     * approved without any office having cleared them, and the fewer windows existed
     * the more approvals went through. A student who has not been through the offices
     * in a window is not cleared, whether the window never opened or has since closed.
     * Ruling 5 adds the other half of the same fact: the slip also has to have been
     * confirmed at Department Evaluation, because that is the desk the student hands it
     * to. First-year and transferee students owe no clearance, so the gate is not theirs.
     *
     * The window is passed in by the queue (which resolves it once for every row) and
     * read fresh otherwise, exactly as the policy that enforces it does.
     *
     * @return array{passed: bool, reason: string|null}
     */
    public static function clearanceVerdict(Enrollments $enrollment, ?Clearanceperiods $window = null): array
    {
        if (! in_array($enrollment->studentType->value, [StudentType::Continuing->value, StudentType::Shifter->value], true)) {
            return ['passed' => true, 'reason' => null];
        }

        $window ??= Clearanceperiods::accepting()->first();

        if (! $window) {
            return ['passed' => false, 'reason' => 'No clearance window is accepting slips, so this student has no clearance the Registrar can read. Open or extend one in Clearance → Periods.'];
        }

        $clearance = Studentclearances::where('studentId', $enrollment->studentId)
            ->where('clearancePeriodId', $window->clearancePeriodId)
            ->first();

        if (! $clearance) {
            return ['passed' => false, 'reason' => 'The student has no clearance slip in the window the Registrar is reading (period '.$window->clearancePeriodId.').'];
        }

        if ($clearance->overallStatus !== ClearanceOverallStatus::Approved) {
            return ['passed' => false, 'reason' => 'The clearance is still with the offices — its overall status is '.$clearance->overallStatus->value.', not approved.'];
        }

        if (! $clearance->receivedBy || ! $clearance->receivedDate) {
            return ['passed' => false, 'reason' => 'The cleared slip was never received at the Registrar desk (BR34), so the paper copy is not on file.'];
        }

        // Ruling 5: the pass slip is confirmed at Department Evaluation, and nothing after
        // that may assert the student was cleared while the department says it never saw
        // the paper. The confirmation is not implied by the rows above — a slip can be
        // approved in the database and never have been handed over.
        if (! $enrollment->clearanceConfirmedBy || ! $enrollment->clearanceConfirmedAt) {
            return ['passed' => false, 'reason' => 'Department Evaluation has not confirmed this student\'s clearance pass slip, so the clearance-passed indicator is absent through the phases behind it.'];
        }

        return ['passed' => true, 'reason' => null];
    }

    /**
     * Every document the applicant's own admission required, verified.
     *
     * A record with no admission behind it (a continuing student readmitting, or a
     * student created directly at the Registrar) has nothing to verify here — the
     * documents were settled when they first enrolled, and there is no application
     * to re-open.
     */
    public static function documentsVerified(Enrollments $enrollment): bool
    {
        $admission = $enrollment->admission;

        if (! $admission) {
            return true;
        }

        return ! $admission->studentrequirementsubmissions()
            ->whereHas('requirement', fn ($query) => $query->where('isRequired', true))
            ->where('submissionStatus', '!=', SubmissionStatus::Verified->value)
            ->exists();
    }

    /**
     * No subject on the confirmed load sits behind a prerequisite the student has
     * not satisfied.
     *
     * The Evaluation desk refuses to *propose* such a subject in its UI, but that
     * is a lock on a button, not a rule on the record: an unmet load can reach this
     * point through a direct proposal, a retake, or a load decided before the
     * curriculum moved. So the Registrar reads the curriculum the enrollment is
     * pinned to, exactly as Evaluation does.
     */
    public static function prerequisitesMet(Enrollments $enrollment): bool
    {
        $curriculum = self::curriculumFor($enrollment);

        if (! $curriculum) {
            return true;
        }

        $confirmedSubjectIds = $enrollment->enrolledSubjects
            ->filter(fn ($subject) => $subject->status !== EnrolledSubjectStatus::Dropped)
            ->pluck('subjectId')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($confirmedSubjectIds === []) {
            return true;
        }

        $satisfied = self::satisfiedSubjectIds($enrollment);

        return ! Curriculumsubjects::where('curriculumId', $curriculum->curriculumId)
            ->where('yearLevel', $enrollment->yearLevel)
            ->whereIn('subjectId', $confirmedSubjectIds)
            ->whereNotNull('prerequisiteSubjectId')
            ->get()
            ->contains(fn ($row) => ! in_array((int) $row->prerequisiteSubjectId, $satisfied, true));
    }

    /**
     * The curriculum version the enrollment belongs to: the pinned one if it has
     * one, otherwise the newest for that course. Same rule Department Evaluation
     * applies, so the two desks cannot disagree about which catalogue is current.
     */
    private static function curriculumFor(Enrollments $enrollment): ?Curriculums
    {
        if ($enrollment->curriculumId) {
            return Curriculums::find($enrollment->curriculumId);
        }

        return Curriculums::where('courseId', $enrollment->courseId)
            ->when($enrollment->majorId, fn ($query) => $query->where('majorId', $enrollment->majorId))
            ->latest('effectiveYear')
            ->first() ?? Curriculums::where('courseId', $enrollment->courseId)->latest('effectiveYear')->first();
    }

    /**
     * Subjects the student has passed or been credited for. A grade counts as
     * passing against the maintained scale (PH convention: lower is better), and a
     * transfer credit counts without a grade.
     *
     * @return list<int>
     */
    private static function satisfiedSubjectIds(Enrollments $enrollment): array
    {
        $passingCeiling = Gradescale::passingCeiling();

        $passed = Enrolledsubjects::query()
            ->join('enrollments as e2', 'e2.enrollmentId', '=', 'enrolledsubjects.enrollmentId')
            ->where('e2.studentId', $enrollment->studentId)
            ->whereNotNull('enrolledsubjects.grade')
            ->groupBy('enrolledsubjects.subjectId')
            ->selectRaw('enrolledsubjects.subjectId, MIN(enrolledsubjects.grade) as best_grade')
            ->pluck('best_grade', 'subjectId')
            ->filter(fn ($grade) => (float) $grade <= $passingCeiling)
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->all();

        $credited = Creditedsubjects::where('enrollmentId', $enrollment->enrollmentId)
            ->pluck('creditedToSubjectId')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($passed, $credited)));
    }
}
