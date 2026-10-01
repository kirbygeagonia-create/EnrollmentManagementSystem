import { usePage } from '@inertiajs/react';
import Badge from './Badge';
import StepProgress from './StepProgress';
import { formatStatusLabel } from './statusLabel';

// Workflow step status → badge tone
const stepStatusTone = {
    completed: 'success',
    current: 'pending',
    pending: 'pending',
    returned: 'danger',
    skipped: 'neutral',
    notApplicable: 'neutral',
};

const statusValue = (status) => (typeof status === 'object' && status !== null ? status.value : status);

/**
 * The enrollment workflow — the digital twin of the paper workflow form.
 *
 * Each box is named from the workflow's own phase vocabulary (`workflowPhases`,
 * shared from WorkflowService::stepLabels()), not from the offices table: the
 * first box is the Department Evaluation phase even though the Guidance office
 * is the one that signs it.
 *
 * Boxes a student type never enters — Assessment for a continuing or shifter
 * student — are printed as Not applicable rather than vanishing, so the tracker
 * answers "where is this student" against every box on the form.
 */
export default function WorkflowStepper({ workflow, enrollment }) {
    const { workflowPhases = {}, workflowReturnOfficeId = null } = usePage().props;

    if (!workflow) return <p className="text-sm text-brand-500">No workflow created yet.</p>;

    const stored = (workflow.workflowsteps || [])
        .slice()
        .sort((a, b) => a.stepOrder - b.stepOrder);

    if (stored.length === 0) return <p className="text-sm text-brand-500">Workflow has no steps yet.</p>;

    const byOffice = new Map(stored.map((step) => [Number(step.officeId), step]));
    const isReturned = statusValue(enrollment?.enrollmentStatus) === 'returnedToEvaluation';
    const currentStepOrder = stored.find((step) => step.stepStatus === 'pending')?.stepOrder;

    const steps = Object.entries(workflowPhases).map(([officeIdKey, label], index) => {
        const officeId = Number(officeIdKey);
        const step = byOffice.get(officeId);

        if (!step) return { officeId, label, status: 'notApplicable', meta: null, position: index + 1 };

        let status = step.stepStatus === 'completed'
            ? 'completed'
            : step.stepOrder === currentStepOrder ? 'current' : 'pending';

        if (isReturned && officeId === Number(workflowReturnOfficeId)) status = 'returned';

        return { officeId, label, status, meta: step, position: index + 1 };
    });

    const boxes = steps.filter((s) => s.meta);
    const current = steps.find((s) => s.status === 'current') || steps.find((s) => s.status === 'returned');
    const doneCount = boxes.filter((s) => s.status === 'completed').length;

    const summary = isReturned && current
        ? `Returned to ${current.label}${enrollment?.returnReason ? ` — ${enrollment.returnReason}` : ''}.`
        : boxes.length > 0 && doneCount === boxes.length
            ? 'Every phase on this enrollment form is signed.'
            : current
                ? `Phase ${boxes.findIndex((s) => s.officeId === current.officeId) + 1} of ${boxes.length} — ${current.label}.`
                : 'Waiting for the first signature.';

    return (
        <div>
            <StepProgress steps={steps.map(({ label, status }) => ({ label, status }))} />
            <p className="mt-3 text-xs font-semibold text-brand-700">{summary}</p>
            <div className="mt-5 space-y-2">
                {steps.map(({ officeId, label, status, meta }) => (
                    <div key={officeId} className="flex items-center justify-between text-sm border-b border-brand-100 pb-2 last:border-0 last:pb-0">
                        <div className="flex items-center gap-2">
                            <Badge tone={stepStatusTone[status]}>{formatStatusLabel(status)}</Badge>
                            <span className="text-brand-900 font-medium">{label}</span>
                        </div>
                        <div className="text-brand-500 text-right">
                            {status === 'returned'
                                ? enrollment?.returnReason || 'Awaiting correction'
                                : meta?.signedBy
                                    ? `${meta.signedBy.firstName} ${meta.signedBy.lastName} — ${meta.signedDate ? new Date(meta.signedDate).toLocaleDateString('en-PH') : ''}`
                                    : meta
                                        ? 'Awaiting signature'
                                        : 'Not part of this student type'}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
