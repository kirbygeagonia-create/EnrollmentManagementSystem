import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, Select, EmptyState } from '@/Components/ui';
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
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (status) params.set('status', status);
        window.location.href = `${window.location.pathname}?${params.toString()}`;
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
                href={route('admission.requirements', { admission: row.admissionId })}
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
                    title="Admissions"
                    subtitle="Manage student admission applications"
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

            {/* Data Table */}
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
                        onAction={!search && !status ? () => window.location.href = route('admission.create') : undefined}
                    />
                )}
            </Card>
        </AuthenticatedLayout>
    );
}