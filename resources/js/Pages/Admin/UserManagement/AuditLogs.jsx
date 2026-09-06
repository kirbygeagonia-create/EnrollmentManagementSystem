import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, Select, EmptyState, Modal, StatCard } from '@/Components/ui';

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
    const [viewingLog, setViewingLog] = useState(null);

    const columns = useMemo(() => [
        { key: 'timestamp', label: 'Timestamp', render: (row) => row.createdAt ? (
            <span className="font-mono text-xs text-brand-600">
                {new Date(row.createdAt).toLocaleString('en-PH', { dateStyle: 'short', timeStyle: 'medium' })}
            </span>
        ) : '—' },
        { key: 'user', label: 'User', render: (row) => row.user ? (
            <div className="flex items-center gap-2">
                <div className="h-6 w-6 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-[10px] font-semibold flex-shrink-0">
                    {row.user.firstName?.[0]}{row.user.lastName?.[0]}
                </div>
                <div className="min-w-0">
                    <div className="text-sm font-medium text-brand-900 truncate">{row.user.firstName} {row.user.lastName}</div>
                    <div className="text-xs text-brand-500 truncate">{row.user.username}</div>
                </div>
            </div>
        ) : (
            <span className="text-xs text-brand-400 italic">System</span>
        )},
        { key: 'action', label: 'Action', render: (row) => (
            <Badge tone={actionToneMap[row.action] || 'neutral'} className="capitalize">
                {row.action?.replace(/_/g, ' ')}
            </Badge>
        )},
        { key: 'entityTable', label: 'Entity', render: (row) => (
            <span className="font-mono text-xs text-brand-700">{row.entityTable || '—'}</span>
        )},
        { key: 'entityId', label: 'Entity ID', className: 'font-mono text-xs text-brand-500' },
        { key: 'description', label: 'Description', render: (row) => {
            if (!row.oldValues && !row.newValues) return <span className="text-brand-400">—</span>;
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
            if (changes.length === 0) return <span className="text-brand-400">No changes</span>;
            const summary = changes.join('; ');
            return (
                <div className="max-w-md">
                    <p className="font-mono text-xs text-brand-600 truncate" title={summary}>{summary}</p>
                </div>
            );
        }},
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('admin.audit-logs.index'), {
            action: action || undefined,
            entityTable: entityTable || undefined,
            dateFrom: dateFrom || undefined,
            dateTo: dateTo || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
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
                onClick={() => setViewingLog(row)}
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
            </button>
        </div>
    );

    const stats = useMemo(() => {
        const data = logs?.data || [];
        return {
            total: logs?.total || data.length,
            created: data.filter(l => l.action === 'created').length,
            updated: data.filter(l => l.action === 'updated').length,
            deleted: data.filter(l => l.action === 'deleted').length,
        };
    }, [logs]);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Audit Logs"
                    subtitle="Read-only trail of system activity and changes"
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Logo"
                />
            }
        >
            <Head title="Audit Logs" />

            {/* Stats Overview */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                <StatCard
                    compact
                    label="Total (this page)"
                    value={stats.total}
                    icon={<svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>}
                    iconBg="brand"
                />
                <StatCard
                    compact
                    label="Created"
                    value={stats.created}
                    icon={<svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" /></svg>}
                    iconBg="success"
                />
                <StatCard
                    compact
                    label="Updated"
                    value={stats.updated}
                    icon={<svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>}
                    iconBg="info"
                />
                <StatCard
                    compact
                    label="Deleted"
                    value={stats.deleted}
                    icon={<svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>}
                    iconBg="danger"
                />
            </div>

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

            {/* View Details Modal */}
            <Modal
                show={!!viewingLog}
                onClose={() => setViewingLog(null)}
                title="Audit Log Details"
                subtitle={viewingLog ? `${viewingLog.entityTable || '—'} #${viewingLog.entityId || '—'}` : ''}
                icon={
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                }
                size="lg"
                footer={
                    <div className="flex justify-end">
                        <button type="button" onClick={() => setViewingLog(null)} className="btn btn-secondary">
                            Close
                        </button>
                    </div>
                }
            >
                {viewingLog && (
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <p className="text-xs uppercase tracking-wide text-brand-400 mb-1">Timestamp</p>
                                <p className="font-mono text-brand-700">{viewingLog.createdAt ? new Date(viewingLog.createdAt).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'medium' }) : '—'}</p>
                            </div>
                            <div>
                                <p className="text-xs uppercase tracking-wide text-brand-400 mb-1">User</p>
                                <p className="text-brand-700">{viewingLog.user ? `${viewingLog.user.firstName} ${viewingLog.user.lastName} (${viewingLog.user.username})` : 'System'}</p>
                            </div>
                            <div>
                                <p className="text-xs uppercase tracking-wide text-brand-400 mb-1">Action</p>
                                <Badge tone={actionToneMap[viewingLog.action] || 'neutral'} className="capitalize">{viewingLog.action?.replace(/_/g, ' ')}</Badge>
                            </div>
                            <div>
                                <p className="text-xs uppercase tracking-wide text-brand-400 mb-1">Entity</p>
                                <p className="font-mono text-brand-700">{viewingLog.entityTable || '—'} #{viewingLog.entityId || '—'}</p>
                            </div>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wide text-brand-400 mb-2">Old Values</p>
                            <pre className="bg-brand-900 text-brand-100 rounded-btn p-3 text-xs font-mono overflow-x-auto max-h-40 overflow-y-auto">
                                {viewingLog.oldValues ? JSON.stringify(viewingLog.oldValues, null, 2) : '—'}
                            </pre>
                        </div>
                        <div>
                            <p className="text-xs uppercase tracking-wide text-brand-400 mb-2">New Values</p>
                            <pre className="bg-brand-900 text-brand-100 rounded-btn p-3 text-xs font-mono overflow-x-auto max-h-40 overflow-y-auto">
                                {viewingLog.newValues ? JSON.stringify(viewingLog.newValues, null, 2) : '—'}
                            </pre>
                        </div>
                    </div>
                )}
            </Modal>
        </AuthenticatedLayout>
    );
}