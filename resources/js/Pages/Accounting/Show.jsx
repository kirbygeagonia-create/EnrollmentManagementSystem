import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Badge, ConfirmDialog, FormSection, Select } from '@/Components/ui';
import { useState, useMemo } from 'react';
import { useForm, router } from '@inertiajs/react';

const paymentStatusToneMap = {
    completed: 'success',
    paid: 'success',
    pending: 'pending',
    voided: 'danger',
    cancelled: 'danger',
};

const paymentModeToneMap = {
    cash: 'info',
    online: 'accent',
};

export default function Show({ assessment, paymentModes }) {
    const [showVoidConfirm, setShowVoidConfirm] = useState(false);
    const [paymentToVoid, setPaymentToVoid] = useState(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const enrollment = assessment.enrollment;
    const student = enrollment?.student;
    const charges = assessment.charges || [];
    const payments = assessment.payments || [];

    const totalPaid = payments.reduce((sum, p) => sum + Number(p.amount || 0), 0);
    const totalAssessed = Number(assessment.totalAssessedAmount || 0);
    const totalScholarship = Number(assessment.totalScholarshipCoverage || 0);
    const totalWaived = Number(assessment.totalWaived || 0);
    const remainingBalance = Number(assessment.remainingBalance || 0);

    const chargeColumns = useMemo(() => [
        { key: 'feeType.feeName', label: 'Fee Type', render: (row) => row.feeType?.feeName || '—' },
        { key: 'amount', label: 'Amount', render: (row) => `₱${Number(row.amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}` },
        { key: 'waivedAmount', label: 'Waived', render: (row) => `₱${Number(row.waivedAmount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}` },
        { key: 'netAmount', label: 'Net Amount', render: (row) => `₱${(Number(row.amount || 0) - Number(row.waivedAmount || 0)).toLocaleString('en-PH', { minimumFractionDigits: 2 })}` },
    ], []);

    const paymentColumns = useMemo(() => [
        { key: 'paymentDate', label: 'Date', render: (row) => row.paymentDate ? new Date(row.paymentDate).toLocaleDateString('en-PH') : '—' },
        { key: 'orNumber', label: 'OR Number', render: (row) => row.orNumber || '—' },
        { key: 'amount', label: 'Amount', render: (row) => `₱${Number(row.amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}` },
        { key: 'paymentMode', label: 'Mode', render: (row) => (
            <Badge tone={paymentModeToneMap[row.paymentMode] || 'neutral'}>
                {row.paymentMode?.charAt(0).toUpperCase() + row.paymentMode?.slice(1)}
            </Badge>
        )},
        { key: 'paymentStatus', label: 'Status', render: (row) => (
            <Badge tone={paymentStatusToneMap[row.paymentStatus] || 'neutral'}>
                {row.paymentStatus?.charAt(0).toUpperCase() + row.paymentStatus?.slice(1)}
            </Badge>
        )},
        { key: 'processedBy', label: 'Processed By', render: (row) => row.processedBy?.name || '—' },
    ], []);

    // Record Payment Form
    const { data, setData, post, errors, reset } = useForm({
        orNumber: '',
        amount: '',
        paymentMode: 'cash',
        paymentDate: new Date().toISOString().split('T')[0],
    });

    const handleRecordPayment = (e) => {
        e.preventDefault();
        setIsSubmitting(true);
        post(route('accounting.payment.record', { assessment: assessment.assessmentId }), {
            onSuccess: () => {
                reset();
                setIsSubmitting(false);
            },
            onError: () => setIsSubmitting(false),
        });
    };

    const handleVoidPayment = (payment) => {
        setPaymentToVoid(payment);
        setShowVoidConfirm(true);
    };

    const confirmVoidPayment = () => {
        if (!paymentToVoid) return;
        setIsSubmitting(true);
        router.post(route('accounting.payment.void', { payment: paymentToVoid.paymentId }), {
            onSuccess: () => {
                setShowVoidConfirm(false);
                setPaymentToVoid(null);
                setIsSubmitting(false);
            },
            onError: () => setIsSubmitting(false),
        });
    };

    const paymentModeOptions = paymentModes.map(mode => ({
        value: mode.value,
        label: mode.value.charAt(0).toUpperCase() + mode.value.slice(1),
    }));

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Payment Recording"
                    subtitle={`${student?.lastName}, ${student?.firstName} ${student?.middleName ? student.middleName.charAt(0) + '.' : ''} — ${enrollment?.course?.name || '—'}`}
                />
            }
        >
            <Head title="Payment Recording" />

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
                ) : (
                    <p className="text-brand-500 text-center py-8">No charges recorded.</p>
                )}
            </Card>

            {/* Record Payment Form */}
            <Card title="Record Payment" subtitle="Enter payment details" className="mb-6">
                <form onSubmit={handleRecordPayment} className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <FormSection label="OR Number" required error={errors.orNumber}>
                            <input
                                type="text"
                                value={data.orNumber}
                                onChange={(e) => setData('orNumber', e.target.value)}
                                className={`form-input ${errors.orNumber ? 'form-input-error' : ''}`}
                                required
                                placeholder="Enter OR number"
                            />
                        </FormSection>
                        <FormSection label="Amount" required error={errors.amount}>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                value={data.amount}
                                onChange={(e) => setData('amount', e.target.value)}
                                className={`form-input ${errors.amount ? 'form-input-error' : ''}`}
                                required
                                placeholder="0.00"
                            />
                        </FormSection>
                        <FormSection label="Payment Mode" required error={errors.paymentMode}>
                            <Select
                                value={data.paymentMode}
                                onChange={(e) => setData('paymentMode', e.target.value)}
                                options={paymentModeOptions}
                                placeholder="Select mode"
                                className={`form-input ${errors.paymentMode ? 'form-input-error' : ''}`}
                                required
                            />
                        </FormSection>
                        <FormSection label="Payment Date" required error={errors.paymentDate}>
                            <input
                                type="date"
                                value={data.paymentDate}
                                onChange={(e) => setData('paymentDate', e.target.value)}
                                className={`form-input ${errors.paymentDate ? 'form-input-error' : ''}`}
                                required
                            />
                        </FormSection>
                    </div>
                    <div className="flex justify-end mt-4">
                        <button type="submit" className="btn btn-primary" disabled={isSubmitting}>
                            {isSubmitting ? 'Recording...' : 'Record Payment'}
                        </button>
                    </div>
                </form>
            </Card>

            {/* Payment History */}
            <Card title="Payment History" subtitle="Recorded payments for this assessment" className="mb-6">
                {payments.length > 0 ? (
                    <DataTable
                        columns={paymentColumns}
                        rows={payments}
                        children={(row) => (
                            <div className="flex items-center gap-2">
                                {row.paymentStatus === 'paid' || row.paymentStatus === 'completed' ? (
                                    <button
                                        type="button"
                                        className="btn btn-ghost btn-sm text-danger-600 hover:text-danger-900"
                                        onClick={() => handleVoidPayment(row)}
                                        disabled={isSubmitting}
                                    >
                                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        <span className="hidden sm:inline">Void</span>
                                    </button>
                                ) : null}
                            </div>
                        )}
                        emptyMessage="No payments recorded"
                    />
                ) : (
                    <p className="text-brand-500 text-center py-8">No payments recorded yet.</p>
                )}
            </Card>

            {/* Void Payment Confirm Dialog */}
            <ConfirmDialog
                show={showVoidConfirm}
                onClose={() => { setShowVoidConfirm(false); setPaymentToVoid(null); }}
                onConfirm={confirmVoidPayment}
                title="Void Payment"
                message={`Are you sure you want to void payment ${paymentToVoid?.orNumber || ''} (₱${Number(paymentToVoid?.amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })})? This action cannot be undone.`}
                confirmText="Void Payment"
                variant="danger"
                loading={isSubmitting}
            />
        </AuthenticatedLayout>
    );
}