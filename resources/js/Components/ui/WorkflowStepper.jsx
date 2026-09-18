import Badge from './Badge';
import StepProgress from './StepProgress';

// Workflow step status → badge tone
const stepStatusTone = {
    completed: 'success',
    pending: 'pending',
    skipped: 'neutral',
};

/**
 * The enrollment workflow — the digital twin of the paper workflow form.
 * Renders the ordered steps with signed/pending states: only the FIRST
 * pending step is "current"; every later pending step stays "pending".
 */
export default function WorkflowStepper({ workflow }) {
    if (!workflow) return <p className="text-sm text-brand-500">No workflow created yet.</p>;

    const sortedSteps = (workflow.workflowsteps || [])
        .slice()
        .sort((a, b) => a.stepOrder - b.stepOrder);

    // Only the FIRST pending step is "current"; every later pending step stays "pending".
    const currentStepOrder = sortedSteps.find((step) => step.stepStatus === 'pending')?.stepOrder;

    const steps = sortedSteps.map((step) => ({
        label: step.office?.officeName || `Step ${step.stepOrder}`,
        status: step.stepStatus === 'completed' ? 'completed'
            : step.stepStatus === 'skipped' ? 'skipped'
            : step.stepOrder === currentStepOrder ? 'current'
            : 'pending',
        meta: step,
    }));

    if (steps.length === 0) return <p className="text-sm text-brand-500">Workflow has no steps yet.</p>;

    return (
        <div>
            <StepProgress steps={steps.map(({ label, status }) => ({ label, status }))} />
            <div className="mt-5 space-y-2">
                {steps.map(({ label, status, meta }) => (
                    <div key={meta.stepOrder} className="flex items-center justify-between text-sm border-b border-brand-100 pb-2 last:border-0 last:pb-0">
                        <div className="flex items-center gap-2">
                            <Badge tone={stepStatusTone[status]}>
                                {status.charAt(0).toUpperCase() + status.slice(1)}
                            </Badge>
                            <span className="text-brand-900 font-medium">{label}</span>
                        </div>
                        <div className="text-brand-500">
                            {meta.signedBy
                                ? `${meta.signedBy.firstName} ${meta.signedBy.lastName} — ${meta.signedDate ? new Date(meta.signedDate).toLocaleDateString('en-PH') : ''}`
                                : 'Awaiting signature'}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
