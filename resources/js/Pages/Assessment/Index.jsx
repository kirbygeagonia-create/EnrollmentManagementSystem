import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, EmptyState, StatCard, formatStatusLabel } from '@/Components/ui';
import { useState, useMemo } from 'react';

const peso = (n) => `₱${Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

// Assessment status (the workflow state of the assessment record itself).
const assessmentStatusToneMap = {
    assessed: 'assessed',
    pending: 'pending',
};

// Balance status derived from remaining vs total — uses the real badge tones.
const balanceToneFor = (balance, total) => {
    if (Number(balance) <= 0) return 'paid';
    if (Number(balance) < Number(total)) return 'partial';
    return 'danger';
};

export default function Index({ assessments, pendingEvaluations = [], filters = {} }) {
    const [search, setSearch] = useState(filters.search || '');

    // Aggregate fee summary across the current page of assessments.
    const summary = useMemo(() => {
        const rows = assessments?.data || [];
        const total = rows.reduce((s, r) => s + Number(r.totalAssessedAmount || 0), 0);
        const balance = rows.reduce((s, r) => s + Number(r.remainingBalance || 0), 0);
        const assessedCount = rows.filter((r) => r.status === 'assessed').length;
        const partialCount = rows.filter((r) => {
            const b = Number(r.remainingBalance || 0);
            const t = Number(r.totalAssessedAmount || 0);
            return b > 0 && b < t;
        }).length;
        const settledCount = rows.filter((r) => Number(r.remainingBalance || 0) <= 0).length;
        return { total, balance, assessedCount, partialCount, settledCount, count: rows.length };
    }, [assessments]);

    const columns = useMemo(() => [
        { key: 'studentIdNumber', label: 'School ID', className: 'font-mono text-sm' },
        { key: 'studentName', label: 'Student Name' },
        { key: 'course', label: 'Course', render: (row) => row.enrollment?.course?.name || '—' },
        { key: 'totalAssessedAmount', label: 'Total Amount', render: (row) => (
            <span className="font-semibold text-brand-900">{peso(row.totalAssessedAmount)}</span>
        )},
        { key: 'remainingBalance', label: 'Remaining Balance', render: (row) => {
            const balance = Number(row.remainingBalance || 0);
            const total = Number(row.totalAssessedAmount || 0);
            const tone = balanceToneFor(balance, total);
            return (
                <Badge tone={tone}>
                    {peso(balance)}
                </Badge>
            );
        }},
        { key: 'status', label: 'Status', render: (row) => (
            <Badge tone={assessmentStatusToneMap[row.status] || 'neutral'}>
                {row.status ? formatStatusLabel(row.status) : '—'}
            </Badge>
        )},
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('assessment.index'), {
            search: search || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const renderActions = (row) => (
        <div className="flex items-center gap-2">
            <Link
                href={route('assessment.show', { assessment: row.assessmentId })}
                className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <span className="hidden sm:inline">View</span>
            </Link>
        </div>
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Scholarship & Financial Assessment Desk"
                    subtitle="Compute itemized semester fees, apply School Grants (100% Free Tuition), stack external scholarships, and determine balances"
                    phaseBadge="Phase 3 · Assessment Desk"
                    officeBadge="Office 3 · Scholarship Office"
                />
            }
        >
            <Head title="Assessment Queue" />

            {/* Fee Summary StatCards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                <StatCard
                    compact
                    label="Total Assessed (page)"
                    value={peso(summary.total)}
                    iconBg="seait"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 7h6m0 10v-3m0 0h-6m6 0V7m-6 10H9a2 2 0 01-2-2V5a2 2 0 012-2h6a2 2 0 012 2v2M9 7a2 2 0 01-2 2v6a2 2 0 002 2h6a2 2 0 002-2V9a2 2 0 00-2-2M9 7v10" />
                        </svg>
                    }
                />
                <StatCard
                    compact
                    label="Outstanding Balance"
                    value={peso(summary.balance)}
                    iconBg="danger"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
                <StatCard
                    compact
                    label="Assessed"
                    value={summary.assessedCount}
                    iconBg="accent"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
                <StatCard
                    compact
                    label="Fully Settled"
                    value={summary.settledCount}
                    iconBg="success"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                        </svg>
                    }
                />
            </div>

            {/* Pending Fee Computations Queue */}
            {pendingEvaluations && pendingEvaluations.length > 0 && (
                <div className="bg-amber-50/80 border border-amber-200 rounded-2xl p-5 mb-5 shadow-xs">
                    <div className="flex items-center justify-between mb-3">
                        <div className="flex items-center gap-2">
                            <span className="flex h-2.5 w-2.5 rounded-full bg-amber-500 animate-pulse" />
                            <h3 className="font-heading font-bold text-amber-950 text-sm">
                                Evaluated Students Awaiting Fee Assessment ({pendingEvaluations.length})
                            </h3>
                        </div>
                        <span className="text-xs text-amber-700 bg-amber-100/70 px-2.5 py-0.5 rounded-full font-medium">
                            Action Required
                        </span>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        {pendingEvaluations.map((ev) => (
                            <div key={ev.enrollmentId} className="bg-white p-3.5 rounded-xl border border-amber-200/80 flex items-center justify-between gap-3 shadow-xs">
                                <div className="min-w-0">
                                    <p className="font-heading font-bold text-slate-900 text-sm truncate">
                                        {ev.student?.lastName}, {ev.student?.firstName}
                                    </p>
                                    <p className="text-xs text-slate-500 font-mono">
                                        {ev.student?.schoolIdNumber} • {ev.course?.courseCode} (Yr {ev.yearLevel})
                                    </p>
                                    <p className="text-[11px] text-slate-400 mt-0.5">
                                        {ev.enrolled_subjects?.length || 0} subjects proposed
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => router.post(route('assessment.compute', { enrollment: ev.enrollmentId }))}
                                    className="px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-heading font-bold text-xs shadow-xs transition-all flex items-center gap-1.5 flex-shrink-0"
                                >
                                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    Compute Fees
                                </button>
                            </div>
                        ))}
                    </div>
                </div>
            )}

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
                            emptyMessage="No assessments found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={assessments} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No assessments found"
                        message={search ? 'Try adjusting your search to find matching records.' : 'No assessments are currently pending.'}
                    />
                )}
            </Card>
        </AuthenticatedLayout>
    );
}
