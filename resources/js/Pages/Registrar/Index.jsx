import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, EmptyState, StatCard } from '@/Components/ui';
import { useState, useMemo } from 'react';

const statusToneMap = {
    pending: 'pending',
    evaluated: 'evaluated',
    assessed: 'assessed',
    paid: 'paid',
    enrolled: 'enrolled',
    dropped: 'dropped',
};

// Inline icon — queue of records awaiting registrar validation
const QueueIcon = () => (
    <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
    </svg>
);

export default function Index({ enrollments, filters = {} }) {
    const [search, setSearch] = useState(filters.search || '');

    const columns = useMemo(() => [
        { key: 'studentIdNumber', label: 'School ID', className: 'font-mono text-sm' },
        { key: 'studentName', label: 'Student Name' },
        { key: 'course', label: 'Course', render: (row) => row.course?.courseName || '—' },
        { key: 'yearLevel', label: 'Year Level', render: (row) => row.yearLevel ? `${row.yearLevel}${getYearSuffix(row.yearLevel)} Year` : '—' },
        { key: 'enrollmentStatus', label: 'Status', render: (row) => (
            <Badge tone={statusToneMap[row.enrollmentStatus?.value || row.enrollmentStatus] || 'neutral'}>
                {formatStatus(row.enrollmentStatus?.value || row.enrollmentStatus)}
            </Badge>
        )},
        { key: 'term', label: 'Term', render: (row) => row.term ? `${row.term.semester?.value || row.term.semester} ${row.term.academicYear?.yearLabel || ''}`.trim() : '—' },
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('registrar.index'), {
            search: search || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const renderActions = (row) => (
        <div className="flex items-center gap-2">
            <Link
                href={route('registrar.show', { enrollment: row.enrollmentId })}
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

    // Summary stats from the paginator
    const totalCount = enrollments?.total ?? enrollments?.data?.length ?? 0;
    const currentCount = enrollments?.data?.length ?? 0;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Office of the Registrar — Approval Terminal"
                    subtitle="Validate upstream clearance, accounting payment, and academic evaluation to officially finalize student enrollment"
                    logo="/images/logos/seait-logo.png"
                    logoAlt="Office of the Registrar Seal"
                    phaseBadge="Phase 5 · Registrar Central Desk"
                    officeBadge="Office 1 · Office of the Registrar"
                />
            }
        >
            <Head title="Registrar Approval Queue" />

            <div className="space-y-6">
                {/* Summary Stat Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <StatCard
                        label="Pending Approval"
                        value={totalCount}
                        icon={<QueueIcon />}
                        iconBg="seait"
                    />
                    <StatCard
                        label="On This Page"
                        value={currentCount}
                        icon={
                            <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        }
                        iconBg="info"
                    />
                    <StatCard
                        label="Office"
                        value="Registrar"
                        icon={
                            <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        }
                        iconBg="brand"
                    />
                    <StatCard
                        label="Workflow Phase"
                        value="Phase 6"
                        icon={
                            <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                        }
                        iconBg="accent"
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
                    {enrollments?.data?.length > 0 ? (
                        <>
                            <DataTable
                                columns={columns}
                                rows={enrollments.data}
                                children={renderActions}
                                emptyMessage="No enrollments found"
                            />
                            <div className="mt-4">
                                <Pagination paginator={enrollments} />
                            </div>
                        </>
                    ) : (
                        <EmptyState
                            title="No enrollments found"
                            message={search ? 'Try adjusting your search to find matching records.' : 'No enrollments are currently pending registrar approval.'}
                        />
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}

function getYearSuffix(year) {
    if (year === 1) return 'st';
    if (year === 2) return 'nd';
    if (year === 3) return 'rd';
    return 'th';
}

function formatStatus(status) {
    if (!status) return '—';
    return status.charAt(0).toUpperCase() + status.slice(1).toLowerCase();
}
