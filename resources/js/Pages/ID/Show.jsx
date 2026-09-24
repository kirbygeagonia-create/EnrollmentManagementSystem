import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { PageHeader, Badge, Card, FormSection, Modal, CauseEffectModal, StatCard, WorkflowStepper, Select, formatStatusLabel, idRequestReasonLabel, idRequestStatusTone } from '@/Components/ui';
import { useState, useRef, useEffect } from 'react';

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

export default function Show({ enrollment, idRequest, requestReasons }) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showValidateConfirm, setShowValidateConfirm] = useState(false);
    const [showReleaseConfirm, setShowReleaseConfirm] = useState(false);
    const [cameraActive, setCameraActive] = useState(false);
    const [cameraError, setCameraError] = useState('');
    const [uploadingPhoto, setUploadingPhoto] = useState(false);

    const videoRef = useRef(null);
    const streamRef = useRef(null);
    const fileInputRef = useRef(null);

    const student = enrollment.student;
    const course = enrollment.course;

    // Form for creating ID request
    const createForm = useForm({
        requestReason: 'newStudent',
        emergencyContactName: '',
        emergencyContactNumber: '',
        bloodType: 'O+',
    });

    const handleCreateSubmit = (e) => {
        e.preventDefault();
        createForm.post(route('id.create', { enrollment: enrollment.enrollmentId }), {
            onSuccess: () => setShowCreateModal(false),
            onError: () => {},
        });
    };

    const handleValidate = () => {
        router.post(route('id.validate', { idRequest: idRequest.idRequestId }), {
            onSuccess: () => setShowValidateConfirm(false),
            onError: () => {},
        });
    };

    const handleRelease = () => {
        router.post(route('id.release', { idRequest: idRequest.idRequestId }), {
            onSuccess: () => setShowReleaseConfirm(false),
            onError: () => {},
        });
    };

    // ---- Camera capture ----
    const stopCamera = () => {
        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
        setCameraActive(false);
    };

    // Release the camera stream when the page unmounts
    useEffect(() => () => stopCamera(), []);

    const startCamera = async () => {
        setCameraError('');
        if (!navigator.mediaDevices?.getUserMedia) {
            setCameraError('Camera is unavailable in this browser context — upload a photo file instead.');
            return;
        }
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
            });
            streamRef.current = stream;
            setCameraActive(true);
        } catch {
            setCameraError('Camera permission was denied or unavailable — upload a photo file instead.');
        }
    };

    // Wire the stream to the video element once the preview mounts
    useEffect(() => {
        if (cameraActive && videoRef.current && streamRef.current) {
            videoRef.current.srcObject = streamRef.current;
            videoRef.current.play().catch(() => {});
        }
    }, [cameraActive]);

    const uploadPhoto = (file) => {
        const formData = new FormData();
        formData.append('photo', file);
        setUploadingPhoto(true);
        router.post(route('id.photo', { idRequest: idRequest.idRequestId }), formData, {
            onSuccess: () => {
                setUploadingPhoto(false);
                stopCamera();
            },
            onError: () => setUploadingPhoto(false),
        });
    };

    const captureFromCamera = () => {
        const video = videoRef.current;
        if (!video || !video.videoWidth) return;
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        canvas.toBlob(
            (blob) => {
                if (blob) uploadPhoto(new File([blob], 'capture.jpg', { type: 'image/jpeg' }));
            },
            'image/jpeg',
            0.9,
        );
    };

    // ---- Gating (irreversible actions strictly gated; disabled states show why) ----
    const photoAttached = Boolean(idRequest?.cardPhotoPath);
    const requestIsPending = idRequest?.status === 'pending';
    const requestIsValidated = idRequest?.status === 'validated';
    const canValidateNow = Boolean(idRequest) && requestIsPending && photoAttached;
    const canReleaseNow = requestIsValidated;
    const validateBlockReason = !idRequest
        ? 'Create the ID request first.'
        : !requestIsPending
            ? `Request is already ${idRequest.status}.`
            : !photoAttached
                ? 'Capture or attach the face photo before validating.'
                : '';
    const releaseBlockReason = !idRequest
        ? 'Create the ID request first.'
        : !requestIsValidated
            ? 'Validate the request before releasing the card.'
            : '';

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

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="ID Validation Desk"
                    subtitle={`${getStudentName()} (${student?.schoolIdNumber})`}
                    phaseBadge="Phase 8 · ID Office"
                    officeBadge="ID Validation Desk"
                    actions={
                        <Link href={route('id.index')} className="btn btn-secondary btn-sm">
                            Back to Queue
                        </Link>
                    }
                />
            }
        >
            <Head title={`ID Validation Desk — ${getStudentName()}`} />

            {/* Quick Metrics */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                <StatCard
                    compact
                    label="Request Status"
                    value={idRequest ? formatStatusLabel(idRequest.status) : 'No Request'}
                    iconBg={idRequest ? (idRequestStatusTone[idRequest.status] || 'neutral') : 'neutral'}
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                        </svg>
                    }
                />
                <StatCard
                    compact
                    label="Face Photo"
                    value={photoAttached ? 'ATTACHED' : 'NOT ATTACHED'}
                    iconBg={photoAttached ? 'success' : 'warning'}
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    }
                />
                <StatCard
                    compact
                    label="Blood Type"
                    value={idRequest?.bloodType || '—'}
                    iconBg="danger"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L7.05 14.95a6 6 0 00-3.86.517M12 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    }
                />
                <StatCard
                    compact
                    label="Requested Date"
                    value={formatDate(idRequest?.requestDate)}
                    iconBg="seait"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    }
                />
            </div>

            {/* Enrollment Workflow Progress */}
            <Card title="Enrollment Workflow Progress" subtitle="Signed offices and pending steps per enrollment type" className="mb-5">
                <WorkflowStepper workflow={enrollment.enrollmentworkflow} />
            </Card>

            {idRequest ? (
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-8">
                    {/* Left: Face photo capture / attached photo */}
                    <div className="lg:col-span-6">
                        <Card
                            title="Face Photo Capture"
                            subtitle={
                                requestIsPending
                                    ? 'Live capture — the photo is attached to the ID request for validation'
                                    : 'Photo on file for this ID request'
                            }
                            className="h-full"
                        >
                            {requestIsPending ? (
                                <div className="space-y-4">
                                    {cameraActive ? (
                                        <>
                                            <div className="relative mx-auto w-full max-w-md rounded-2xl overflow-hidden border-2 border-seait-500/40 bg-slate-900">
                                                <video ref={videoRef} autoPlay playsInline muted className="w-full h-64 object-cover" />
                                                {/* Face guide overlay */}
                                                <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                                                    <div className="h-44 w-36 rounded-[50%] border-2 border-dashed border-white/60" />
                                                </div>
                                            </div>
                                            <div className="flex justify-center gap-3">
                                                <button
                                                    type="button"
                                                    onClick={captureFromCamera}
                                                    disabled={uploadingPhoto}
                                                    className="btn btn-primary"
                                                >
                                                    {uploadingPhoto ? 'Attaching...' : 'Capture Photo'}
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={stopCamera}
                                                    disabled={uploadingPhoto}
                                                    className="btn btn-secondary"
                                                >
                                                    Stop Camera
                                                </button>
                                            </div>
                                        </>
                                    ) : (
                                        <div className="text-center py-8 space-y-3">
                                            <div className="mx-auto h-12 w-12 rounded-full bg-slate-100 flex items-center justify-center">
                                                <svg className="h-6 w-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                            <p className="text-xs text-slate-500 max-w-sm mx-auto">
                                                {cameraError || 'Start the camera to capture the student\'s face photo, or upload an existing photo file.'}
                                            </p>
                                            <div className="flex justify-center gap-3">
                                                <button type="button" onClick={startCamera} className="btn btn-primary">
                                                    Start Camera
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => fileInputRef.current?.click()}
                                                    disabled={uploadingPhoto}
                                                    className="btn btn-secondary"
                                                >
                                                    {uploadingPhoto ? 'Uploading...' : 'Upload Photo File'}
                                                </button>
                                            </div>
                                        </div>
                                    )}
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        accept="image/jpeg,image/png"
                                        className="hidden"
                                        onChange={(e) => {
                                            const file = e.target.files?.[0];
                                            if (file) uploadPhoto(file);
                                            e.target.value = '';
                                        }}
                                    />
                                </div>
                            ) : photoAttached ? (
                                <div className="space-y-4">
                                    <div className="mx-auto w-full max-w-xs">
                                        <img
                                            src={idRequest.cardPhotoPath}
                                            alt={`Face photo for ${getStudentName()}`}
                                            className="rounded-2xl border-2 border-slate-200 object-cover w-full"
                                        />
                                    </div>
                                    <div className="text-center">
                                        <Badge tone={idRequestStatusTone[idRequest.status] || 'neutral'}>
                                            {formatStatusLabel(idRequest.status)}
                                        </Badge>
                                    </div>
                                </div>
                            ) : (
                                <div className="text-center py-10">
                                    <p className="text-xs text-slate-500">No photo on file for this request.</p>
                                </div>
                            )}
                        </Card>
                    </div>

                    {/* Right: Request details & desk actions */}
                    <div className="lg:col-span-6">
                        <Card
                            title="Request Details & Desk Actions"
                            className="h-full"
                            actions={
                                <Badge tone={idRequestStatusTone[idRequest.status] || 'neutral'}>
                                    {formatStatusLabel(idRequest.status)}
                                </Badge>
                            }
                        >

                            <div className="grid grid-cols-2 gap-4 p-4 rounded-xl bg-slate-50 border border-slate-200/60 text-xs mb-5">
                                <div>
                                    <span className="text-slate-400 font-semibold block">Intake Reason</span>
                                    <span className="font-bold text-slate-800">{idRequestReasonLabel[idRequest.requestReason] || formatStatusLabel(idRequest.requestReason)}</span>
                                </div>
                                <div>
                                    <span className="text-slate-400 font-semibold block">Requested Date</span>
                                    <span className="font-bold text-slate-800">{formatDate(idRequest.requestDate)}</span>
                                </div>
                                <div>
                                    <span className="text-slate-400 font-semibold block">Emergency Contact</span>
                                    <span className="font-bold text-slate-800">{idRequest.emergencyContactName}</span>
                                </div>
                                <div>
                                    <span className="text-slate-400 font-semibold block">Contact Number</span>
                                    <span className="font-mono text-slate-800">{idRequest.emergencyContactNumber}</span>
                                </div>
                                {requestIsValidated && (
                                    <div className="col-span-2">
                                        <span className="text-slate-400 font-semibold block">Validated</span>
                                        <span className="font-bold text-slate-800">
                                            {formatDate(idRequest.validatedDate)}
                                            {idRequest.validatedBy?.name ? ` · by ${idRequest.validatedBy.name}` : ''}
                                        </span>
                                    </div>
                                )}
                            </div>

                            {/* Action buttons */}
                            <div className="space-y-1 pt-2">
                                <div className="space-y-1">
                                    <button
                                        type="button"
                                        onClick={() => setShowValidateConfirm(true)}
                                        disabled={!canValidateNow}
                                        className={`w-full py-3 px-4 rounded-xl font-heading font-bold text-xs shadow-md transition-all flex items-center justify-center gap-2 ${
                                            canValidateNow
                                                ? 'bg-gradient-to-r from-emerald-600 to-teal-700 hover:from-emerald-500 hover:to-teal-600 text-white'
                                                : 'bg-slate-100 text-slate-400 border border-slate-200 cursor-not-allowed shadow-none'
                                        }`}
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Validate ID & Sign Workflow Step
                                    </button>
                                    {!canValidateNow && validateBlockReason && (
                                        <p className="text-[11px] text-slate-500 flex items-start gap-1 px-1">
                                            <svg className="w-3.5 h-3.5 mt-px flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                            {validateBlockReason}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-1">
                                    <button
                                        type="button"
                                        onClick={() => setShowReleaseConfirm(true)}
                                        disabled={!canReleaseNow}
                                        className={`w-full py-3 px-4 rounded-xl font-heading font-bold text-xs shadow-md transition-all flex items-center justify-center gap-2 ${
                                            canReleaseNow
                                                ? 'bg-gradient-to-r from-indigo-600 to-blue-700 hover:from-indigo-500 hover:to-blue-600 text-white'
                                                : 'bg-slate-100 text-slate-400 border border-slate-200 cursor-not-allowed shadow-none'
                                        }`}
                                    >
                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                        </svg>
                                        Release ID Card to Student
                                    </button>
                                    {!canReleaseNow && releaseBlockReason && (
                                        <p className="text-[11px] text-slate-500 flex items-start gap-1 px-1">
                                            <svg className="w-3.5 h-3.5 mt-px flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                            {releaseBlockReason}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </Card>
                    </div>
                </div>
            ) : (
                <div className="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs">
                    <div className="text-center py-10">
                        <p className="text-xs text-slate-500 mb-4">No ID request logged for this enrollment.</p>
                        <button onClick={() => setShowCreateModal(true)} className="btn btn-primary">
                            Create ID Request
                        </button>
                    </div>
                </div>
            )}

            {/* Create ID Request Modal */}
            <Modal
                show={showCreateModal}
                onClose={() => setShowCreateModal(false)}
                title="Create ID Request"
                subtitle="Open a new intake record with emergency contact and medical details."
                size="lg"
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={() => setShowCreateModal(false)} className="btn btn-secondary" disabled={createForm.processing}>
                            Cancel
                        </button>
                        <button type="submit" form="id-create-form" className="btn btn-primary" disabled={createForm.processing}>
                            {createForm.processing ? 'Creating...' : 'Create Request'}
                        </button>
                    </div>
                }
            >
                <form id="id-create-form" onSubmit={handleCreateSubmit} className="space-y-4 text-xs">
                    <div className="grid grid-cols-2 gap-4">
                        <FormSection label="Request Reason" required>
                            <Select
                                value={createForm.data.requestReason}
                                onChange={(e) => createForm.setData('requestReason', e.target.value)}
                                options={requestReasons || []}
                                required
                            />
                        </FormSection>
                        <FormSection label="Blood Type" required>
                            <Select
                                value={createForm.data.bloodType}
                                onChange={(e) => createForm.setData('bloodType', e.target.value)}
                                options={bloodTypeOptions}
                                className="font-mono"
                                required
                            />
                        </FormSection>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <FormSection label="Emergency Contact Name" required>
                            <input
                                type="text"
                                value={createForm.data.emergencyContactName}
                                onChange={(e) => createForm.setData('emergencyContactName', e.target.value)}
                                className="form-input text-xs"
                                placeholder="Full Name"
                                required
                            />
                        </FormSection>
                        <FormSection label="Emergency Contact Number" required>
                            <input
                                type="text"
                                value={createForm.data.emergencyContactNumber}
                                onChange={(e) => createForm.setData('emergencyContactNumber', e.target.value)}
                                className="form-input text-xs"
                                placeholder="09XX-XXX-XXXX"
                                required
                            />
                        </FormSection>
                    </div>
                </form>
            </Modal>

            {/* Validate ID Cause & Effect Modal */}
            <CauseEffectModal
                show={showValidateConfirm}
                onClose={() => setShowValidateConfirm(false)}
                onConfirm={handleValidate}
                title="Validate Student ID & Sign Workflow Step"
                subtitle="Desk Validation — Face Photo & Enrollment Check"
                tone="success"
                entityContext={{
                    label: 'Student ID Request',
                    value: `${student?.lastName}, ${student?.firstName}`,
                    badge: student?.schoolIdNumber || 'ID NUMBER',
                }}
                cause="Validating marks this ID request as validated and signs the ID Office workflow step of the enrollment journey."
                effects={[
                    'Records the validating staff member and timestamp on the ID request.',
                    'Signs the ID Office workflow step, completing the final enrollment-desk gate.',
                    'Enables release of the printed ID card to the student.',
                ]}
                requiresAcknowledgement={false}
                confirmText="Yes, Validate ID"
                cancelText="Keep Pending"
            />

            {/* Release ID Cause & Effect Modal */}
            <CauseEffectModal
                show={showReleaseConfirm}
                onClose={() => setShowReleaseConfirm(false)}
                onConfirm={handleRelease}
                title="Release ID Card to Student"
                subtitle="Physical Card Handover"
                tone="info"
                entityContext={{
                    label: 'Card Recipient',
                    value: `${student?.lastName}, ${student?.firstName}`,
                    badge: course?.courseCode || 'PROGRAM',
                }}
                cause="Releasing records the handover of the printed ID card (printed off-system) to the student."
                effects={[
                    'Logs the release against the ID request.',
                    'The request moves to Released and can no longer be edited.',
                ]}
                requiresAcknowledgement={false}
                confirmText="Confirm Release"
                cancelText="Hold Card in Office"
            />
        </AuthenticatedLayout>
    );
}
