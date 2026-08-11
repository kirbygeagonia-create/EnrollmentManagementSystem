import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, EmptyState, Select, StatCard, ConfirmDialog } from '@/Components/ui';
import { useState, useMemo } from 'react';

// Overall clearance status → badge tone (uses the dedicated .badge-* classes).
const overallStatusToneMap = {
    pending: 'pending',
    approved: 'approved',
    rejected: 'rejected',
    waived: 'waived',
    incomplete: 'incomplete',
};

// Per-requirement approval status → badge tone.
const approvalToneMap = {
    pending: 'pending',
    approved: 'approved',
    rejected: 'rejected',
    waived: 'waived',
};

const fmtDate = (d) => (d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: '2-digit' }) : '—');

const titleCase = (s) => (s ? s.charAt(0).toUpperCase() + s.slice(1) : '—');

export default function Index({ clearances, periods, filters = {} }) {
    const [search, setSearch] = useState(filters.search || '');
    const [periodId, setPeriodId] = useState(filters.periodId || '');
    const [status, setStatus] = useState(filters.status || '');
    const [selectedId, setSelectedId] = useState(null);
    const [confirmState, setConfirmState] = useState(null); // { approval, action }

    // Inline form for per-requirement approve/waive/reject.
    const approveForm = useForm({ status: 'approved', remarks: '' });

    const rows = useMemo(() => clearances?.data || [], [clearances]);

    // The currently selected clearance (full object incl. approvals).
    const selected = useMemo(
        () => rows.find((r) => r.studentClearanceId === selectedId) || null,
        [rows, selectedId],
    );

    // Page-level summary tiles.
    const stats = useMemo(() => {
        let pending = 0;
        let approved = 0;
        let rejected = 0;
        let waived = 0;
        let incomplete = 0;
        rows.forEach((r) => {
            if (r.overallStatus === 'pending') pending += 1;
            else if (r.overallStatus === 'approved') approved += 1;
            else if (r.overallStatus === 'rejected') rejected += 1;
            else if (r.overallStatus === 'waived') waived += 1;
            else if (r.overallStatus === 'incomplete') incomplete += 1;
        });
        return { pending, approved, rejected, waived, incomplete };
    }, [rows]);

    const periodOptions = useMemo(() => [
        { value: '', label: 'All Periods' },
        ...periods.map((p) => ({
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

    const handleFilter = (e) => {
        e.preventDefault();
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (periodId) params.set('periodId', periodId);
        if (status) params.set('status', status);
        window.location.href = `${window.location.pathname}?${params.toString()}`;
    };

    const openConfirm = (approval, action) => {
        approveForm.setData('status', action);
        approveForm.setData('remarks', '');
        setConfirmState({ approval, action });
    };

    const closeConfirm = () => setConfirmState(null);

    const confirmAction = () => {
        if (!confirmState?.approval) return;
        approveForm.post(route('clearance.approve', { approval: confirmState.approval.clearanceApprovalId }), {
            preserveScroll: true,
            onSuccess: () => closeConfirm(),
            onFinish: () => closeConfirm(),
        });
    };

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
                {titleCase(row.overallStatus)}
            </Badge>
        )},
        { key: 'receivedDate', label: 'Received', render: (row) => fmtDate(row.receivedDate) },
    ], []);

    const renderActions = (row) => (
        <div className="flex items-center gap-2">
            <button
                type="button"
                className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900"
                onClick={() => setSelectedId(selectedId === row.studentClearanceId ? null : row.studentClearanceId)}
                aria-pressed={selectedId === row.studentClearanceId}
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
                <span className="hidden sm:inline">{selectedId === row.studentClearanceId ? 'Hide' : 'Review'}</span>
            </button>
            <Link
                href={route('clearance.print-slip', { clearance: row.studentClearanceId })}
                className="btn btn-accent btn-sm"
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
                    subtitle="Track per-student requirement approvals and issue clearance slips"
                    logo="/images/logos/scholarship.jpg"
                    logoAlt="Clearance Office"
                />
            }
        >
            <Head title="Clearance Management" />

            {/* Summary tiles */}
            <div className="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                <StatCard
                    label="Pending"
                    value={stats.pending}
                    iconBg="warning"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
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
                <StatCard
                    label="Rejected"
                    value={stats.rejected}
                    iconBg="danger"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    }
                />
                <StatCard
                    label="Waived"
                    value={stats.waived}
                    iconBg="seait"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
                <StatCard
                    label="Incomplete"
                    value={stats.incomplete}
                    iconBg="accent"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
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

            {/* Clearance list */}
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
                        icon={
                            <svg className="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        }
                    />
                )}
            </Card>

            {/* Per-student clearance progress panel (appears when a row is selected) */}
            {selected && (
                <ClearanceDetailPanel
                    clearance={selected}
                    onClose={() => setSelectedId(null)}
                    onAct={openConfirm}
                />
            )}

            {/* Confirm dialog for irreversible per-requirement approvals */}
            <ConfirmDialog
                show={!!confirmState}
                onClose={closeConfirm}
                onConfirm={confirmAction}
                title={
                    confirmState?.action === 'approved' ? 'Approve Requirement'
                    : confirmState?.action === 'waived' ? 'Waive Requirement'
                    : 'Reject Requirement'
                }
                message={
                    confirmState?.action === 'approved'
                        ? 'This will mark the requirement as approved and count it toward the student’s clearance. This action cannot be undone.'
                        : confirmState?.action === 'waived'
                        ? 'This will waive the requirement for this student. A waiver is recorded permanently and cannot be undone.'
                        : 'This will reject the requirement. The student’s overall clearance will be marked as rejected. This action cannot be undone.'
                }
                confirmText={
                    confirmState?.action === 'approved' ? 'Approve'
                    : confirmState?.action === 'waived' ? 'Waive'
                    : 'Reject'
                }
                variant={confirmState?.action === 'rejected' ? 'danger' : confirmState?.action === 'waived' ? 'warning' : 'primary'}
                loading={approveForm.processing}
            />
        </AuthenticatedLayout>
    );
}

