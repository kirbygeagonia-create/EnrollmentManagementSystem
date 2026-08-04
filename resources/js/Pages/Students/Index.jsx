import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, EmptyState } from '@/Components/ui';
import { useState } from 'react';

const statusToneMap = {
    active: 'success',
    inactive: 'neutral',
    graduated: 'info',
    dropped: 'dropped',
};

export default function Index({ students, filters = {} }) {
    const [search, setSearch] = useState(filters.search || '');

    const columns = [
        { key: 'schoolIdNumber', label: 'School ID', className: 'font-mono text-sm' },
        { key: 'studentName', label: 'Student Name' },
        { key: 'gender', label: 'Gender', render: (row) => row.gender ? row.gender.charAt(0).toUpperCase() + row.gender.slice(1) : '—' },
        { key: 'status', label: 'Status', render: (row) => (
            <Badge tone={statusToneMap[row.status] || 'neutral'}>
                {row.status ? row.status.charAt(0).toUpperCase() + row.status.slice(1) : '—'}
            </Badge>
        )},
    ];

    const handleFilter = (e) => {
        e.preventDefault();
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        window.location.href = `${window.location.pathname}?${params.toString()}`;
    };

    const renderActions = (row) => (
        <Link
            href={route('students.show', { student: row.studentId })}
            className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900"
        >
            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            <span className="hidden sm:inline">360 View</span>
        </Link>
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Student 360"
                    subtitle="Search any student to see their full enrollment trail"
                />
            }
        >
            <Head title="Student 360" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                    <Card>
                        <FilterBar onSubmit={handleFilter}>
                            <FilterBarField label="Search">
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Name or School ID..."
                                    className="input input-sm"
                                />
                            </FilterBarField>
                        </FilterBar>
                    </Card>

                    <Card>
                        {students.data.length === 0 ? (
                            <EmptyState
                                title="No students found"
                                message="Try a different name or school ID number."
                            />
                        ) : (
                            <>
                                <DataTable columns={columns} rows={students.data} actions={renderActions} />
                                <Pagination meta={students} />
                            </>
                        )}
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}