import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, EmptyState, StatCard } from '@/Components/ui';
import { useState, useMemo } from 'react';

const peso = (n) => `₱${Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

// Balance tone — uses real badge tones (paid/partial/danger for unpaid).
const balanceToneFor = (balance, total) => {
    if (Number(balance) <= 0) return 'paid';
    if (Number(balance) < Number(total)) return 'partial';
    return 'danger';
};

export default function Index({ assessments, filters = {} }) {
    const [search, setSearch] = useState(filters.search || '');

    // Financial overview across the current page.
    const summary = useMemo(() => {
        const rows = assessments?.data || [];
        const totalAssessed = rows.reduce((s, r) => s + Number(r.totalAssessedAmount || 0), 0);
        const totalBalance = rows.reduce((s, r) => s + Number(r.remainingBalance || 0), 0);
        const paidCount = rows.filter((r) => Number(r.remainingBalance || 0) <= 0).length;
        const partialCount = rows.filter((r) => {
            const b = Number(r.remainingBalance || 0);
            const t = Number(r.totalAssessedAmount || 0);
            return b > 0 && b < t;
        }).length;
        const unpaidCount = rows.filter((r) => {
            const b = Number(r.remainingBalance || 0);
            const t = Number(r.totalAssessedAmount || 0);
            return b >= t && t > 0;
        }).length;
        return { totalAssessed, totalBalance, paidCount, partialCount, unpaidCount, count: rows.length };
    }, [assessments]);

    const columns = useMemo(() => [
        { key: 'enrollment.student.schoolIdNumber', label: 'School ID', className: 'font-mono text-sm', render: (row) => row.enrollment?.student?.schoolIdNumber || '—' },
        { key: 'enrollment.student.lastName', label: 'Student Name', render: (row) => {
            const s = row.enrollment?.student;
            return s ? `${s.lastName}, ${s.firstName} ${s.middleName ? s.middleName.charAt(0) + '.' : ''}` : '—';
        }},
        { key: 'enrollment.course.name', label: 'Course', render: (row) => row.enrollment?.course?.name || '—' },
        { key: 'totalAssessedAmount', label: 'Total Amount', render: (row) => (
            <span className="font-semibold text-brand-900">{peso(row.totalAssessedAmount)}</span>
        )},
        { key: 'remainingBalance', label: 'Balance', render: (row) => {
            const balance = Number(row.remainingBalance || 0);
            const totalAssessed = Number(row.totalAssessedAmount || 0);
            const tone = balanceToneFor(balance, totalAssessed);
            return (
                <Badge tone={tone}>
                    {peso(balance)}
                </Badge>
            );
        }},
        { key: 'createdAt', label: 'Assessed Date', render: (row) => row.assessmentDate ? new Date(row.assessmentDate).toLocaleDateString('en-PH') : '—' },
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        window.location.href = `${window.location.pathname}?${params.toString()}`;
    };

    const renderActions = (row) => (
        <div className="flex items-center gap-2">
            <Link
                href={route('accounting.show', { assessment: row.assessmentId })}
                className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <span className="hidden sm:inline">Record Payment</span>
            </Link>
        </div>
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Accounting"
                    subtitle="Collect student payments and track outstanding balances"
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Logo"
                />
            }
        >
            <Head title="Accounting" />

            {/* Financial Overview StatCards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <StatCard
                    label="Total Assessed (page)"
                    value={peso(summary.totalAssessed)}
                    iconBg="seait"
                    icon={
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 7h6m0 10v-3m0 0h-6m6 0V7" />
                        </svg>
                    }
                />
                <StatCard
                    label="Outstanding Balance"
                    value={peso(summary.totalBalance)}
                    iconBg="danger"
                    icon={
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
                <StatCard
                    label="Partially Paid"
                    value={summary.partialCount}
                    iconBg="warning"
                    icon={
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
                <StatCard
                    label="Fully Paid"
                    value={summary.paidCount}
                    iconBg="success"
                    icon={
                        <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                        </svg>
                    }
                />
            </div>

            {/* Filter Bar */}
            <FilterBar onSubmit={handleFilter}>
                <FilterBarField label="Search">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by name, ID number..."
                        className="form-input"
                    />
                </FilterBarField>
            </FilterBar>

            {/* Data Table */}
            <Card>
                {assessments?.data?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={assessments.data}
                            children={renderActions}
                            emptyMessage="No assessments with outstanding balance found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={assessments} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No outstanding balances found"
                        message={search ? 'Try adjusting your search to find matching records.' : 'All students have settled their balances.'}
                    />
                )}
            </Card>
        </AuthenticatedLayout>
    );
}
