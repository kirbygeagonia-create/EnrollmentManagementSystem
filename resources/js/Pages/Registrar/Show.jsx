import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Link, useForm } from '@inertiajs/react';
import { PageHeader, Card, Badge, StepProgress, FormSection, ConfirmDialog } from '@/Components/ui';
import PrimaryButton from '@/Components/PrimaryButton';
import { useState, useMemo } from 'react';

const checklistSteps = [
    { key: 'evaluation_signed', label: 'Dept. Evaluation' },
    { key: 'assessment_completed', label: 'Assessment' },
    { key: 'payment_completed', label: 'Payment' },
    { key: 'clearance_verified', label: 'Clearance' },
    { key: 'registrarApprovalPending', label: 'Registrar' },
];

const workflowSteps = [
    { label: 'Dept. Evaluation', key: 'evaluation_signed' },
    { label: 'Assessment', key: 'assessment_completed' },
    { label: 'Payment', key: 'payment_completed' },
    { label: 'Registrar', key: 'registrarApprovalPending' },
    { label: 'Blocking', key: 'blocking' },
    { label: 'Clinic', key: 'clinic' },
    { label: 'ID', key: 'id' },
];

export default function Show({ enrollment, checklist, allValid }) {
    const [confirmOpen, setConfirmOpen] = useState(false);

    const form = useForm({
        _method: 'post',
    });

    const handleApprove = () => {
        setConfirmOpen(true);
    };

    const confirmApprove = () => {
        form.post(route('registrar.approve', { enrollment: enrollment.enrollmentId }), {
            onSuccess: () => setConfirmOpen(false),
            onError: () => setConfirmOpen(false),
        });
    };

    const termLabel = enrollment.term
        ? `${enrollment.term.semester?.value || enrollment.term.semester} ${enrollment.term.academicYear?.yearLabel || ''}`.trim()
        : '—';

    const studentName = enrollment.student
        ? `${enrollment.student.lastName}, ${enrollment.student.firstName}${enrollment.student.middleName ? ` ${enrollment.student.middleName.charAt(0)}.` : ''}${enrollment.student.suffix ? ` ${enrollment.student.suffix}` : ''}`
        : '—';

    const steps = useMemo(() => workflowSteps.map((step, index) => {
        const isCompleted = checklist[step.key] === true;
        const isCurrent = !isCompleted && Object.values(checklist).slice(0, index).every(v => v === true);
        let status = 'pending';
        if (isCompleted) status = 'completed';
        else if (isCurrent) status = 'current';
        return { label: step.label, status };
    }), [checklist]);

    const printLinks = [
        { label: 'Enrollment Certificate', route: 'registrar.print-certificate', icon: (
            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
        )},
        { label: 'Class Cards', route: 'registrar.print-class-cards', icon: (
            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
        )},
        { label: 'Subject Load', route: 'registrar.print-subject-load', icon: (
            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
        )},
    ];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Enrollment Review"
                    subtitle={`${studentName} — ${enrollment.course?.courseName || '—'}`}
                />
            }
        >
            <Head title={`Enrollment Review — ${studentName}`} />

            {/* Enrollment Info Card */}
            <Card className="mb-6">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <p className="text-sm text-brand-500">School ID</p>
                        <p className="font-mono font-medium text-brand-900">{enrollment.student?.schoolIdNumber || '—'}</p>
                    </div>
                    <div>
                        <p className="text-sm text-brand-500">Student Name</p>
                        <p className="font-medium text-brand-900">{studentName}</p>
                    </div>
                    <div>
                        <p className="text-sm text-brand-500">Course</p>
                        <p className="font-medium text-brand-900">{enrollment.course?.courseName || '—'}</p>
                    </div>
                    <div>
                        <p className="text-sm text-brand-500">Major</p>
                        <p className="font-medium text-brand-900">{enrollment.major?.majorName || '—'}</p>
                    </div>
                    <div>
                        <p className="text-sm text-brand-500">Year Level</p>
                        <p className="font-medium text-brand-900">{enrollment.yearLevel ? `${enrollment.yearLevel}${getYearSuffix(enrollment.yearLevel)} Year` : '—'}</p>
                    </div>
                    <div>
                        <p className="text-sm text-brand-500">Student Type</p>
                        <p className="font-medium text-brand-900">{formatStudentType(enrollment.studentType?.value || enrollment.studentType)}</p>
                    </div>
                    <div>
                        <p className="text-sm text-brand-500">Term</p>
                        <p className="font-medium text-brand-900">{termLabel}</p>
                    </div>
                    <div>
                        <p className="text-sm text-brand-500">Enrollment Status</p>
                        <Badge tone={getStatusTone(enrollment.enrollmentStatus?.value || enrollment.enrollmentStatus)}>
                            {formatStatus(enrollment.enrollmentStatus?.value || enrollment.enrollmentStatus)}
                        </Badge>
                    </div>
                </div>
            </Card>

            {/* Validation Checklist */}
            <Card className="mb-6">
                <FormSection title="Validation Checklist" subtitle="All items must be completed before approval">
                    <StepProgress steps={steps} className="mb-6" />

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {checklistSteps.map((item) => (
                            <div key={item.key} className="flex items-center gap-3 p-3 bg-brand-50 rounded-lg">
                                <div className={`w-6 h-6 rounded-full flex items-center justify-center ${checklist[item.key] ? 'bg-success-100 text-success-600' : 'bg-brand-200 text-brand-400'}`}>
                                    {checklist[item.key] ? (
                                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7" />
                                        </svg>
                                    ) : (
                                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    )}
                                </div>
                                <span className="text-sm font-medium text-brand-900">{item.label}</span>
                            </div>
                        ))}
                    </div>
                </FormSection>
            </Card>

            {/* Enrolled Subjects */}
            <Card className="mb-6">
                <FormSection title="Enrolled Subjects" subtitle={`${enrollment.enrolledSubjects?.length || 0} subject(s)`}>
                    {enrollment.enrolledSubjects && enrollment.enrolledSubjects.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="data-table data-table-striped">
                                <thead>
                                    <tr>
                                        <th>Subject Code</th>
                                        <th>Subject Name</th>
                                        <th>Units</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {enrollment.enrolledSubjects.map((es, index) => (
                                        <tr key={index}>
                                            <td className="font-mono text-sm">{es.subject?.subjectCode || '—'}</td>
                                            <td>{es.subject?.subjectName || '—'}</td>
                                            <td>{getTotalUnits(es.subject)}</td>
                                            <td>
                                                <Badge tone={getSubjectStatusTone(es.status?.value || es.status)}>
                                                    {formatStatus(es.status?.value || es.status)}
                                                </Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="empty-state">
                            <svg className="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p className="empty-state-message">No enrolled subjects found.</p>
                        </div>
                    )}
                </FormSection>
            </Card>

            {/* Actions */}
            <Card>
                <div className="flex flex-col sm:flex-row gap-4">
                    <div className="flex-1">
                        <h3 className="font-medium text-brand-900 mb-3">Print Documents</h3>
                        <div className="flex flex-wrap gap-2">
                            {printLinks.map((link) => (
                                <Link
                                    key={link.route}
                                    href={route(link.route, { enrollment: enrollment.enrollmentId })}
                                    target="_blank"
                                    className="btn btn-outline btn-sm flex items-center gap-2"
                                >
                                    {link.icon}
                                    <span>{link.label}</span>
                                </Link>
                            ))}
                        </div>
                    </div>

                    <div className="flex items-end sm:items-center sm:justify-end">
                        {checklist.registrarApprovalPending && (
                            <>
                                <ConfirmDialog
                                    isOpen={confirmOpen}
                                    onClose={() => setConfirmOpen(false)}
                                    onConfirm={confirmApprove}
                                    title="Approve Enrollment"
                                    message="This will mark the enrollment as enrolled and confirm all proposed subjects. This action cannot be undone."
                                    confirmText="Approve"
                                    confirmClassName="btn-primary"
                                />
                                <PrimaryButton
                                    onClick={handleApprove}
                                    disabled={!allValid || form.processing}
                                    className="w-full sm:w-auto"
                                >
                                    {form.processing ? 'Processing...' : 'Approve Enrollment'}
                                </PrimaryButton>
                            </>
                        )}
                        {!checklist.registrarApprovalPending && (
                            <Badge tone="info" className="text-sm">
                                Not pending registrar approval
                            </Badge>
                        )}
                    </div>
                </div>
            </Card>
        </AuthenticatedLayout>
    );
}

