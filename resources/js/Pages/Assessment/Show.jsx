import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Badge, ConfirmDialog, FormSection, Modal, StatCard } from '@/Components/ui';
import { useState, useMemo } from 'react';
import { router } from '@inertiajs/react';

const peso = (n) => `₱${Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const coverageTypeToneMap = {
    full: 'info',
    partial: 'partial',
};

export default function Show({ assessment, scholarshipTypes }) {
    const [showConfirmFinalize, setShowConfirmFinalize] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [showApplyScholarship, setShowApplyScholarship] = useState(false);
    const [selectedScholarshipTypeId, setSelectedScholarshipTypeId] = useState('');

    const enrollment = assessment.enrollment;
    const student = enrollment?.student;
    const charges = assessment.charges || [];
    const scholarships = assessment.scholarships || [];
    const payments = assessment.payments || [];

    const totalPaid = payments.reduce((sum, p) => sum + Number(p.amount || 0), 0);
    const totalAssessed = Number(assessment.totalAssessedAmount || 0);
    const totalScholarship = Number(assessment.totalScholarshipCoverage || 0);
    const remainingBalance = Number(assessment.remainingBalance || 0);
    const outstanding = Math.max(0, remainingBalance - totalPaid);

    // Status banner tone — assessed = accent, pending = warning, settled = success.
    const statusBanner = (() => {
        if (outstanding <= 0 && charges.length > 0) {
            return { tone: 'success', label: 'Fully Settled', message: 'All charges have been paid. This assessment is complete.' };
        }
        if (assessment.status === 'assessed') {
            return { tone: 'assessed', label: 'Assessed', message: 'Assessment finalized. Forward to Accounting for payment collection.' };
        }
        return { tone: 'pending', label: 'Pending', message: 'Assessment not yet finalized. Compute charges and finalize to proceed.' };
    })();

    const studentName = student ? `${student.firstName} ${student.middleName ? student.middleName.charAt(0) + '. ' : ''}${student.lastName}` : '—';

    const chargeColumns = useMemo(() => [
        { key: 'feeName', label: 'Fee Type', render: (row) => row.feeType?.feeName || '—' },
        { key: 'amount', label: 'Amount', render: (row) => peso(row.amount) },
        { key: 'waivedAmount', label: 'Waived', render: (row) => (
            <span className={Number(row.waivedAmount || 0) > 0 ? 'text-info-600 font-medium' : 'text-brand-400'}>
                {peso(row.waivedAmount)}
            </span>
        )},
        { key: 'netAmount', label: 'Net Amount', render: (row) => (
            <span className="font-semibold text-brand-900">{peso(Number(row.amount || 0) - Number(row.waivedAmount || 0))}</span>
        )},
    ], []);

    const scholarshipColumns = useMemo(() => [
        { key: 'scholarshipName', label: 'Scholarship', render: (row) => row.scholarshipType?.scholarshipName || '—' },
        { key: 'coverageType', label: 'Coverage', render: (row) => (
            <Badge tone={coverageTypeToneMap[row.scholarshipType?.coverageType] || 'neutral'}>
                {row.scholarshipType?.coverageType ? row.scholarshipType.coverageType.charAt(0).toUpperCase() + row.scholarshipType.coverageType.slice(1) : '—'}
            </Badge>
        )},
        { key: 'coveragePercent', label: 'Percent', render: (row) => row.scholarshipType?.coveragePercent ? `${row.scholarshipType.coveragePercent}%` : '—' },
        { key: 'status', label: 'Status', render: (row) => (
            <Badge tone={row.status === 'active' ? 'success' : row.status === 'pending' ? 'pending' : 'danger'}>
                {row.status ? row.status.charAt(0).toUpperCase() + row.status.slice(1) : '—'}
            </Badge>
        )},
    ], []);

    const paymentColumns = useMemo(() => [
        { key: 'paymentDate', label: 'Date', render: (row) => row.paymentDate ? new Date(row.paymentDate).toLocaleDateString('en-PH') : '—' },
        { key: 'amount', label: 'Amount', render: (row) => peso(row.amount) },
        { key: 'paymentMethod', label: 'Method', render: (row) => row.paymentMethod || '—' },
        { key: 'referenceNumber', label: 'Reference', render: (row) => row.referenceNumber || '—' },
        { key: 'status', label: 'Status', render: (row) => (
            <Badge tone={row.status === 'completed' ? 'paid' : row.status === 'pending' ? 'pending' : 'danger'}>
                {row.status ? row.status.charAt(0).toUpperCase() + row.status.slice(1) : '—'}
            </Badge>
        )},
    ], []);

    const handleCompute = () => {
        router.post(route('assessment.compute', { enrollment: enrollment.enrollmentId }), {}, {
            onSuccess: () => setIsSubmitting(false),
            onError: () => setIsSubmitting(false),
        });
        setIsSubmitting(true);
    };

    const handleApplyScholarship = () => {
        if (!selectedScholarshipTypeId) return;
        router.post(route('assessment.scholarships.apply', { assessment: assessment.assessmentId }), { scholarshipTypeId: selectedScholarshipTypeId }, {
            onSuccess: () => {
                setShowApplyScholarship(false);
                setSelectedScholarshipTypeId('');
                setIsSubmitting(false);
            },
            onError: () => setIsSubmitting(false),
        });
        setIsSubmitting(true);
    };

    const handleAdjustCharges = (e) => {
        e.preventDefault();
        const formData = new FormData(e.currentTarget);
        const chargesData = [];

        for (const [key, value] of formData.entries()) {
            if (key.startsWith('charges[')) {
                const match = key.match(/charges\[(\d+)\]\[(\w+)\]/);
                if (match) {
                    const idx = parseInt(match[1]);
                    const field = match[2];
                    if (!chargesData[idx]) chargesData[idx] = {};
                    chargesData[idx][field] = value;
                }
            }
        }

        router.patch(route('assessment.charges.adjust', { assessment: assessment.assessmentId }), { charges: chargesData.filter(Boolean) }, {
            onSuccess: () => setIsSubmitting(false),
            onError: () => setIsSubmitting(false),
        });
        setIsSubmitting(true);
    };

    const handleFinalize = () => {
        router.post(route('assessment.finalize', { assessment: assessment.assessmentId }), {}, {
            onSuccess: () => {
                setShowConfirmFinalize(false);
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
                    title="Assessment Details"
                    subtitle={`${studentName} — ${enrollment?.course?.name || '—'}`}
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Logo"
                />
            }
        >
            <Head title="Assessment Details" />

            {/* Status Banner */}
            <div className={`rounded-card border p-4 mb-6 flex items-start gap-3 ${
                statusBanner.tone === 'success' ? 'bg-success-50 border-success-200' :
                statusBanner.tone === 'assessed' ? 'bg-accent-50 border-accent-200' :
                'bg-warning-50 border-warning-200'
            }`}>
                <span className={`flex-shrink-0 inline-flex items-center justify-center h-10 w-10 rounded-xl ${
                    statusBanner.tone === 'success' ? 'bg-success-100 text-success-700' :
                    statusBanner.tone === 'assessed' ? 'bg-accent-100 text-accent-700' :
                    'bg-warning-100 text-warning-700'
                }`}>
                    {statusBanner.tone === 'success' ? (
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                        </svg>
                    ) : statusBanner.tone === 'assessed' ? (
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    ) : (
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    )}
                </span>
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                        <h3 className="font-semibold text-brand-900">{statusBanner.label}</h3>
                        <Badge tone={statusBanner.tone === 'success' ? 'paid' : statusBanner.tone}>
                            {assessment.status ? assessment.status.charAt(0).toUpperCase() + assessment.status.slice(1) : '—'}
                        </Badge>
                    </div>
                    <p className="text-sm text-brand-600 mt-1">{statusBanner.message}</p>
                </div>
            </div>

            {/* Student + Enrollment info strip */}
            <Card className="mb-6">
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div>
                        <dt className="text-xs font-medium text-brand-500 uppercase tracking-wider">School ID</dt>
                        <dd className="mt-1 font-mono text-sm text-brand-900">{student?.schoolIdNumber || '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-xs font-medium text-brand-500 uppercase tracking-wider">Course</dt>
                        <dd className="mt-1 text-sm text-brand-900">{enrollment?.course?.name || '—'}</dd>
                    </div>
                    <div>
                        <dt className="text-xs font-medium text-brand-500 uppercase tracking-wider">Term</dt>
                        <dd className="mt-1 text-sm text-brand-900">
                            {enrollment?.term ? `${enrollment.term.semester?.value || enrollment.term.semester} ${enrollment.term.academicYear?.yearLabel || ''}`.trim() : '—'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-xs font-medium text-brand-500 uppercase tracking-wider">Assessment ID</dt>
                        <dd className="mt-1 font-mono text-sm text-brand-900">#{assessment.assessmentId}</dd>
                    </div>
                </div>
            </Card>

            {/* Assessment Summary StatCards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <StatCard
                    label="Total Assessed"
                    value={peso(totalAssessed)}
                    iconBg="seait"
                    icon={
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 7h6m0 10v-3m0 0h-6m6 0V7" />
                        </svg>
                    }
                />
                <StatCard
                    label="Scholarship Coverage"
                    value={peso(totalScholarship)}
                    iconBg="info"
                    icon={
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 14l9-5-9-5-9 5 9 5z" />
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055A11.952 11.952 0 003.175 12.83a12.083 12.083 0 01.665-6.479L12 14z" />
                        </svg>
                    }
                />
                <StatCard
                    label="Total Paid"
                    value={peso(totalPaid)}
                    iconBg="success"
                    icon={
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                        </svg>
                    }
                />
                <StatCard
                    label="Remaining Balance"
                    value={peso(remainingBalance)}
                    iconBg="danger"
                    icon={
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
            </div>

            {/* Charges Breakdown */}
            <Card title="Charges Breakdown" subtitle="Individual fee items" className="mb-6">
                {charges.length > 0 ? (
                    <>
                        <form onSubmit={handleAdjustCharges} className="space-y-4">
                            <DataTable
                                columns={chargeColumns}
                                rows={charges.map((charge) => ({
                                    ...charge,
                                    chargeId: charge.chargeId,
                                    amount: charge.amount,
                                    waivedAmount: charge.waivedAmount || 0,
                                }))}
                                emptyMessage="No charges found"
                            />
                            <div className="flex justify-end mt-4">
                                <button type="submit" className="btn btn-secondary" disabled={isSubmitting}>
                                    {isSubmitting ? 'Saving...' : 'Save Adjustments'}
                                </button>
                            </div>
                        </form>
                    </>
                ) : (
                    <p className="text-brand-500 text-center py-8">No charges recorded. Click "Compute Assessment" to generate charges.</p>
                )}
            </Card>

            {/* Scholarships */}
            <Card
                title="Scholarships Applied"
                subtitle="Active scholarships for this assessment"
                className="mb-6"
                actions={
                    <button
                        type="button"
                        className="btn btn-secondary btn-sm"
                        onClick={() => setShowApplyScholarship(true)}
                        disabled={isSubmitting}
                    >
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Apply Scholarship
                    </button>
                }
            >
                {scholarships.length > 0 ? (
                    <DataTable
                        columns={scholarshipColumns}
                        rows={scholarships}
                        emptyMessage="No scholarships applied"
                    />
                ) : (
                    <p className="text-brand-500 text-center py-8">No scholarships applied yet.</p>
                )}

                {/* Apply Scholarship Modal — uses new icon/subtitle/footer props */}
                <Modal
                    show={showApplyScholarship}
                    onClose={() => { setShowApplyScholarship(false); setSelectedScholarshipTypeId(''); }}
                    title="Apply Scholarship"
                    subtitle="Award a scholarship type to reduce the assessed balance."
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 14l9-5-9-5-9 5 9 5z" />
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055" />
                        </svg>
                    }
                    size="md"
                    footer={
                        <div className="flex justify-end gap-3">
                            <button
                                type="button"
                                className="btn btn-secondary"
                                onClick={() => { setShowApplyScholarship(false); setSelectedScholarshipTypeId(''); }}
                                disabled={isSubmitting}
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                className="btn btn-primary"
                                onClick={handleApplyScholarship}
                                disabled={isSubmitting || !selectedScholarshipTypeId}
                            >
                                {isSubmitting ? 'Applying...' : 'Apply Scholarship'}
                            </button>
                        </div>
                    }
                >
                    <FormSection label="Scholarship Type" required>
                        <select
                            value={selectedScholarshipTypeId}
                            onChange={(e) => setSelectedScholarshipTypeId(e.target.value)}
                            className="form-select"
                            required
                        >
                            <option value="" disabled>Select scholarship</option>
                            {scholarshipTypes.map(st => (
                                <option key={st.scholarshipTypeId} value={st.scholarshipTypeId}>
                                    {st.scholarshipName} ({st.coverageType} - {st.coveragePercent}%)
                                </option>
                            ))}
                        </select>
                    </FormSection>
                </Modal>
            </Card>

            {/* Payments */}
            <Card title="Payments" subtitle="Payment history for this assessment" className="mb-6">
                {payments.length > 0 ? (
                    <DataTable
                        columns={paymentColumns}
                        rows={payments}
                        emptyMessage="No payments recorded"
                    />
                ) : (
                    <p className="text-brand-500 text-center py-8">No payments recorded yet.</p>
                )}
            </Card>

            {/* Actions */}
            <Card title="Actions" className="mb-6">
                <div className="flex flex-wrap gap-3">
                    <button
                        type="button"
                        className="btn btn-primary"
                        onClick={handleCompute}
                        disabled={isSubmitting || charges.length > 0}
                    >
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 7h6m0 10v-3m0 0h-6m6 0V7" />
                        </svg>
                        {isSubmitting ? 'Computing...' : 'Compute Assessment'}
                    </button>

                    <button
                        type="button"
                        className="btn btn-secondary"
                        onClick={() => setShowConfirmFinalize(true)}
                        disabled={isSubmitting || charges.length === 0}
                    >
                        Finalize Assessment
                    </button>
                </div>

                {/* Confirm Dialog for Finalize */}
                <ConfirmDialog
                    show={showConfirmFinalize}
                    onClose={() => setShowConfirmFinalize(false)}
                    onConfirm={handleFinalize}
                    title="Finalize Assessment"
                    message="This will finalize the assessment and sign the assessment step in the enrollment workflow. Are you sure you want to proceed?"
                    confirmText="Finalize"
                    variant="primary"
                    loading={isSubmitting}
                />
            </Card>
        </AuthenticatedLayout>
    );
}
