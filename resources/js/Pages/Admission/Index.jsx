import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, Select, EmptyState, StatCard } from '@/Components/ui';
import { useState, useMemo } from 'react';

const statusOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
];

const statusToneMap = {
    pending: 'pending',
    approved: 'approved',
    rejected: 'rejected',
};

export default function Index({ admissions, filters = {} }) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');

    // Derive quick stats from the full paginator payload (if available)
    const stats = useMemo(() => {
        const rows = admissions?.data || [];
        const count = (tone) => rows.filter((r) => r.admissionStatus === tone).length;
        return {
            total: admissions?.total ?? rows.length,
            pending: count('pending'),
            approved: count('approved'),
            rejected: count('rejected'),
        };
    }, [admissions]);

    const columns = useMemo(() => [
        { key: 'studentIdNumber', label: 'School ID', className: 'font-mono text-sm' },
        { key: 'studentName', label: 'Student Name' },
        { key: 'course', label: 'Course', render: (row) => row.course?.name || '—' },
        { key: 'term', label: 'Term', render: (row) => row.term?.name || '—' },
        { key: 'admissionStatus', label: 'Status', render: (row) => (
            <Badge tone={statusToneMap[row.admissionStatus] || 'neutral'}>
                {row.admissionStatus?.charAt(0).toUpperCase() + row.admissionStatus?.slice(1)}
            </Badge>
        )},
        { key: 'createdAt', label: 'Submitted', render: (row) => row.createdAt ? new Date(row.createdAt).toLocaleDateString('en-PH') : '—' },
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('admission.index'), {
            search: search || undefined,
            status: status || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const renderActions = (row) => (
        <div className="flex items-center gap-2">
            <Link
                href={route('admission.show', { admission: row.admissionId })}
                className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <span className="hidden sm:inline">View</span>
            </Link>
            <Link
                href={route('admission.show', { admission: row.admissionId })}
                className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
                <span className="hidden sm:inline">Requirements</span>
            </Link>
        </div>
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Admissions & Application Intake"
                    subtitle="Register first-year and transferee applicants, verify submitted physical documents, and gate admission approval"
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Admissions Office"
                    phaseBadge="Phase 0 · Admissions Desk"
                    officeBadge="Office 6 · Admission Office"
                    actions={
                        <Link href={route('admission.create')} className="btn btn-primary">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            New Admission
                        </Link>
                    }
                />
            }
        >
            <Head title="Admissions" />

            {/* Quick Stats */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div className="animate-slide-up" style={{ animationDelay: '0ms' }}>
                    <StatCard
                        label="Total Applications"
                        value={stats.total}
                        iconBg="seait"
                        icon={
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        }
                    />
                </div>
                <div className="animate-slide-up" style={{ animationDelay: '60ms' }}>
                    <StatCard
                        label="Pending Review"
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
                        label="Approved"
                        value={stats.approved}
                        iconBg="success"
                        icon={
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        }
                    />
                </div>
                <div className="animate-slide-up" style={{ animationDelay: '180ms' }}>
                    <StatCard
                        label="Rejected"
                        value={stats.rejected}
                        iconBg="danger"
                        icon={
                            <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
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
                    <FilterBarField label="Status">
                        <Select
                            value={status}
                            onChange={setStatus}
                            options={statusOptions}
                            placeholder="All Statuses"
                            className="form-input"
                        />
                    </FilterBarField>
                </FilterBar>
            </div>

            {/* Data Table */}
            <div className="animate-slide-up" style={{ animationDelay: '180ms' }}>
                <Card>
                    {admissions?.data?.length > 0 ? (
                        <>
                            <DataTable
                                columns={columns}
                                rows={admissions.data}
                                children={renderActions}
                                emptyMessage="No admissions found"
                            />
                            <div className="mt-4">
                                <Pagination paginator={admissions} />
                            </div>
                        </>
                    ) : (
                        <EmptyState
                            title="No admissions found"
                            message={search || status ? 'Try adjusting your filters to find matching records.' : 'No admission applications have been submitted yet.'}
                            actionLabel={!search && !status ? 'Create First Admission' : undefined}
                            onAction={!search && !status ? () => router.visit(route('admission.create')) : undefined}
                        />
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
