import { usePage } from '@inertiajs/react';
import Badge from './Badge';
import StepProgress from './StepProgress';
import { formatStatusLabel } from './statusLabel';
import { useWorkflowBoxes } from './deskPhase';
import { formatSchoolDay } from '@/Components/ui/calendarDay';

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
 * Each box is named and ordered by the workflow's own declaration (`workflowPhases`, shared
 * from WorkflowService::boxOrder()), not from the offices table: the first box is the
 * Department Evaluation phase even though the Guidance office is the one that signs it.
 *
 * Boxes a student type never enters — Assessment for a continuing or shifter student — are
 * printed as Not applicable rather than vanishing, so the tracker answers "where is this
 * student" against every box on the form. Their numbers, though, come from the boxes the
 * record holds: a continuing student's Registrar box is the third of six, and printing the
 * form's canonical fourth-of-seven beside it would be a second answer to the same question.
 */
export default function WorkflowStepper({ workflow, enrollment }) {
    const boxes = useWorkflowBoxes();
    const { workflowReturnOfficeId = null } = usePage().props;

    if (!workflow) return <p className="text-sm text-brand-500">No workflow created yet.</p>;

    const stored = (workflow.workflowsteps || [])
        .slice()
        .sort((a, b) => a.stepOrder - b.stepOrder);

    if (stored.length === 0) return <p className="text-sm text-brand-500">Workflow has no steps yet.</p>;

    const byOffice = new Map(stored.map((step) => [Number(step.officeId), step]));
    const isReturned = statusValue(enrollment?.enrollmentStatus) === 'returnedToEvaluation';
    const isReturnedToAdmission = statusValue(enrollment?.enrollmentStatus) === 'returnedToAdmission';
    const currentStepOrder = stored.find((step) => step.stepStatus === 'pending')?.stepOrder;

    // The record's own numbering: the boxes it holds, counted in the order it holds them.
    const positions = new Map(stored.map((step, index) => [Number(step.officeId), index + 1]));

    const steps = boxes.map((box) => {
        const officeId = Number(box.officeId);
        const step = byOffice.get(officeId);

        if (!step) return { officeId, label: box.label, status: 'notApplicable', meta: null, position: null };

        const isCurrent = step.stepOrder === currentStepOrder;
        const status = step.stepStatus === 'completed'
            ? 'completed'
            : isCurrent ? 'current' : 'pending';
        const isMarkedReturned = isReturned && officeId === Number(workflowReturnOfficeId);

        return {
            officeId,
            label: box.label,
            status: isMarkedReturned ? 'returned' : status,
            meta: step,
            position: positions.get(officeId) ?? null,
        };
    });

    const current = steps.find((s) => s.status === 'current') || steps.find((s) => s.status === 'returned');
    const doneCount = steps.filter((s) => s.status === 'completed').length;

    const summary = isReturnedToAdmission
        // §6.4 (gap G-10): Admission holds no box on this form, so the tracker has nothing to mark as
        // returned — and "Phase 1 of 7" would be a true reading of the boxes and a false one of the
        // record. The work restarts here only after that desk approves the application again.
        ? 'Returned to Admission — the application is back with that desk, and every box on this form restarts once it approves again.'
        : isReturned && current
            ? `Returned to ${current.label}${enrollment?.returnReason ? ` — ${enrollment.returnReason}` : ''}.`
            : doneCount === stored.length
            ? 'Every phase on this enrollment form is signed.'
            : current
                ? `Phase ${current.position} of ${stored.length} — ${current.label}.`
                : 'Waiting for the first signature.';

    return (
        <div>
            <StepProgress steps={steps.map(({ label, status, position }) => ({ label, status, number: position }))} />
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
                                    ? `${meta.signedBy.firstName} ${meta.signedBy.lastName} — ${meta.signedDate ? formatSchoolDay(meta.signedDate) : ''}`
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
