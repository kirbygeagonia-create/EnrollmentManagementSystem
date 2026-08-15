import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { PageHeader, Card, Badge, FormSection, Modal, ConfirmDialog } from '@/Components/ui';
import { useState } from 'react';

const bloodTypeOptions = [
    { value: 'A+', label: 'A+' },
    { value: 'A-', label: 'A-' },
    { value: 'B+', label: 'B+' },
    { value: 'B-', label: 'B-' },
    { value: 'AB+', label: 'AB+' },
    { value: 'AB-', label: 'AB-' },
    { value: 'O+', label: 'O+' },
    { value: 'O-', label: 'O-' },
];

const requestReasonOptions = [
    { value: 'newStudent', label: 'New Student' },
    { value: 'shifted', label: 'Shifted' },
    { value: 'lost', label: 'Lost' },
    { value: 'replaced', label: 'Replaced' },
    { value: 'renewed', label: 'Renewed' },
];

const idRequestStatusToneMap = {
    pending: 'warning',
    cardProduced: 'info',
    validated: 'success',
    released: 'success',
    reissuePending: 'warning',
    cancelled: 'danger',
};

const idValidationStatusToneMap = {
    pendingValidation: 'warning',
    active: 'success',
    lost: 'danger',
    replaced: 'info',
};

const idValidationStatusLabelMap = {
    pendingValidation: 'Pending Validation',
    active: 'Active',
    lost: 'Lost',
    replaced: 'Replaced',
};

