import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { PageHeader, Card, Badge, FormSection, Modal } from '@/Components/ui';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Show({ enrollment, clinicRecord }) {
    const [showRecordModal, setShowRecordModal] = useState(false);
    const [showUpdateModal, setShowUpdateModal] = useState(false);

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
                    title="Clinic Assessment"
                    subtitle={`Phase 7 — ${getStudentName()} (${student?.schoolIdNumber})`}
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

            {/* Clinic Record Card */}
            <Card title="Clinic Assessment Record" subtitle={clinicRecord ? `Recorded on ${formatDate(clinicRecord.assessmentDate)}` : 'No clinic record exists yet'} className="mb-6">
                {clinicRecord ? (
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
                        <FormSection label="PhilHealth Number">
                            <p className="text-brand-900">{clinicRecord.philhealthNumber || '—'}</p>
                        </FormSection>
                        <FormSection label="PhilHealth Registered">
                            <Badge tone={clinicRecord.philhealthRegistered ? 'success' : 'warning'}>
                                {clinicRecord.philhealthRegistered ? 'Yes' : 'No'}
                            </Badge>
                        </FormSection>
                        <FormSection label="Assessment Date">
                            <p className="text-brand-900">{formatDate(clinicRecord.assessmentDate)}</p>
                        </FormSection>
                        <FormSection label="Assessment Notes" className="sm:col-span-2">
                            <p className="text-brand-900 whitespace-pre-wrap">{clinicRecord.assessmentNotes || '—'}</p>
                        </FormSection>
                        <FormSection label="Findings" className="sm:col-span-2">
                            <p className="text-brand-900 whitespace-pre-wrap">{clinicRecord.findings || '—'}</p>
                        </FormSection>
                    </div>
                ) : (
                    <div className="text-center py-8">
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

                {clinicRecord && (
                    <div className="mt-6 flex gap-3 border-t border-brand-100 pt-4">
                        <button
                            onClick={handleOpenUpdate}
                            className="btn btn-secondary btn-sm"
                        >
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Update Record
                        </button>
                    </div>
                )}
            </Card>

            {/* Record/Update Modal */}
            <Modal
                show={showRecordModal}
                onClose={() => setShowRecordModal(false)}
                title={isCreating ? 'Record Clinic Assessment' : 'Update Clinic Assessment'}
                size="lg"
            >
                <form onSubmit={handleSubmit} className="space-y-4">
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
                            {recordForm.errors.heightCm && <p className="text-sm text-red-600">{recordForm.errors.heightCm}</p>}
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
                            {recordForm.errors.weightKg && <p className="text-sm text-red-600">{recordForm.errors.weightKg}</p>}
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
                            {recordForm.errors.bloodPressure && <p className="text-sm text-red-600">{recordForm.errors.bloodPressure}</p>}
                        </FormSection>
                        <FormSection label="Assessment Date" required>
                            <input
                                type="date"
                                value={recordForm.data.assessmentDate}
                                onChange={(e) => recordForm.setData('assessmentDate', e.target.value)}
                                className="form-input"
                                required
                            />
                            {recordForm.errors.assessmentDate && <p className="text-sm text-red-600">{recordForm.errors.assessmentDate}</p>}
                        </FormSection>
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
                                    className="checkbox"
                                />
                                <span className="text-sm text-brand-700">Registered</span>
                            </label>
                        </FormSection>
                    </div>
                    <FormSection label="Assessment Notes" className="sm:col-span-2">
                        <textarea
                            value={recordForm.data.assessmentNotes}
                            onChange={(e) => recordForm.setData('assessmentNotes', e.target.value)}
                            className="form-input"
                            rows={3}
                            placeholder="General assessment notes..."
                        />
                    </FormSection>
                    <FormSection label="Findings" className="sm:col-span-2">
                        <textarea
                            value={recordForm.data.findings}
                            onChange={(e) => recordForm.setData('findings', e.target.value)}
                            className="form-input"
                            rows={3}
                            placeholder="Clinical findings..."
                        />
                    </FormSection>
                    <div className="flex justify-end gap-3 pt-4 border-t border-brand-100">
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
                            className="btn btn-primary"
                            disabled={recordForm.processing}
                        >
                            {recordForm.processing ? 'Saving...' : (isCreating ? 'Record Assessment' : 'Update Record')}
                        </button>
                    </div>
                </form>
            </Modal>

            <Modal
                show={showUpdateModal}
                onClose={() => setShowUpdateModal(false)}
                title="Update Clinic Assessment"
                size="lg"
            >
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <FormSection label="Height (cm)">
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                max="300"
                                value={recordForm.data.heightCm}
                                onChange={(e) => recordForm.setData('heightCm', e.target.value)}
                                className="form-input"
                                placeholder="e.g., 165.50"
                            />
                            {recordForm.errors.heightCm && <p className="text-sm text-red-600">{recordForm.errors.heightCm}</p>}
                        </FormSection>
                        <FormSection label="Weight (kg)">
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                max="300"
                                value={recordForm.data.weightKg}
                                onChange={(e) => recordForm.setData('weightKg', e.target.value)}
                                className="form-input"
                                placeholder="e.g., 60.00"
                            />
                            {recordForm.errors.weightKg && <p className="text-sm text-red-600">{recordForm.errors.weightKg}</p>}
                        </FormSection>
                        <FormSection label="Blood Pressure">
                            <input
                                type="text"
                                maxLength={20}
                                value={recordForm.data.bloodPressure}
                                onChange={(e) => recordForm.setData('bloodPressure', e.target.value)}
                                className="form-input"
                                placeholder="e.g., 120/80"
                            />
                            {recordForm.errors.bloodPressure && <p className="text-sm text-red-600">{recordForm.errors.bloodPressure}</p>}
                        </FormSection>
                        <FormSection label="Assessment Date">
                            <input
                                type="date"
                                value={recordForm.data.assessmentDate}
                                onChange={(e) => recordForm.setData('assessmentDate', e.target.value)}
                                className="form-input"
                            />
                            {recordForm.errors.assessmentDate && <p className="text-sm text-red-600">{recordForm.errors.assessmentDate}</p>}
                        </FormSection>
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
                                    className="checkbox"
                                />
                                <span className="text-sm text-brand-700">Registered</span>
                            </label>
                        </FormSection>
                    </div>
                    <FormSection label="Assessment Notes" className="sm:col-span-2">
                        <textarea
                            value={recordForm.data.assessmentNotes}
                            onChange={(e) => recordForm.setData('assessmentNotes', e.target.value)}
                            className="form-input"
                            rows={3}
                            placeholder="General assessment notes..."
                        />
                    </FormSection>
                    <FormSection label="Findings" className="sm:col-span-2">
                        <textarea
                            value={recordForm.data.findings}
                            onChange={(e) => recordForm.setData('findings', e.target.value)}
                            className="form-input"
                            rows={3}
                            placeholder="Clinical findings..."
                        />
                    </FormSection>
                    <div className="flex justify-end gap-3 pt-4 border-t border-brand-100">
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
                            className="btn btn-primary"
                            disabled={recordForm.processing}
                        >
                            {recordForm.processing ? 'Saving...' : 'Update Record'}
                        </button>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}