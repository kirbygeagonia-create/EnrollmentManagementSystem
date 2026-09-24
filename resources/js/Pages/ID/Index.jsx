import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, EmptyState, StatCard } from '@/Components/ui';
import { useState, useMemo } from 'react';

const idStatusToneMap = {
    pending: 'warning',
    validated: 'seait',
    released: 'success',
    cancelled: 'danger',
};

const idStatusLabelMap = {
    pending: 'Pending',
    validated: 'Validated',
    released: 'Released',
    cancelled: 'Cancelled',
};

export default function Index({ enrollments, filters = {} }) {
    const [search, setSearch] = useState(filters.search || '');

    const rows = useMemo(() => enrollments?.data || [], [enrollments]);

    // Summary tiles derived from the current page (request status only)
    const stats = useMemo(() => {
        let pending = 0;
        let validated = 0;
        let released = 0;
        rows.forEach((row) => {
            const status = row.idrequests?.[0]?.status;
            if (!status) return;
            if (status === 'pending') pending += 1;
            else if (status === 'validated') validated += 1;
            else if (status === 'released') released += 1;
        });
        return { pending, validated, released };
    }, [rows]);

    const columns = useMemo(() => [
        { key: 'studentIdNumber', label: 'School ID', className: 'font-mono text-sm' },
        { key: 'studentName', label: 'Student Name' },
        { key: 'course', label: 'Course', render: (row) => row.course?.name || '—' },
        { key: 'idStatus', label: 'ID Status', render: (row) => {
            const status = row.idrequests?.[0]?.status;

            if (!status) {
                return <Badge tone="neutral">None</Badge>;
            }

            return (
                <Badge tone={idStatusToneMap[status] || 'neutral'}>
                    {idStatusLabelMap[status] || status}
                </Badge>
            );
        }},
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('id.index'), {
            search: search || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const renderActions = (row) => (
        <div className="flex items-center gap-2">
            <Link
                href={route('id.show', { enrollment: row.enrollmentId })}
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
                    title="Student ID Processing & Validation"
                    subtitle="Validate ID requests with face-photo capture, then track release of the printed cards"
                    phaseBadge="Phase 8 · ID Office"
                    officeBadge="ID Validation Desk"
                />
            }
        >
            <Head title="ID Requests" />

            {/* Summary tiles */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-5">
                <StatCard
                    compact
                    label="Pending Validation"
                    value={stats.pending}
                    iconBg="warning"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
                <StatCard
                    compact
                    label="Validated"
                    value={stats.validated}
                    iconBg="seait"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
                <StatCard
                    compact
                    label="Released"
                    value={stats.released}
                    iconBg="success"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
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
                {enrollments?.data?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={enrollments.data}
                            children={renderActions}
                            emptyMessage="No ID requests found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={enrollments} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No ID requests found"
                        message={search ? 'Try adjusting your search to find matching records.' : 'No students are currently pending ID processing.'}
                        icon={
                            <svg className="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-2M10 6l1.5-1.5a2 2 0 011.414-.586H16a2 2 0 012 2v2.586a2 2 0 01-.586 1.414L16 12M10 6V4a2 2 0 012-2h2a2 2 0 012 2v2" />
                            </svg>
                        }
                    />
                )}
            </Card>
        </AuthenticatedLayout>
    );
}
