import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageHeader, Card, DataTable, StatCard, Badge } from '@/Components/ui';
import { useMemo } from 'react';

const paymentModeToneMap = {
    cash: 'info',
    online: 'accent',
};

export default function DailyReport({ payments, summary, date }) {
    const formattedDate = date ? new Date(date).toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : '—';

    const paymentColumns = useMemo(() => [
        { key: 'paymentDate', label: 'Time', render: (row) => row.paymentDate ? new Date(row.paymentDate).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' }) : '—' },
        { key: 'orNumber', label: 'OR Number', render: (row) => row.orNumber || '—' },
        { key: 'enrollment.student.schoolIdNumber', label: 'School ID', render: (row) => row.enrollment?.student?.schoolIdNumber || '—', className: 'font-mono text-sm' },
        { key: 'enrollment.student.lastName', label: 'Student Name', render: (row) => {
            const s = row.enrollment?.student;
            return s ? `${s.lastName}, ${s.firstName} ${s.middleName ? s.middleName.charAt(0) + '.' : ''}` : '—';
        }},
        { key: 'amount', label: 'Amount', render: (row) => `₱${Number(row.amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}` },
        { key: 'paymentMode', label: 'Mode', render: (row) => (
            <Badge tone={paymentModeToneMap[row.paymentMode] || 'neutral'}>
                {row.paymentMode?.charAt(0).toUpperCase() + row.paymentMode?.slice(1)}
            </Badge>
        )},
        { key: 'processedBy', label: 'Processed By', render: (row) => row.processedBy?.name || '—' },
    ], []);

    const byModeEntries = summary.byMode ? Object.entries(summary.byMode) : [];

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Daily Collection Report"
                    subtitle={`Report for ${formattedDate}`}
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

            {/* Summary Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <StatCard
                    label="Total Collections"
                    value={`₱${Number(summary.totalAmount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`}
                    icon={
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                    iconBg="success"
                />
                <StatCard
                    label="Total Transactions"
                    value={summary.totalCount || 0}
                    icon={
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                    }
                    iconBg="brand"
                />
                <StatCard
                    label="Payment Modes"
                    value={byModeEntries.length}
                    icon={
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2h2m-4 4h-2m8-4h2m-4-8v8" />
                        </svg>
                    }
                    iconBg="info"
                />
            </div>

            {/* Breakdown by Payment Mode */}
            {byModeEntries.length > 0 && (
                <Card title="Breakdown by Payment Mode" className="mb-6">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        {byModeEntries.map(([mode, data]) => (
                            <div key={mode} className="card p-4">
                                <p className="text-sm text-brand-500 capitalize">{mode}</p>
                                <p className="text-2xl font-bold text-brand-900">{data.count} transactions</p>
                                <p className="text-lg font-semibold text-success-600">₱{Number(data.amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</p>
                            </div>
                        ))}
                    </div>
                </Card>
            )}

            {/* Payments Table */}
            <Card title="Transaction Details" subtitle={`All payments collected on ${formattedDate}`}>
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
        </AuthenticatedLayout>
    );
}