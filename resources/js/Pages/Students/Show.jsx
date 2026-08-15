import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { PageHeader, Card, Badge, StepProgress, EmptyState, StatCard, FormSection } from '@/Components/ui';
import { useMemo } from 'react';

const stepStatusTone = {
    completed: 'success',
    pending: 'pending',
    skipped: 'neutral',
};

const studentStatusToneMap = {
    active: 'success',
    inactive: 'neutral',
    graduated: 'info',
    dropped: 'dropped',
};

const enrollmentStatusToneMap = {
    pending: 'pending',
    evaluated: 'evaluated',
    assessed: 'assessed',
    paid: 'paid',
    enrolled: 'enrolled',
    dropped: 'dropped',
};

const clearanceStatusToneMap = {
    pending: 'pending',
    approved: 'approved',
    rejected: 'rejected',
    waived: 'waived',
    incomplete: 'incomplete',
};

const scholarshipStatusToneMap = {
    approved: 'approved',
    pending: 'pending',
    rejected: 'rejected',
};

/**
 * Map a course/college name to the matching college logo.
 * Conservative keyword matching — falls back to the school logo.
 */
function collegeLogoFor(courseName, collegeName) {
    const haystack = `${courseName || ''} ${collegeName || ''}`.toLowerCase();
    const map = [
        { keys: ['agriculture', 'fisheries', 'agri', 'fishery'], logo: 'college-of-agriculture-and-fisheries.jpg' },
        { keys: ['business', 'good governance', 'accountancy', 'management'], logo: 'college-of-business-and-good-governance.jpg' },
        { keys: ['criminal', 'criminology', 'justice'], logo: 'college-of-criminal-justice-education.jpg' },
        { keys: ['information', 'communication', 'technology', 'ict', 'computer', 'programming'], logo: 'college-of-information-and-communication-technology.jpg' },
        { keys: ['teacher', 'education', 'teaching', 'beed', 'bsed'], logo: 'college-of-teacher-education.jpg' },
        { keys: ['civil engineering', 'engineering'], logo: 'department-of-civil-engineering.jpg' },
    ];
    for (const entry of map) {
        if (entry.keys.some((k) => haystack.includes(k))) {
            return `/images/logos/${entry.logo}`;
        }
    }
    return '/images/logos/seait-logo.png';
}

function formatStatus(status) {
    if (!status) return '—';
    return status.charAt(0).toUpperCase() + status.slice(1);
}

function getInitials(student) {
    const first = student?.firstName?.[0] || '';
    const last = student?.lastName?.[0] || '';
    return (first + last).toUpperCase() || '—';
}

