<?php

namespace App\Services;

use App\Enums\WorkflowStatus;
use App\Enums\WorkflowStepStatus;
use App\Events\WorkflowStepSigned;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Enrollments;
use App\Models\Enrollmentworkflow;
use App\Models\Staffusers;
use App\Models\Workflowsteps;
use Illuminate\Support\Facades\DB;

class WorkflowService
{
    /**
     * Build the ordered workflow steps for an enrollment.
     *
     * Assessment (officeId 3) is included only for firstYear and transferee
     * students. Continuing and shifter students skip it: their scholarship
     * status was already settled by the clearance that precedes enrollment.
     *
     * @return array<int, array{0: int, 1: string}> [officeId, label]
     */
    private function stepsFor(Enrollments $enrollment): array
    {
        $includesAssessment = in_array($enrollment->studentType->value, ['firstYear', 'transferee'], true);

        $steps = [
            1 => [4, 'Department Evaluation'],
            2 => [3, 'Assessment'],
            3 => [2, 'Accounting Payment'],
            4 => [1, 'Registrar Approval'],
            5 => [5, 'Blocking and Scheduling'],
            6 => [11, 'Clinic'],
            7 => [22, 'ID Office'],
        ];

        if (! $includesAssessment) {
            unset($steps[2]);
        }

        return array_values($steps);
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
     * Sign a workflow step. Only the assigned office can sign.
     * Steps must be signed in order (BR13/BR14).
     *
     * @throws InvalidStateTransitionException
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

        // Check office scope (BR14)
        $isAcademicSigner = $step->officeId === 4 && (
            in_array($signedBy->role?->value, ['dean', 'programHead', 'instructor'])
            || $signedBy->hasRole(['Dean', 'ProgramHead', 'Instructor', 'DeptEvaluator'])
            || $signedBy->unitId !== null
        );

        if ($step->officeId !== $signedBy->officeId && ! $isAcademicSigner && ! $signedBy->hasRole(['SysAdmin', 'Admin'])) {
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
            ->where('stepStatus', '!=', 'completed')
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
            ->where('stepStatus', 'pending')
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
