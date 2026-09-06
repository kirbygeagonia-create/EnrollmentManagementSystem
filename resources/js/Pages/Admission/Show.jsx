import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageHeader, Card, Badge, FormSection, CauseEffectModal } from '@/Components/ui';
import { router } from '@inertiajs/react';
import { useState } from 'react';

const admissionStatusToneMap = {
    pending: 'pending',
    approved: 'approved',
    rejected: 'rejected',
};

const applicantTypeToneMap = {
    firstYear: 'info',
    transferee: 'warning',
    continuing: 'success',
    shifter: 'accent',
};

const submissionStatusToneMap = {
    pending: 'pending',
    submitted: 'info',
    verified: 'success',
    rejected: 'danger',
    incomplete: 'warning',
};

// Status banner styling per admission status
const statusBannerMap = {
    pending: {
        wrap: 'bg-warning-50 border-warning-200',
        chip: 'bg-warning-100 text-warning-800',
        title: 'Pending Review',
        desc: 'This application is awaiting admission office action.',
    },
    approved: {
        wrap: 'bg-success-50 border-success-200',
        chip: 'bg-success-100 text-success-800',
        title: 'Admission Approved',
        desc: 'This applicant has been admitted and may proceed to evaluation.',
    },
    rejected: {
        wrap: 'bg-danger-50 border-danger-200',
        chip: 'bg-danger-100 text-danger-800',
        title: 'Admission Rejected',
        desc: 'This application has been rejected. Contact the admission office for details.',
    },
};

