import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { PageHeader, Badge, FormSection, Modal, CauseEffectModal, StatCard } from '@/Components/ui';
import { useState, useMemo } from 'react';

export default function Show({ enrollment, clinicRecord }) {
    const [showRecordModal, setShowRecordModal] = useState(false);
    const [showUpdateModal, setShowUpdateModal] = useState(false);
    const [showReopenConfirm, setShowReopenConfirm] = useState(false);

    const student = enrollment.student;

    // Form for creating/updating clinic record
    const recordForm = useForm({
        heightCm: clinicRecord?.heightCm || '',
        weightKg: clinicRecord?.weightKg || '',
        bloodPressure: clinicRecord?.bloodPressure || '',
        philhealthNumber: clinicRecord?.philhealthNumber || '',
        philhealthRegistered: clinicRecord?.philhealthRegistered || false,
        assessmentNotes: clinicRecord?.assessmentNotes || '',
        findings: clinicRecord?.findings || '',
        assessmentDate: clinicRecord?.assessmentDate ? new Date(clinicRecord.assessmentDate).toISOString().split('T')[0] : new Date().toISOString().split('T')[0],
    });

    const isCreating = !clinicRecord;
    const submitRoute = isCreating ? route('clinic.record', { enrollment: enrollment.enrollmentId }) : route('clinic.update', { clinic: clinicRecord.clinicRecordId });
    const submitMethod = isCreating ? 'post' : 'patch';

    const handleSubmit = (e) => {
        e.preventDefault();
        recordForm[submitMethod](submitRoute, {
            onSuccess: () => {
                if (isCreating) setShowRecordModal(false);
                else setShowUpdateModal(false);
            },
            onError: () => {},
        });
    };

    const handleOpenRecord = () => {
        recordForm.reset({
            heightCm: '',
            weightKg: '',
            bloodPressure: '',
            philhealthNumber: '',
            philhealthRegistered: false,
            assessmentNotes: '',
            findings: '',
            assessmentDate: new Date().toISOString().split('T')[0],
        });
        setShowRecordModal(true);
    };

    const handleOpenUpdate = () => {
        recordForm.reset({
            heightCm: clinicRecord?.heightCm || '',
            weightKg: clinicRecord?.weightKg || '',
            bloodPressure: clinicRecord?.bloodPressure || '',
            philhealthNumber: clinicRecord?.philhealthNumber || '',
            philhealthRegistered: clinicRecord?.philhealthRegistered || false,
            assessmentNotes: clinicRecord?.assessmentNotes || '',
            findings: clinicRecord?.findings || '',
            assessmentDate: clinicRecord?.assessmentDate ? new Date(clinicRecord.assessmentDate).toISOString().split('T')[0] : new Date().toISOString().split('T')[0],
        });
        setShowUpdateModal(true);
    };

    const handleReopen = () => {
        router.post(route('clinic.reopen', { clinic: clinicRecord.clinicRecordId }), {
            onSuccess: () => setShowReopenConfirm(false),
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

    // Calculate BMI
    const bmiData = useMemo(() => {
        const h = Number(clinicRecord?.heightCm || recordForm.data.heightCm || 0);
        const w = Number(clinicRecord?.weightKg || recordForm.data.weightKg || 0);
        if (h <= 0 || w <= 0) return { val: '—', label: 'Not calculated', tone: 'neutral' };

        const hm = h / 100;
        const bmi = (w / (hm * hm)).toFixed(1);

        if (bmi < 18.5) return { val: bmi, label: 'Underweight', tone: 'warning' };
        if (bmi <= 24.9) return { val: bmi, label: 'Normal / Healthy', tone: 'success' };
        if (bmi <= 29.9) return { val: bmi, label: 'Overweight', tone: 'warning' };
        return { val: bmi, label: 'Obese Range', tone: 'danger' };
    }, [clinicRecord, recordForm.data.heightCm, recordForm.data.weightKg]);

    const renderRecordForm = () => (
        <div className="space-y-6 text-xs">
            {/* Vitals Section */}
            <div>
                <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wide mb-3 flex items-center gap-2">
                    <span className="h-2 w-2 rounded-full bg-rose-500" />
                    Physical Vitals Examination
                </h4>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <FormSection label="Height (cm)" required>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            max="300"
                            value={recordForm.data.heightCm}
                            onChange={(e) => recordForm.setData('heightCm', e.target.value)}
                            className="form-input"
                            placeholder="e.g., 165.50"
                            required
                        />
                        {recordForm.errors.heightCm && <p className="form-error">{recordForm.errors.heightCm}</p>}
                    </FormSection>
                    <FormSection label="Weight (kg)" required>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            max="300"
                            value={recordForm.data.weightKg}
                            onChange={(e) => recordForm.setData('weightKg', e.target.value)}
                            className="form-input"
                            placeholder="e.g., 60.00"
                            required
                        />
                        {recordForm.errors.weightKg && <p className="form-error">{recordForm.errors.weightKg}</p>}
                    </FormSection>
                    <FormSection label="Blood Pressure" required>
                        <input
                            type="text"
                            maxLength={20}
                            value={recordForm.data.bloodPressure}
                            onChange={(e) => recordForm.setData('bloodPressure', e.target.value)}
                            className="form-input"
                            placeholder="e.g., 120/80"
                            required
                        />
                        {recordForm.errors.bloodPressure && <p className="form-error">{recordForm.errors.bloodPressure}</p>}
                    </FormSection>
                    <FormSection label="Assessment Date" required>
                        <input
                            type="date"
                            value={recordForm.data.assessmentDate}
                            onChange={(e) => recordForm.setData('assessmentDate', e.target.value)}
                            className="form-input"
                            required
                        />
                        {recordForm.errors.assessmentDate && <p className="form-error">{recordForm.errors.assessmentDate}</p>}
                    </FormSection>
                </div>
            </div>

            {/* PhilHealth Section */}
            <div className="pt-2 border-t border-slate-100">
                <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wide mb-3 flex items-center gap-2">
                    <span className="h-2 w-2 rounded-full bg-emerald-500" />
                    PhilHealth Coverage
                </h4>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <FormSection label="PhilHealth Identification No. (PIN)">
                        <input
                            type="text"
                            maxLength={50}
                            value={recordForm.data.philhealthNumber}
                            onChange={(e) => recordForm.setData('philhealthNumber', e.target.value)}
                            className="form-input font-mono"
                            placeholder="XX-XXXXXXXXX-X"
                        />
                    </FormSection>
                    <FormSection label="Registration Status">
                        <label className="flex items-center gap-2 cursor-pointer pt-2">
                            <input
                                type="checkbox"
                                checked={recordForm.data.philhealthRegistered}
                                onChange={(e) => recordForm.setData('philhealthRegistered', e.target.checked)}
                                className="form-checkbox h-4 w-4 text-emerald-600 rounded"
                            />
                            <span className="text-xs font-semibold text-slate-800">Confirmed Registered Member</span>
                        </label>
                    </FormSection>
                </div>
            </div>

            {/* Clinical Notes Section */}
            <div className="pt-2 border-t border-slate-100">
                <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wide mb-3">Medical Diagnosis & Clinical Notes</h4>
                <div className="space-y-3">
                    <FormSection label="Assessment Notes & Observations">
                        <textarea
                            value={recordForm.data.assessmentNotes}
                            onChange={(e) => recordForm.setData('assessmentNotes', e.target.value)}
                            className="form-input"
                            rows={3}
                            placeholder="General physical observations and notes..."
                        />
                    </FormSection>
                    <FormSection label="Clinical Findings & Recommendations">
                        <textarea
                            value={recordForm.data.findings}
                            onChange={(e) => recordForm.setData('findings', e.target.value)}
                            className="form-input"
                            rows={3}
                            placeholder="Medical recommendations or clearance notes..."
                        />
                    </FormSection>
                </div>
            </div>
        </div>
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Student Health Assessment & Clinical Chart"
                    subtitle={`Phase 7 — ${getStudentName()} (${student?.schoolIdNumber})`}
                    logo="/images/logos/clinic.jpg"
                    logoAlt="SEAIT School Clinic"
                    phaseBadge="Phase 7 · Clinic Examination"
                    officeBadge="Office 11 · School Clinic"
                    actions={
                        <Link href={route('clinic.index')} className="btn btn-secondary btn-sm">
                            Back to Queue
                        </Link>
                    }
                />
            }
        >
            <Head title={`Clinic — ${getStudentName()}`} />

            {/* Quick Vitals Dials Banner */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <StatCard
                    label="Height & Weight"
                    value={clinicRecord ? `${clinicRecord.heightCm} cm / ${clinicRecord.weightKg} kg` : '—'}
                    iconBg="rose"
                    icon={
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    }
                />
                <StatCard
                    label="Calculated BMI"
                    value={bmiData.val}
                    iconBg={bmiData.tone === 'success' ? 'success' : bmiData.tone === 'warning' ? 'warning' : 'neutral'}
                    icon={
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    }
                />
                <StatCard
                    label="Blood Pressure (BP)"
                    value={clinicRecord?.bloodPressure || '—'}
                    iconBg="danger"
                    icon={
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                    }
                />
                <StatCard
                    label="PhilHealth Status"
                    value={clinicRecord?.philhealthRegistered ? 'Registered' : 'Not Registered'}
                    iconBg={clinicRecord?.philhealthRegistered ? 'success' : 'warning'}
                    icon={
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    }
                />
            </div>

            {/* Split Screen Medical Chart */}
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-8">
                {/* Left: Student Clinical Profile */}
                <div className="lg:col-span-8 space-y-6">
                    <div className="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3 mb-5">
                            <h3 className="font-heading font-bold text-slate-900 text-sm flex items-center gap-2">
                                <span className="h-2.5 w-2.5 rounded-full bg-rose-500" />
                                Physical Examination & Medical History
                            </h3>
                            {clinicRecord && (
                                <Badge tone={clinicRecord.status === 'completed' ? 'success' : 'pending'}>
                                    {clinicRecord.status?.toUpperCase()}
                                </Badge>
                            )}
                        </div>

                        {clinicRecord ? (
                            <div className="space-y-6">
                                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 rounded-xl bg-slate-50 border border-slate-200/60 text-xs">
                                    <div>
                                        <span className="text-slate-400 font-semibold block">Height</span>
                                        <span className="font-bold text-slate-800 text-sm">{clinicRecord.heightCm} cm</span>
                                    </div>
                                    <div>
                                        <span className="text-slate-400 font-semibold block">Weight</span>
                                        <span className="font-bold text-slate-800 text-sm">{clinicRecord.weightKg} kg</span>
                                    </div>
                                    <div>
                                        <span className="text-slate-400 font-semibold block">Blood Pressure</span>
                                        <span className="font-bold text-slate-800 text-sm">{clinicRecord.bloodPressure}</span>
                                    </div>
                                    <div>
                                        <span className="text-slate-400 font-semibold block">BMI Classification</span>
                                        <span className="font-bold text-rose-700 text-sm">{bmiData.label}</span>
                                    </div>
                                </div>

                                <div>
                                    <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wide mb-2">Observations & Notes</h4>
                                    <div className="p-4 rounded-xl bg-white border border-slate-200 text-xs text-slate-700 leading-relaxed min-h-[60px]">
                                        {clinicRecord.assessmentNotes || 'No notes logged.'}
                                    </div>
                                </div>

                                <div>
                                    <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wide mb-2">Clinical Findings & Clearance</h4>
                                    <div className="p-4 rounded-xl bg-white border border-slate-200 text-xs text-slate-700 leading-relaxed min-h-[60px]">
                                        {clinicRecord.findings || 'No findings logged.'}
                                    </div>
                                </div>

                                <div className="pt-4 border-t border-slate-100 flex items-center justify-between">
                                    <div className="text-xs text-slate-400">
                                        Examined on <span className="font-semibold text-slate-700">{formatDate(clinicRecord.assessmentDate)}</span>
                                    </div>
                                    <div className="flex gap-2">
                                        {clinicRecord.status !== 'completed' ? (
                                            <button onClick={handleOpenUpdate} className="btn btn-primary btn-sm">
                                                Update Medical Chart
                                            </button>
                                        ) : (
                                            <button onClick={() => setShowReopenConfirm(true)} className="btn btn-secondary btn-sm">
                                                Reopen Chart for Editing
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="text-center py-12">
                                <div className="h-16 w-16 rounded-full bg-rose-50 text-rose-500 mx-auto flex items-center justify-center mb-3">
                                    <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                    </svg>
                                </div>
                                <h3 className="font-bold text-slate-900 text-sm">No Health Assessment Recorded</h3>
                                <p className="text-xs text-slate-500 mt-1 max-w-sm mx-auto mb-4">
                                    Measure the student's height, weight, and blood pressure to complete Phase 7.
                                </p>
                                <button onClick={handleOpenRecord} className="btn btn-primary">
                                    Perform Physical Exam
                                </button>
                            </div>
                        )}
                    </div>
                </div>

                {/* Right: PhilHealth Card & Doctor Desk Stamp */}
                <div className="lg:col-span-4 space-y-6">
                    {/* PhilHealth Card Mockup */}
                    <div className="bg-gradient-to-br from-emerald-800 to-teal-950 text-white rounded-2xl p-5 border border-emerald-700 shadow-lg text-xs">
                        <div className="flex items-center justify-between border-b border-emerald-700/60 pb-3 mb-4">
                            <div className="flex items-center gap-2">
                                <span className="h-2 w-2 rounded-full bg-emerald-400 animate-pulse" />
                                <span className="font-heading font-extrabold text-white text-xs uppercase tracking-wider">PhilHealth Verified</span>
                            </div>
                            <span className="text-[10px] font-mono text-emerald-300">Republic of the Philippines</span>
                        </div>

                        <div className="space-y-2 mb-4">
                            <p className="text-[10px] text-emerald-300 uppercase tracking-wider font-semibold">Member Identification No.</p>
                            <p className="font-mono text-base font-extrabold text-white tracking-widest">
                                {clinicRecord?.philhealthNumber || 'NOT SUBMITTED'}
                            </p>
                        </div>

                        <div className="flex justify-between border-t border-emerald-700/60 pt-3 text-[11px] text-emerald-200">
                            <div>
                                <span className="text-[10px] text-emerald-400 block">Member Name</span>
                                <span className="font-bold text-white">{getStudentName()}</span>
                            </div>
                            <div className="text-right">
                                <span className="text-[10px] text-emerald-400 block">Status</span>
                                <span className="font-bold text-emerald-300">
                                    {clinicRecord?.philhealthRegistered ? 'Active' : 'Unconfirmed'}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Record Modal */}
            <Modal
                show={showRecordModal}
                onClose={() => setShowRecordModal(false)}
                title="Record Clinic Examination"
                subtitle="Capture vital signs, PhilHealth registration, and medical notes."
                size="lg"
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={() => setShowRecordModal(false)} className="btn btn-secondary" disabled={recordForm.processing}>
                            Cancel
                        </button>
                        <button type="submit" form="clinic-record-form" className="btn btn-primary" disabled={recordForm.processing}>
                            {recordForm.processing ? 'Saving...' : 'Save & Sign Workflow'}
                        </button>
                    </div>
                }
            >
                <form id="clinic-record-form" onSubmit={handleSubmit}>
                    {renderRecordForm()}
                </form>
            </Modal>

            {/* Update Modal */}
            <Modal
                show={showUpdateModal}
                onClose={() => setShowUpdateModal(false)}
                title="Update Clinic Examination"
                subtitle="Revise the existing clinical record details."
                size="lg"
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={() => setShowUpdateModal(false)} className="btn btn-secondary" disabled={recordForm.processing}>
                            Cancel
                        </button>
                        <button type="submit" form="clinic-update-form" className="btn btn-primary" disabled={recordForm.processing}>
                            {recordForm.processing ? 'Saving...' : 'Update Record'}
                        </button>
                    </div>
                }
            >
                <form id="clinic-update-form" onSubmit={handleSubmit}>
                    {renderRecordForm()}
                </form>
            </Modal>

            {/* Reopen Clinic Record Cause & Effect Modal */}
            <CauseEffectModal
                show={showReopenConfirm}
                onClose={() => setShowReopenConfirm(false)}
                onConfirm={handleReopen}
                title="Reopen Completed Health Examination Record"
                subtitle="School Health Clinic — Medical Chart Audit"
                tone="warning"
                entityContext={{
                    label: 'Student Patient',
                    value: `${student?.lastName}, ${student?.firstName}`,
                    badge: student?.schoolIdNumber || 'STUDENT',
                }}
                cause="Reopening this record unlocks the clinical vitals, BMI assessment, and physician physical exam findings for modifications."
                effects={[
                    'Reverts medical clearance status from Completed back to Pending / Under Assessment.',
                    'Permits nursing and medical staff to update height, weight, BP, and diagnostic remarks.',
                    'Logs an administrative amendment event in the clinic health audit logs.',
                ]}
                requiresAcknowledgement={false}
                confirmText="Yes, Reopen Medical Record"
                cancelText="Keep Record Finalized"
            />
        </AuthenticatedLayout>
    );
}
