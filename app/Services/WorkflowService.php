<?php

namespace App\Services;

use App\Models\Enrollments;
use App\Models\Enrollmentworkflow;
use App\Models\Workflowsteps;
use App\Models\Staffusers;
use App\Enums\WorkflowStatus;
use App\Enums\WorkflowStepStatus;
use Illuminate\Support\Facades\DB;

class WorkflowService
{
    /**
     * The 8-step enrollment workflow with officeId mappings.
     * stepOrder => [officeId, label]
     */
    private const STEPS = [
        1 => [1, 'Clearance Desk Receipt'],          // Registrar
        2 => [4, 'Department Evaluation'],            // Guidance/Dept
        3 => [3, 'Assessment'],                       // Accounting/Scholarship
        4 => [2, 'Accounting Payment'],               // Accounting
        5 => [1, 'Registrar Approval'],               // Registrar
        6 => [5, 'Blocking and Scheduling'],          // Academic Dept (Blocking)
        7 => [11, 'Clinic'],                          // Clinic
        8 => [22, 'ID Office'],                       // ID Office
    ];

    /**
     * Create the 8-step workflow for a new enrollment.
     */
    public function createWorkflow(Enrollments $enrollment): Enrollmentworkflow
    {
        return DB::transaction(function () use ($enrollment) {
            $workflow = Enrollmentworkflow::create([
                'enrollmentId'  => $enrollment->enrollmentId,
                'currentStep'   => 1,
                'workflowStatus' => WorkflowStatus::InProgress,
            ]);

            foreach (self::STEPS as $order => [$officeId, $label]) {
                Workflowsteps::create([
                    'workflowId'  => $workflow->workflowId,
                    'officeId'    => $officeId,
                    'stepOrder'   => $order,
                    'stepStatus'  => $order === 1 ? WorkflowStepStatus::Pending : WorkflowStepStatus::Pending,
                ]);
            }

            return $workflow;
        });
    }

    /**
     * Sign a workflow step. Only the assigned office can sign.
     * Steps must be signed in order (BR13/BR14).
     *
     * @throws \App\Exceptions\InvalidStateTransitionException
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
        if ($step->officeId !== $signedBy->officeId) {
            throw new \App\Exceptions\InvalidStateTransitionException(
                "Staff from office {$signedBy->officeId} cannot sign step {$stepOrder} (requires office {$step->officeId})."
            );
        }

        // Check step order (BR13)
        if ($stepOrder > 1) {
            $prevStep = $workflow->workflowsteps()
                ->where('stepOrder', $stepOrder - 1)
                ->first();

            if (!$prevStep || $prevStep->stepStatus->value !== 'completed') {
                throw new \App\Exceptions\InvalidStateTransitionException(
                    "Step {$stepOrder} cannot be signed: step " . ($stepOrder - 1) . " is not yet completed."
                );
            }
        }

        // Sign the step
        $step->stepStatus = WorkflowStepStatus::from($status);
        $step->signedBy = $signedBy->userId;
        $step->signedDate = now();
        $step->save();

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