export default function Show({ admission, requirements }) {
    const [showConfirmApprove, setShowConfirmApprove] = useState(false);
    const [showConfirmReject, setShowConfirmReject] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [infoTab, setInfoTab] = useState('demographics');

    const student = admission.student;
    const addresses = student?.addresses || [];
    const guardians = student?.guardians || [];
    const requirementSubmissions = admission.requirementSubmissions || [];

    const getAddress = (type) => addresses.find(a => a.addressType === type);
    const homeAddress = getAddress('home');
    const currentAddress = getAddress('current');
    const permanentAddress = getAddress('permanent');

    const formatAddress = (addr) => {
        if (!addr) return '—';
        const parts = [addr.houseBuildingNo, addr.street, addr.sitioPurok, addr.barangay, addr.cityMunicipality, addr.province, addr.zipCode, addr.country].filter(Boolean);
        return parts.join(', ') || '—';
    };

    const handleSubmitRequirement = (requirementId) => {
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = '.pdf,.jpg,.jpeg,.png,.doc,.docx';
        fileInput.onchange = (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('file', file);

            router.post(route('admission.requirements.submit', { admission: admission.admissionId, requirement: requirementId }), formData, {
                onSuccess: () => setIsSubmitting(false),
                onError: () => setIsSubmitting(false),
            });
            setIsSubmitting(true);
        };
        fileInput.click();
    };

    const handleVerifyRequirement = (requirementId, approved) => {
        const remarks = approved ? '' : prompt('Please provide a reason for rejection:');
        if (!approved && remarks === null) return;

        router.post(route('admission.requirements.verify', { admission: admission.admissionId, requirement: requirementId }), { approved, remarks }, {
            onSuccess: () => setIsSubmitting(false),
            onError: () => setIsSubmitting(false),
        });
        setIsSubmitting(true);
    };

    const handleApprove = () => {
        setShowConfirmApprove(true);
    };

    const confirmApprove = () => {
        setIsSubmitting(true);
        router.post(route('admission.approve', { admission: admission.admissionId }), {}, {
            onSuccess: () => {
                setShowConfirmApprove(false);
                setIsSubmitting(false);
            },
            onError: () => setIsSubmitting(false),
        });
    };

    const handleReject = () => {
        setShowConfirmReject(true);
    };

    const confirmReject = () => {
        router.post(route('admission.reject', { admission: admission.admissionId }), {}, {
            onSuccess: () => {
                setShowConfirmReject(false);
                setIsSubmitting(false);
            },
            onError: () => setIsSubmitting(false),
        });
        setIsSubmitting(true);
    };

    const banner = statusBannerMap[admission.admissionStatus] || statusBannerMap.pending;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Applicant Admission & Requirement Verification"
                    subtitle={`${student?.firstName} ${student?.lastName} — ${admission.course?.courseName || '—'} (${admission.term?.termName || 'Current Term'})`}
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Admissions Office"
                    phaseBadge="Phase 0 · Document Verification"
                    officeBadge="Office 6 · Admission Desk"
                />
            }
        >
            <Head title="Admission Details" />

            {/* Status Banner with Quick Decision Actions */}
            <div className={`mb-6 rounded-2xl border p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 shadow-sm ${banner.wrap}`}>
                <div className="flex items-center gap-3">
                    <span className={`inline-flex items-center justify-center h-10 w-10 rounded-xl ${banner.chip}`}>
                        {admission.admissionStatus === 'approved' ? (
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M5 13l4 4L19 7" /></svg>
                        ) : admission.admissionStatus === 'rejected' ? (
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M6 18L18 6M6 6l12 12" /></svg>
                        ) : (
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        )}
                    </span>
                    <div>
                        <p className="font-semibold text-brand-900 text-base">{banner.title}</p>
                        <p className="text-xs text-brand-600">{banner.desc}</p>
                    </div>
                </div>

                <div className="flex items-center gap-3 flex-wrap">
                    {admission.admissionStatus === 'pending' && (
                        <>
                            <button
                                type="button"
                                onClick={handleApprove}
                                disabled={isSubmitting}
                                className="btn btn-primary btn-sm flex items-center gap-1.5 shadow-sm"
                            >
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                {isSubmitting ? 'Processing...' : 'Approve Admission'}
                            </button>
                            <button
                                type="button"
                                onClick={handleReject}
                                disabled={isSubmitting}
                                className="btn btn-danger btn-sm flex items-center gap-1.5"
                            >
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                {isSubmitting ? 'Processing...' : 'Reject'}
                            </button>
                        </>
                    )}
                    <Badge tone={admissionStatusToneMap[admission.admissionStatus] || 'neutral'}>
                        {admission.admissionStatus?.charAt(0).toUpperCase() + admission.admissionStatus?.slice(1)}
                    </Badge>
                </div>
            </div>

            {/* 2-Column High-Efficiency Workstation Layout */}
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-8">
                {/* Left Column (7 cols): Student Profile Dossier with Tab switcher */}
                <div className="lg:col-span-7 space-y-4">
                    {/* Tab Switcher for Student Profile */}
                    <div className="flex items-center gap-2 border-b border-slate-200 pb-2">
                        <button
                            type="button"
                            onClick={() => setInfoTab('demographics')}
                            className={`px-3.5 py-1.5 rounded-xl font-heading font-semibold text-xs transition-all ${
                                infoTab === 'demographics'
                                    ? 'bg-seait-600 text-white shadow-sm'
                                    : 'bg-white hover:bg-slate-100 text-slate-700 border border-slate-200'
                            }`}
                        >
                            Demographic & Academic
                        </button>
                        <button
                            type="button"
                            onClick={() => setInfoTab('guardians')}
                            className={`px-3.5 py-1.5 rounded-xl font-heading font-semibold text-xs transition-all ${
                                infoTab === 'guardians'
                                    ? 'bg-seait-600 text-white shadow-sm'
                                    : 'bg-white hover:bg-slate-100 text-slate-700 border border-slate-200'
                            }`}
                        >
                            Addresses & Guardians
                        </button>
                    </div>

                    {infoTab === 'demographics' && (
                        <Card title="Candidate Academic & Personal Profile" subtitle="Intake registration details on file">
                            <dl className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4 text-xs">
                                <FormSection label="School ID">
                                    <dd className="text-brand-900 font-mono font-bold text-sm">{student?.schoolIdNumber || '—'}</dd>
                                </FormSection>
                                <FormSection label="Full Name">
                                    <dd className="text-brand-900 font-semibold text-sm">
                                        {student?.lastName}, {student?.firstName} {student?.middleName ? student.middleName.charAt(0) + '.' : ''} {student?.suffix || ''}
                                    </dd>
                                </FormSection>
                                <FormSection label="Applicant Type">
                                    <dd>
                                        <Badge tone={applicantTypeToneMap[admission.applicantType] || 'neutral'}>
                                            {admission.applicantType?.replace(/([A-Z])/g, ' $1') || '—'}
                                        </Badge>
                                    </dd>
                                </FormSection>
                                <FormSection label="Course / Program">
                                    <dd className="text-brand-900 font-medium">{admission.course?.courseName || '—'}</dd>
                                </FormSection>
                                <FormSection label="Term">
                                    <dd className="text-brand-900">{admission.term?.semester || '—'} {admission.term?.academicYear?.year || ''}</dd>
                                </FormSection>
                                <FormSection label="Gender">
                                    <dd className="text-brand-900 capitalize">{student?.gender || '—'}</dd>
                                </FormSection>
                                <FormSection label="Birthdate">
                                    <dd className="text-brand-900">{student?.birthdate ? new Date(student.birthdate).toLocaleDateString('en-PH') : '—'}</dd>
                                </FormSection>
                                <FormSection label="Birthplace">
                                    <dd className="text-brand-900">{student?.birthplace || '—'}</dd>
                                </FormSection>
                                <FormSection label="Citizenship">
                                    <dd className="text-brand-900">{student?.citizenship || '—'}</dd>
                                </FormSection>
                                <FormSection label="Civil Status">
                                    <dd className="text-brand-900 capitalize">{student?.civilStatus || '—'}</dd>
                                </FormSection>
                                <FormSection label="Religion">
                                    <dd className="text-brand-900">{student?.religion?.religionName || '—'}</dd>
                                </FormSection>
                                <FormSection label="Contact Number">
                                    <dd className="text-brand-900 font-mono">{student?.contactNumber || '—'}</dd>
                                </FormSection>
                                <FormSection label="Email">
                                    <dd className="text-brand-900 truncate font-mono">{student?.email || '—'}</dd>
                                </FormSection>
                            </dl>
                        </Card>
                    )}

                    {infoTab === 'guardians' && (
                        <div className="space-y-4">
                            <Card title="Registered Addresses" subtitle="Residential geographic contact">
                                <dl className="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                                    <FormSection label="Home Address">
                                        <dd className="text-brand-700">{formatAddress(homeAddress)}</dd>
                                    </FormSection>
                                    <FormSection label="Current Address">
                                        <dd className="text-brand-700">{formatAddress(currentAddress)}</dd>
                                    </FormSection>
                                    <FormSection label="Permanent Address">
                                        <dd className="text-brand-700">{formatAddress(permanentAddress)}</dd>
                                    </FormSection>
                                </dl>
                            </Card>

                            {guardians.length > 0 && (
                                <Card title="Guardians & Emergency Contacts" subtitle="Authorized representatives">
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                        {guardians.map((guardian, idx) => (
                                            <div key={idx} className="border border-slate-200 rounded-xl p-3 bg-slate-50/50">
                                                <p className="font-semibold text-brand-900">{guardian.fullName}</p>
                                                <p className="text-slate-500 capitalize">{guardian.relationship}</p>
                                                <p className="text-slate-600 font-mono mt-1">{guardian.contactNumber}</p>
                                                {guardian.email && <p className="text-slate-500 truncate">{guardian.email}</p>}
                                                <div className="mt-2 flex flex-wrap gap-1.5">
                                                    {guardian.isEmergencyContact && <span className="badge badge-info text-[10px]">Emergency</span>}
                                                    {guardian.isAuthorizedToActOnBehalf && <span className="badge badge-warning text-[10px]">Authorized</span>}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </Card>
                            )}
                        </div>
                    )}
                </div>

                {/* Right Column (5 cols): Requirements Verification Station */}
                <div className="lg:col-span-5 space-y-4">
                    <Card title="Admission Requirements Verification" subtitle="Physical & digital document checklist">
                        {requirements.length > 0 ? (
                            <ul className="space-y-3">
                                {requirements.map((req) => {
                                    const submission = requirementSubmissions.find(s => s.requirementId === req.requirementId);
                                    const status = submission?.submissionStatus || 'pending';
                                    const hasDocument = submission?.documents && submission.documents.length > 0;

                                    return (
                                        <li key={req.requirementId} className="border border-slate-200 rounded-xl p-3.5 bg-white shadow-2xs flex flex-col gap-2.5">
                                            <div className="flex items-start justify-between gap-2">
                                                <div>
                                                    <p className="font-semibold text-brand-900 text-xs">{req.requirementName}</p>
                                                    <p className="text-[11px] text-slate-500">
                                                        {req.appliesTo === 'all' ? 'All applicants' : req.appliesTo}
                                                        {req.isRequired && ' • Required'}
                                                    </p>
                                                </div>
                                                <Badge tone={submissionStatusToneMap[status] || 'neutral'}>
                                                    {status?.charAt(0).toUpperCase() + status?.slice(1)}
                                                </Badge>
                                            </div>

                                            <div className="flex items-center justify-between gap-2 pt-1 border-t border-slate-100">
                                                {hasDocument ? (
                                                    <span className="badge badge-info text-[10px]">Document attached</span>
                                                ) : (
                                                    <span className="text-[11px] text-slate-400">No file attached</span>
                                                )}

                                                <div className="flex items-center gap-1.5">
                                                    {status === 'pending' && (
                                                        <button
                                                            type="button"
                                                            onClick={() => handleSubmitRequirement(req.requirementId)}
                                                            disabled={isSubmitting}
                                                            className="btn btn-secondary btn-sm text-xs py-1 px-2.5"
                                                        >
                                                            Upload File
                                                        </button>
                                                    )}
                                                    {(status === 'submitted' || status === 'incomplete') && (
                                                        <>
                                                            <button
                                                                type="button"
                                                                onClick={() => handleVerifyRequirement(req.requirementId, true)}
                                                                disabled={isSubmitting}
                                                                className="btn btn-primary btn-sm text-xs py-1 px-2.5"
                                                            >
                                                                Verify
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() => handleVerifyRequirement(req.requirementId, false)}
                                                                disabled={isSubmitting}
                                                                className="btn btn-danger btn-sm text-xs py-1 px-2"
                                                            >
                                                                Reject
                                                            </button>
                                                        </>
                                                    )}
                                                </div>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        ) : (
                            <p className="text-slate-400 text-center py-6 text-xs">No requirements configured for this applicant type.</p>
                        )}
                    </Card>
                </div>
            </div>

                {/* Confirm Approve Cause & Effect Modal */}
                <CauseEffectModal
                    show={showConfirmApprove}
                    onClose={() => setShowConfirmApprove(false)}
                    onConfirm={confirmApprove}
                    title="Approve Institutional Admission"
                    subtitle="Admissions Office (Phase 0) — Official Intake Endorsement"
                    tone="success"
                    entityContext={{
                        label: 'Applicant Name',
                        value: `${student?.lastName}, ${student?.firstName}`,
                        badge: admission.applicantType || 'FIRST YEAR',
                    }}
                    cause={`Approving this application accepts ${student?.lastName}, ${student?.firstName} into institutional records.`}
                    effects={[
                        'Assigns an official SEAIT Student ID Number (e.g. 2026-XXXX) if not already generated.',
                        'Locks applicant demographic profile and submitted checklist requirements.',
                        'Queues applicant for Phase 0.5 Guidance Entrance / Retention Exam Lab.',
                    ]}
                    requiresAcknowledgement={false}
                    confirmText="Yes, Approve Admission"
                    cancelText="Keep Pending"
                    loading={isSubmitting}
                />

                {/* Confirm Reject Cause & Effect Modal */}
                <CauseEffectModal
                    show={showConfirmReject}
                    onClose={() => setShowConfirmReject(false)}
                    onConfirm={confirmReject}
                    title="Reject Admission Application"
                    subtitle="Admissions Office Intake Disqualification"
                    tone="danger"
                    entityContext={{
                        label: 'Applicant Name',
                        value: `${student?.lastName}, ${student?.firstName}`,
                        badge: 'REJECTION',
                    }}
                    cause="Rejecting this admission stops the application and denies entrance for the selected academic term."
                    effects={[
                        'Changes admission status to REJECTED and archives applicant profile.',
                        'Blocks student from scheduling Guidance Entrance Exams or accessing Evaluation.',
                        'Applicant will be required to re-apply if credentials or deficiencies are resolved.',
                    ]}
                    requiresAcknowledgement={true}
                    acknowledgementText="I confirm that this applicant fails admission criteria and must be formally rejected."
                    confirmText="Yes, Formally Reject Application"
                    cancelText="Cancel, Keep Under Review"
                    loading={isSubmitting}
                />
        </AuthenticatedLayout>
    );
}
