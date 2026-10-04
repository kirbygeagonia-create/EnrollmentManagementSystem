import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageHeader, Card, DataTable, StatCard, Badge, formatStatusLabel } from '@/Components/ui';
import { useMemo } from 'react';

const peso = (n) => `₱${Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const paymentModeToneMap = {
    cash: 'info',
    online: 'accent',
    check: 'neutral',
};

export default function DailyReport({ payments, refunds = [], summary, date }) {
    const formattedDate = date ? new Date(date).toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : '—';

    const refundColumns = useMemo(() => [
        { key: 'refundedAt', label: 'Paid back at', render: (row) => (row.refundedAt ? new Date(row.refundedAt).toLocaleString('en-PH', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: 'short' }) : '—') },
        { key: 'orNumber', label: 'Original OR', render: (row) => <span className="font-mono text-sm">{row.orNumber || '—'}</span> },
        { key: 'enrollment.student.schoolIdNumber', label: 'Student', render: (row) => {
            const s = row.enrollment?.student;
            return s ? `${s.lastName}, ${s.firstName}` : '—';
        }},
        { key: 'amount', label: 'Amount returned', render: (row) => (
            <span className="font-semibold text-rose-700">{peso(row.amount)}</span>
        )},
        { key: 'refundedReason', label: 'Reason', render: (row) => (
            <span className="text-xs text-slate-600" title={row.refundedReason || ''}>
                {(row.refundedReason || '').slice(0, 60)}{(row.refundedReason || '').length > 60 ? '…' : ''}
            </span>
        )},
        { key: 'refundedByUser', label: 'Handed back by', render: (row) => (row.refundedByUser ? `${row.refundedByUser.firstName} ${row.refundedByUser.lastName}` : '—') },
    ], []);

    const paymentColumns = useMemo(() => [
        { key: 'paymentDate', label: 'Time', render: (row) => row.paymentDate ? new Date(row.paymentDate).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' }) : '—' },
        { key: 'orNumber', label: 'OR Number', render: (row) => (
            <span className="font-mono text-sm">{row.orNumber || '—'}</span>
        )},
        { key: 'enrollment.student.schoolIdNumber', label: 'School ID', render: (row) => row.enrollment?.student?.schoolIdNumber || '—', className: 'font-mono text-sm' },
        { key: 'enrollment.student.lastName', label: 'Student Name', render: (row) => {
            const s = row.enrollment?.student;
            return s ? `${s.lastName}, ${s.firstName} ${s.middleName ? s.middleName.charAt(0) + '.' : ''}` : '—';
        }},
        { key: 'amount', label: 'Amount', render: (row) => (
            <span className="font-semibold text-brand-900">{peso(row.amount)}</span>
        )},
        { key: 'paymentMode', label: 'Mode', render: (row) => (
            <Badge tone={paymentModeToneMap[row.paymentMode] || 'neutral'}>
                {row.paymentMode ? formatStatusLabel(row.paymentMode) : '—'}
            </Badge>
        )},
        { key: 'processedBy', label: 'Processed By', render: (row) => row.processedBy?.name || '—' },
    ], []);

    const byModeEntries = summary.byMode ? Object.entries(summary.byMode) : [];
    const totalAmount = Number(summary.totalAmount || 0);
    const totalCount = Number(summary.totalCount || 0);
    const refundedAmount = Number(summary.refundedAmount || 0);
    const refundedCount = Number(summary.refundedCount || 0);
    // What the drawer should actually hold at close: cash in, minus cash handed back.
    const netAmount = totalAmount - refundedAmount;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Daily Collection & Cashier Report"
                    subtitle={`Collection audit for ${formattedDate}`}
                    phaseBadge="Phase 4 · Financial Audit"
                    officeBadge="Office 2 · Cashier Terminal"
                    actions={
                        <button
                            onClick={() => window.print()}
                            className="btn btn-primary no-print"
                        >
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Print Report
                        </button>
                    }
                />
            }
        >
            <Head title="Daily Collection Report" />

            {/* Report meta strip */}
            <Card className="mb-6">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <p className="text-xs font-medium text-brand-500 uppercase tracking-wider">Report Date</p>
                        <p className="mt-1 text-lg font-semibold text-brand-900">{formattedDate}</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge tone="info">Daily Report</Badge>
                        {/* Money in and money back out are both drawer movements, so both are
                            on the sheet — a payout with no line here is cash missing without an
                            explanation (ruling 14). */}
                        <Badge tone="paid">Receipts held</Badge>
                        {refunds.length > 0 && <Badge tone="danger">{refunds.length} refunded</Badge>}
                    </div>
                </div>
            </Card>

            {/* Summary StatCards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                <StatCard
                    compact
                    label="Total Collections"
                    value={peso(totalAmount)}
                    iconBg="success"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
                <StatCard
                    compact
                    label="Total Transactions"
                    value={totalCount}
                    iconBg="seait"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                    }
                />
                <StatCard
                    compact
                    label="Paid Back Out"
                    value={peso(refundedAmount)}
                    iconBg="danger"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h11a4 4 0 014 4v3M3 10l4-4M3 10l4 4" />
                        </svg>
                    }
                />
                <StatCard
                    compact
                    label="Payment Modes"
                    value={byModeEntries.length}
                    iconBg="info"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2h2m-4 4h-2m8-4h2m-4-8v8" />
                        </svg>
                    }
                />
            </div>

            {/* Breakdown by Payment Mode — organized totals section */}
            {byModeEntries.length > 0 && (
                <Card title="Breakdown by Payment Mode" subtitle="Collection totals grouped by payment method" className="mb-6">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        {byModeEntries.map(([mode, data]) => (
                            <div key={mode} className="card p-4">
                                <div className="flex items-center justify-between mb-2">
                                    <p className="text-sm text-brand-500 capitalize">{mode}</p>
                                    <Badge tone={paymentModeToneMap[mode] || 'neutral'}>
                                        {mode ? formatStatusLabel(mode) : '—'}
                                    </Badge>
                                </div>
                                <p className="text-2xl font-bold text-brand-900">{peso(data.amount)}</p>
                                <p className="text-sm text-brand-500 mt-1">{data.count} transaction{data.count === 1 ? '' : 's'}</p>
                            </div>
                        ))}
                    </div>

                    {/* Totals row */}
                    <div className="mt-6 pt-4 border-t border-brand-200">
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <p className="text-sm font-medium text-brand-600 uppercase tracking-wider">Total Cash Collected</p>
                            <div className="flex items-baseline gap-4">
                                <p className="text-sm text-brand-500">
                                    {totalCount} transaction{totalCount === 1 ? '' : 's'}
                                </p>
                                <p className="text-2xl font-bold text-success-700">{peso(totalAmount)}</p>
                            </div>
                        </div>
                    </div>
                </Card>
            )}

            {/* Payments Table */}
            <Card
                title="Transaction Details"
                subtitle={`Receipts the drawer is holding from ${formattedDate}`}
                className={refunds.length > 0 ? 'mb-6' : ''}
            >
                {payments.length > 0 ? (
                    <DataTable
                        columns={paymentColumns}
                        rows={payments}
                        emptyMessage="No payments recorded for this date"
                    />
                ) : (
                    <p className="text-brand-500 text-center py-8">No payments recorded for this date.</p>
                )}
            </Card>

            {/* Refunds Table — the payouts of the same day, so the sheet balances (ruling 14) */}
            {refunds.length > 0 && (
                <Card title="Cash Handed Back" subtitle="Refunds recorded against receipts of any date, paid out today">
                    <DataTable
                        columns={refundColumns}
                        rows={refunds}
                        emptyMessage="No refunds recorded for this date"
                    />
                    <div className="mt-6 pt-4 border-t border-brand-200 space-y-2">
                        <div className="flex items-center justify-between gap-2">
                            <p className="text-sm font-medium text-brand-600 uppercase tracking-wider">Total Paid Back</p>
                            <p className="text-sm text-rose-700">
                                -{peso(refundedAmount)} ({refundedCount} refund{refundedCount === 1 ? '' : 's'})
                            </p>
                        </div>
                        <div className="flex items-center justify-between gap-2">
                            <p className="text-sm font-medium text-brand-600 uppercase tracking-wider">Net Left In The Drawer</p>
                            <p className="text-2xl font-bold text-brand-900">{peso(netAmount)}</p>
                        </div>
                    </div>
                </Card>
            )}
        </AuthenticatedLayout>
    );
}
