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

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Admission Details"
                    subtitle={`${student?.firstName} ${student?.lastName} — ${admission.course?.courseName || '—'}`}
                />
            }
        >
            <Head title="Admission Details" />

            {/* Student Profile Card */}
            <Card title="Student Profile" subtitle="Demographic and academic information" className="mb-6">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <FormSection label="School ID">
                        <p className="text-brand-900 font-mono">{student?.schoolIdNumber || '—'}</p>
                    </FormSection>
                    <FormSection label="Full Name">
                        <p className="text-brand-900">
                            {student?.lastName}, {student?.firstName} {student?.middleName ? student.middleName.charAt(0) + '.' : ''} {student?.suffix || ''}
                        </p>
                    </FormSection>
                    <FormSection label="Applicant Type">
                        <Badge tone={applicantTypeToneMap[admission.applicantType] || 'neutral'}>
                            {admission.applicantType?.replace(/([A-Z])/g, ' $1') || '—'}
                        </Badge>
                    </FormSection>
                    <FormSection label="Course">
                        <p className="text-brand-900">{admission.course?.courseName || '—'}</p>
                    </FormSection>
                    <FormSection label="Term">
                        <p className="text-brand-900">{admission.term?.semester || '—'} {admission.term?.academicYear?.year || ''}</p>
                    </FormSection>
                    <FormSection label="Admission Status">
                        <Badge tone={admissionStatusToneMap[admission.admissionStatus] || 'neutral'}>
                            {admission.admissionStatus?.charAt(0).toUpperCase() + admission.admissionStatus?.slice(1)}
                        </Badge>
                    </FormSection>
                    <FormSection label="Gender">
                        <p className="text-brand-900">{student?.gender?.charAt(0).toUpperCase() + student?.gender?.slice(1) || '—'}</p>
                    </FormSection>
                    <FormSection label="Birthdate">
                        <p className="text-brand-900">{student?.birthdate ? new Date(student.birthdate).toLocaleDateString('en-PH') : '—'}</p>
                    </FormSection>
                    <FormSection label="Birthplace">
                        <p className="text-brand-900">{student?.birthplace || '—'}</p>
                    </FormSection>
                    <FormSection label="Citizenship">
                        <p className="text-brand-900">{student?.citizenship || '—'}</p>
                    </FormSection>
                    <FormSection label="Civil Status">
                        <p className="text-brand-900">{student?.civilStatus?.charAt(0).toUpperCase() + student?.civilStatus?.slice(1) || '—'}</p>
                    </FormSection>
                    <FormSection label="Religion">
                        <p className="text-brand-900">{student?.religion?.religionName || '—'}</p>
                    </FormSection>
                    <FormSection label="Contact Number">
                        <p className="text-brand-900">{student?.contactNumber || '—'}</p>
                    </FormSection>
                    <FormSection label="Email">
                        <p className="text-brand-900">{student?.email || '—'}</p>
                    </FormSection>
                </div>

                {/* Addresses */}
                <div className="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <FormSection label="Home Address">
                        <p className="text-brand-600">{formatAddress(homeAddress)}</p>
                    </FormSection>
                    <FormSection label="Current Address">
                        <p className="text-brand-600">{formatAddress(currentAddress)}</p>
                    </FormSection>
                    <FormSection label="Permanent Address">
                        <p className="text-brand-600">{formatAddress(permanentAddress)}</p>
                    </FormSection>
                </div>

                {/* Guardians */}
                {guardians.length > 0 && (
                    <div className="mt-6">
                        <h4 className="font-medium text-brand-900 mb-3">Guardians</h4>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {guardians.map((guardian, idx) => (
                                <div key={idx} className="border border-brand-200 rounded-card p-4">
                                    <p className="font-medium text-brand-900">{guardian.fullName}</p>
                                    <p className="text-sm text-brand-600">{guardian.relationship?.charAt(0).toUpperCase() + guardian.relationship?.slice(1)}</p>
                                    <p className="text-sm text-brand-600">{guardian.contactNumber}</p>
                                    {guardian.email && <p className="text-sm text-brand-600">{guardian.email}</p>}
                                    {guardian.isEmergencyContact && <span className="badge badge-info mt-2 inline-block">Emergency Contact</span>}
                                    {guardian.isAuthorizedToActOnBehalf && <span className="badge badge-warning mt-2 inline-block ml-2">Authorized Representative</span>}
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </Card>

            {/* Requirements */}
            <Card title="Requirements" subtitle="Document submissions and verification status" className="mb-6">
                {requirements.length > 0 ? (
                    <div className="space-y-3">
                        {requirements.map((req) => {
                            const submission = requirementSubmissions.find(s => s.requirementId === req.requirementId);
                            const status = submission?.submissionStatus || 'pending';
                            const hasDocument = submission?.documents && submission.documents.length > 0;

                            return (
                                <div key={req.requirementId} className="border border-brand-200 rounded-card p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
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
                                                    className="btn btn-success btn-sm"
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
                                </div>
                            );
                        })}
                    </div>
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
                                className="btn btn-success"
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