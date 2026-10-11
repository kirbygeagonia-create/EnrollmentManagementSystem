<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\OfficeId;
use App\Enums\WorkflowStatus;
use App\Enums\WorkflowStepStatus;
use App\Events\WorkflowStepSigned;
use App\Exceptions\InvalidStateTransitionException;
use App\Exceptions\WorkflowAlreadySignedException;
use App\Models\Enrollments;
use App\Models\Enrollmentstatushistory;
use App\Models\Enrollmentworkflow;
use App\Models\Staffusers;
use App\Models\Workflowsteps;
use Illuminate\Support\Facades\DB;

class WorkflowService
{
    /**
     * The label of each workflow box, keyed by the office that signs it.
     *
     * Keyed by office rather than step order because stepOrder is positional:
     * the Assessment box is removed for returning students, so an order-keyed
     * label would describe a different box depending on the student type.
     *
     * @return array<int, string>
     */
    public static function stepLabels(): array
    {
        return [
            OfficeId::Guidance->value => 'Department Evaluation',
            OfficeId::Scholarship->value => 'Assessment',
            OfficeId::Accounting->value => 'Accounting Payment',
            OfficeId::Registrar->value => 'Registrar Approval',
            OfficeId::Blocking->value => 'Blocking and Scheduling',
            OfficeId::Clinic->value => 'Clinic',
            OfficeId::IdOffice->value => 'ID Validation',
        ];
    }

    /**
     * Build the ordered workflow steps for an enrollment.
     *
     * Assessment (officeId 3) is included only for firstYear and transferee students. Continuing
     * and shifter students skip it: their clearance carries a signed Scholarship-office line, but no
     * clearance code reads studentscholarships — an office attests, no data is checked, §28 C-7 asks.
     *
     * @return array<int, array{0: int, 1: string}> [officeId, label]
     */
    private function stepsFor(Enrollments $enrollment): array
    {
        $includesAssessment = in_array($enrollment->studentType->value, ['firstYear', 'transferee'], true);

        $boxes = [
            OfficeId::Guidance->value,
            OfficeId::Scholarship->value,
            OfficeId::Accounting->value,
            OfficeId::Registrar->value,
            OfficeId::Blocking->value,
            OfficeId::Clinic->value,
            OfficeId::IdOffice->value,
        ];

        if (! $includesAssessment) {
            $boxes = array_values(array_filter(
                $boxes,
                fn (int $officeId) => $officeId !== OfficeId::Scholarship->value
            ));
        }

        $labels = self::stepLabels();

        return array_map(fn (int $officeId) => [$officeId, $labels[$officeId]], $boxes);
    }

    /**
     * Create the enrollment workflow for a new enrollment.
     *
     * Step 1 is Department Evaluation. Assessment (step 2) is included only
     * for firstYear and transferee students.
     */
    public function createWorkflow(Enrollments $enrollment): Enrollmentworkflow
    {
        return DB::transaction(function () use ($enrollment) {
            $workflow = Enrollmentworkflow::create([
                'enrollmentId' => $enrollment->enrollmentId,
                'currentStep' => 1,
                'workflowStatus' => WorkflowStatus::InProgress,
            ]);

            foreach ($this->stepsFor($enrollment) as $order => [$officeId, $label]) {
                Workflowsteps::create([
                    'workflowId' => $workflow->workflowId,
                    'officeId' => $officeId,
                    'stepOrder' => $order + 1,
                    'stepStatus' => WorkflowStepStatus::Pending,
                ]);
            }

            return $workflow;
        });
    }

    /**
     * The enrollment status a box leaves behind when it is signed, for the four boxes
     * that move the record at all. A box absent from this map (Blocking, Clinic, ID)
     * certifies something the enrollment status does not carry, so nothing on the record
     * can show it came back, and a second signature on it is refused outright.
     */
    private const STAGE_AFTER_BOX = [
        OfficeId::Guidance->value => EnrollmentStatus::Evaluated,
        OfficeId::Scholarship->value => EnrollmentStatus::Assessed,
        OfficeId::Accounting->value => EnrollmentStatus::Paid,
        OfficeId::Registrar->value => EnrollmentStatus::Enrolled,
    ];

