import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, EmptyState, StatCard } from '@/Components/ui';
import { useState, useMemo } from 'react';

export default function Index({ enrollments, filters = {} }) {
    const [search, setSearch] = useState(filters.search || '');

    // Summary tiles derived from the current page of enrollments
    const rows = useMemo(() => enrollments?.data || [], [enrollments]);
    const completedCount = useMemo(
        () => rows.filter((r) => r.clinicrecords && r.clinicrecords.length > 0).length,
        [rows],
    );
    const pendingCount = rows.length - completedCount;

    const columns = useMemo(() => [
        { key: 'studentIdNumber', label: 'School ID', className: 'font-mono text-sm' },
        { key: 'studentName', label: 'Student Name' },
        { key: 'course', label: 'Course', render: (row) => row.course?.name || '—' },
        { key: 'clinicStatus', label: 'Clinic Status', render: (row) => {
            const hasRecord = row.clinicrecords && row.clinicrecords.length > 0;
            const status = hasRecord ? 'Completed' : 'Pending';
            const tone = hasRecord ? 'success' : 'warning';
            return (
                <Badge tone={tone}>
                    {status}
                </Badge>
            );
        }},
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
                href={route('clinic.show', { enrollment: row.enrollmentId })}
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
                    title="Clinic Records"
                    subtitle="Manage student clinic assessments (Phase 7)"
                    logo="/images/logos/clinic.jpg"
                    logoAlt="Clinic Office"
                />
            }
        >
            <Head title="Clinic Records" />

            {/* Summary tiles */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                <StatCard
                    label="Students on Page"
                    value={rows.length}
                    iconBg="brand"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-8 0 4 4 0 008 0zm6 0a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    }
                />
                <StatCard
                    label="Assessments Completed"
                    value={completedCount}
                    iconBg="success"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
                <StatCard
                    label="Pending Assessment"
                    value={pendingCount}
                    iconBg="warning"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
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
                            emptyMessage="No clinic records found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={enrollments} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No clinic records found"
                        message={search ? 'Try adjusting your search to find matching records.' : 'No students are currently pending clinic assessment.'}
                        icon={
                            <svg className="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                        }
                    />
                )}
            </Card>
        </AuthenticatedLayout>
    );
}
