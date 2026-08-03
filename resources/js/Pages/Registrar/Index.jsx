import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, EmptyState } from '@/Components/ui';
import { useState, useMemo } from 'react';

const statusToneMap = {
    pending: 'pending',
    evaluated: 'evaluated',
    assessed: 'assessed',
    paid: 'paid',
    enrolled: 'enrolled',
    dropped: 'dropped',
};

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
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        window.location.href = `${window.location.pathname}?${params.toString()}`;
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

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Registrar Approval Queue"
                    subtitle="Review and approve enrollments pending registrar validation"
                />
            }
        >
            <Head title="Registrar Approval Queue" />

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