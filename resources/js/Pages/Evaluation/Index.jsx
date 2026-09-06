import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, EmptyState, StatCard } from '@/Components/ui';
import { useState, useMemo } from 'react';

const studentTypeToneMap = {
    firstYear: 'info',
    continuing: 'success',
    transferee: 'warning',
    shifter: 'accent',
};

const enrollmentStatusToneMap = {
    pending: 'pending',
    evaluated: 'evaluated',
    assessed: 'assessed',
    paid: 'paid',
    enrolled: 'enrolled',
    dropped: 'dropped',
};

export default function Index({ enrollments, filters = {} }) {
    const [search, setSearch] = useState(filters.search || '');

    // Derive quick stats from the full paginator payload (if available)
    const stats = useMemo(() => {
        const rows = enrollments?.data || [];
        return {
            total: enrollments?.total ?? rows.length,
            pending: rows.filter((r) => r.enrollmentStatus === 'pending').length,
            evaluated: rows.filter((r) => r.enrollmentStatus === 'evaluated').length,
            transferees: rows.filter((r) => r.studentType === 'transferee' || r.studentType === 'shifter').length,
        };
    }, [enrollments]);

    const columns = useMemo(() => [
        { key: 'studentIdNumber', label: 'School ID', className: 'font-mono text-sm' },
        { key: 'studentName', label: 'Student Name' },
        { key: 'course', label: 'Course', render: (row) => row.course?.name || '—' },
        { key: 'yearLevel', label: 'Year Level', render: (row) => row.yearLevel ? `Year ${row.yearLevel}` : '—' },
        { key: 'studentType', label: 'Student Type', render: (row) => (
            <Badge tone={studentTypeToneMap[row.studentType] || 'neutral'}>
                {row.studentType?.replace(/([A-Z])/g, ' $1') || '—'}
            </Badge>
        )},
        { key: 'enrollmentStatus', label: 'Status', render: (row) => (
            <Badge tone={enrollmentStatusToneMap[row.enrollmentStatus] || 'neutral'}>
                {row.enrollmentStatus?.charAt(0).toUpperCase() + row.enrollmentStatus?.slice(1)}
            </Badge>
        )},
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('evaluation.index'), {
            search: search || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const renderActions = (row) => (
        <div className="flex items-center gap-2">
            <Link
                href={route('evaluation.show', { enrollment: row.enrollmentId })}
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
                    title="Academic Department Evaluation Desk"
                    subtitle="Capture student demographic profiles (BR32), evaluate transfer credits, and propose curriculum subject loads"
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Academic Evaluation"
                    phaseBadge="Phase 2 · Department Evaluation"
                    officeBadge="Office 4 · Academic Evaluation Desk"
                />
            }
        >
            <Head title="Evaluation Queue" />

            {/* Quick Stats */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                <div className="animate-slide-up" style={{ animationDelay: '0ms' }}>
                    <StatCard
                        compact
                        label="In Queue"
                        value={stats.total}
                        iconBg="seait"
                        icon={
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        }
                    />
                </div>
                <div className="animate-slide-up" style={{ animationDelay: '60ms' }}>
                    <StatCard
                        compact
                        label="Pending Evaluation"
                        value={stats.pending}
                        iconBg="warning"
                        icon={
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        }
                    />
                </div>
                <div className="animate-slide-up" style={{ animationDelay: '120ms' }}>
                    <StatCard
                        compact
                        label="Evaluated"
                        value={stats.evaluated}
                        iconBg="success"
                        icon={
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                            </svg>
                        }
                    />
                </div>
                <div className="animate-slide-up" style={{ animationDelay: '180ms' }}>
                    <StatCard
                        compact
                        label="Transferees / Shifters"
                        value={stats.transferees}
                        iconBg="info"
                        icon={
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                            </svg>
                        }
                    />
                </div>
            </div>

            {/* Filter Bar */}
            <div className="animate-slide-up" style={{ animationDelay: '120ms' }}>
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
            </div>

            {/* Data Table */}
            <div className="animate-slide-up" style={{ animationDelay: '180ms' }}>
                <Card>
                    {enrollments?.data?.length > 0 ? (
                        <>
                            <DataTable
                                columns={columns}
                                rows={enrollments.data}
                                children={renderActions}
                                emptyMessage="No enrollments pending evaluation"
                            />
                            <div className="mt-4">
                                <Pagination paginator={enrollments} />
                            </div>
                        </>
                    ) : (
                        <EmptyState
                            title="No enrollments found"
                            message={search ? 'Try adjusting your search to find matching records.' : 'No enrollments are currently pending evaluation.'}
                        />
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
