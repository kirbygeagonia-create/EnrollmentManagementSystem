import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Badge, CauseEffectModal, StatCard } from '@/Components/ui';
import { useState, useMemo } from 'react';
import useFormKeyboardNav from '@/Hooks/useFormKeyboardNav';

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
    check: 'neutral',
};

export default function Show({ assessment }) {
    const [showVoidConfirm, setShowVoidConfirm] = useState(false);
    const [paymentToVoid, setPaymentToVoid] = useState(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const enrollment = assessment.enrollment;
    const student = enrollment?.student;
    const charges = assessment.charges || [];
    const payments = assessment.payments || [];

    const totalPaid = payments.reduce((sum, p) => p.paymentStatus === 'paid' || p.paymentStatus === 'completed' ? sum + Number(p.amount || 0) : sum, 0);
    const totalAssessed = Number(assessment.totalAssessedAmount || 0);
    const totalScholarship = Number(assessment.totalScholarshipCoverage || 0);
    const totalWaived = Number(assessment.totalWaived || 0);
    const netAssessed = Math.max(0, totalAssessed - totalScholarship - totalWaived);
    const outstanding = Math.max(0, netAssessed - totalPaid);

    const studentName = student ? `${student.lastName}, ${student.firstName} ${student.middleName ? student.middleName.charAt(0) + '.' : ''}` : '—';

    // Record Payment Form
    const { formProps } = useFormKeyboardNav();
    const { data, setData, post, errors, reset } = useForm({
        orNumber: '',
        amount: outstanding > 0 ? outstanding.toString() : '',
        paymentMode: 'cash',
        paymentDate: '',
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

    const setQuickAmount = (val) => {
        setData('amount', val.toString());
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

    const chargeColumns = useMemo(() => [
        { key: 'feeType.feeName', label: 'Fee Item', render: (row) => <span className="font-semibold text-slate-800">{row.feeType?.feeName || '—'}</span> },
        { key: 'amount', label: 'Assessed', render: (row) => peso(row.amount) },
        { key: 'waivedAmount', label: 'Waived/Grant', render: (row) => (
            <span className={Number(row.waivedAmount || 0) > 0 ? 'text-emerald-600 font-medium' : 'text-slate-400'}>
                {peso(row.waivedAmount)}
            </span>
        )},
        { key: 'netAmount', label: 'Net Payable', render: (row) => (
            <span className="font-bold text-slate-900">{peso(Number(row.amount || 0) - Number(row.waivedAmount || 0))}</span>
        )},
    ], []);

    const paymentColumns = useMemo(() => [
        { key: 'paymentDate', label: 'Date', render: (row) => row.paymentDate ? new Date(row.paymentDate).toLocaleDateString('en-PH') : '—' },
        { key: 'orNumber', label: 'Official Receipt (OR)', render: (row) => (
            <span className="font-mono text-xs font-bold text-seait-700 bg-seait-50 px-2 py-0.5 rounded border border-seait-200">
                {row.orNumber || '—'}
            </span>
        )},
        { key: 'amount', label: 'Amount Paid', render: (row) => (
            <span className="font-bold text-emerald-700">{peso(row.amount)}</span>
        )},
        { key: 'paymentMode', label: 'Mode', render: (row) => (
            <Badge tone={paymentModeToneMap[row.paymentMode] || 'neutral'}>
                {row.paymentMode ? row.paymentMode.toUpperCase() : '—'}
            </Badge>
        )},
        { key: 'paymentStatus', label: 'Status', render: (row) => (
            <Badge tone={paymentStatusToneMap[row.paymentStatus] || 'neutral'}>
                {row.paymentStatus ? row.paymentStatus.charAt(0).toUpperCase() + row.paymentStatus.slice(1) : '—'}
            </Badge>
        )},
        { key: 'processedBy', label: 'Cashier In-Charge', render: (row) => row.processedBy?.name || 'Cashier Desk' },
    ], []);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Cashier Terminal & Payment Desk"
                    subtitle={`${studentName} • ${enrollment?.course?.courseName || '—'} (${enrollment?.term?.semester?.value || enrollment?.term?.semester || 'Current Term'})`}
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Cashier Office"
                    phaseBadge="Phase 4 · Cashier Collections"
                    officeBadge="Office 2 · Cashier Terminal"
                />
            }
        >
            <Head title="Cashier Terminal" />

            {/* Quick Metrics */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <StatCard
                    compact
                    label="Total Assessed"
                    value={peso(totalAssessed)}
                    iconBg="seait"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 7h6m0 10v-3m0 0h-6m6 0V7" />
                        </svg>
                    }
                />
                <StatCard
                    compact
                    label="Total Grants / Waived"
                    value={peso(totalScholarship + totalWaived)}
                    iconBg="info"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 14l9-5-9-5-9 5 9 5z" />
                        </svg>
                    }
                />
                <StatCard
                    compact
                    label="Total Paid To Date"
                    value={peso(totalPaid)}
                    iconBg="success"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                        </svg>
                    }
                />
                <StatCard
                    compact
                    label="Outstanding Balance"
                    value={peso(outstanding)}
                    iconBg={outstanding > 0 ? "danger" : "success"}
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1" />
                        </svg>
                    }
                />
            </div>

            {/* Split Screen POS Terminal View */}
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-8">
                {/* Left Side: Fee Ledger & Student Info */}
                <div className="lg:col-span-7 space-y-6">
                    {/* Student Card */}
                    <div className="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                            <h3 className="font-heading font-bold text-slate-900 text-sm flex items-center gap-2">
                                <span className="h-2.5 w-2.5 rounded-full bg-seait-500" />
                                Student Enrollment Summary
                            </h3>
                            <span className="font-mono text-xs font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded border">
                                Assessment #{assessment.assessmentId}
                            </span>
                        </div>
                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
                            <div>
                                <span className="text-slate-400 font-semibold block">Student ID Number</span>
                                <span className="font-mono font-bold text-slate-800 text-sm">{student?.schoolIdNumber || '—'}</span>
                            </div>
                            <div>
                                <span className="text-slate-400 font-semibold block">Full Name</span>
                                <span className="font-bold text-slate-800 text-sm">{studentName}</span>
                            </div>
                            <div>
                                <span className="text-slate-400 font-semibold block">Academic Course</span>
                                <span className="font-bold text-slate-800">{enrollment?.course?.courseCode || '—'}</span>
                            </div>
                        </div>
                    </div>

                    {/* Fee Ledger Table */}
                    <Card title="Itemized Fee Charges Breakdown" subtitle="Detailed billing line items and fee allocations">
                        {charges.length > 0 ? (
                            <DataTable
                                columns={chargeColumns}
                                rows={charges}
                                emptyMessage="No charges found"
                            />
                        ) : (
                            <p className="text-slate-400 text-center py-6 text-xs">No charges recorded.</p>
                        )}
                    </Card>

                    {/* Recorded Payments History */}
                    <Card title="Transaction History (Official Receipts)" subtitle="Previous payments logged under this enrollment">
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
                                                Void
                                            </button>
                                        ) : null}
                                    </div>
                                )}
                                emptyMessage="No payments recorded"
                            />
                        ) : (
                            <p className="text-slate-400 text-center py-6 text-xs">No payments recorded yet.</p>
                        )}
                    </Card>
                </div>

                {/* Right Side: Point-of-Sale (POS) Terminal & Receipt Preview */}
                <div className="lg:col-span-5 space-y-6">
                    {/* POS Payment Form */}
                    <div className="bg-gradient-to-br from-slate-900 to-navy-950 text-white rounded-2xl p-6 border border-slate-800 shadow-xl">
                        <div className="flex items-center justify-between border-b border-slate-800 pb-3 mb-5">
                            <div className="flex items-center gap-2">
                                <div className="h-8 w-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-sm">
                                    ₱
                                </div>
                                <h3 className="font-heading font-bold text-white text-base">Collect Payment</h3>
                            </div>
                            <span className="text-[10px] font-bold uppercase tracking-wider text-emerald-400 bg-emerald-500/20 px-2 py-0.5 rounded border border-emerald-500/30">
                                Cashier Active
                            </span>
                        </div>

                        <div className="flex items-center justify-between text-[11px] text-slate-400 bg-slate-800/80 px-3 py-1.5 rounded-lg border border-slate-700/60 mb-3">
                            <span className="flex items-center gap-1.5">
                                <span className="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse" />
                                <span><kbd className="px-1.5 py-0.5 bg-slate-700 text-slate-200 rounded font-mono text-[10px]">Enter ↵</kbd> next field</span>
                            </span>
                            <span><kbd className="px-1.5 py-0.5 bg-slate-700 text-slate-200 rounded font-mono text-[10px]">Ctrl+Enter</kbd> submit OR</span>
                        </div>

                        <form onSubmit={handleRecordPayment} {...formProps} className="space-y-4 text-xs">
                            {/* OR Number */}
                            <div>
                                <label className="block text-slate-300 font-semibold mb-1">
                                    Official Receipt (OR) Number <span className="text-rose-400">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={data.orNumber}
                                    onChange={(e) => setData('orNumber', e.target.value)}
                                    className="w-full rounded-xl bg-slate-800/90 border border-slate-700 text-white font-mono text-sm px-3.5 py-2.5 focus:ring-2 focus:ring-emerald-400 focus:outline-none"
                                    required
                                    placeholder="OR-123456"
                                />
                                {errors.orNumber && <p className="text-rose-400 text-xs mt-1">{errors.orNumber}</p>}
                            </div>

                            {/* Payment Amount & Presets */}
                            <div>
                                <div className="flex items-center justify-between mb-1">
                                    <label className="text-slate-300 font-semibold">
                                        Amount to Pay (PHP) <span className="text-rose-400">*</span>
                                    </label>
                                    {outstanding > 0 && (
                                        <button
                                            type="button"
                                            onClick={() => setQuickAmount(outstanding)}
                                            className="text-[11px] font-bold text-emerald-400 hover:text-emerald-300 transition-colors"
                                        >
                                            Full Balance ({peso(outstanding)})
                                        </button>
                                    )}
                                </div>
                                <div className="relative">
                                    <span className="absolute left-3.5 top-2.5 text-slate-400 font-bold text-base">₱</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        value={data.amount}
                                        onChange={(e) => setData('amount', e.target.value)}
                                        className="w-full rounded-xl bg-slate-800/90 border border-slate-700 text-white font-bold text-lg pl-8 pr-4 py-2 focus:ring-2 focus:ring-emerald-400 focus:outline-none font-mono"
                                        required
                                        placeholder="0.00"
                                    />
                                </div>
                                {errors.amount && <p className="text-rose-400 text-xs mt-1">{errors.amount}</p>}

                                {/* Quick Cash Keypad Buttons */}
                                <div className="grid grid-cols-4 gap-1.5 mt-2">
                                    {[100, 500, 1000, 2000].map((preset) => (
                                        <button
                                            key={preset}
                                            type="button"
                                            onClick={() => setQuickAmount(preset)}
                                            className="px-2 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 hover:text-white font-mono text-xs transition-colors"
                                        >
                                            +{peso(preset)}
                                        </button>
                                    ))}
                                </div>
                            </div>

                            {/* Payment Mode & Date */}
                            <div className="grid grid-cols-2 gap-3 pt-1">
                                <div>
                                    <label className="block text-slate-300 font-semibold mb-1">
                                        Payment Method <span className="text-rose-400">*</span>
                                    </label>
                                    <select
                                        value={data.paymentMode}
                                        onChange={(e) => setData('paymentMode', e.target.value)}
                                        className="w-full rounded-xl bg-slate-800/90 border border-slate-700 text-white text-xs px-3 py-2 focus:ring-2 focus:ring-emerald-400 focus:outline-none"
                                        required
                                    >
                                        <option value="cash">Cash Payment</option>
                                        <option value="online">Online / G-Cash</option>
                                        <option value="check">Bank Check</option>
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-slate-300 font-semibold mb-1">
                                        Transaction Date <span className="text-rose-400">*</span>
                                    </label>
                                    <input
                                        type="date"
                                        value={data.paymentDate}
                                        onChange={(e) => setData('paymentDate', e.target.value)}
                                        className="w-full rounded-xl bg-slate-800/90 border border-slate-700 text-white text-xs px-3 py-2 focus:ring-2 focus:ring-emerald-400 focus:outline-none"
                                        required
                                    />
                                </div>
                            </div>

                            {/* Submit Button */}
                            <div className="pt-3">
                                <button
                                    type="submit"
                                    disabled={isSubmitting}
                                    className="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-white font-heading font-bold text-sm shadow-lg hover:shadow-emerald-500/20 transition-all flex items-center justify-center gap-2 disabled:opacity-50"
                                >
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    {isSubmitting ? 'Recording Receipt...' : 'Record Payment & Issue OR'}
                                </button>
                            </div>
                        </form>
                    </div>

                    {/* Live Receipt Paper Preview Mockup */}
                    <div className="bg-amber-50/70 border-2 border-dashed border-amber-300 rounded-2xl p-5 shadow-xs text-slate-800 font-mono text-xs relative">
                        <div className="text-center border-b border-amber-200 pb-3 mb-3">
                            <p className="font-bold uppercase tracking-widest text-sm text-slate-900">SEAIT CASHIER</p>
                            <p className="text-[10px] text-slate-600">OFFICIAL RECEIPT PREVIEW</p>
                            <p className="text-[10px] font-bold text-seait-700 mt-1">{data.orNumber || 'OR-PENDING'}</p>
                        </div>
                        <div className="space-y-1.5 text-[11px]">
                            <div className="flex justify-between">
                                <span className="text-slate-500">Student:</span>
                                <span className="font-bold truncate max-w-[180px]">{studentName}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">School ID:</span>
                                <span>{student?.schoolIdNumber || '—'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">Method:</span>
                                <span className="uppercase font-semibold">{data.paymentMode}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-slate-500">Date:</span>
                                <span>{data.paymentDate}</span>
                            </div>
                            <div className="border-t border-amber-200 pt-2 mt-2 flex justify-between font-bold text-sm text-slate-900">
                                <span>TENDERED:</span>
                                <span className="text-emerald-700 font-mono">{peso(data.amount || 0)}</span>
                            </div>
                        </div>
                        <p className="text-[9px] text-center text-slate-400 mt-3 uppercase tracking-wider">
                            *** Valid Official Institutional Receipt ***
                        </p>
                    </div>
                </div>
            </div>

            {/* Void Payment Cause & Effect Confirmation Modal */}
            <CauseEffectModal
                show={showVoidConfirm}
                onClose={() => { setShowVoidConfirm(false); setPaymentToVoid(null); }}
                onConfirm={confirmVoidPayment}
                title="Void Official Payment Receipt"
                subtitle="High-risk institutional financial reversal"
                tone="danger"
                entityContext={{
                    label: 'Official Receipt (OR)',
                    value: paymentToVoid?.orNumber || '—',
                    badge: peso(paymentToVoid?.amount),
                }}
                cause={`Voiding this payment will permanently cancel Official Receipt ${paymentToVoid?.orNumber || ''} issued to ${studentName}.`}
                effects={[
                    `Reverses ${peso(paymentToVoid?.amount)} from today's cashier drawer and collection reconciliation total.`,
                    `Restores ${peso(paymentToVoid?.amount)} to the student's outstanding tuition balance (${student?.schoolIdNumber || '—'}).`,
                    'Generates an immutable audit trail entry logged under Cashier Supervisor compliance records.',
                    'The cancelled receipt number cannot be reused or reassigned to another transaction.',
                ]}
                requiresAcknowledgement={true}
                acknowledgementText="I confirm that this official receipt is being voided due to an error, and I acknowledge the financial impact."
                confirmText="Yes, Permanently Void Receipt"
                cancelText="Keep Receipt Active"
                loading={isSubmitting}
            />
        </AuthenticatedLayout>
    );
}
