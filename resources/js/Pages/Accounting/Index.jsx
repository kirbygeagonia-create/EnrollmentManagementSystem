import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, EmptyState, StatCard, formatStatusLabel, studentTypeTone, academicStandingLabel, academicStandingToneFor } from '@/Components/ui';
import { useState, useMemo } from 'react';

const peso = (n) => `₱${Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

// Balance tone — uses real badge tones (paid/partial/danger for unpaid).
const balanceToneFor = (balance, total) => {
    if (Number(balance) <= 0) return 'paid';
    if (Number(balance) < Number(total)) return 'partial';
    return 'danger';
};

// The desk has two queues: accounts to collect from, and accounts a grant or
// waiver already covered. Only the first was ever listed, so a student who owed
// nothing vanished from Accounting and could never be signed off for Registrar.
const QUEUES = [
    { key: 'due', label: 'Balance Due' },
    { key: 'no_balance', label: 'Nothing To Collect' },
];

export default function Index({ assessments, summary = {}, filters = {}, queueCounts = {} }) {
    const [search, setSearch] = useState(filters.search || '');
    const queue = filters.queue === 'no_balance' ? 'no_balance' : 'due';

    const columns = useMemo(() => [
        { key: 'enrollment.student.schoolIdNumber', label: 'School ID', className: 'font-mono text-sm', render: (row) => row.enrollment?.student?.schoolIdNumber || '—' },
        { key: 'enrollment.student.lastName', label: 'Student Name', render: (row) => {
            const s = row.enrollment?.student;
            return s ? `${s.lastName}, ${s.firstName} ${s.middleName ? s.middleName.charAt(0) + '.' : ''}` : '—';
        }},
        { key: 'enrollment.course.name', label: 'Course', render: (row) => row.enrollment?.course?.courseName || '—' },
        { key: 'enrollment.studentType', label: 'Student Type', render: (row) => (
            <Badge tone={studentTypeTone[row.enrollment?.studentType] || 'neutral'}>
                {formatStatusLabel(row.enrollment?.studentType)}
            </Badge>
        )},
        { key: 'enrollment.academicStanding', label: 'Standing', render: (row) => (
            <Badge tone={academicStandingToneFor(row.enrollment?.academicStanding)}>
                {academicStandingLabel(row.enrollment?.academicStanding)}
            </Badge>
        )},
        { key: 'totalAssessedAmount', label: 'Total Amount', render: (row) => (
            <span className="font-semibold text-brand-900">{peso(row.totalAssessedAmount)}</span>
        )},
        queue === 'no_balance'
            ? { key: 'totalScholarshipCoverage', label: 'Covered By', render: (row) => (
                <Badge tone="paid">
                    {peso(Number(row.totalScholarshipCoverage || 0) + Number(row.totalWaived || 0))}
                </Badge>
            )}
            : { key: 'remainingBalance', label: 'Balance', render: (row) => {
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
    ], [queue]);

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('accounting.index'), {
            search: search || undefined,
            queue: queue === 'due' ? undefined : queue,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleQueue = (key) => {
        router.get(route('accounting.index'), {
            search: search || undefined,
            queue: key === 'due' ? undefined : key,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
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
                <span className="hidden sm:inline">{queue === 'no_balance' ? 'Review & Settle' : 'Record Payment'}</span>
            </Link>
        </div>
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Accounting & Cashier Terminal"
                    subtitle="Collect enrollment fee payments, generate Official Receipts (OR), and settle accounts a grant or waiver already covers"
                    phaseBadge="Phase 4 · Cashier Desk"
                    officeBadge="Office 2 · Accounting Department"
                />
            }
        >
            <Head title="Accounting" />

            {/* Queue toggle — the two queues are different work, not two filter
                values, so both counts are always shown. */}
            <div className="flex flex-wrap gap-2 mb-4">
                {QUEUES.map((q) => (
                    <button
                        key={q.key}
                        type="button"
                        onClick={() => handleQueue(q.key)}
                        aria-pressed={queue === q.key}
                        className={`btn btn-sm ${queue === q.key ? 'btn-primary' : 'btn-secondary'}`}
                    >
                        {q.label}
                        <span className="ml-2 font-mono text-xs opacity-80">{queueCounts[q.key] ?? 0}</span>
                    </button>
                ))}
            </div>

            {/* Financial Overview StatCards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
                {queue === 'no_balance' ? (<>
                    <StatCard
                        compact
                        label="Total Assessed"
                        value={peso(summary.totalAssessed ?? 0)}
                        iconBg="seait"
                        icon={
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 7h6m0 10v-3m0 0h-6m6 0V7" />
                            </svg>
                        }
                    />
                    <StatCard
                        compact
                        label="Covered By Grants / Waivers"
                        value={peso(summary.totalCovered ?? 0)}
                        iconBg="info"
                        icon={
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 14l9-5-9-5-9 5 9 5z" />
                            </svg>
                        }
                    />
                    <StatCard
                        compact
                        label="Awaiting Cashier Sign-Off"
                        value={queueCounts.no_balance ?? 0}
                        iconBg="warning"
                        icon={
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        }
                    />
                </>) : (<>
                    <StatCard
                        compact
                        label="Total Assessed"
                        value={peso(summary.totalAssessed ?? 0)}
                        iconBg="seait"
                        icon={
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 7h6m0 10v-3m0 0h-6m6 0V7" />
                            </svg>
                        }
                    />
                    <StatCard
                        compact
                        label="Outstanding Balance"
                        value={peso(summary.totalBalance ?? 0)}
                        iconBg="danger"
                        icon={
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        }
                    />
                    <StatCard
                        compact
                        label="Partially Paid"
                        value={summary.partialCount ?? 0}
                        iconBg="warning"
                        icon={
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        }
                    />
                    <StatCard
                        compact
                        label="Nothing Paid Yet"
                        value={summary.unpaidCount ?? 0}
                        iconBg="success"
                        icon={
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                            </svg>
                        }
                    />
                </>)}
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
                        title={queue === 'no_balance' ? 'No accounts waiting to be settled' : 'No outstanding balances found'}
                        message={search
                            ? 'Try adjusting your search to find matching records.'
                            : queue === 'no_balance'
                                ? 'Every fully covered account has been signed off by the cashier desk.'
                                : 'All students have settled their balances.'}
                    />
                )}
            </Card>
        </AuthenticatedLayout>
    );
}