export default function Show({ enrollment, idRequest, studentId }) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showProduceModal, setShowProduceModal] = useState(false);
    const [showValidateConfirm, setShowValidateConfirm] = useState(false);
    const [showReleaseConfirm, setShowReleaseConfirm] = useState(false);
    const [showReissueModal, setShowReissueModal] = useState(false);
    const [showCancelConfirm, setShowCancelConfirm] = useState(false);

    const student = enrollment.student;
    const course = enrollment.course;
    const term = enrollment.term;

    // Form for creating ID request
    const createForm = useForm({
        requestReason: 'newStudent',
        emergencyContactName: '',
        emergencyContactNumber: '',
        bloodType: 'O+',
        cardPhotoPath: '',
        producedByVendor: '',
    });

    // Form for producing ID card
    const produceForm = useForm({
        qrCode: '',
        securityPhotoPath: '',
    });

    // Form for reissue
    const reissueForm = useForm({
        reissueReason: '',
    });

    const handleCreateSubmit = (e) => {
        e.preventDefault();
        createForm.post(route('id.create', { enrollment: enrollment.enrollmentId }), {
            onSuccess: () => setShowCreateModal(false),
            onError: () => {},
        });
    };

    const handleProduceSubmit = (e) => {
        e.preventDefault();
        produceForm.post(route('id.produce', { idRequest: idRequest.idRequestId }), {
            onSuccess: () => setShowProduceModal(false),
            onError: () => {},
        });
    };

    const handleValidate = () => {
        router.post(route('id.validate', { studentId: studentId.idId }), {
            onSuccess: () => setShowValidateConfirm(false),
            onError: () => {},
        });
    };

    const handleRelease = () => {
        router.post(route('id.release', { studentId: studentId.idId }), {
            onSuccess: () => setShowReleaseConfirm(false),
            onError: () => {},
        });
    };

    const handleReissue = () => {
        reissueForm.post(route('id.reissue', { idRequest: idRequest.idRequestId }), {
            onSuccess: () => setShowReissueModal(false),
            onError: () => {},
        });
    };

    const handleCancel = () => {
        router.post(route('id.cancel', { idRequest: idRequest.idRequestId }), {
            onSuccess: () => setShowCancelConfirm(false),
            onError: () => {},
        });
    };

    const formatDate = (dateStr) => {
        if (!dateStr) return '—';
        return new Date(dateStr).toLocaleDateString('en-PH');
    };

    const getStudentName = () => {
        if (!student) return '—';
        const parts = [student.lastName, student.firstName];
        if (student.middleName) parts.splice(1, 0, student.middleName);
        if (student.suffix) parts.push(student.suffix);
        return parts.join(', ');
    };

    const getRequestReasonLabel = (value) => {
        const opt = requestReasonOptions.find(o => o.value === value);
        return opt ? opt.label : value;
    };

    const canReissue = idRequest && ['cardProduced', 'released'].includes(idRequest.status);
    const canCancel = idRequest && ['pending', 'cardProduced'].includes(idRequest.status);

    const createIcon = (
        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
        </svg>
    );
    const produceIcon = (
        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
        </svg>
    );
    const reissueIcon = (
        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Student ID Production & QR Barcode Validation"
                    subtitle={`Phase 8 — ${getStudentName()} (${student?.schoolIdNumber})`}
                    logo="/images/logos/gzel-id-validation.jpg"
                    logoAlt="GZEL ID Validation Office"
                    phaseBadge="Phase 8 · ID Issuance"
                    officeBadge="Office 22 · ID Processing Desk"
                    actions={
                        <Link
                            href={route('id.index')}
                            className="btn btn-secondary btn-sm"
                        >
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Back to Queue
                        </Link>
                    }
                />
            }
        >
            <Head title={`ID — ${getStudentName()}`} />

            {/* Student Info Card */}
            <Card title="Student Information" className="mb-6">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <p className="text-sm text-brand-500">School ID</p>
                        <p className="font-mono text-lg font-medium text-brand-900">{student?.schoolIdNumber || '—'}</p>
                    </div>
                    <div>
                        <p className="text-sm text-brand-500">Name</p>
                        <p className="font-medium text-brand-900">{getStudentName()}</p>
                    </div>
                    <div>
                        <p className="text-sm text-brand-500">Course</p>
                        <p className="font-medium text-brand-900">{course?.name || '—'}</p>
                    </div>
                    <div>
                        <p className="text-sm text-brand-500">Term</p>
                        <p className="font-medium text-brand-900">{term?.name || '—'}</p>
                    </div>
                </div>
            </Card>

            {/* ID Request Card */}
            <Card
                title="ID Request"
                subtitle={idRequest ? `Requested on ${formatDate(idRequest.requestDate)}` : 'No ID request exists yet'}
                actions={
                    idRequest ? (
                        <Badge tone={idRequestStatusToneMap[idRequest.status] || 'neutral'}>
                            {idRequest.status?.charAt(0).toUpperCase() + idRequest.status?.slice(1)}
                        </Badge>
                    ) : null
                }
                className="mb-6"
            >
                {idRequest ? (
                    <>
                        <div className="space-y-6">
                            {/* Request Details */}
                            <div>
                                <h4 className="text-sm font-semibold text-brand-900 uppercase tracking-wide mb-3 flex items-center gap-2">
                                    <svg className="h-4 w-4 text-seait-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    Request Details
                                </h4>
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <FormSection label="Request Reason">
                                        <p className="text-brand-900">{getRequestReasonLabel(idRequest.requestReason)}</p>
                                    </FormSection>
                                    <FormSection label="Request Date">
                                        <p className="text-brand-900">{formatDate(idRequest.requestDate)}</p>
                                    </FormSection>
                                    {idRequest.is_reissue && (
                                        <FormSection label="Reissue Reason">
                                            <p className="text-brand-900">{idRequest.reissueReason || '—'}</p>
                                        </FormSection>
                                    )}
                                </div>
                            </div>

                            {/* Emergency & Medical */}
                            <div>
                                <h4 className="text-sm font-semibold text-brand-900 uppercase tracking-wide mb-3 flex items-center gap-2">
                                    <svg className="h-4 w-4 text-seait-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                    </svg>
                                    Emergency & Medical
                                </h4>
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <FormSection label="Emergency Contact Name">
                                        <p className="text-brand-900">{idRequest.emergencyContactName || '—'}</p>
                                    </FormSection>
                                    <FormSection label="Emergency Contact Number">
                                        <p className="text-brand-900">{idRequest.emergencyContactNumber || '—'}</p>
                                    </FormSection>
                                    <FormSection label="Blood Type">
                                        <p className="text-brand-900 font-mono">{idRequest.bloodType || '—'}</p>
                                    </FormSection>
                                </div>
                            </div>

                            {/* Production Details */}
                            <div>
                                <h4 className="text-sm font-semibold text-brand-900 uppercase tracking-wide mb-3 flex items-center gap-2">
                                    <svg className="h-4 w-4 text-seait-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                    </svg>
                                    Production Details
                                </h4>
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <FormSection label="Card Photo Path">
                                        <p className="text-brand-900 font-mono text-sm break-all">{idRequest.cardPhotoPath || '—'}</p>
                                    </FormSection>
                                    <FormSection label="Produced By Vendor">
                                        <p className="text-brand-900">{idRequest.producedByVendor || '—'}</p>
                                    </FormSection>
                                </div>
                            </div>

                            {/* Actions */}
                            <div className="flex flex-wrap gap-3 border-t border-brand-100 pt-4">
                                {!studentId && (
                                    <button
                                        onClick={() => setShowProduceModal(true)}
                                        className="btn btn-primary btn-sm"
                                        disabled={idRequest.status !== 'pending'}
                                    >
                                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        Produce ID Card
                                    </button>
                                )}
                                {studentId && canReissue && (
                                    <button
                                        onClick={() => setShowReissueModal(true)}
                                        className="btn btn-accent btn-sm"
                                    >
                                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        {idRequest.status === 'released' ? 'Request Replacement' : 'Request Reprint'}
                                    </button>
                                )}
                                {canCancel && (
                                    <button
                                        onClick={() => setShowCancelConfirm(true)}
                                        className="btn btn-danger btn-sm"
                                    >
                                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        Cancel Request
                                    </button>
                                )}
                            </div>
                        </div>
                    </>
                ) : (
                    <div className="text-center py-8">
                        <svg className="mx-auto h-12 w-12 text-brand-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-2M10 6l1.5-1.5a2 2 0 011.414-.586H16a2 2 0 012 2v2.586a2 2 0 01-.586 1.414L16 12M10 6V4a2 2 0 012-2h2a2 2 0 012 2v2" />
                        </svg>
                        <p className="text-brand-500 mb-4">No ID request has been created for this student.</p>
                        <button
                            onClick={() => setShowCreateModal(true)}
                            className="btn btn-primary"
                        >
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Create ID Request
                        </button>
                    </div>
                )}
            </Card>

            {/* ID Card Card */}
            {studentId && (
                <Card
                    title="ID Card"
                    subtitle={studentId.issueDate ? `Issued on ${formatDate(studentId.issueDate)}` : ''}
                    actions={
                        <Badge tone={idValidationStatusToneMap[studentId.validationStatus] || 'neutral'}>
                            {idValidationStatusLabelMap[studentId.validationStatus] || studentId.validationStatus}
                        </Badge>
                    }
                    className="mb-6"
                >
                    <div className="space-y-6">
                        {/* Card Details */}
                        <div>
                            <h4 className="text-sm font-semibold text-brand-900 uppercase tracking-wide mb-3 flex items-center gap-2">
                                <svg className="h-4 w-4 text-seait-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                </svg>
                                Card Details
                            </h4>
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <FormSection label="QR Code">
                                    <p className="text-brand-900 font-mono text-sm break-all">{studentId.qrCode || '—'}</p>
                                </FormSection>
                                <FormSection label="Issue Date">
                                    <p className="text-brand-900">{formatDate(studentId.issueDate)}</p>
                                </FormSection>
                                <FormSection label="Security Photo Path">
                                    <p className="text-brand-900 font-mono text-sm break-all">{studentId.securityPhotoPath || '—'}</p>
                                </FormSection>
                            </div>
                        </div>

                        {/* Validation Details */}
                        <div>
                            <h4 className="text-sm font-semibold text-brand-900 uppercase tracking-wide mb-3 flex items-center gap-2">
                                <svg className="h-4 w-4 text-seait-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Validation Details
                            </h4>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <FormSection label="Validated By">
                                    <p className="text-brand-900">{studentId.validatedBy ? `${studentId.validatedBy}` : '—'}</p>
                                </FormSection>
                                <FormSection label="Validated Date">
                                    <p className="text-brand-900">{studentId.validatedDate ? new Date(studentId.validatedDate).toLocaleString('en-PH') : '—'}</p>
                                </FormSection>
                            </div>
                        </div>

                        {/* Actions */}
                        <div className="flex flex-wrap gap-3 border-t border-brand-100 pt-4">
                            {studentId.validationStatus === 'pendingValidation' && (
                                <button
                                    onClick={() => setShowValidateConfirm(true)}
                                    className="btn btn-primary btn-sm"
                                >
                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Validate (QR Scan)
                                </button>
                            )}
                            {studentId.validationStatus === 'active' && (
                                <button
                                    onClick={() => setShowReleaseConfirm(true)}
                                    className="btn btn-secondary btn-sm"
                                >
                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                    </svg>
                                    Release to Student
                                </button>
                            )}
                        </div>
                    </div>
                </Card>
            )}

            {/* Create ID Request Modal */}
            <Modal
                show={showCreateModal}
                onClose={() => setShowCreateModal(false)}
                title="Create ID Request"
                subtitle="Open a new ID request with emergency contact and medical details."
                icon={createIcon}
                size="lg"
                footer={
                    <div className="flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={() => setShowCreateModal(false)}
                            className="btn btn-secondary"
                            disabled={createForm.processing}
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            form="id-create-form"
                            className="btn btn-primary"
                            disabled={createForm.processing}
                        >
                            {createForm.processing ? 'Creating...' : 'Create Request'}
                        </button>
                    </div>
                }
            >
                <form id="id-create-form" onSubmit={handleCreateSubmit} className="space-y-6">
                    {/* Request Details */}
                    <div>
                        <h4 className="text-sm font-semibold text-brand-900 uppercase tracking-wide mb-3">Request Details</h4>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <FormSection label="Request Reason" required>
                                <select
                                    value={createForm.data.requestReason}
                                    onChange={(e) => createForm.setData('requestReason', e.target.value)}
                                    className="form-select"
                                    required
                                >
                                    {requestReasonOptions.map((opt) => (
                                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                                    ))}
                                </select>
                                {createForm.errors.requestReason && <p className="form-error">{createForm.errors.requestReason}</p>}
                            </FormSection>
                            <FormSection label="Blood Type" required>
                                <select
                                    value={createForm.data.bloodType}
                                    onChange={(e) => createForm.setData('bloodType', e.target.value)}
                                    className="form-select"
                                    required
                                >
                                    {bloodTypeOptions.map((opt) => (
                                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                                    ))}
                                </select>
                                {createForm.errors.bloodType && <p className="form-error">{createForm.errors.bloodType}</p>}
                            </FormSection>
                        </div>
                    </div>

                    {/* Emergency Contact */}
                    <div>
                        <h4 className="text-sm font-semibold text-brand-900 uppercase tracking-wide mb-3">Emergency Contact</h4>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <FormSection label="Emergency Contact Name" required>
                                <input
                                    type="text"
                                    maxLength={255}
                                    value={createForm.data.emergencyContactName}
                                    onChange={(e) => createForm.setData('emergencyContactName', e.target.value)}
                                    className="form-input"
                                    placeholder="Full name"
                                    required
                                />
                                {createForm.errors.emergencyContactName && <p className="form-error">{createForm.errors.emergencyContactName}</p>}
                            </FormSection>
                            <FormSection label="Emergency Contact Number" required>
                                <input
                                    type="text"
                                    maxLength={20}
                                    value={createForm.data.emergencyContactNumber}
                                    onChange={(e) => createForm.setData('emergencyContactNumber', e.target.value)}
                                    className="form-input"
                                    placeholder="Phone number"
                                    required
                                />
                                {createForm.errors.emergencyContactNumber && <p className="form-error">{createForm.errors.emergencyContactNumber}</p>}
                            </FormSection>
                        </div>
                    </div>

                    {/* Production (optional) */}
                    <div>
                        <h4 className="text-sm font-semibold text-brand-900 uppercase tracking-wide mb-3">Production (Optional)</h4>
                        <div className="grid grid-cols-1 gap-4">
                            <FormSection label="Card Photo Path">
                                <input
                                    type="text"
                                    maxLength={500}
                                    value={createForm.data.cardPhotoPath}
                                    onChange={(e) => createForm.setData('cardPhotoPath', e.target.value)}
                                    className="form-input"
                                    placeholder="Optional: path or URL to photo"
                                />
                            </FormSection>
                            <FormSection label="Produced By Vendor">
                                <input
                                    type="text"
                                    maxLength={255}
                                    value={createForm.data.producedByVendor}
                                    onChange={(e) => createForm.setData('producedByVendor', e.target.value)}
                                    className="form-input"
                                    placeholder="Optional: vendor name"
                                />
                            </FormSection>
                        </div>
                    </div>
                </form>
            </Modal>

            {/* Produce ID Card Modal */}
            <Modal
                show={showProduceModal}
                onClose={() => setShowProduceModal(false)}
                title="Produce ID Card"
                subtitle="Generate the physical ID card with a unique QR code."
                icon={produceIcon}
                size="lg"
                footer={
                    <div className="flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={() => setShowProduceModal(false)}
                            className="btn btn-secondary"
                            disabled={produceForm.processing}
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            form="id-produce-form"
                            className="btn btn-primary"
                            disabled={produceForm.processing}
                        >
                            {produceForm.processing ? 'Producing...' : 'Produce Card'}
                        </button>
                    </div>
                }
            >
                <form id="id-produce-form" onSubmit={handleProduceSubmit} className="space-y-4">
                    <FormSection label="QR Code" required>
                        <input
                            type="text"
                            maxLength={100}
                            value={produceForm.data.qrCode}
                            onChange={(e) => produceForm.setData('qrCode', e.target.value)}
                            className="form-input"
                            placeholder="Unique QR code for the ID card"
                            required
                        />
                        {produceForm.errors.qrCode && <p className="form-error">{produceForm.errors.qrCode}</p>}
                    </FormSection>
                    <FormSection label="Security Photo Path">
                        <input
                            type="text"
                            maxLength={500}
                            value={produceForm.data.securityPhotoPath}
                            onChange={(e) => produceForm.setData('securityPhotoPath', e.target.value)}
                            className="form-input"
                            placeholder="Optional: path or URL to security photo"
                        />
                    </FormSection>
                </form>
            </Modal>

            {/* Reissue Modal */}
            <Modal
                show={showReissueModal}
                onClose={() => setShowReissueModal(false)}
                title={idRequest?.status === 'released' ? 'Request ID Replacement' : 'Request ID Reprint'}
                subtitle="Provide a reason for the reissue request."
                icon={reissueIcon}
                size="lg"
                footer={
                    <div className="flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={() => setShowReissueModal(false)}
                            className="btn btn-secondary"
                            disabled={reissueForm.processing}
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            form="id-reissue-form"
                            className="btn btn-accent"
                            disabled={reissueForm.processing}
                        >
                            {reissueForm.processing ? 'Processing...' : (idRequest?.status === 'released' ? 'Request Replacement' : 'Request Reprint')}
                        </button>
                    </div>
                }
            >
                <form id="id-reissue-form" onSubmit={handleReissue}>
                    <FormSection label="Reissue Reason" required>
                        <textarea
                            value={reissueForm.data.reissueReason}
                            onChange={(e) => reissueForm.setData('reissueReason', e.target.value)}
                            className="form-input"
                            rows={3}
                            placeholder="Reason for reissue (e.g., lost, damaged, name change, etc.)"
                            required
                        />
                        {reissueForm.errors.reissueReason && <p className="form-error">{reissueForm.errors.reissueReason}</p>}
                    </FormSection>
                </form>
            </Modal>

            {/* Validate Confirm Dialog */}
            <ConfirmDialog
                show={showValidateConfirm}
                onClose={() => setShowValidateConfirm(false)}
                onConfirm={handleValidate}
                title="Validate ID Card"
                message="This will mark the ID card as Active (validated via QR scan). This action also signs the workflow step for ID Office. Continue?"
                confirmText="Validate"
                variant="primary"
            />

            {/* Release Confirm Dialog */}
            <ConfirmDialog
                show={showReleaseConfirm}
                onClose={() => setShowReleaseConfirm(false)}
                onConfirm={handleRelease}
                title="Release ID Card"
                message="Confirm releasing this ID card to the student?"
                confirmText="Release"
                variant="primary"
            />

            {/* Cancel Confirm Dialog */}
            <ConfirmDialog
                show={showCancelConfirm}
                onClose={() => setShowCancelConfirm(false)}
                onConfirm={handleCancel}
                title="Cancel ID Request"
                message="Are you sure you want to cancel this ID request? This action cannot be undone."
                confirmText="Cancel Request"
                variant="danger"
            />
        </AuthenticatedLayout>
    );
}