function WorkflowStepper({ workflow }) {
    if (!workflow) return <p className="text-sm text-brand-500">No workflow created yet.</p>;

    const steps = (workflow.workflowsteps || [])
        .slice()
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

function EnrollmentCard({ enrollment }) {
    const termLabel = enrollment.term
        ? `${enrollment.term.semester?.value || enrollment.term.semester} ${enrollment.term.academicYear?.yearLabel || ''}`.trim()
        : '—';

    const courseName = enrollment.course?.courseName || '';
    const logo = collegeLogoFor(courseName);

    const totalAssessment = enrollment.studentassessments?.totalAssessment
        ? Number(enrollment.studentassessments.totalAssessment)
        : null;
    const totalPaid = enrollment.payments?.length
        ? enrollment.payments.reduce((sum, p) => sum + Number(p.amount || 0), 0)
        : 0;
    const balance = totalAssessment != null ? totalAssessment - totalPaid : null;
    const subjectsCount = enrollment.enrolledsubjects?.length || 0;

    return (
        <Card className="mb-6">
            <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-4">
                <div className="flex items-start gap-4">
                    <div className="flex-shrink-0 h-12 w-12 rounded-card bg-white border border-brand-100 shadow-card overflow-hidden flex items-center justify-center p-1">
                        <img src={logo} alt="College logo" className="max-h-full max-w-full object-contain" />
                    </div>
                    <div>
                        <h3 className="font-heading font-semibold text-brand-900 text-lg">
                            {courseName || '—'}
                            {enrollment.major?.majorName ? ` — ${enrollment.major.majorName}` : ''}
                        </h3>
                        <p className="text-sm text-brand-500 mt-0.5">
                            {termLabel} · {enrollment.studentType?.value || enrollment.studentType || '—'} ·{' '}
                            {enrollment.enrollmentType?.value || enrollment.enrollmentType || '—'}
                        </p>
                    </div>
                </div>
                <Badge tone={enrollmentStatusToneMap[enrollment.enrollmentStatus] || enrollment.enrollmentStatus || 'neutral'}>
                    {formatStatus(enrollment.enrollmentStatus)}
                </Badge>
            </div>

            <WorkflowStepper workflow={enrollment.enrollmentworkflow} />

            <div className="mt-5 pt-4 border-t border-brand-100">
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <div>
                        <p className="text-brand-500 text-xs uppercase tracking-wider">Assessment</p>
                        <p className="font-semibold text-brand-900 mt-0.5">
                            {totalAssessment != null ? `₱${totalAssessment.toLocaleString('en-PH')}` : '—'}
                        </p>
                    </div>
                    <div>
                        <p className="text-brand-500 text-xs uppercase tracking-wider">Paid</p>
                        <p className="font-semibold text-brand-900 mt-0.5">
                            {totalPaid > 0 ? `₱${totalPaid.toLocaleString('en-PH')}` : '—'}
                        </p>
                    </div>
                    <div>
                        <p className="text-brand-500 text-xs uppercase tracking-wider">Balance</p>
                        <p className={`font-semibold mt-0.5 ${balance != null && balance > 0 ? 'text-danger-700' : 'text-success-700'}`}>
                            {balance != null ? `₱${balance.toLocaleString('en-PH')}` : '—'}
                        </p>
                    </div>
                    <div>
                        <p className="text-brand-500 text-xs uppercase tracking-wider">Subjects</p>
                        <p className="font-semibold text-brand-900 mt-0.5">{subjectsCount} enrolled</p>
                    </div>
                </div>
            </div>
        </Card>
    );
}

export default function Show({ student }) {
    const studentName = useMemo(
        () => `${student.lastName}, ${student.firstName}${student.middleName ? ` ${student.middleName.charAt(0)}.` : ''}${student.suffix ? ` ${student.suffix}` : ''}`.trim(),
        [student]
    );

    const enrollments = useMemo(
        () => [...(student.enrollments || [])].sort((a, b) => b.enrollmentId - a.enrollmentId),
        [student.enrollments]
    );

    // Derive course/college for the identity header from the most recent enrollment
    const latestEnrollment = enrollments[0];
    const primaryCourseName = latestEnrollment?.course?.courseName || '';
    const primaryMajorName = latestEnrollment?.major?.majorName || '';
    const identityLogo = collegeLogoFor(primaryCourseName);

    // Status overview for StatCards
    const latestStatus = latestEnrollment?.enrollmentStatus || student.status || '—';
    const latestClearance = student.studentclearances?.[0];
    const clearanceStatus = latestClearance?.overallStatus || '—';
    const latestScholarship = student.studentscholarships?.[0];
    const scholarshipStatus = latestScholarship?.status || '—';

    const totalAssessed = enrollments.reduce((sum, e) => sum + (e.studentassessments?.totalAssessment ? Number(e.studentassessments.totalAssessment) : 0), 0);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title={studentName}
                    subtitle={student.schoolIdNumber ? `School ID: ${student.schoolIdNumber} · Complete 8-Phase Enrollment Record` : 'Student 360 Record'}
                    logo={identityLogo}
                    logoAlt="Institutional Record"
                    phaseBadge="Central 360"
                    officeBadge="Unified Academic History"
                    actions={
                        <Link href={route('students.index')} className="btn btn-secondary btn-sm">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Back to Directory
                        </Link>
                    }
                />
            }
        >
            <Head title={`Student 360 — ${studentName}`} />

            <div className="space-y-6">
                {/* Identity Header Card */}
                <Card>
                    <div className="flex flex-col lg:flex-row lg:items-center gap-6">
                        {/* Avatar + crest */}
                        <div className="flex items-center gap-5 flex-shrink-0">
                            <div className="relative">
                                <div className="h-20 w-20 rounded-card bg-gradient-to-br from-seait-500 to-seait-600 text-white flex items-center justify-center text-2xl font-bold font-heading shadow-card ring-4 ring-seait-100">
                                    {getInitials(student)}
                                </div>
                                <div className="absolute -bottom-2 -right-2 h-9 w-9 rounded-card bg-white border border-brand-100 shadow-card overflow-hidden flex items-center justify-center p-0.5">
                                    <img src={identityLogo} alt="College logo" className="max-h-full max-w-full object-contain" />
                                </div>
                            </div>
                            <div className="lg:hidden">
                                <h2 className="font-heading font-bold text-brand-900 text-xl leading-tight">{studentName}</h2>
                                <p className="font-mono text-sm text-seait-700 mt-0.5">{student.schoolIdNumber || '—'}</p>
                            </div>
                        </div>

                        {/* Identity fields */}
                        <div className="flex-1 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-4">
                            <FormSection label="School ID">
                                <p className="font-mono font-medium text-brand-900">{student.schoolIdNumber || '—'}</p>
                            </FormSection>
                            <FormSection label="Course">
                                <p className="font-medium text-brand-900">{primaryCourseName || '—'}</p>
                            </FormSection>
                            <FormSection label="Major">
                                <p className="font-medium text-brand-900">{primaryMajorName || '—'}</p>
                            </FormSection>
                            <FormSection label="Gender">
                                <p className="font-medium text-brand-900 capitalize">{student.gender || '—'}</p>
                            </FormSection>
                            <FormSection label="Birthdate">
                                <p className="font-medium text-brand-900">{student.birthdate ? new Date(student.birthdate).toLocaleDateString('en-PH') : '—'}</p>
                            </FormSection>
                            <FormSection label="Contact">
                                <p className="font-medium text-brand-900">{student.contactNumber || '—'}</p>
                            </FormSection>
                            <FormSection label="Email">
                                <p className="font-medium text-brand-900 truncate">{student.email || '—'}</p>
                            </FormSection>
                            <FormSection label="Status">
                                <Badge tone={studentStatusToneMap[student.status] || 'neutral'}>
                                    {formatStatus(student.status)}
                                </Badge>
                            </FormSection>
                        </div>
                    </div>

                    {student.addresses?.length > 0 && (
                        <div className="mt-5 pt-4 border-t border-brand-100">
                            <FormSection label="Address">
                                <p className="text-brand-700">
                                    {student.addresses.map((a) => [a.addressLine1, a.city, a.province, a.zipCode].filter(Boolean).join(', ')).join('; ')}
                                </p>
                            </FormSection>
                        </div>
                    )}
                </Card>

                {/* Status Overview StatCards */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <StatCard
                        label="Enrollment Status"
                        value={formatStatus(latestStatus)}
                        icon={
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        }
                        iconBg="seait"
                    />
                    <StatCard
                        label="Clearance Status"
                        value={formatStatus(clearanceStatus)}
                        icon={
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        }
                        iconBg="warning"
                    />
                    <StatCard
                        label="Total Assessed"
                        value={totalAssessed > 0 ? `₱${totalAssessed.toLocaleString('en-PH')}` : '—'}
                        icon={
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        }
                        iconBg="accent"
                    />
                    <StatCard
                        label="Scholarship"
                        value={formatStatus(scholarshipStatus)}
                        icon={
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 14l9-5-9-5-9 5 9 5z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />
                            </svg>
                        }
                        iconBg="success"
                    />
                </div>

                {/* Admissions */}
                <Card title="Admissions" subtitle="Admission applications on record">
                    {student.admissions?.length ? (
                        <div className="space-y-3">
                            {student.admissions.map((ad) => (
                                <div key={ad.admissionId} className="flex items-center justify-between text-sm border-b border-brand-100 pb-3 last:border-0 last:pb-0">
                                    <div>
                                        <p className="font-medium text-brand-900">{ad.course?.courseName || '—'}</p>
                                        <p className="text-brand-500 mt-0.5">
                                            {ad.applicantType || '—'} · {ad.submittedDate ? new Date(ad.submittedDate).toLocaleDateString('en-PH') : ''}
                                        </p>
                                    </div>
                                    <Badge tone={ad.admissionStatus || 'neutral'}>
                                        {formatStatus(ad.admissionStatus)}
                                    </Badge>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No admissions" message="No admission records for this student." />
                    )}
                </Card>

                {/* Enrollment Workflows */}
                <Card title="Enrollment Workflows" subtitle="Enrollment history with workflow progress">
                    {enrollments.length ? (
                        <div>
                            {enrollments.map((enrollment) => (
                                <EnrollmentCard key={enrollment.enrollmentId} enrollment={enrollment} />
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No enrollments" message="No enrollment records for this student." />
                    )}
                </Card>

                {/* Clearances */}
                <Card title="Clearances" subtitle="Clearance periods and overall status">
                    {student.studentclearances?.length ? (
                        <div className="space-y-3">
                            {student.studentclearances.map((sc) => (
                                <div key={sc.studentClearanceId} className="flex items-center justify-between text-sm border-b border-brand-100 pb-3 last:border-0 last:pb-0">
                                    <div>
                                        <p className="font-medium text-brand-900">
                                            {sc.clearancePeriod?.periodName || `Period #${sc.clearancePeriodId || ''}`}
                                        </p>
                                        <p className="text-brand-500 mt-0.5">
                                            {sc.receivedDate ? `Received ${new Date(sc.receivedDate).toLocaleDateString('en-PH')}` : 'Not yet received'}
                                        </p>
                                    </div>
                                    <Badge tone={clearanceStatusToneMap[sc.overallStatus] || sc.overallStatus || 'neutral'}>
                                        {formatStatus(sc.overallStatus)}
                                    </Badge>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <EmptyState title="No clearances" message="No clearance records for this student." />
                    )}
                </Card>

                {/* Exam Results */}
                {student.examresults?.length > 0 && (
                    <Card title="Exam Results" subtitle="Entrance and qualifying exam outcomes">
                        <div className="space-y-3">
                            {student.examresults.map((er) => (
                                <div key={er.examId} className="flex items-center justify-between text-sm border-b border-brand-100 pb-3 last:border-0 last:pb-0">
                                    <div>
                                        <p className="font-medium text-brand-900">
                                            {er.examStage} · {er.examType}
                                        </p>
                                        <p className="text-brand-500 mt-0.5">{er.examDate ? new Date(er.examDate).toLocaleDateString('en-PH') : ''}</p>
                                    </div>
                                    <Badge tone={er.examResult === 'pass' ? 'success' : er.examResult === 'fail' ? 'danger' : 'neutral'}>
                                        {er.examResult?.toUpperCase() || '—'}
                                    </Badge>
                                </div>
                            ))}
                        </div>
                    </Card>
                )}

                {/* IDs & Scholarships */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <Card title="IDs" subtitle="Issued student identification">
                        {student.studentids?.length ? (
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
                        ) : (
                            <EmptyState title="No IDs" message="No ID records for this student." />
                        )}
                    </Card>

                    <Card title="Scholarships" subtitle="Awarded scholarship records">
                        {student.studentscholarships?.length ? (
                            <div className="space-y-3">
                                {student.studentscholarships.map((ss) => (
                                    <div key={ss.studentScholarshipId} className="flex items-center justify-between text-sm border-b border-brand-100 pb-3 last:border-0 last:pb-0">
                                        <p className="font-medium text-brand-900">
                                            {ss.scholarshipType?.scholarshipName || `Scholarship #${ss.scholarshipTypeId}`}
                                        </p>
                                        <Badge tone={scholarshipStatusToneMap[ss.status] || 'neutral'}>
                                            {formatStatus(ss.status)}
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <EmptyState title="No scholarships" message="No scholarship records for this student." />
                        )}
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
