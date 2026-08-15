import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageHeader, Card, Badge, Modal, ConfirmDialog, EmptyState, FormSection, Select, StatCard } from '@/Components/ui';
import { useState, useMemo } from 'react';
import { useForm } from '@inertiajs/react';

// Period status → badge tone.
const periodStatusToneMap = {
    open: 'success',
    closed: 'neutral',
    extended: 'info',
};

const fmtDate = (d) => (d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: '2-digit' }) : '—');

const titleCase = (s) => (s ? s.charAt(0).toUpperCase() + s.slice(1) : '—');

// Distinct terms available for selection (deduped by termId).
const useTermOptions = (periods) => useMemo(() => periods
    .map((p) => ({
        value: p.termId,
        label: `${p.term?.academicYear?.yearStart}-${p.term?.academicYear?.yearEnd} ${p.term?.semester}`,
    }))
    .filter((v, i, a) => a.findIndex((t) => t.value === v.value) === i), [periods]);

export default function Periods({ periods }) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editingPeriod, setEditingPeriod] = useState(null); // the period object being edited
    const [confirmCloseId, setConfirmCloseId] = useState(null); // period to confirm-close
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Create Period Form — keys preserved: termId, clearanceStartDate, clearanceEndDate, periodStatus
    const {
        data: createData,
        setData: setCreateData,
        post: createPost,
        errors: createErrors,
        reset: createReset,
    } = useForm({
        termId: '',
        clearanceStartDate: '',
        clearanceEndDate: '',
        periodStatus: 'open',
    });

    // Edit Period Form — key preserved: periodStatus
    const {
        data: editData,
        setData: setEditData,
        patch: editPatch,
        errors: editErrors,
        reset: editReset,
    } = useForm({
        periodStatus: 'open',
    });

    // Dedicated form for the "close period" confirmation action (keeps it
    // decoupled from the edit modal's state).
    const closeForm = useForm({ periodStatus: 'closed' });

    const termOptions = useTermOptions(periods);

    // Aggregate summary tiles.
    const stats = useMemo(() => {
        let open = 0;
        let closed = 0;
        let total = periods.length;
        periods.forEach((p) => {
            if (p.periodStatus === 'open') open += 1;
            else if (p.periodStatus === 'closed') closed += 1;
        });
        return { open, closed, total };
    }, [periods]);

    const handleCreatePeriod = (e) => {
        e.preventDefault();
        setIsSubmitting(true);
        createPost(route('clearance.periods.store'), {
            onSuccess: () => {
                createReset();
                setShowCreateModal(false);
                setIsSubmitting(false);
            },
            onError: () => setIsSubmitting(false),
        });
    };

    const openEditModal = (period) => {
        setEditingPeriod(period);
        setEditData('periodStatus', period.periodStatus);
    };

    const closeEditModal = () => {
        setEditingPeriod(null);
        editReset();
    };

    const handleUpdatePeriod = (e) => {
        if (e) e.preventDefault();
        if (!editingPeriod) return;
        setIsSubmitting(true);
        editPatch(route('clearance.periods.update', { period: editingPeriod.clearancePeriodId }), {
            onSuccess: () => {
                closeEditModal();
                setIsSubmitting(false);
            },
            onError: () => setIsSubmitting(false),
        });
    };

    const confirmClosePeriod = () => {
        if (!confirmCloseId) return;
        setIsSubmitting(true);
        closeForm.patch(route('clearance.periods.update', { period: confirmCloseId }), {
            preserveScroll: true,
            onSuccess: () => {
                setConfirmCloseId(null);
                setIsSubmitting(false);
            },
            onError: () => {
                setConfirmCloseId(null);
                setIsSubmitting(false);
            },
        });
    };

    const periodLabel = (p) => {
        const ay = p.term?.academicYear;
        return ay ? `${ay.yearStart}-${ay.yearEnd} ${p.term?.semester}` : '—';
    };

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Clearance Windows & Term Periods"
                    subtitle="Open and close clearance processing windows for each academic semester"
                    logo="/images/logos/safety-and-security.jpg"
                    logoAlt="Safety and Security Office (Clearance Periods)"
                    phaseBadge="Phase 1 · Clearance Setup"
                    officeBadge="Office 6 · Safety & Security"
                    actions={
                        <button
                            type="button"
                            className="btn btn-primary"
                            onClick={() => setShowCreateModal(true)}
                        >
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            New Period
                        </button>
                    }
                />
            }
        >
            <Head title="Clearance Periods" />

            {/* Summary tiles */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <StatCard
                    label="Open Periods"
                    value={stats.open}
                    iconBg="success"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    }
                />
                <StatCard
                    label="Closed Periods"
                    value={stats.closed}
                    iconBg="neutral"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    }
                />
                <StatCard
                    label="Total Periods"
                    value={stats.total}
                    iconBg="seait"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    }
                />
            </div>

            {/* Periods grid */}
            <Card
                title="Existing Periods"
                subtitle="Clearance windows grouped by academic term"
            >
                {periods.length > 0 ? (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        {periods.map((p) => {
                            const isOpen = p.periodStatus === 'open';
                            return (
                                <div
                                    key={p.clearancePeriodId}
                                    className="flex flex-col rounded-card border border-brand-100 bg-white overflow-hidden hover:shadow-card transition-shadow"
                                >
                                    {/* Status accent bar */}
                                    <div
                                        aria-hidden="true"
                                        className={`h-1.5 w-full ${isOpen ? 'bg-gradient-to-r from-success-400 to-success-600' : 'bg-brand-200'}`}
                                    />
                                    <div className="p-4 flex-1 flex flex-col gap-3">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <h4 className="text-sm font-semibold text-brand-900 truncate">
                                                    {periodLabel(p)}
                                                </h4>
                                                <p className="text-xs text-brand-500 mt-0.5">
                                                    {fmtDate(p.clearanceStartDate)} – {fmtDate(p.clearanceEndDate)}
                                                </p>
                                            </div>
                                            <Badge tone={periodStatusToneMap[p.periodStatus] || 'neutral'}>
                                                {titleCase(p.periodStatus)}
                                            </Badge>
                                        </div>

                                        {/* Date range visual */}
                                        <div className="flex items-center gap-2 text-xs text-brand-500">
                                            <svg className="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            <span>
                                                {p.clearanceStartDate && p.clearanceEndDate
                                                    ? `${Math.max(0, Math.round((new Date(p.clearanceEndDate) - new Date(p.clearanceStartDate)) / 86400000))} day window`
                                                    : 'No dates set'}
                                            </span>
                                        </div>

                                        {/* Actions */}
                                        <div className="flex flex-wrap items-center gap-2 pt-2 mt-auto border-t border-brand-100">
                                            <button
                                                type="button"
                                                className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900"
                                                onClick={() => openEditModal(p)}
                                            >
                                                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                                Edit
                                            </button>
                                            {isOpen && (
                                                <button
                                                    type="button"
                                                    className="btn btn-secondary btn-sm"
                                                    onClick={() => setConfirmCloseId(p.clearancePeriodId)}
                                                >
                                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                    </svg>
                                                    Close
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <EmptyState
                        title="No clearance periods found"
                        message="Create your first clearance period using the “New Period” button above."
                        icon={
                            <svg className="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        }
                    />
                )}
            </Card>

            {/* Create Period Modal */}
            <Modal
                show={showCreateModal}
                onClose={() => { createReset(); setShowCreateModal(false); }}
                title="Create Clearance Period"
                subtitle="Open a new clearance window for an academic term."
                icon={
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                }
                size="lg"
                footer={
                    <div className="flex justify-end gap-3">
                        <button
                            type="button"
                            className="btn btn-secondary"
                            onClick={() => { createReset(); setShowCreateModal(false); }}
                            disabled={isSubmitting}
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            className="btn btn-primary"
                            onClick={handleCreatePeriod}
                            disabled={isSubmitting}
                        >
                            {isSubmitting ? 'Creating...' : 'Create Period'}
                        </button>
                    </div>
                }
            >
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <FormSection label="Term" required error={createErrors.termId} className="sm:col-span-2">
                        <Select
                            value={createData.termId}
                            onChange={(e) => setCreateData('termId', e.target.value)}
                            options={termOptions}
                            placeholder="Select term"
                            className={`form-input ${createErrors.termId ? 'form-input-error' : ''}`}
                            required
                        />
                    </FormSection>
                    <FormSection label="Start Date" required error={createErrors.clearanceStartDate}>
                        <input
                            type="date"
                            value={createData.clearanceStartDate}
                            onChange={(e) => setCreateData('clearanceStartDate', e.target.value)}
                            className={`form-input ${createErrors.clearanceStartDate ? 'form-input-error' : ''}`}
                            required
                        />
                    </FormSection>
                    <FormSection label="End Date" required error={createErrors.clearanceEndDate}>
                        <input
                            type="date"
                            value={createData.clearanceEndDate}
                            onChange={(e) => setCreateData('clearanceEndDate', e.target.value)}
                            className={`form-input ${createErrors.clearanceEndDate ? 'form-input-error' : ''}`}
                            required
                        />
                    </FormSection>
                    <FormSection label="Status" required error={createErrors.periodStatus} className="sm:col-span-2">
                        <Select
                            value={createData.periodStatus}
                            onChange={(e) => setCreateData('periodStatus', e.target.value)}
                            options={[
                                { value: 'open', label: 'Open' },
                                { value: 'closed', label: 'Closed' },
                            ]}
                            placeholder="Select status"
                            className={`form-input ${createErrors.periodStatus ? 'form-input-error' : ''}`}
                            required
                        />
                    </FormSection>
                </div>
            </Modal>

            {/* Edit Period Modal */}
            <Modal
                show={!!editingPeriod}
                onClose={closeEditModal}
                title="Edit Clearance Period"
                subtitle={editingPeriod ? periodLabel(editingPeriod) : ''}
                icon={
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                }
                size="md"
                footer={
                    <div className="flex justify-end gap-3">
                        <button
                            type="button"
                            className="btn btn-secondary"
                            onClick={closeEditModal}
                            disabled={isSubmitting}
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            className="btn btn-primary"
                            onClick={handleUpdatePeriod}
                            disabled={isSubmitting}
                        >
                            {isSubmitting ? 'Saving...' : 'Save Changes'}
                        </button>
                    </div>
                }
            >
                {editingPeriod && (
                    <div className="space-y-4">
                        {/* Read-only period summary */}
                        <div className="grid grid-cols-2 gap-3 p-3 rounded-btn bg-brand-50">
                            <div>
                                <p className="text-xs text-brand-500">Start Date</p>
                                <p className="text-sm font-medium text-brand-900">{fmtDate(editingPeriod.clearanceStartDate)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-brand-500">End Date</p>
                                <p className="text-sm font-medium text-brand-900">{fmtDate(editingPeriod.clearanceEndDate)}</p>
                            </div>
                        </div>
                        <FormSection label="Status" required error={editErrors.periodStatus} hint="Open periods accept new clearance slips. Closed periods are read-only.">
                            <Select
                                value={editData.periodStatus}
                                onChange={(e) => setEditData('periodStatus', e.target.value)}
                                options={[
                                    { value: 'open', label: 'Open' },
                                    { value: 'closed', label: 'Closed' },
                                ]}
                                placeholder="Select status"
                                className={`form-input ${editErrors.periodStatus ? 'form-input-error' : ''}`}
                                required
                            />
                        </FormSection>
                    </div>
                )}
            </Modal>

            {/* Confirm close period */}
            <ConfirmDialog
                show={!!confirmCloseId}
                onClose={() => setConfirmCloseId(null)}
                onConfirm={confirmClosePeriod}
                title="Close Clearance Period"
                message="Closing this period will stop it from accepting new clearance slips. Students with in-progress clearances can still be processed. This can be reversed by re-opening the period."
                confirmText="Close Period"
                variant="warning"
                loading={isSubmitting}
            />
        </AuthenticatedLayout>
    );
}
