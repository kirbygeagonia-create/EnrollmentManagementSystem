import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, EmptyState, Select, StatCard, ConfirmDialog, Modal, FormSection } from '@/Components/ui';
import { useState, useMemo } from 'react';

// Overall clearance status → badge tone
const overallStatusToneMap = {
    pending: 'pending',
    approved: 'approved',
    rejected: 'rejected',
    waived: 'waived',
    incomplete: 'incomplete',
};

// Per-requirement approval status → badge tone
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
    const [showLostSlipModal, setShowLostSlipModal] = useState(false);
    const [lostSlipStudent, setLostSlipStudent] = useState(null);

    // Inline form for per-requirement approve/waive/reject
    const approveForm = useForm({ status: 'approved', remarks: '' });

    // Lost slip replacement form (₱100 at Accounting)
    const lostSlipForm = useForm({
        studentId: '',
        clearancePeriodId: '',
        orNumber: '',
    });

    const rows = useMemo(() => clearances?.data || [], [clearances]);

    // The currently selected clearance (full object incl. approvals)
    const selected = useMemo(
        () => rows.find((r) => r.studentClearanceId === selectedId) || null,
        [rows, selectedId],
    );

    // Page-level summary tiles
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
        router.get(route('clearance.index'), {
            search: search || undefined,
            periodId: periodId || undefined,
            status: status || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
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

    const openLostSlipReplacement = (clearance) => {
        lostSlipForm.reset({
            studentId: clearance.studentId,
            clearancePeriodId: clearance.clearancePeriodId,
            orNumber: '',
        });
        setLostSlipStudent(clearance.student);
        setShowLostSlipModal(true);
    };

    const handleLostSlipSubmit = (e) => {
        e.preventDefault();
        lostSlipForm.post(route('clearance.slip.replace'), {
            onSuccess: () => setShowLostSlipModal(false),
        });
    };

    const columns = useMemo(() => [
        { key: 'student.schoolIdNumber', label: 'School ID', className: 'font-mono text-sm font-bold', render: (row) => row.student?.schoolIdNumber || '—' },
        { key: 'student.lastName', label: 'Student Full Name', render: (row) => {
            const s = row.student;
            return s ? `${s.lastName}, ${s.firstName} ${s.middleName ? s.middleName.charAt(0) + '.' : ''}` : '—';
        }},
        { key: 'clearancePeriod.term', label: 'Academic Term', render: (row) => {
            const cp = row.clearancePeriod;
            if (!cp) return '—';
            const ay = cp.term?.academicYear;
            return ay ? `${ay.yearStart}-${ay.yearEnd} ${cp.term?.semester}` : '—';
        }},
        { key: 'overallStatus', label: 'Clearance Status', render: (row) => (
            <Badge tone={overallStatusToneMap[row.overallStatus] || 'neutral'}>
                {titleCase(row.overallStatus)}
            </Badge>
        )},
        { key: 'receivedDate', label: 'Registrar Receiving', render: (row) => (
            row.receivedDate ? (
                <span className="text-xs font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                    Received {fmtDate(row.receivedDate)}
                </span>
            ) : (
                <span className="text-xs text-slate-400">Not Submitted</span>
            )
        )},
    ], []);

    const renderActions = (row) => (
        <div className="flex items-center gap-2">
            <button
                type="button"
                className="btn btn-ghost btn-sm text-slate-700 hover:text-slate-900"
                onClick={() => setSelectedId(selectedId === row.studentClearanceId ? null : row.studentClearanceId)}
                aria-pressed={selectedId === row.studentClearanceId}
            >
                {selectedId === row.studentClearanceId ? 'Close Matrix' : 'Stamp Matrix'}
            </button>
            <Link
                href={route('clearance.print-slip', { clearance: row.studentClearanceId })}
                className="btn btn-accent btn-sm"
            >
                Print Slip
            </Link>
        </div>
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Campus Clearance & Multi-Office Sign-off Matrix"
                    subtitle="Track multi-office obligation sign-offs (SSC, Library, Security, Scholarship, Dean) and issue official clearance slips"
                    logo="/images/logos/safety-and-security.jpg"
                    logoAlt="Safety and Security Office (Clearance)"
                    phaseBadge="Phase 1 · Campus Clearance"
                    officeBadge="Office 8 · Multi-Office Clearance"
                />
            }
        >
            <Head title="Clearance Terminal" />

            {/* Summary tiles */}
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
                <StatCard
                    label="Pending Obligation"
                    value={stats.pending}
                    iconBg="warning"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
                <StatCard
                    label="Fully Cleared"
                    value={stats.approved}
                    iconBg="success"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
                <StatCard
                    label="With Deficiencies"
                    value={stats.rejected}
                    iconBg="danger"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    }
                />
                <StatCard
                    label="Waived Obligations"
                    value={stats.waived}
                    iconBg="seait"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
                <StatCard
                    label="Lost Slips / Incomplete"
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
                <FilterBarField label="Search Student">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search student name, ID number..."
                        className="form-input text-xs"
                    />
                </FilterBarField>
                <FilterBarField label="Clearance Period">
                    <Select
                        value={periodId}
                        onChange={setPeriodId}
                        options={periodOptions}
                        placeholder="All Periods"
                        className="form-input text-xs"
                    />
                </FilterBarField>
                <FilterBarField label="Clearance Status">
                    <Select
                        value={status}
                        onChange={setStatus}
                        options={statusOptions}
                        placeholder="All Statuses"
                        className="form-input text-xs"
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
                    />
                )}
            </Card>

            {/* Multi-Office Digital Stamp Matrix Panel */}
            {selected && (
                <ClearanceDetailPanel
                    clearance={selected}
                    onClose={() => setSelectedId(null)}
                    onAct={openConfirm}
                    onLostSlip={() => openLostSlipReplacement(selected)}
                />
            )}

            {/* Lost Slip Replacement Modal (₱100 fee) */}
            <Modal
                show={showLostSlipModal}
                onClose={() => setShowLostSlipModal(false)}
                title="Process Lost Clearance Slip Replacement"
                subtitle="BR33: Requires ₱100 payment verification at Accounting before reissuing slip."
                size="md"
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={() => setShowLostSlipModal(false)} className="btn btn-secondary" disabled={lostSlipForm.processing}>
                            Cancel
                        </button>
                        <button type="submit" form="lost-slip-form" className="btn btn-primary" disabled={lostSlipForm.processing}>
                            {lostSlipForm.processing ? 'Processing...' : 'Verify OR & Reissue Slip'}
                        </button>
                    </div>
                }
            >
                <form id="lost-slip-form" onSubmit={handleLostSlipSubmit} className="space-y-4 text-xs">
                    <div className="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-800">
                        <span className="font-bold block mb-0.5">Student: {lostSlipStudent?.lastName}, {lostSlipStudent?.firstName}</span>
                        <span>Fee Amount: <strong>₱100.00 (Clearance Slip Replacement)</strong></span>
                    </div>
                    <FormSection label="Official Receipt (OR) Number from Cashier" required error={lostSlipForm.errors.orNumber}>
                        <input
                            type="text"
                            value={lostSlipForm.data.orNumber}
                            onChange={(e) => lostSlipForm.setData('orNumber', e.target.value)}
                            className="form-input font-mono text-xs"
                            placeholder="OR-XXXXXX"
                            required
                        />
                    </FormSection>
                </form>
            </Modal>

            {/* Confirm dialog for per-requirement approvals */}
            <ConfirmDialog
                show={!!confirmState}
                onClose={closeConfirm}
                onConfirm={confirmAction}
                title={
                    confirmState?.action === 'approved' ? 'Approve Office Obligation'
                    : confirmState?.action === 'waived' ? 'Waive Obligation'
                    : 'Reject Clearance Requirement'
                }
                message={
                    confirmState?.action === 'approved'
                        ? 'This will digitally stamp and approve this office obligation for the student.'
                        : confirmState?.action === 'waived'
                        ? 'This will permanently waive this requirement for this student.'
                        : 'This will reject the requirement due to student liabilities.'
                }
                confirmText={
                    confirmState?.action === 'approved' ? 'Approve Stamp'
                    : confirmState?.action === 'waived' ? 'Waive Requirement'
                    : 'Reject Requirement'
                }
                variant={confirmState?.action === 'rejected' ? 'danger' : confirmState?.action === 'waived' ? 'warning' : 'primary'}
                loading={approveForm.processing}
            />
        </AuthenticatedLayout>
    );
}

