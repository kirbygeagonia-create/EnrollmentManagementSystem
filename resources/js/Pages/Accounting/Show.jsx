import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Badge, ConfirmDialog, FormSection, Select, StatCard } from '@/Components/ui';
import { useState, useMemo } from 'react';
import { useForm, router } from '@inertiajs/react';

const peso = (n) => `₱${Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const paymentStatusToneMap = {
    completed: 'paid',
    paid: 'paid',
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
    const outstanding = Math.max(0, remainingBalance - totalPaid);

    // Status banner — fully paid / partial / unpaid.
    const statusBanner = (() => {
        if (outstanding <= 0 && charges.length > 0) {
            return { tone: 'success', label: 'Fully Paid', message: 'All charges have been settled. The enrollment is ready to advance to the next workflow step.' };
        }
        if (totalPaid > 0) {
            return { tone: 'partial', label: 'Partially Paid', message: 'A partial payment has been recorded. Collect the remaining balance to complete this assessment.' };
        }
        return { tone: 'danger', label: 'Unpaid', message: 'No payments have been recorded yet. Collect the full balance to advance the enrollment.' };
    })();

    const studentName = student ? `${student.lastName}, ${student.firstName} ${student.middleName ? student.middleName.charAt(0) + '.' : ''}` : '—';

    const chargeColumns = useMemo(() => [
        { key: 'feeType.feeName', label: 'Fee Type', render: (row) => row.feeType?.feeName || '—' },
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

    const paymentColumns = useMemo(() => [
        { key: 'paymentDate', label: 'Date', render: (row) => row.paymentDate ? new Date(row.paymentDate).toLocaleDateString('en-PH') : '—' },
        { key: 'orNumber', label: 'OR Number', render: (row) => (
            <span className="font-mono text-sm">{row.orNumber || '—'}</span>
        )},
        { key: 'amount', label: 'Amount', render: (row) => (
            <span className="font-semibold text-brand-900">{peso(row.amount)}</span>
        )},
        { key: 'paymentMode', label: 'Mode', render: (row) => (
            <Badge tone={paymentModeToneMap[row.paymentMode] || 'neutral'}>
                {row.paymentMode ? row.paymentMode.charAt(0).toUpperCase() + row.paymentMode.slice(1) : '—'}
            </Badge>
        )},
        { key: 'paymentStatus', label: 'Status', render: (row) => (
            <Badge tone={paymentStatusToneMap[row.paymentStatus] || 'neutral'}>
                {row.paymentStatus ? row.paymentStatus.charAt(0).toUpperCase() + row.paymentStatus.slice(1) : '—'}
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
                    subtitle={`${studentName} — ${enrollment?.course?.name || '—'}`}
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Logo"
                />
            }
        >
            <Head title="Payment Recording" />

            {/* Status Banner */}
            <div className={`rounded-card border p-4 mb-6 flex items-start gap-3 ${
                statusBanner.tone === 'success' ? 'bg-success-50 border-success-200' :
                statusBanner.tone === 'partial' ? 'bg-warning-50 border-warning-200' :
                'bg-danger-50 border-danger-200'
            }`}>
                <span className={`flex-shrink-0 inline-flex items-center justify-center h-10 w-10 rounded-xl ${
                    statusBanner.tone === 'success' ? 'bg-success-100 text-success-700' :
                    statusBanner.tone === 'partial' ? 'bg-warning-100 text-warning-700' :
                    'bg-danger-100 text-danger-700'
                }`}>
                    {statusBanner.tone === 'success' ? (
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                        </svg>
                    ) : statusBanner.tone === 'partial' ? (
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    ) : (
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
                        </svg>
                    )}
                </span>
                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                        <h3 className="font-semibold text-brand-900">{statusBanner.label}</h3>
                        <Badge tone={statusBanner.tone === 'success' ? 'paid' : statusBanner.tone === 'partial' ? 'partial' : 'danger'}>
                            {statusBanner.label}
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
                    label="Waived / Scholarship"
                    value={peso(Number(totalWaived) + Number(totalScholarship))}
                    iconBg="info"
                    icon={
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 14l9-5-9-5-9 5 9 5z" />
                        </svg>
                    }
                />
                <StatCard
                    label="Outstanding Balance"
                    value={peso(outstanding)}
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
            <Card title="Record Payment" subtitle="Enter payment details to collect a new payment" className="mb-6">
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
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1" />
                            </svg>
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
                message={`Are you sure you want to void payment ${paymentToVoid?.orNumber || ''} (${peso(paymentToVoid?.amount)})? This action cannot be undone.`}
                confirmText="Void Payment"
                variant="danger"
                loading={isSubmitting}
            />
        </AuthenticatedLayout>
    );
}