    /**
     * The pipeline in desk order. `dropped` is deliberately absent: a dropped record has
     * not fallen back to a box, it has left the pipeline, and no box may be re-signed on it.
     */
    private const PIPELINE_ORDER = [
        EnrollmentStatus::Pending,
        EnrollmentStatus::ReturnedToEvaluation,
        EnrollmentStatus::Evaluated,
        EnrollmentStatus::Assessed,
        EnrollmentStatus::Paid,
        EnrollmentStatus::Enrolled,
    ];

    /**
     * Has this record demonstrably fallen back below the box it is being signed into again?
     *
     * Proof is the record's own status history, not its present status: a desk that pays a
     * reopened account writes the new status *before* it signs the box, so reading the live
     * status here would refuse the very case the owner allowed. So the question is answered
     * from history — did the enrollment, at or after the moment this box was first signed,
     * move to a status earlier in the pipeline than the one this box produces?
     *
     * True for the account closed by a receipt, reopened by a refund and collected again.
     * False for the same desk acting twice on a record that never moved, which is the case
     * the guard exists for, and false for every box that does not move the status at all.
     */
    private function recordFellBackBelowTheBox(Enrollmentworkflow $workflow, Workflowsteps $step): bool
    {
        $produces = self::STAGE_AFTER_BOX[$step->officeId] ?? null;

        if ($produces === null || $step->signedDate === null) {
            return false;
        }

        $whereTheBoxLeftIt = array_search($produces, self::PIPELINE_ORDER, true);

        if ($whereTheBoxLeftIt === false) {
            return false;
        }

        $moves = Enrollmentstatushistory::where('enrollmentId', $workflow->enrollmentId)
            ->where('changedAt', '>=', $step->signedDate)
            ->pluck('toStatus');

        foreach ($moves as $toStatus) {
            $status = $toStatus instanceof EnrollmentStatus ? $toStatus : EnrollmentStatus::tryFrom((string) $toStatus);
            $whereItCameBackTo = array_search($status, self::PIPELINE_ORDER, true);

            if ($whereItCameBackTo !== false && $whereItCameBackTo < $whereTheBoxLeftIt) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sign a workflow step. Only the assigned office can sign.
     * Steps must be signed in order (BR13/BR14), and one signing is the most a box takes.
     *
     * @throws InvalidStateTransitionException
     * @throws WorkflowAlreadySignedException
     */
    public function signStep(
        Enrollmentworkflow $workflow,
        int $stepOrder,
        Staffusers $signedBy,
        string $status = 'completed'
    ): Workflowsteps {
        // Find the step
        $step = $workflow->workflowsteps()
            ->where('stepOrder', $stepOrder)
            ->firstOrFail();

        // Ruled 2026-10-11: a box that is closed does not open again because a desk
        // pressed its button twice. This method stamped whatever it was handed, so a
        // second act on an enrollment whose office box another officer had already
        // signed replaced that signature's name and date in place and fired a second
        // WorkflowStepSigned for one signing.
        //
        // The one exception the owner allowed is a record that has genuinely fallen
        // back below the box — today an account closed by a receipt, reopened by a
        // refund, and collected again (ruling 14 keeps the refund from un-signing the
        // box, so the second collection would otherwise be impossible and the payment
        // would roll back with it). See recordFellBackBelowTheBox() for what counts as
        // proof, and the audit observer for the trace: the updated row on workflowsteps
        // carries the first signer and date in oldValues.
        if ($step->stepStatus === WorkflowStepStatus::Completed && ! $this->recordFellBackBelowTheBox($workflow, $step)) {
            $signer = $step->signedBy()->first();
            $signerName = $signer === null ? 'an officer who no longer has an account' : $signer->name;
            $signedWhen = $step->signedDate === null ? 'an unrecorded time' : $step->signedDate->format('F j, Y \a\t g:i a');

            throw new WorkflowAlreadySignedException(
                [
                    'office' => self::stepLabels()[$step->officeId] ?? "Office {$step->officeId}",
                    'signedBy' => $signerName,
                    'signedDate' => $signedWhen,
                    'enrollment' => (string) $workflow->enrollmentId,
                ],
                "Workflow step {$stepOrder} (office {$step->officeId}) of enrollment {$workflow->enrollmentId} was already signed"
                    ." by {$signerName} on {$signedWhen}; the record has not fallen back below that box, so refusing to overwrite it."
            );
        }

        // Check office scope (BR14)
        $isAcademicSigner = $step->officeId === OfficeId::Guidance->value && (
            in_array($signedBy->role->value, ['dean', 'programHead', 'instructor'])
            || $signedBy->hasRole(['Dean', 'ProgramHead', 'Instructor', 'DeptEvaluator'])
            || $signedBy->unitId !== null
        );

        // Item 3 write-boundary: only SysAdmin may sign across offices. The
        // Admin role is read-everywhere with NO mutation permissions
        // (RbacSeeder), and every HTTP path into signStep authorizes first
        // with an office-scoped permission Admin lacks — so including Admin
        // here inverted the boundary at the one choke point every step write
        // routes through, and would hand Admin a silent cross-office write
        // the moment a caller forgets its authorize() call.
        if ($step->officeId !== $signedBy->officeId && ! $isAcademicSigner && ! $signedBy->hasRole('SysAdmin')) {
            throw new InvalidStateTransitionException(
                "Staff member {$signedBy->name} cannot sign step {$stepOrder} (requires office {$step->officeId})."
            );
        }

        // Check step order (BR13)
        if ($stepOrder > 1) {
            $prevStep = $workflow->workflowsteps()
                ->where('stepOrder', $stepOrder - 1)
                ->first();

            if (! $prevStep || $prevStep->stepStatus->value !== 'completed') {
                throw new InvalidStateTransitionException(
                    "Step {$stepOrder} cannot be signed: step ".($stepOrder - 1).' is not yet completed.'
                );
            }
        }

        // Sign the step
        $step->stepStatus = WorkflowStepStatus::from($status);
        $step->signedBy = $signedBy->userId;
        $step->signedDate = now();
        $step->save();

        event(new WorkflowStepSigned($workflow, $step, $signedBy));

        // Update workflow current step
        $workflow->currentStep = $stepOrder;
        $workflow->save();

        // Check if all steps are done
        $remaining = $workflow->workflowsteps()
            ->where('stepStatus', '!=', WorkflowStepStatus::Completed->value)
            ->count();

        if ($remaining === 0) {
            $workflow->workflowStatus = WorkflowStatus::Completed;
            $workflow->save();
        }

        return $step->fresh();
    }

    /**
     * Sign the workflow step belonging to a specific office.
     * Layout-independent: resolves the office's step by officeId rather than stepOrder.
     * Returns null when the office has no step in this workflow (e.g. Assessment
     * is skipped for continuing/shifter students).
     *
     * @throws InvalidStateTransitionException
     */
    public function signStepByOffice(
        Enrollmentworkflow $workflow,
        int $officeId,
        Staffusers $signedBy,
        string $status = 'completed'
    ): ?Workflowsteps {
        $step = $workflow->workflowsteps()
            ->where('officeId', $officeId)
            ->orderBy('stepOrder')
            ->first();

        if (! $step) {
            return null;
        }

        return $this->signStep($workflow, $step->stepOrder, $signedBy, $status);
    }

    /**
     * Get the current pending step for a workflow.
     */
    public function getCurrentStep(Enrollmentworkflow $workflow): ?Workflowsteps
    {
        return $workflow->workflowsteps()
            ->where('stepStatus', WorkflowStepStatus::Pending->value)
            ->orderBy('stepOrder')
            ->first();
    }

    /**
     * Get all steps for a workflow.
     */
    public function getSteps(Enrollmentworkflow $workflow): array
    {
        return $workflow->workflowsteps()
            ->orderBy('stepOrder')
            ->get()
            ->toArray();
    }
}