/**
 * Per-student clearance detail panel.
 * Shows requirement-by-requirement progress with badges and per-requirement
 * approve / waive / reject action buttons (for pending items).
 */
function ClearanceDetailPanel({ clearance, onClose, onAct }) {
    const approvals = clearance.approvals || [];
    const total = approvals.length;
    const done = approvals.filter((a) => a.status === 'approved' || a.status === 'waived').length;
    const pct = total ? Math.round((done / total) * 100) : 0;

    const student = clearance.student;
    const studentName = student
        ? `${student.lastName}, ${student.firstName}${student.middleName ? ` ${student.middleName.charAt(0)}.` : ''}`
        : '—';
    const cp = clearance.clearancePeriod;
    const periodLabel = cp?.term?.academicYear
        ? `${cp.term.academicYear.yearStart}-${cp.term.academicYear.yearEnd} ${cp.term?.semester}`
        : '—';

    return (
        <Card
            title={`Clearance Review — ${studentName}`}
            subtitle={`${student?.schoolIdNumber || '—'} · ${periodLabel} · ${done}/${total} requirement${total === 1 ? '' : 's'} cleared`}
            actions={
                <div className="flex items-center gap-2">
                    <Badge tone={overallStatusToneMap[clearance.overallStatus] || 'neutral'}>
                        {titleCase(clearance.overallStatus)}
                    </Badge>
                    <button
                        type="button"
                        className="btn btn-ghost btn-sm text-brand-500 hover:text-brand-700"
                        onClick={onClose}
                        aria-label="Close review panel"
                    >
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            }
        >
            {/* Progress bar */}
            <div className="mb-6">
                <div className="flex items-center justify-between mb-2">
                    <span className="text-xs font-medium text-brand-500">Clearance progress</span>
                    <span className="text-xs font-semibold text-brand-700">{pct}%</span>
                </div>
                <div className="h-2 w-full rounded-full bg-brand-100 overflow-hidden">
                    <div
                        className="h-full bg-gradient-to-r from-seait-500 to-seait-550 transition-all"
                        style={{ width: `${pct}%` }}
                    />
                </div>
            </div>

            {/* Receipt stamp (if received) */}
            {clearance.receivedDate && (
                <div className="mb-4 flex items-center gap-2 text-xs text-brand-500">
                    <svg className="h-4 w-4 text-success-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Received by {clearance.receivedByUser?.name || 'staff'} on {fmtDate(clearance.receivedDate)}
                </div>
            )}

            {/* Requirement list */}
            {approvals.length === 0 ? (
                <p className="text-sm text-brand-500 italic">No requirement approvals recorded for this clearance.</p>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    {approvals.map((a) => {
                        const req = a.requirement;
                        const office = req?.office;
                        const isPending = a.status === 'pending';
                        return (
                            <div
                                key={a.clearanceApprovalId}
                                className="flex flex-col gap-2 p-3 rounded-btn border border-brand-100 bg-white"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="text-sm font-semibold text-brand-900 truncate">
                                            {req?.requirementName || 'Requirement'}
                                        </p>
                                        <p className="text-xs text-brand-500">
                                            {office?.officeName || 'Office'}
                                            {a.approvalDate ? ` · ${fmtDate(a.approvalDate)}` : ''}
                                        </p>
                                    </div>
                                    <Badge tone={approvalToneMap[a.status] || 'neutral'}>
                                        {titleCase(a.status)}
                                    </Badge>
                                </div>

                                {a.remarks && (
                                    <p className="text-xs text-brand-600 italic border-l-2 border-seait-300 pl-2">
                                        “{a.remarks}”
                                    </p>
                                )}

                                {isPending && (
                                    <div className="flex flex-wrap gap-2 pt-1">
                                        <button
                                            type="button"
                                            className="btn btn-primary btn-sm"
                                            onClick={() => onAct(a, 'approved')}
                                        >
                                            <svg className="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M5 13l4 4L19 7" />
                                            </svg>
                                            Approve
                                        </button>
                                        <button
                                            type="button"
                                            className="btn btn-secondary btn-sm"
                                            onClick={() => onAct(a, 'waived')}
                                        >
                                            <svg className="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            Waive
                                        </button>
                                        <button
                                            type="button"
                                            className="btn btn-danger btn-sm"
                                            onClick={() => onAct(a, 'rejected')}
                                        >
                                            <svg className="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            Reject
                                        </button>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}

            {/* Print slip */}
            <div className="mt-6 pt-4 border-t border-brand-100 flex flex-wrap items-center justify-between gap-3">
                <p className="text-xs text-brand-500">
                    Slip is signed before enrollment — the completed clearance slip is the student’s enrollment requirement.
                </p>
                <Link
                    href={route('clearance.print-slip', { clearance: clearance.studentClearanceId })}
                    className="btn btn-accent btn-sm"
                >
                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Print Clearance Slip
                </Link>
            </div>
        </Card>
    );
}
