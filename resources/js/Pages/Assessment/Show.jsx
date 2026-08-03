import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Badge, ConfirmDialog, FormSection } from '@/Components/ui';
import { useState, useMemo } from 'react';
import { router } from '@inertiajs/react';

const coverageTypeToneMap = {
    full: 'info',
    partial: 'warning',
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
    const totalWaived = Number(assessment.totalWaived || 0);
    const remainingBalance = Number(assessment.remainingBalance || 0);

    const chargeColumns = useMemo(() => [
        { key: 'feeName', label: 'Fee Type', render: (row) => row.feeType?.feeName || '—' },
        { key: 'amount', label: 'Amount', render: (row) => `₱${Number(row.amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}` },
        { key: 'waivedAmount', label: 'Waived', render: (row) => `₱${Number(row.waivedAmount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}` },
        { key: 'netAmount', label: 'Net Amount', render: (row) => `₱${(Number(row.amount || 0) - Number(row.waivedAmount || 0)).toLocaleString('en-PH', { minimumFractionDigits: 2 })}` },
    ], []);

    const scholarshipColumns = useMemo(() => [
        { key: 'scholarshipName', label: 'Scholarship', render: (row) => row.scholarshipType?.scholarshipName || '—' },
        { key: 'coverageType', label: 'Coverage', render: (row) => (
            <Badge tone={coverageTypeToneMap[row.scholarshipType?.coverageType] || 'neutral'}>
                {row.scholarshipType?.coverageType?.charAt(0).toUpperCase() + row.scholarshipType?.coverageType?.slice(1)}
            </Badge>
        )},
        { key: 'coveragePercent', label: 'Percent', render: (row) => row.scholarshipType?.coveragePercent ? `${row.scholarshipType.coveragePercent}%` : '—' },
        { key: 'status', label: 'Status', render: (row) => (
            <Badge tone={row.status === 'active' ? 'success' : row.status === 'pending' ? 'pending' : 'danger'}>
                {row.status?.charAt(0).toUpperCase() + row.status?.slice(1)}
            </Badge>
        )},
    ], []);

    const paymentColumns = useMemo(() => [
        { key: 'paymentDate', label: 'Date', render: (row) => row.paymentDate ? new Date(row.paymentDate).toLocaleDateString('en-PH') : '—' },
        { key: 'amount', label: 'Amount', render: (row) => `₱${Number(row.amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}` },
        { key: 'paymentMethod', label: 'Method', render: (row) => row.paymentMethod || '—' },
        { key: 'referenceNumber', label: 'Reference', render: (row) => row.referenceNumber || '—' },
        { key: 'status', label: 'Status', render: (row) => (
            <Badge tone={row.status === 'completed' ? 'success' : row.status === 'pending' ? 'pending' : 'danger'}>
                {row.status?.charAt(0).toUpperCase() + row.status?.slice(1)}
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
                    subtitle={`${student?.firstName} ${student?.lastName} — ${enrollment?.course?.name || '—'}`}
                />
            }
        >
            <Head title="Assessment Details" />

            {/* Assessment Summary Card */}
            <Card title="Assessment Summary" subtitle="Financial overview" className="mb-6">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div className="card p-4">
                        <p className="text-sm text-brand-500">Total Assessed</p>
                        <p className="text-2xl font-bold text-brand-900">₱{totalAssessed.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</p>
                    </div>
                    <div className="card p-4">
                        <p className="text-sm text-brand-500">Scholarship Coverage</p>
                        <p className="text-2xl font-bold text-info-600">₱{totalScholarship.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</p>
                    </div>
                    <div className="card p-4">
                        <p className="text-sm text-brand-500">Waived Amount</p>
                        <p className="text-2xl font-bold text-warning-600">₱{totalWaived.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</p>
                    </div>
                    <div className="card p-4">
                        <p className="text-sm text-brand-500">Remaining Balance</p>
                        <p className="text-2xl font-bold text-danger-600">₱{remainingBalance.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</p>
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="card p-4">
                        <p className="text-sm text-brand-500">Total Paid</p>
                        <p className="text-2xl font-bold text-success-600">₱{totalPaid.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</p>
                    </div>
                    <div className="card p-4">
                        <p className="text-sm text-brand-500">Outstanding</p>
                        <p className="text-2xl font-bold text-danger-600">₱{Math.max(0, remainingBalance - totalPaid).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</p>
                    </div>
                </div>
            </Card>

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
            <Card title="Scholarships Applied" subtitle="Active scholarships for this assessment" className="mb-6">
                <div className="flex justify-between items-center mb-4">
                    <h4 className="font-medium text-brand-900">Applied Scholarships</h4>
                    <button
                        type="button"
                        className="btn btn-secondary btn-sm"
                        onClick={() => setShowApplyScholarship(true)}
                        disabled={isSubmitting}
                    >
                        Apply Scholarship
                    </button>
                </div>
                {scholarships.length > 0 ? (
                    <DataTable
                        columns={scholarshipColumns}
                        rows={scholarships}
                        emptyMessage="No scholarships applied"
                    />
                ) : (
                    <p className="text-brand-500 text-center py-8">No scholarships applied yet.</p>
                )}

                {/* Apply Scholarship Modal */}
                {showApplyScholarship && (
                    <div className="fixed inset-0 bg-black/50 backdrop-blur-sm z-40 flex items-center justify-center p-4">
                        <div className="bg-white rounded-card shadow-modal max-w-md w-full p-6 animate-scale-in">
                            <h3 className="text-lg font-semibold text-brand-900 mb-4">Apply Scholarship</h3>
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
                            <div className="flex justify-end gap-3 mt-6">
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
                                    {isSubmitting ? 'Applying...' : 'Apply'}
                                </button>
                            </div>
                        </div>
                    </div>
                )}
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