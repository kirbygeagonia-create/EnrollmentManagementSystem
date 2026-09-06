import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { PageHeader, Badge, FormSection, Modal, CauseEffectModal, StatCard } from '@/Components/ui';
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
    { value: 'shifted', label: 'Shifted Program' },
    { value: 'lost', label: 'Lost Replacement' },
    { value: 'renewed', label: 'Annual Renewal' },
];

export default function Show({ enrollment, idRequest, studentId }) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showProduceModal, setShowProduceModal] = useState(false);
    const [showValidateConfirm, setShowValidateConfirm] = useState(false);
    const [showReleaseConfirm, setShowReleaseConfirm] = useState(false);
    const [showReissueModal, setShowReissueModal] = useState(false);
    const [showCancelConfirm, setShowCancelConfirm] = useState(false);
    const [idSide, setIdSide] = useState('front'); // 'front' | 'back'

    const student = enrollment.student;
    const course = enrollment.course;

    // Form for creating ID request
    const createForm = useForm({
        requestReason: 'newStudent',
        emergencyContactName: '',
        emergencyContactNumber: '',
        bloodType: 'O+',
        cardPhotoPath: '',
        producedByVendor: 'JZEL Printing Services',
    });

    // Form for producing ID card
    const produceForm = useForm({
        qrCode: `SEAIT-${student?.schoolIdNumber || 'ID'}-QR`,
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

    const canReissue = idRequest && ['cardProduced', 'released'].includes(idRequest.status);
    const canCancel = idRequest && ['pending', 'cardProduced'].includes(idRequest.status);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Student ID Production & QR Card Center"
                    subtitle={`Phase 8 — ${getStudentName()} (${student?.schoolIdNumber})`}
                    logo="/images/logos/gzel-id-validation.jpg"
                    logoAlt="SEAIT ID Office"
                    phaseBadge="Phase 8 · ID Production"
                    officeBadge="Office 22 · ID Processing Desk"
                    actions={
                        <Link href={route('id.index')} className="btn btn-secondary btn-sm">
                            Back to Queue
                        </Link>
                    }
                />
            }
        >
            <Head title={`ID Center — ${getStudentName()}`} />

            {/* Quick Metrics */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                <StatCard
                    compact
                    label="Request Status"
                    value={idRequest ? idRequest.status?.toUpperCase() : 'NO REQUEST'}
                    iconBg={idRequest?.status === 'released' ? 'success' : idRequest ? 'warning' : 'neutral'}
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2" />
                        </svg>
                    }
                />
                <StatCard
                    compact
                    label="Card QR Security Code"
                    value={studentId?.qrCode || 'NOT ENCODED'}
                    iconBg="seait"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                        </svg>
                    }
                />
                <StatCard
                    compact
                    label="Card Validation"
                    value={studentId ? studentId.validationStatus?.toUpperCase() : 'PENDING'}
                    iconBg={studentId?.validationStatus === 'active' ? 'success' : 'warning'}
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
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
            </div>

            {/* Split Screen PVC Card Studio */}
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-8">
                {/* Left: Interactive PVC ID Card Mockup */}
                <div className="lg:col-span-6 space-y-4">
                    <div className="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3 mb-5">
                            <h3 className="font-heading font-bold text-slate-900 text-sm flex items-center gap-2">
                                <span className="h-2.5 w-2.5 rounded-full bg-seait-500" />
                                Interactive PVC Card Preview (CR80 Standard)
                            </h3>
                            <div className="flex bg-slate-100 rounded-lg p-0.5 border border-slate-200 text-xs">
                                <button
                                    type="button"
                                    onClick={() => setIdSide('front')}
                                    className={`px-3 py-1 rounded-md font-bold transition-all ${
                                        idSide === 'front' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900'
                                    }`}
                                >
                                    Front Side
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setIdSide('back')}
                                    className={`px-3 py-1 rounded-md font-bold transition-all ${
                                        idSide === 'back' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-500 hover:text-slate-900'
                                    }`}
                                >
                                    Back Side
                                </button>
                            </div>
                        </div>

                        {/* The PVC Card Body */}
                        <div className="flex justify-center p-2">
                            {idSide === 'front' ? (
                                /* Front Card */
                                <div className="w-[340px] sm:w-[380px] h-[220px] sm:h-[240px] rounded-2xl bg-gradient-to-br from-[#0B1528] via-navy-900 to-[#0B1528] text-white p-5 shadow-2xl border-2 border-seait-500/40 relative overflow-hidden flex flex-col justify-between select-none">
                                    {/* Gold Accent Corner Glow */}
                                    <div className="absolute top-0 right-0 w-32 h-32 bg-seait-500/10 rounded-full blur-2xl pointer-events-none" />

                                    {/* Header */}
                                    <div className="flex items-center justify-between border-b border-white/10 pb-2">
                                        <div className="flex items-center gap-2">
                                            <div className="h-7 w-7 rounded-lg bg-white/10 flex items-center justify-center p-1 border border-white/20">
                                                <img src="/images/logos/seait-logo.png" alt="SEAIT" className="h-full object-contain" />
                                            </div>
                                            <div className="leading-tight">
                                                <p className="font-heading font-extrabold text-white text-xs tracking-wider">SEAIT COLLEGE</p>
                                                <p className="text-[8px] text-seait-400 font-semibold tracking-widest uppercase">Student Identity Card</p>
                                            </div>
                                        </div>
                                        <span className="text-[9px] font-bold text-slate-300 bg-white/10 px-2 py-0.5 rounded border border-white/20">
                                            2026-2027
                                        </span>
                                    </div>

                                    {/* Center: Photo + Details */}
                                    <div className="flex items-center gap-4 my-auto">
                                        {/* Student Photo Mockup */}
                                        <div className="h-24 w-20 rounded-xl bg-slate-800 border-2 border-seait-400 shadow-md flex items-center justify-center text-slate-500 font-bold text-xs overflow-hidden flex-shrink-0">
                                            {idRequest?.cardPhotoPath ? (
                                                <img src={idRequest.cardPhotoPath} alt="Photo" className="h-full w-full object-cover" />
                                            ) : (
                                                <span className="text-center text-[10px] text-slate-400 p-1">STUDENT PHOTO</span>
                                            )}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-[10px] text-slate-400 uppercase font-semibold">Student Name</p>
                                            <p className="font-heading font-extrabold text-white text-sm truncate">{getStudentName()}</p>
                                            <p className="text-[10px] text-slate-400 uppercase font-semibold mt-1">ID Number</p>
                                            <p className="font-mono font-bold text-seait-400 text-sm tracking-wider">{student?.schoolIdNumber || '—'}</p>
                                            <p className="text-[10px] text-slate-300 font-semibold mt-0.5 truncate">{course?.courseCode} • Year {enrollment.yearLevel}</p>
                                        </div>
                                    </div>

                                    {/* Footer */}
                                    <div className="border-t border-white/10 pt-1.5 flex justify-between items-center text-[9px] text-slate-400">
                                        <span>South East Asian Institute of Technology</span>
                                        <span className="font-bold text-seait-400">OFFICIAL ID</span>
                                    </div>
                                </div>
                            ) : (
                                /* Back Card */
                                <div className="w-[340px] sm:w-[380px] h-[220px] sm:h-[240px] rounded-2xl bg-gradient-to-br from-slate-900 to-navy-950 text-white p-5 shadow-2xl border-2 border-slate-700 relative overflow-hidden flex flex-col justify-between select-none text-xs">
                                    {/* Mag Stripe Mockup */}
                                    <div className="w-full h-8 bg-black/80 rounded-md mb-2 flex items-center px-3 text-[9px] font-mono text-slate-500">
                                        ||| |||| || |||||| | |||||||| ||||| |||||||
                                    </div>

                                    <div className="grid grid-cols-2 gap-3 my-auto text-[10px]">
                                        <div className="space-y-1">
                                            <span className="text-slate-400 font-semibold block">Blood Type:</span>
                                            <span className="font-bold text-rose-400 text-xs font-mono">{idRequest?.bloodType || '—'}</span>
                                            <span className="text-slate-400 font-semibold block pt-1">Emergency Contact:</span>
                                            <p className="font-bold text-slate-200 truncate">{idRequest?.emergencyContactName || '—'}</p>
                                            <p className="font-mono text-slate-300">{idRequest?.emergencyContactNumber || '—'}</p>
                                        </div>
                                        <div className="flex flex-col items-center justify-center p-2 rounded-xl bg-white text-slate-900 border">
                                            <div className="h-16 w-16 bg-slate-900 p-1 rounded flex items-center justify-center text-white font-mono text-[8px] text-center">
                                                [ QR CODE ]
                                                <br />
                                                {studentId?.qrCode?.slice(-6) || 'QR-SEC'}
                                            </div>
                                            <span className="text-[8px] font-mono font-bold text-slate-600 mt-1 truncate max-w-[100px]">
                                                {studentId?.qrCode || 'SCAN ME'}
                                            </span>
                                        </div>
                                    </div>

                                    <p className="text-[8px] text-center text-slate-500 border-t border-slate-800 pt-1">
                                        If found, please return to SEAIT Registrar Office, Tupi, South Cotabato.
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Right: Request Details & Production Actions */}
                <div className="lg:col-span-6 space-y-6">
                    <div className="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                            <h3 className="font-heading font-bold text-slate-900 text-sm flex items-center gap-2">
                                <span className="h-2.5 w-2.5 rounded-full bg-indigo-600" />
                                Vendor Production & Validation Pipeline
                            </h3>
                            {idRequest && (
                                <Badge tone={idRequest.status === 'released' ? 'success' : 'pending'}>
                                    {idRequest.status?.toUpperCase()}
                                </Badge>
                            )}
                        </div>

                        {idRequest ? (
                            <div className="space-y-5 text-xs">
                                <div className="grid grid-cols-2 gap-4 p-4 rounded-xl bg-slate-50 border border-slate-200/60">
                                    <div>
                                        <span className="text-slate-400 font-semibold block">Vendor / Producer</span>
                                        <span className="font-bold text-slate-800">{idRequest.producedByVendor || 'JZEL Printing Services'}</span>
                                    </div>
                                    <div>
                                        <span className="text-slate-400 font-semibold block">Intake Reason</span>
                                        <span className="font-bold text-slate-800">{idRequest.requestReason}</span>
                                    </div>
                                    <div>
                                        <span className="text-slate-400 font-semibold block">Emergency Contact</span>
                                        <span className="font-bold text-slate-800">{idRequest.emergencyContactName}</span>
                                    </div>
                                    <div>
                                        <span className="text-slate-400 font-semibold block">Contact Number</span>
                                        <span className="font-mono text-slate-800">{idRequest.emergencyContactNumber}</span>
                                    </div>
                                    {studentId?.issueDate && (
                                        <div className="col-span-2">
                                            <span className="text-slate-400 font-semibold block">Issued Date</span>
                                            <span className="font-bold text-slate-800">{formatDate(studentId.issueDate)}</span>
                                        </div>
                                    )}
                                </div>

                                {/* Action Buttons */}
                                <div className="space-y-3 pt-2">
                                    {!studentId && idRequest.status === 'pending' && (
                                        <button
                                            onClick={() => setShowProduceModal(true)}
                                            className="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-seait-500 to-seait-600 hover:from-seait-400 hover:to-seait-500 text-white font-heading font-bold text-xs shadow-md transition-all flex items-center justify-center gap-2"
                                        >
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                            Produce Physical Card (Encode QR)
                                        </button>
                                    )}

                                    {studentId && studentId.validationStatus === 'pendingValidation' && (
                                        <button
                                            onClick={() => setShowValidateConfirm(true)}
                                            className="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-700 hover:from-emerald-500 hover:to-teal-600 text-white font-heading font-bold text-xs shadow-md transition-all flex items-center justify-center gap-2"
                                        >
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Validate Card (QR Scan & Sign Step 8)
                                        </button>
                                    )}

                                    {studentId && studentId.validationStatus === 'active' && idRequest.status !== 'released' && (
                                        <button
                                            onClick={() => setShowReleaseConfirm(true)}
                                            className="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-indigo-600 to-blue-700 hover:from-indigo-500 hover:to-blue-600 text-white font-heading font-bold text-xs shadow-md transition-all flex items-center justify-center gap-2"
                                        >
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                            </svg>
                                            Release Physical ID Card to Student
                                        </button>
                                    )}

                                    {canReissue && (
                                        <button
                                            onClick={() => setShowReissueModal(true)}
                                            className="w-full py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-heading font-bold text-xs border border-slate-300 transition-all flex items-center justify-center gap-2"
                                        >
                                            Request ID Card Reissue / Replacement
                                        </button>
                                    )}

                                    {canCancel && (
                                        <button
                                            onClick={() => setShowCancelConfirm(true)}
                                            className="w-full py-2 px-4 rounded-xl text-rose-600 hover:bg-rose-50 font-bold text-xs border border-rose-200 transition-all"
                                        >
                                            Cancel ID Request
                                        </button>
                                    )}
                                </div>
                            </div>
                        ) : (
                            <div className="text-center py-10">
                                <p className="text-xs text-slate-500 mb-4">No ID production request logged for this term.</p>
                                <button onClick={() => setShowCreateModal(true)} className="btn btn-primary">
                                    Create ID Production Request
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Create ID Request Modal */}
            <Modal
                show={showCreateModal}
                onClose={() => setShowCreateModal(false)}
                title="Create ID Production Request"
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
                            <select
                                value={createForm.data.requestReason}
                                onChange={(e) => createForm.setData('requestReason', e.target.value)}
                                className="form-select text-xs"
                                required
                            >
                                {requestReasonOptions.map((opt) => (
                                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                                ))}
                            </select>
                        </FormSection>
                        <FormSection label="Blood Type" required>
                            <select
                                value={createForm.data.bloodType}
                                onChange={(e) => createForm.setData('bloodType', e.target.value)}
                                className="form-select text-xs font-mono"
                                required
                            >
                                {bloodTypeOptions.map((opt) => (
                                    <option key={opt.value} value={opt.value}>{opt.label}</option>
                                ))}
                            </select>
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

            {/* Produce Modal */}
            <Modal
                show={showProduceModal}
                onClose={() => setShowProduceModal(false)}
                title="Produce ID Card & Encode QR"
                subtitle="Assign unique QR code to the physical PVC card."
                size="md"
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={() => setShowProduceModal(false)} className="btn btn-secondary" disabled={produceForm.processing}>
                            Cancel
                        </button>
                        <button type="submit" form="id-produce-form" className="btn btn-primary" disabled={produceForm.processing}>
                            {produceForm.processing ? 'Encoding...' : 'Produce & Encode'}
                        </button>
                    </div>
                }
            >
                <form id="id-produce-form" onSubmit={handleProduceSubmit} className="space-y-4 text-xs">
                    <FormSection label="Unique QR Code Serial" required>
                        <input
                            type="text"
                            value={produceForm.data.qrCode}
                            onChange={(e) => produceForm.setData('qrCode', e.target.value)}
                            className="form-input font-mono text-xs"
                            required
                        />
                    </FormSection>
                </form>
            </Modal>

            {/* Reissue Modal */}
            <Modal
                show={showReissueModal}
                onClose={() => setShowReissueModal(false)}
                title="Request ID Reissue / Replacement"
                subtitle="Provide justification for replacement card."
                size="md"
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={() => setShowReissueModal(false)} className="btn btn-secondary" disabled={reissueForm.processing}>
                            Cancel
                        </button>
                        <button type="submit" form="id-reissue-form" className="btn btn-primary" disabled={reissueForm.processing}>
                            Submit Reissue Request
                        </button>
                    </div>
                }
            >
                <form id="id-reissue-form" onSubmit={handleReissue}>
                    <FormSection label="Reason for Replacement" required>
                        <textarea
                            value={reissueForm.data.reissueReason}
                            onChange={(e) => reissueForm.setData('reissueReason', e.target.value)}
                            className="form-input text-xs"
                            rows={3}
                            placeholder="e.g., Lost physical card, damaged QR barcode..."
                            required
                        />
                    </FormSection>
                </form>
            </Modal>

            {/* Validate ID Cause & Effect Modal */}
            <CauseEffectModal
                show={showValidateConfirm}
                onClose={() => setShowValidateConfirm(false)}
                onConfirm={handleValidate}
                title="Activate & Validate Student ID in Campus Network"
                subtitle="QR Barcode Security & Campus Registry Activation"
                tone="success"
                entityContext={{
                    label: 'Student PVC ID',
                    value: `${student?.lastName}, ${student?.firstName}`,
                    badge: student?.schoolIdNumber || 'ID NUMBER',
                }}
                cause="Validating this card activates the embedded QR code and signs the final institutional clearance gate (Office 22)."
                effects={[
                    'Grants official student gate entry authorization across campus turnstiles and library scanners.',
                    'Marks the Student ID workflow phase as 100% completed in the Student 360 trail.',
                    'Synchronizes the student digital badge with active campus services.',
                ]}
                requiresAcknowledgement={false}
                confirmText="Yes, Validate & Activate ID"
                cancelText="Keep Unvalidated"
            />

            {/* Release ID Cause & Effect Modal */}
            <CauseEffectModal
                show={showReleaseConfirm}
                onClose={() => setShowReleaseConfirm(false)}
                onConfirm={handleRelease}
                title="Handover & Release Physical PVC Card"
                subtitle="Physical ID Card Issuance to Student"
                tone="info"
                entityContext={{
                    label: 'Card Recipient',
                    value: `${student?.lastName}, ${student?.firstName}`,
                    badge: course?.courseCode || 'PROGRAM',
                }}
                cause="Certifies that the manufactured CR80 PVC card has been physically inspected and handed over to the student."
                effects={[
                    'Logs physical card issuance timestamp and staff custody transfer in campus records.',
                    'Flags the physical card as in-possession by the student.',
                ]}
                requiresAcknowledgement={false}
                confirmText="Confirm Physical Release"
                cancelText="Hold Card in Office"
            />

            {/* Cancel ID Request Cause & Effect Modal */}
            <CauseEffectModal
                show={showCancelConfirm}
                onClose={() => setShowCancelConfirm(false)}
                onConfirm={handleCancel}
                title="Cancel Student ID Production Request"
                subtitle="ID Card Request Revocation"
                tone="danger"
                entityContext={{
                    label: 'Target ID Request',
                    value: `Request #${idRequest?.idRequestId || '—'}`,
                    badge: student?.schoolIdNumber || 'STUDENT',
                }}
                cause="Cancelling this request terminates card manufacturing and removes the student from the vendor print queue."
                effects={[
                    'Revokes vendor production orders with JZEL Printing Services.',
                    'Deactivates QR barcode security assignment for this enrollment cycle.',
                    'The student will not receive a physical campus ID card until a new request is filed.',
                ]}
                requiresAcknowledgement={true}
                acknowledgementText="I confirm that this ID card request is being permanently cancelled."
                confirmText="Yes, Cancel ID Request"
                cancelText="Keep Request Active"
            />
        </AuthenticatedLayout>
    );
}
