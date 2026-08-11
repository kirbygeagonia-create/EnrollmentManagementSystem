import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageHeader, Card, Badge, FormSection, ConfirmDialog } from '@/Components/ui';
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
    const [showConfirmReject, setShowConfirmReject] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

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
        router.post(route('admission.approve', { admission: admission.admissionId }), {}, {
            onSuccess: () => setIsSubmitting(false),
            onError: () => setIsSubmitting(false),
        });
        setIsSubmitting(true);
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
                    title="Admission Details"
                    subtitle={`${student?.firstName} ${student?.lastName} — ${admission.course?.courseName || '—'}`}
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Logo"
                />
            }
        >
            <Head title="Admission Details" />

            {/* Status Banner */}
            <div className={`mb-6 rounded-card border p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 ${banner.wrap}`}>
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
                        <p className="font-semibold text-brand-900">{banner.title}</p>
                        <p className="text-sm text-brand-600">{banner.desc}</p>
                    </div>
                </div>
                <Badge tone={admissionStatusToneMap[admission.admissionStatus] || 'neutral'}>
                    {admission.admissionStatus?.charAt(0).toUpperCase() + admission.admissionStatus?.slice(1)}
                </Badge>
            </div>

            {/* Student Profile Card */}
            <Card title="Student Profile" subtitle="Demographic and academic information" className="mb-6">
                <dl className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
                    <FormSection label="School ID">
                        <dd className="text-brand-900 font-mono">{student?.schoolIdNumber || '—'}</dd>
                    </FormSection>
                    <FormSection label="Full Name">
                        <dd className="text-brand-900">
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
                    <FormSection label="Course">
                        <dd className="text-brand-900">{admission.course?.courseName || '—'}</dd>
                    </FormSection>
                    <FormSection label="Term">
                        <dd className="text-brand-900">{admission.term?.semester || '—'} {admission.term?.academicYear?.year || ''}</dd>
                    </FormSection>
                    <FormSection label="Admission Status">
                        <dd>
                            <Badge tone={admissionStatusToneMap[admission.admissionStatus] || 'neutral'}>
                                {admission.admissionStatus?.charAt(0).toUpperCase() + admission.admissionStatus?.slice(1)}
                            </Badge>
                        </dd>
                    </FormSection>
                    <FormSection label="Gender">
                        <dd className="text-brand-900">{student?.gender?.charAt(0).toUpperCase() + student?.gender?.slice(1) || '—'}</dd>
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
                        <dd className="text-brand-900">{student?.civilStatus?.charAt(0).toUpperCase() + student?.civilStatus?.slice(1) || '—'}</dd>
                    </FormSection>
                    <FormSection label="Religion">
                        <dd className="text-brand-900">{student?.religion?.religionName || '—'}</dd>
                    </FormSection>
                    <FormSection label="Contact Number">
                        <dd className="text-brand-900">{student?.contactNumber || '—'}</dd>
                    </FormSection>
                    <FormSection label="Email">
                        <dd className="text-brand-900">{student?.email || '—'}</dd>
                    </FormSection>
                </dl>

                {/* Addresses */}
                <div className="mt-6 pt-6 border-t border-brand-100">
                    <h4 className="font-medium text-brand-900 mb-3">Addresses</h4>
                    <dl className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <FormSection label="Home Address">
                            <dd className="text-brand-600">{formatAddress(homeAddress)}</dd>
                        </FormSection>
                        <FormSection label="Current Address">
                            <dd className="text-brand-600">{formatAddress(currentAddress)}</dd>
                        </FormSection>
                        <FormSection label="Permanent Address">
                            <dd className="text-brand-600">{formatAddress(permanentAddress)}</dd>
                        </FormSection>
                    </dl>
                </div>

                {/* Guardians */}
                {guardians.length > 0 && (
                    <div className="mt-6 pt-6 border-t border-brand-100">
                        <h4 className="font-medium text-brand-900 mb-3">Guardians</h4>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {guardians.map((guardian, idx) => (
                                <div key={idx} className="border border-brand-200 rounded-card p-4">
                                    <p className="font-medium text-brand-900">{guardian.fullName}</p>
                                    <p className="text-sm text-brand-600">{guardian.relationship?.charAt(0).toUpperCase() + guardian.relationship?.slice(1)}</p>
                                    <p className="text-sm text-brand-600">{guardian.contactNumber}</p>
                                    {guardian.email && <p className="text-sm text-brand-600">{guardian.email}</p>}
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {guardian.isEmergencyContact && <span className="badge badge-info">Emergency Contact</span>}
                                        {guardian.isAuthorizedToActOnBehalf && <span className="badge badge-warning">Authorized Representative</span>}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </Card>

            {/* Requirements */}
            <Card title="Requirements" subtitle="Document submissions and verification status" className="mb-6">
                {requirements.length > 0 ? (
                    <ul className="space-y-3">
                        {requirements.map((req) => {
                            const submission = requirementSubmissions.find(s => s.requirementId === req.requirementId);
                            const status = submission?.submissionStatus || 'pending';
                            const hasDocument = submission?.documents && submission.documents.length > 0;

                            return (
                                <li key={req.requirementId} className="border border-brand-200 rounded-card p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div className="flex-1">
                                        <p className="font-medium text-brand-900">{req.requirementName}</p>
                                        <p className="text-sm text-brand-500">
                                            {req.appliesTo === 'all' ? 'Applies to all applicant types' : `Applies to: ${req.appliesTo}`}
                                            {req.isRequired && ' • Required'}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <Badge tone={submissionStatusToneMap[status] || 'neutral'}>
                                            {status?.charAt(0).toUpperCase() + status?.slice(1)}
                                        </Badge>
                                        {hasDocument && (
                                            <span className="badge badge-info text-xs">Document uploaded</span>
                                        )}
                                        {status === 'pending' && (
                                            <button
                                                onClick={() => handleSubmitRequirement(req.requirementId)}
                                                disabled={isSubmitting}
                                                className="btn btn-primary btn-sm"
                                            >
                                                Submit
                                            </button>
                                        )}
                                        {(status === 'submitted' || status === 'incomplete') && (
                                            <div className="flex gap-2">
                                                <button
                                                    onClick={() => handleVerifyRequirement(req.requirementId, true)}
                                                    disabled={isSubmitting}
                                                    className="btn btn-primary btn-sm"
                                                >
                                                    Verify
                                                </button>
                                                <button
                                                    onClick={() => handleVerifyRequirement(req.requirementId, false)}
                                                    disabled={isSubmitting}
                                                    className="btn btn-danger btn-sm"
                                                >
                                                    Reject
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                ) : (
                    <p className="text-brand-500 text-center py-8">No requirements configured for this applicant type.</p>
                )}
            </Card>

            {/* Actions */}
            <Card title="Actions" className="mb-6">
                <div className="flex flex-wrap gap-3">
                    {admission.admissionStatus === 'pending' && (
                        <>
                            <button
                                onClick={handleApprove}
                                disabled={isSubmitting}
                                className="btn btn-primary"
                            >
                                {isSubmitting ? 'Approving...' : 'Approve Admission'}
                            </button>
                            <button
                                onClick={handleReject}
                                disabled={isSubmitting}
                                className="btn btn-danger"
                            >
                                {isSubmitting ? 'Rejecting...' : 'Reject Admission'}
                            </button>
                        </>
                    )}
                    {admission.admissionStatus === 'approved' && (
                        <span className="badge badge-success self-center">Admission approved</span>
                    )}
                    {admission.admissionStatus === 'rejected' && (
                        <span className="badge badge-danger self-center">Admission rejected</span>
                    )}
                </div>

                {/* Confirm Dialog for Reject */}
                <ConfirmDialog
                    show={showConfirmReject}
                    onClose={() => setShowConfirmReject(false)}
                    onConfirm={confirmReject}
                    title="Reject Admission"
                    message="This will reject the admission application. This action cannot be undone. Are you sure you want to proceed?"
                    confirmText="Reject"
                    variant="danger"
                    loading={isSubmitting}
                />
            </Card>
        </AuthenticatedLayout>
    );
}
