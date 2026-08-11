import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { PageHeader, Card, Badge, FormSection, Modal, ConfirmDialog } from '@/Components/ui';
import { useState } from 'react';

export default function Show({ enrollment, clinicRecord }) {
    const [showRecordModal, setShowRecordModal] = useState(false);
    const [showUpdateModal, setShowUpdateModal] = useState(false);
    const [showReopenConfirm, setShowReopenConfirm] = useState(false);

    const student = enrollment.student;
    const course = enrollment.course;
    const term = enrollment.term;

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

    const statusToneMap = {
        pending: 'warning',
        completed: 'success',
        reopened: 'info',
    };

    const statusLabelMap = {
        pending: 'Pending',
        completed: 'Completed',
        reopened: 'Reopened',
    };

    const recordModalIcon = (
        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
    );

    const renderRecordForm = () => (
        <form onSubmit={handleSubmit} className="space-y-6">
            {/* Vitals Section */}
            <div>
                <h4 className="text-sm font-semibold text-brand-900 uppercase tracking-wide mb-3">Vitals</h4>
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
            <div>
                <h4 className="text-sm font-semibold text-brand-900 uppercase tracking-wide mb-3">PhilHealth Registration</h4>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <FormSection label="PhilHealth Number">
                        <input
                            type="text"
                            maxLength={50}
                            value={recordForm.data.philhealthNumber}
                            onChange={(e) => recordForm.setData('philhealthNumber', e.target.value)}
                            className="form-input"
                            placeholder="Optional"
                        />
                    </FormSection>
                    <FormSection label="PhilHealth Registered">
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={recordForm.data.philhealthRegistered}
                                onChange={(e) => recordForm.setData('philhealthRegistered', e.target.checked)}
                                className="form-checkbox"
                            />
                            <span className="text-sm text-brand-700">Registered</span>
                        </label>
                    </FormSection>
                </div>
            </div>

            {/* Clinical Notes Section */}
            <div>
                <h4 className="text-sm font-semibold text-brand-900 uppercase tracking-wide mb-3">Clinical Notes</h4>
                <div className="space-y-4">
                    <FormSection label="Assessment Notes">
                        <textarea
                            value={recordForm.data.assessmentNotes}
                            onChange={(e) => recordForm.setData('assessmentNotes', e.target.value)}
                            className="form-input"
                            rows={3}
                            placeholder="General assessment notes..."
                        />
                    </FormSection>
                    <FormSection label="Findings">
                        <textarea
                            value={recordForm.data.findings}
                            onChange={(e) => recordForm.setData('findings', e.target.value)}
                            className="form-input"
                            rows={3}
                            placeholder="Clinical findings..."
                        />
                    </FormSection>
                </div>
            </div>
        </form>
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Clinic Assessment"
                    subtitle={`Phase 7 — ${getStudentName()} (${student?.schoolIdNumber})`}
                    logo="/images/logos/clinic.jpg"
                    logoAlt="Clinic Office"
                    actions={
                        <Link
                            href={route('clinic.index')}
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
            <Head title={`Clinic — ${getStudentName()}`} />

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

            {/* Clinic Record Card */}
            <Card
                title="Clinic Assessment Record"
                subtitle={clinicRecord ? `Recorded on ${formatDate(clinicRecord.assessmentDate)}` : 'No clinic record exists yet'}
                actions={
                    clinicRecord ? (
                        <Badge tone={statusToneMap[clinicRecord.status] || 'neutral'}>
                            {statusLabelMap[clinicRecord.status] || clinicRecord.status}
                        </Badge>
                    ) : null
                }
                className="mb-6"
            >
                {clinicRecord ? (
                    <div className="space-y-6">
                        {/* Vitals */}
                        <div>
                            <h4 className="text-sm font-semibold text-brand-900 uppercase tracking-wide mb-3 flex items-center gap-2">
                                <svg className="h-4 w-4 text-seait-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                </svg>
                                Vitals
                            </h4>
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <FormSection label="Height (cm)">
                                    <p className="text-brand-900">{clinicRecord.heightCm || '—'}</p>
                                </FormSection>
                                <FormSection label="Weight (kg)">
                                    <p className="text-brand-900">{clinicRecord.weightKg || '—'}</p>
                                </FormSection>
                                <FormSection label="Blood Pressure">
                                    <p className="text-brand-900">{clinicRecord.bloodPressure || '—'}</p>
                                </FormSection>
                                <FormSection label="Assessment Date">
                                    <p className="text-brand-900">{formatDate(clinicRecord.assessmentDate)}</p>
                                </FormSection>
                            </div>
                        </div>

                        {/* PhilHealth Registration */}
                        <div>
                            <h4 className="text-sm font-semibold text-brand-900 uppercase tracking-wide mb-3 flex items-center gap-2">
                                <svg className="h-4 w-4 text-seait-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                PhilHealth Registration
                            </h4>
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <FormSection label="PhilHealth Number">
                                    <p className="text-brand-900 font-mono">{clinicRecord.philhealthNumber || '—'}</p>
                                </FormSection>
                                <FormSection label="PhilHealth Registered">
                                    <Badge tone={clinicRecord.philhealthRegistered ? 'success' : 'warning'}>
                                        {clinicRecord.philhealthRegistered ? 'Yes' : 'No'}
                                    </Badge>
                                </FormSection>
                            </div>
                        </div>

                        {/* Clinical Notes */}
                        <div>
                            <h4 className="text-sm font-semibold text-brand-900 uppercase tracking-wide mb-3 flex items-center gap-2">
                                <svg className="h-4 w-4 text-seait-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Clinical Notes
                            </h4>
                            <div className="space-y-4">
                                <FormSection label="Assessment Notes">
                                    <p className="text-brand-900 whitespace-pre-wrap">{clinicRecord.assessmentNotes || '—'}</p>
                                </FormSection>
                                <FormSection label="Findings">
                                    <p className="text-brand-900 whitespace-pre-wrap">{clinicRecord.findings || '—'}</p>
                                </FormSection>
                            </div>
                        </div>

                        {/* Actions */}
                        <div className="flex flex-wrap gap-3 border-t border-brand-100 pt-4">
                            {clinicRecord.status !== 'completed' && (
                                <button
                                    onClick={handleOpenUpdate}
                                    className="btn btn-secondary btn-sm"
                                >
                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    Update Record
                                </button>
                            )}
                            {clinicRecord.status === 'completed' && (
                                <button
                                    onClick={() => setShowReopenConfirm(true)}
                                    className="btn btn-accent btn-sm"
                                >
                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    Reopen Record
                                </button>
                            )}
                        </div>
                    </div>
                ) : (
                    <div className="text-center py-8">
                        <svg className="mx-auto h-12 w-12 text-brand-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L7.05 14.95a6 6 0 00-3.86.517M12 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <p className="text-brand-500 mb-4">No clinic assessment has been recorded for this student.</p>
                        <button
                            onClick={handleOpenRecord}
                            className="btn btn-primary"
                        >
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Record Assessment
                        </button>
                    </div>
                )}
            </Card>

            {/* Record Modal */}
            <Modal
                show={showRecordModal}
                onClose={() => setShowRecordModal(false)}
                title="Record Clinic Assessment"
                subtitle="Capture vitals, PhilHealth registration, and clinical notes."
                icon={recordModalIcon}
                size="lg"
                footer={
                    <div className="flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={() => setShowRecordModal(false)}
                            className="btn btn-secondary"
                            disabled={recordForm.processing}
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            form="clinic-record-form"
                            className="btn btn-primary"
                            disabled={recordForm.processing}
                        >
                            {recordForm.processing ? 'Saving...' : 'Record Assessment'}
                        </button>
                    </div>
                }
            >
                <form id="clinic-record-form" onSubmit={handleSubmit} className="space-y-6">
                    {renderRecordForm()}
                </form>
            </Modal>

            {/* Update Modal */}
            <Modal
                show={showUpdateModal}
                onClose={() => setShowUpdateModal(false)}
                title="Update Clinic Assessment"
                subtitle="Revise the existing clinic record details."
                icon={recordModalIcon}
                size="lg"
                footer={
                    <div className="flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={() => setShowUpdateModal(false)}
                            className="btn btn-secondary"
                            disabled={recordForm.processing}
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            form="clinic-update-form"
                            className="btn btn-primary"
                            disabled={recordForm.processing}
                        >
                            {recordForm.processing ? 'Saving...' : 'Update Record'}
                        </button>
                    </div>
                }
            >
                <form id="clinic-update-form" onSubmit={handleSubmit} className="space-y-6">
                    {renderRecordForm()}
                </form>
            </Modal>

            {/* Reopen Confirm Dialog */}
            <ConfirmDialog
                show={showReopenConfirm}
                onClose={() => setShowReopenConfirm(false)}
                onConfirm={handleReopen}
                title="Reopen Clinic Record"
                message="This will reopen the completed clinic record for editing. The record status will change to 'Reopened'. Continue?"
                confirmText="Reopen"
                variant="warning"
            />
        </AuthenticatedLayout>
    );
}