/**
 * Multi-Office Digital Stamp Matrix Panel
 */
function ClearanceDetailPanel({ clearance, onClose, onAct, onLostSlip }) {
    const approvals = clearance.approvals || [];
    const total = approvals.length;
    const done = approvals.filter((a) => a.status === 'approved' || a.status === 'waived').length;
    const pct = total ? Math.round((done / total) * 100) : 0;

    const student = clearance.student;
    const studentName = student
        ? `${student.lastName}, ${student.firstName} ${student.middleName ? `${student.middleName[0]}.` : ''}`
        : '—';
    const cp = clearance.clearancePeriod;
    const periodLabel = cp?.term?.academicYear
        ? `${cp.term.academicYear.yearStart}-${cp.term.academicYear.yearEnd} ${cp.term?.semester}`
        : '—';

    return (
        <Card
            title={`Multi-Office Stamp Matrix — ${studentName}`}
            subtitle={`${student?.schoolIdNumber || '—'} • ${periodLabel} • ${done}/${total} Offices Cleared`}
            actions={
                <div className="flex items-center gap-2">
                    <Badge tone={overallStatusToneMap[clearance.overallStatus] || 'neutral'}>
                        {titleCase(clearance.overallStatus)}
                    </Badge>
                    <button
                        type="button"
                        className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 transition-colors"
                        onClick={onClose}
                        aria-label="Close matrix"
                    >
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            }
        >
            {/* Progress Bar */}
            <div className="mb-6">
                <div className="flex items-center justify-between mb-1.5 text-xs font-semibold">
                    <span className="text-slate-500">Obligation Clearance Progress</span>
                    <span className="text-slate-800">{pct}% Complete</span>
                </div>
                <div className="h-2.5 w-full rounded-full bg-slate-100 overflow-hidden">
                    <div
                        className="h-full bg-gradient-to-r from-seait-500 to-emerald-500 transition-all duration-300"
                        style={{ width: `${pct}%` }}
                    />
                </div>
            </div>

            {/* Stamp Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                {approvals.map((a) => {
                    const req = a.requirement;
                    const office = req?.office;
                    const isPending = a.status === 'pending';
                    const isApproved = a.status === 'approved';
                    const isWaived = a.status === 'waived';
                    const isRejected = a.status === 'rejected';

                    return (
                        <div
                            key={a.clearanceApprovalId}
                            className={`p-4 rounded-2xl border transition-all relative overflow-hidden flex flex-col justify-between ${
                                isApproved
                                    ? 'bg-emerald-50/60 border-emerald-300'
                                    : isWaived
                                    ? 'bg-blue-50/60 border-blue-300'
                                    : isRejected
                                    ? 'bg-rose-50/60 border-rose-300'
                                    : 'bg-white border-slate-200'
                            }`}
                        >
                            {/* Watermark Seal */}
                            {isApproved && (
                                <div className="absolute right-2 bottom-2 text-emerald-600/20 font-extrabold text-3xl font-heading rotate-[-12deg] pointer-events-none select-none">
                                    CLEARED
                                </div>
                            )}

                            <div>
                                <div className="flex items-start justify-between gap-2 mb-2">
                                    <div>
                                        <p className="font-heading font-bold text-slate-900 text-xs truncate">
                                            {req?.requirementName || 'Obligation'}
                                        </p>
                                        <p className="text-[11px] text-slate-500 font-semibold">
                                            {office?.officeName || 'Department Office'}
                                        </p>
                                    </div>
                                    <Badge tone={approvalToneMap[a.status] || 'neutral'}>
                                        {titleCase(a.status)}
                                    </Badge>
                                </div>

                                {a.remarks && (
                                    <p className="text-[11px] text-slate-600 italic bg-white/80 p-1.5 rounded border border-slate-200/60 mb-2">
                                        "{a.remarks}"
                                    </p>
                                )}
                            </div>

                            {isPending && (
                                <div className="flex items-center gap-1.5 pt-3 border-t border-slate-100">
                                    <button
                                        type="button"
                                        className="btn btn-primary btn-sm flex-1 text-[11px]"
                                        onClick={() => onAct(a, 'approved')}
                                    >
                                        Approve
                                    </button>
                                    <button
                                        type="button"
                                        className="btn btn-secondary btn-sm text-[11px]"
                                        onClick={() => onAct(a, 'waived')}
                                    >
                                        Waive
                                    </button>
                                    <button
                                        type="button"
                                        className="btn btn-danger btn-sm text-[11px]"
                                        onClick={() => onAct(a, 'rejected')}
                                    >
                                        Reject
                                    </button>
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>

            {/* Bottom Actions */}
            <div className="pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs">
                <button
                    type="button"
                    onClick={onLostSlip}
                    className="px-3 py-1.5 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-800 font-bold border border-amber-300 transition-colors"
                >
                    ₱100 Lost Slip Replacement Flow
                </button>

                <Link
                    href={route('clearance.print-slip', { clearance: clearance.studentClearanceId })}
                    className="btn btn-accent btn-sm"
                >
                    Print Official Clearance Slip
                </Link>
            </div>
        </Card>
    );
}
