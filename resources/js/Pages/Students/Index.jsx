import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, EmptyState, Select } from '@/Components/ui';
import { useState, useMemo } from 'react';

const statusOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'graduated', label: 'Graduated' },
    { value: 'dropped', label: 'Dropped' },
];

const statusToneMap = {
    active: 'success',
    inactive: 'neutral',
    graduated: 'info',
    dropped: 'dropped',
};

function formatName(row) {
    if (!row) return '—';
    const last = row.lastName || '';
    const first = row.firstName || '';
    const middle = row.middleName ? ` ${row.middleName.charAt(0)}.` : '';
    const suffix = row.suffix ? ` ${row.suffix}` : '';
    const full = `${last}, ${first}${middle}${suffix}`.trim();
    return full === ',' ? '—' : full;
}

function formatStatus(status) {
    if (!status) return '—';
    return status.charAt(0).toUpperCase() + status.slice(1);
}

export default function Index({ students, filters = {} }) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');

    const columns = useMemo(() => [
        { key: 'schoolIdNumber', label: 'School ID', className: 'font-mono text-sm text-brand-700', render: (row) => row.schoolIdNumber || '—' },
        { key: 'studentName', label: 'Student Name', render: (row) => (
            <div className="flex items-center gap-3">
                <span className="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-seait-100 text-seait-700 text-xs font-semibold ring-1 ring-seait-200">
                    {((row.firstName?.[0] || '') + (row.lastName?.[0] || '')).toUpperCase() || '—'}
                </span>
                <span className="font-medium text-brand-900">{formatName(row)}</span>
            </div>
        )},
        { key: 'gender', label: 'Gender', render: (row) => row.gender ? row.gender.charAt(0).toUpperCase() + row.gender.slice(1) : '—' },
        { key: 'status', label: 'Status', render: (row) => (
            <Badge tone={statusToneMap[row.status] || 'neutral'}>
                {formatStatus(row.status)}
            </Badge>
        )},
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (status) params.set('status', status);
        window.location.href = `${window.location.pathname}?${params.toString()}`;
    };

    const renderActions = (row) => (
        <Link
            href={route('students.show', { student: row.studentId })}
            className="btn btn-ghost btn-sm text-seait-600 hover:text-seait-800"
        >
            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            <span className="hidden sm:inline">360 View</span>
        </Link>
    );

    const hasRows = students?.data?.length > 0;
    const isFiltered = !!(search || status);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Student 360"
                    subtitle="Search any student to see their full enrollment trail"
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT logo"
                />
            }
        >
            <Head title="Student 360" />

            <div className="space-y-6">
                <FilterBar onSubmit={handleFilter}>
                    <FilterBarField label="Search">
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Name or School ID..."
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

                <Card>
                    {hasRows ? (
                        <>
                            <DataTable
                                columns={columns}
                                rows={students.data}
                                children={renderActions}
                                emptyMessage="No students found"
                            />
                            <div className="mt-4">
                                <Pagination paginator={students} />
                            </div>
                        </>
                    ) : (
                        <EmptyState
                            title="No students found"
                            message={isFiltered ? 'Try a different name, school ID, or status filter.' : 'No student records have been entered yet.'}
                        />
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
