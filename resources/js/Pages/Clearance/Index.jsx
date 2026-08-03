import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, EmptyState, Select } from '@/Components/ui';
import { useState, useMemo } from 'react';

const overallStatusToneMap = {
    pending: 'pending',
    approved: 'success',
    rejected: 'danger',
    waived: 'info',
    incomplete: 'warning',
};

export default function Index({ clearances, periods, filters = {} }) {
    const [search, setSearch] = useState(filters.search || '');
    const [periodId, setPeriodId] = useState(filters.periodId || '');
    const [status, setStatus] = useState(filters.status || '');

    const periodOptions = useMemo(() => [
        { value: '', label: 'All Periods' },
        ...periods.map(p => ({
            value: p.clearancePeriodId,
            label: `${p.term?.academicYear?.yearStart}-${p.term?.academicYear?.yearEnd} ${p.term?.semester} (${p.periodStatus})`,
        })),
    ], [periods]);

    const statusOptions = useMemo(() => [
        { value: '', label: 'All Statuses' },
        { value: 'pending', label: 'Pending' },
        { value: 'approved', label: 'Approved' },
        { value: 'rejected', label: 'Rejected' },
        { value: 'waived', label: 'Waived' },
        { value: 'incomplete', label: 'Incomplete' },
    ], []);

    const columns = useMemo(() => [
        { key: 'student.schoolIdNumber', label: 'School ID', className: 'font-mono text-sm', render: (row) => row.student?.schoolIdNumber || '—' },
        { key: 'student.lastName', label: 'Student Name', render: (row) => {
            const s = row.student;
            return s ? `${s.lastName}, ${s.firstName} ${s.middleName ? s.middleName.charAt(0) + '.' : ''}` : '—';
        }},
        { key: 'clearancePeriod.term', label: 'Period', render: (row) => {
            const cp = row.clearancePeriod;
            if (!cp) return '—';
            const ay = cp.term?.academicYear;
            return ay ? `${ay.yearStart}-${ay.yearEnd} ${cp.term?.semester}` : '—';
        }},
        { key: 'overallStatus', label: 'Status', render: (row) => (
            <Badge tone={overallStatusToneMap[row.overallStatus] || 'neutral'}>
                {row.overallStatus?.charAt(0).toUpperCase() + row.overallStatus?.slice(1)}
            </Badge>
        )},
        { key: 'receivedDate', label: 'Received Date', render: (row) => row.receivedDate ? new Date(row.receivedDate).toLocaleDateString('en-PH') : '—' },
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (periodId) params.set('periodId', periodId);
        if (status) params.set('status', status);
        window.location.href = `${window.location.pathname}?${params.toString()}`;
    };

    const renderActions = (row) => (
        <div className="flex items-center gap-2">
            <Link
                href={route('clearance.print-slip', { clearance: row.studentClearanceId })}
                className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                <span className="hidden sm:inline">Print Slip</span>
            </Link>
        </div>
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Clearance Management"
                    subtitle="Manage student clearance records and approvals"
                />
            }
        >
            <Head title="Clearance Management" />

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
                <FilterBarField label="Period">
                    <Select
                        value={periodId}
                        onChange={setPeriodId}
                        options={periodOptions}
                        placeholder="All Periods"
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
                {clearances?.data?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={clearances.data}
                            children={renderActions}
                            emptyMessage="No clearance records found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={clearances} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No clearance records found"
                        message={search || periodId || status ? 'Try adjusting your filters to find matching records.' : 'No clearance records have been generated yet.'}
                    />
                )}
            </Card>
        </AuthenticatedLayout>
    );
}