function getYearSuffix(year) {
    if (year === 1) return 'st';
    if (year === 2) return 'nd';
    if (year === 3) return 'rd';
    return 'th';
}

function formatStudentType(type) {
    if (!type) return '—';
    return type
        .replace(/([A-Z])/g, ' $1')
        .replace(/^./, str => str.toUpperCase());
}

function formatStatus(status) {
    if (!status) return '—';
    return status.charAt(0).toUpperCase() + status.slice(1).toLowerCase();
}

function getStatusTone(status) {
    const toneMap = {
        pending: 'pending',
        evaluated: 'evaluated',
        assessed: 'assessed',
        paid: 'paid',
        enrolled: 'enrolled',
        dropped: 'dropped',
    };
    return toneMap[status] || 'neutral';
}

function getSubjectStatusTone(status) {
    const toneMap = {
        proposed: 'pending',
        confirmed: 'enrolled',
        dropped: 'dropped',
    };
    return toneMap[status] || 'neutral';
}

function getTotalUnits(subject) {
    if (!subject) return '—';
    const lecture = parseFloat(subject.lectureUnits) || 0;
    const lab = parseFloat(subject.labUnits) || 0;
    const total = lecture + lab;
    return total > 0 ? `${total} (${lecture} lec / ${lab} lab)` : '—';
}