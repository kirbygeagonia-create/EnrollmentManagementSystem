import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { PageHeader, Card, Badge, StepProgress, EmptyState } from '@/Components/ui';
import { useMemo } from 'react';

const stepStatusTone = {
    completed: 'success',
    pending: 'pending',
    skipped: 'neutral',
};

function WorkflowStepper({ workflow }) {
    if (!workflow) return <p className="text-sm text-brand-500">No workflow created yet.</p>;

    const steps = (workflow.workflowsteps || [])
        .sort((a, b) => a.stepOrder - b.stepOrder)
        .map((step) => ({
            label: step.office?.officeName || `Step ${step.stepOrder}`,
            status: step.stepStatus === 'completed' ? 'completed'
                : step.stepStatus === 'skipped' ? 'skipped'
                : 'current',
            meta: step,
        }));

    if (steps.length === 0) return <p className="text-sm text-brand-500">Workflow has no steps yet.</p>;

    return (
        <div>
            <StepProgress steps={steps.map(({ label, status }) => ({ label, status }))} />
            <div className="mt-4 space-y-2">
                {steps.map(({ label, status, meta }) => (
                    <div key={meta.stepOrder} className="flex items-center justify-between text-sm">
                        <div className="flex items-center gap-2">
                            <Badge tone={stepStatusTone[status]}>
                                {status.charAt(0).toUpperCase()}
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

function EnrollmentCard({ enrollment }) {
    const termLabel = enrollment.term
        ? `${enrollment.term.semester?.value || enrollment.term.semester} ${enrollment.term.academicYear?.yearLabel || ''}`.trim()
        : '—';

    return (
        <Card className="mb-6">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                <div>
                    <h3 className="font-heading font-semibold text-brand-900">
                        {enrollment.course?.courseName || '—'}
                        {enrollment.major?.majorName ? ` — ${enrollment.major.majorName}` : ''}
                    </h3>
                    <p className="text-sm text-brand-500">
                        {termLabel} · {enrollment.studentType?.value || enrollment.studentType || '—'} ·{' '}
                        {enrollment.enrollmentType?.value || enrollment.enrollmentType || '—'}
                    </p>
                </div>
                <Badge tone={enrollment.enrollmentStatus}>
                    {enrollment.enrollmentStatus?.charAt(0).toUpperCase() + enrollment.enrollmentStatus?.slice(1)}
                </Badge>
            </div>

            <WorkflowStepper workflow={enrollment.enrollmentworkflow} />

            <div className="mt-4 pt-4 border-t border-brand-100">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <p className="text-brand-500">Assessment</p>
                        <p className="font-medium text-brand-900">
                            {enrollment.studentassessments?.totalAssessment
                                ? `₱${Number(enrollment.studentassessments.totalAssessment).toLocaleString('en-PH')}`
                                : '—'}
                        </p>
                    </div>
                    <div>
                        <p className="text-brand-500">Paid</p>
                        <p className="font-medium text-brand-900">
                            {enrollment.payments?.length
                                ? `₱${enrollment.payments.reduce((sum, p) => sum + Number(p.amount || 0), 0).toLocaleString('en-PH')}`
                                : '—'}
                        </p>
                    </div>
                    <div>
                        <p className="text-brand-500">Subjects</p>
                        <p className="font-medium text-brand-900">{enrollment.enrolledsubjects?.length || 0} enrolled</p>
                    </div>
                </div>
            </div>
        </Card>
    );
}

export default function Show({ student }) {
    const studentName = useMemo(
        () => `${student.lastName}, ${student.firstName}${student.middleName ? ` ${student.middleName.charAt(0)}.` : ''}${student.suffix ? ` ${student.suffix}` : ''}`,
        [student]
    );

    const enrollments = [...(student.enrollments || [])].sort((a, b) => b.enrollmentId - a.enrollmentId);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={`${studentName}`}
                    subtitle={student.schoolIdNumber ? `School ID: ${student.schoolIdNumber}` : 'Student 360'}
                    actions={
                        <Link href={route('students.index')} className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900">
                            ← Back to Search
                        </Link>
                    }
                />
            }
        >
            <Head title={`Student 360 — ${studentName}`} />

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                    {/* Student Info */}
                    <Card>
                        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 text-sm">
                            <div>
                                <p className="text-brand-500">School ID</p>
                                <p className="font-mono font-medium text-brand-900">{student.schoolIdNumber || '—'}</p>
                            </div>
                            <div>
                                <p className="text-brand-500">Gender</p>
                                <p className="font-medium text-brand-900">{student.gender ? student.gender.charAt(0).toUpperCase() + student.gender.slice(1) : '—'}</p>
                            </div>
                            <div>
                                <p className="text-brand-500">Birthdate</p>
                                <p className="font-medium text-brand-900">{student.birthdate ? new Date(student.birthdate).toLocaleDateString('en-PH') : '—'}</p>
                            </div>
                            <div>
                                <p className="text-brand-500">Contact</p>
                                <p className="font-medium text-brand-900">{student.contactNumber || '—'}</p>
                            </div>
                            <div>
                                <p className="text-brand-500">Email</p>
                                <p className="font-medium text-brand-900">{student.email || '—'}</p>
                            </div>
                            <div>
                                <p className="text-brand-500">Status</p>
                                <p className="font-medium text-brand-900 capitalize">{student.status || '—'}</p>
                            </div>
                        </div>
                        {student.addresses?.length > 0 && (
                            <div className="mt-4 pt-4 border-t border-brand-100 text-sm">
                                <p className="text-brand-500">Address</p>
                                <p className="font-medium text-brand-900">
                                    {student.addresses.map((a) => [a.addressLine1, a.city, a.province, a.zipCode].filter(Boolean).join(', ')).join('; ')}
                                </p>
                            </div>
                        )}
                    </Card>

                    {/* Admissions */}
                    <div>
                        <h2 className="font-heading font-semibold text-brand-900 text-lg mb-3">Admissions</h2>
                        {student.admissions?.length ? (
                            <Card>
                                <div className="space-y-3">
                                    {student.admissions.map((ad) => (
                                        <div key={ad.admissionId} className="flex items-center justify-between text-sm border-b border-brand-100 pb-3 last:border-0 last:pb-0">
                                            <div>
                                                <p className="font-medium text-brand-900">{ad.course?.courseName || '—'}</p>
                                                <p className="text-brand-500">
                                                    {ad.applicantType || '—'} · {ad.submittedDate ? new Date(ad.submittedDate).toLocaleDateString('en-PH') : ''}
                                                </p>
                                            </div>
                                            <Badge tone={ad.admissionStatus || 'neutral'}>
                                                {ad.admissionStatus?.charAt(0).toUpperCase() + ad.admissionStatus?.slice(1)}
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                            </Card>
                        ) : (
                            <EmptyState title="No admissions" message="No admission records for this student." />
                        )}
                    </div>

                    {/* Enrollment Workflows */}
                    <div>
                        <h2 className="font-heading font-semibold text-brand-900 text-lg mb-3">Enrollment Workflows</h2>
                        {enrollments.length ? (
                            enrollments.map((enrollment) => (
                                <EnrollmentCard key={enrollment.enrollmentId} enrollment={enrollment} />
                            ))
                        ) : (
                            <EmptyState title="No enrollments" message="No enrollment records for this student." />
                        )}
                    </div>

                    {/* Clearances */}
                    <div>
                        <h2 className="font-heading font-semibold text-brand-900 text-lg mb-3">Clearances</h2>
                        {student.studentclearances?.length ? (
                            <Card>
                                <div className="space-y-3">
                                    {student.studentclearances.map((sc) => (
                                        <div key={sc.studentClearanceId} className="flex items-center justify-between text-sm border-b border-brand-100 pb-3 last:border-0 last:pb-0">
                                            <div>
                                                <p className="font-medium text-brand-900">
                                                    {sc.clearancePeriod?.periodName || `Period #${sc.clearancePeriodId || ''}`}
                                                </p>
                                                <p className="text-brand-500">
                                                    {sc.receivedDate ? `Received ${new Date(sc.receivedDate).toLocaleDateString('en-PH')}` : 'Not yet received'}
                                                </p>
                                            </div>
                                            <Badge tone={sc.overallStatus || 'neutral'}>
                                                {sc.overallStatus?.charAt(0).toUpperCase() + sc.overallStatus?.slice(1)}
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                            </Card>
                        ) : (
                            <EmptyState title="No clearances" message="No clearance records for this student." />
                        )}
                    </div>

                    {/* Exam Results */}
                    {student.examresults?.length > 0 && (
                        <div>
                            <h2 className="font-heading font-semibold text-brand-900 text-lg mb-3">Exam Results</h2>
                            <Card>
                                <div className="space-y-3">
                                    {student.examresults.map((er) => (
                                        <div key={er.examId} className="flex items-center justify-between text-sm border-b border-brand-100 pb-3 last:border-0 last:pb-0">
                                            <div>
                                                <p className="font-medium text-brand-900">
                                                    {er.examStage} · {er.examType}
                                                </p>
                                                <p className="text-brand-500">{er.examDate ? new Date(er.examDate).toLocaleDateString('en-PH') : ''}</p>
                                            </div>
                                            <Badge tone={er.examResult === 'pass' ? 'success' : er.examResult === 'fail' ? 'danger' : 'neutral'}>
                                                {er.examResult?.toUpperCase() || '—'}
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                            </Card>
                        </div>
                    )}

                    {/* IDs & Scholarships */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <div>
                            <h2 className="font-heading font-semibold text-brand-900 text-lg mb-3">IDs</h2>
                            {student.studentids?.length ? (
                                <Card>
                                    <div className="space-y-3">
                                        {student.studentids.map((id) => (
                                            <div key={id.idId} className="flex items-center justify-between text-sm border-b border-brand-100 pb-3 last:border-0 last:pb-0">
                                                <p className="font-medium text-brand-900">
                                                    Issued {id.issueDate ? new Date(id.issueDate).toLocaleDateString('en-PH') : '—'}
                                                </p>
                                                <Badge tone={id.validationStatus === 'valid' ? 'success' : 'neutral'}>
                                                    {id.validationStatus || '—'}
                                                </Badge>
                                            </div>
                                        ))}
                                    </div>
                                </Card>
                            ) : (
                                <EmptyState title="No IDs" message="No ID records for this student." />
                            )}
                        </div>

                        <div>
                            <h2 className="font-heading font-semibold text-brand-900 text-lg mb-3">Scholarships</h2>
                            {student.studentscholarships?.length ? (
                                <Card>
                                    <div className="space-y-3">
                                        {student.studentscholarships.map((ss) => (
                                            <div key={ss.studentScholarshipId} className="flex items-center justify-between text-sm border-b border-brand-100 pb-3 last:border-0 last:pb-0">
                                                <p className="font-medium text-brand-900">
                                                    {ss.scholarshipType?.scholarshipName || `Scholarship #${ss.scholarshipTypeId}`}
                                                </p>
                                                <Badge tone={ss.status === 'approved' ? 'success' : ss.status === 'pending' ? 'pending' : 'neutral'}>
                                                    {ss.status?.charAt(0).toUpperCase() + ss.status?.slice(1)}
                                                </Badge>
                                            </div>
                                        ))}
                                    </div>
                                </Card>
                            ) : (
                                <EmptyState title="No scholarships" message="No scholarship records for this student." />
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}