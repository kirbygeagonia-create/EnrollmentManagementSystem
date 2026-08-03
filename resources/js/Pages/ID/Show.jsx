import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { PageHeader, Card, Badge, FormSection, Modal, ConfirmDialog } from '@/Components/ui';
import { useForm, router } from '@inertiajs/react';
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

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="ID Request & Card"
                    subtitle={`Phase 8 — ${getStudentName()} (${student?.schoolIdNumber})`}
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
                        <p className="font-mono text-lg font-medium">{student?.schoolIdNumber || '—'}</p>
                    </div>
                    <div>
                        <p className="text-sm text-brand-500">Name</p>
                        <p className="font-medium">{getStudentName()}</p>
                    </div>
                    <div>
                        <p className="text-sm text-brand-500">Course</p>
                        <p className="font-medium">{course?.name || '—'}</p>
                    </div>
                    <div>
                        <p className="text-sm text-brand-500">Term</p>
                        <p className="font-medium">{term?.name || '—'}</p>
                    </div>
                </div>
            </Card>

            {/* ID Request Card */}
            <Card title="ID Request" subtitle={idRequest ? `Requested on ${formatDate(idRequest.requestDate)}` : 'No ID request exists yet'} className="mb-6">
                {idRequest ? (
                    <>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <FormSection label="Request Reason">
                                <p className="text-brand-900">{getRequestReasonLabel(idRequest.requestReason)}</p>
                            </FormSection>
                            <FormSection label="Status">
                                <Badge tone={idRequestStatusToneMap[idRequest.status] || 'neutral'}>
                                    {idRequest.status?.charAt(0).toUpperCase() + idRequest.status?.slice(1)}
                                </Badge>
                            </FormSection>
                            <FormSection label="Request Date">
                                <p className="text-brand-900">{formatDate(idRequest.requestDate)}</p>
                            </FormSection>
                            <FormSection label="Emergency Contact Name">
                                <p className="text-brand-900">{idRequest.emergencyContactName || '—'}</p>
                            </FormSection>
                            <FormSection label="Emergency Contact Number">
                                <p className="text-brand-900">{idRequest.emergencyContactNumber || '—'}</p>
                            </FormSection>
                            <FormSection label="Blood Type">
                                <p className="text-brand-900">{idRequest.bloodType || '—'}</p>
                            </FormSection>
                            <FormSection label="Card Photo Path" className="sm:col-span-2">
                                <p className="text-brand-900 font-mono text-sm">{idRequest.cardPhotoPath || '—'}</p>
                            </FormSection>
                            <FormSection label="Produced By Vendor" className="sm:col-span-2">
                                <p className="text-brand-900">{idRequest.producedByVendor || '—'}</p>
                            </FormSection>
                        </div>

                        {!studentId && (
                            <div className="mt-6 flex gap-3 border-t border-brand-100 pt-4">
                                <button
                                    onClick={() => setShowProduceModal(true)}
                                    className="btn btn-primary"
                                    disabled={idRequest.status !== 'pending'}
                                >
                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Produce ID Card
                                </button>
                            </div>
                        )}
                    </>
                ) : (
                    <div className="text-center py-8">
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
                <Card title="ID Card" subtitle={studentId.issueDate ? `Issued on ${formatDate(studentId.issueDate)}` : ''} className="mb-6">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <FormSection label="QR Code">
                            <p className="text-brand-900 font-mono text-sm">{studentId.qrCode || '—'}</p>
                        </FormSection>
                        <FormSection label="Issue Date">
                            <p className="text-brand-900">{formatDate(studentId.issueDate)}</p>
                        </FormSection>
                        <FormSection label="Validation Status">
                            <Badge tone={idValidationStatusToneMap[studentId.validationStatus] || 'neutral'}>
                                {idValidationStatusLabelMap[studentId.validationStatus] || studentId.validationStatus}
                            </Badge>
                        </FormSection>
                        <FormSection label="Security Photo Path" className="sm:col-span-2">
                            <p className="text-brand-900 font-mono text-sm">{studentId.securityPhotoPath || '—'}</p>
                        </FormSection>
                        <FormSection label="Validated By" className="sm:col-span-2">
                            <p className="text-brand-900">{studentId.validatedBy ? `${studentId.validatedBy}` : '—'}</p>
                        </FormSection>
                        <FormSection label="Validated Date" className="sm:col-span-2">
                            <p className="text-brand-900">{studentId.validatedDate ? new Date(studentId.validatedDate).toLocaleString('en-PH') : '—'}</p>
                        </FormSection>
                    </div>

                    <div className="mt-6 flex gap-3 border-t border-brand-100 pt-4">
                        {studentId.validationStatus === 'pendingValidation' && (
                            <button
                                onClick={() => setShowValidateConfirm(true)}
                                className="btn btn-primary"
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
                                className="btn btn-secondary"
                            >
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                                Release to Student
                            </button>
                        )}
                    </div>
                </Card>
            )}

            {/* Create ID Request Modal */}
            <Modal
                show={showCreateModal}
                onClose={() => setShowCreateModal(false)}
                title="Create ID Request"
                size="lg"
            >
                <form onSubmit={handleCreateSubmit} className="space-y-4">
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
                            {createForm.errors.requestReason && <p className="text-sm text-red-600">{createForm.errors.requestReason}</p>}
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
                            {createForm.errors.bloodType && <p className="text-sm text-red-600">{createForm.errors.bloodType}</p>}
                        </FormSection>
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
                            {createForm.errors.emergencyContactName && <p className="text-sm text-red-600">{createForm.errors.emergencyContactName}</p>}
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
                            {createForm.errors.emergencyContactNumber && <p className="text-sm text-red-600">{createForm.errors.emergencyContactNumber}</p>}
                        </FormSection>
                    </div>
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
                    <div className="flex justify-end gap-3 pt-4 border-t border-brand-100">
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
                            className="btn btn-primary"
                            disabled={createForm.processing}
                        >
                            {createForm.processing ? 'Creating...' : 'Create Request'}
                        </button>
                    </div>
                </form>
            </Modal>

            {/* Produce ID Card Modal */}
            <Modal
                show={showProduceModal}
                onClose={() => setShowProduceModal(false)}
                title="Produce ID Card"
                size="lg"
            >
                <form onSubmit={handleProduceSubmit} className="space-y-4">
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
                        {produceForm.errors.qrCode && <p className="text-sm text-red-600">{produceForm.errors.qrCode}</p>}
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
                    <div className="flex justify-end gap-3 pt-4 border-t border-brand-100">
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
                            className="btn btn-primary"
                            disabled={produceForm.processing}
                        >
                            {produceForm.processing ? 'Producing...' : 'Produce Card'}
                        </button>
                    </div>
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
        </AuthenticatedLayout>
    );
}