import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, Select, EmptyState } from '@/Components/ui';

const actionOptions = [
    { value: '', label: 'All Actions' },
    { value: 'created', label: 'Created' },
    { value: 'updated', label: 'Updated' },
    { value: 'deleted', label: 'Deleted' },
    { value: 'viewed', label: 'Viewed' },
    { value: 'printed', label: 'Printed' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'status_changed', label: 'Status Changed' },
    { value: 'assigned', label: 'Assigned' },
    { value: 'unassigned', label: 'Unassigned' },
    { value: 'login', label: 'Login' },
    { value: 'logout', label: 'Logout' },
];

const actionToneMap = {
    created: 'success',
    updated: 'info',
    deleted: 'danger',
    viewed: 'neutral',
    printed: 'accent',
    approved: 'success',
    rejected: 'danger',
    status_changed: 'warning',
    assigned: 'info',
    unassigned: 'warning',
    login: 'success',
    logout: 'neutral',
};

export default function AuditLogs({ logs, filters = {} }) {
    const [action, setAction] = useState(filters.action || '');
    const [entityTable, setEntityTable] = useState(filters.entityTable || '');
    const [dateFrom, setDateFrom] = useState(filters.dateFrom || '');
    const [dateTo, setDateTo] = useState(filters.dateTo || '');

    const columns = useMemo(() => [
        { key: 'timestamp', label: 'Timestamp', render: (row) => row.createdAt ? new Date(row.createdAt).toLocaleString('en-PH', { dateStyle: 'short', timeStyle: 'medium' }) : '—' },
        { key: 'user', label: 'User', render: (row) => row.user ? `${row.user.firstName} ${row.user.lastName} (${row.user.username})` : 'System' },
        { key: 'action', label: 'Action', render: (row) => (
            <Badge tone={actionToneMap[row.action] || 'neutral'} className="capitalize">
                {row.action?.replace(/_/g, ' ')}
            </Badge>
        )},
        { key: 'entityTable', label: 'Entity', render: (row) => row.entityTable || '—' },
        { key: 'entityId', label: 'Entity ID', className: 'font-mono text-sm' },
        { key: 'description', label: 'Description', render: (row) => {
            if (!row.oldValues && !row.newValues) return '—';
            const changes = [];
            if (row.oldValues && row.newValues) {
                Object.keys(row.newValues).forEach(key => {
                    if (JSON.stringify(row.oldValues[key]) !== JSON.stringify(row.newValues[key])) {
                        changes.push(`${key}: ${JSON.stringify(row.oldValues[key])} → ${JSON.stringify(row.newValues[key])}`);
                    }
                });
            } else if (row.newValues) {
                Object.keys(row.newValues).forEach(key => {
                    changes.push(`${key}: ${JSON.stringify(row.newValues[key])}`);
                });
            }
            return changes.length > 0 ? changes.join('; ') : 'No changes';
        }},
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        const params = new URLSearchParams();
        if (action) params.set('action', action);
        if (entityTable) params.set('entityTable', entityTable);
        if (dateFrom) params.set('dateFrom', dateFrom);
        if (dateTo) params.set('dateTo', dateTo);
        window.location.href = `${window.location.pathname}?${params.toString()}`;
    };

    const entityTableOptions = useMemo(() => {
        const tables = [...new Set(logs?.data?.map(l => l.entityTable).filter(Boolean))];
        return [
            { value: '', label: 'All Entities' },
            ...tables.map(t => ({ value: t, label: t })),
        ];
    }, [logs?.data]);

    const renderActions = (row) => (
        <div className="flex items-center gap-1">
            <button
                className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900"
                title="View Details"
                onClick={() => alert(JSON.stringify({ oldValues: row.oldValues, newValues: row.newValues }, null, 2))}
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
            </button>
        </div>
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Audit Logs"
                    subtitle="View system activity and audit trail"
                />
            }
        >
            <Head title="Audit Logs" />

            {/* Filter Bar */}
            <FilterBar onSubmit={handleFilter}>
                <FilterBarField label="Action">
                    <Select
                        value={action}
                        onChange={setAction}
                        options={actionOptions}
                        placeholder="All Actions"
                        className="form-input"
                    />
                </FilterBarField>
                <FilterBarField label="Entity">
                    <Select
                        value={entityTable}
                        onChange={setEntityTable}
                        options={entityTableOptions}
                        placeholder="All Entities"
                        className="form-input"
                    />
                </FilterBarField>
                <FilterBarField label="Date From">
                    <input
                        type="date"
                        value={dateFrom}
                        onChange={(e) => setDateFrom(e.target.value)}
                        className="form-input"
                    />
                </FilterBarField>
                <FilterBarField label="Date To">
                    <input
                        type="date"
                        value={dateTo}
                        onChange={(e) => setDateTo(e.target.value)}
                        className="form-input"
                    />
                </FilterBarField>
            </FilterBar>

            {/* Data Table */}
            <Card>
                {logs?.data?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={logs.data}
                            children={renderActions}
                            emptyMessage="No audit logs found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={logs} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No audit logs found"
                        message={action || entityTable || dateFrom || dateTo ? 'Try adjusting your filters to find matching records.' : 'No audit logs have been recorded yet.'}
                    />
                )}
            </Card>
        </AuthenticatedLayout>
    );
}