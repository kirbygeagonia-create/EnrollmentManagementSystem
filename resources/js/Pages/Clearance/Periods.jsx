import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageHeader, Card, DataTable, FormSection, Select, Badge, EmptyState } from '@/Components/ui';
import { useState, useMemo } from 'react';
import { useForm } from '@inertiajs/react';

const periodStatusToneMap = {
    open: 'success',
    closed: 'neutral',
    extended: 'info',
};

export default function Periods({ periods }) {
    const [editingPeriodId, setEditingPeriodId] = useState(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Create Period Form
    const { data: createData, setData: setCreateData, post: createPost, errors: createErrors, reset: createReset } = useForm({
        termId: '',
        clearanceStartDate: '',
        clearanceEndDate: '',
        periodStatus: 'open',
    });

    // Edit Period Form
    const { setData: setEditData, patch: editPatch } = useForm({
        periodStatus: 'open',
    });

    const handleCreatePeriod = (e) => {
        e.preventDefault();
        setIsSubmitting(true);
        createPost(route('clearance.periods.store'), {
            onSuccess: () => {
                createReset();
                setIsSubmitting(false);
            },
            onError: () => setIsSubmitting(false),
        });
    };

    const handleEditPeriod = (period) => {
        setEditingPeriodId(period.clearancePeriodId);
        setEditData('periodStatus', period.periodStatus);
    };

    const handleUpdatePeriod = (period) => {
        setIsSubmitting(true);
        editPatch(route('clearance.periods.update', { period: period.clearancePeriodId }), {
            onSuccess: () => {
                setEditingPeriodId(null);
                setIsSubmitting(false);
            },
            onError: () => setIsSubmitting(false),
        });
    };

    const periodColumns = useMemo(() => [
        { key: 'term.academicYear', label: 'Academic Year', render: (row) => {
            const ay = row.term?.academicYear;
            return ay ? `${ay.yearStart}-${ay.yearEnd}` : '—';
        }},
        { key: 'term.semester', label: 'Semester', render: (row) => row.term?.semester || '—' },
        { key: 'clearanceStartDate', label: 'Start Date', render: (row) => row.clearanceStartDate ? new Date(row.clearanceStartDate).toLocaleDateString('en-PH') : '—' },
        { key: 'clearanceEndDate', label: 'End Date', render: (row) => row.clearanceEndDate ? new Date(row.clearanceEndDate).toLocaleDateString('en-PH') : '—' },
        { key: 'periodStatus', label: 'Status', render: (row) => (
            <Badge tone={periodStatusToneMap[row.periodStatus] || 'neutral'}>
                {row.periodStatus?.charAt(0).toUpperCase() + row.periodStatus?.slice(1)}
            </Badge>
        )},
    ], []);

    const renderActions = (row) => (
        <div className="flex items-center gap-2">
            {editingPeriodId === row.clearancePeriodId ? (
                <>
                    <button
                        type="button"
                        className="btn btn-primary btn-sm"
                        onClick={() => handleUpdatePeriod(row)}
                        disabled={isSubmitting}
                    >
                        {isSubmitting ? 'Saving...' : 'Save'}
                    </button>
                    <button
                        type="button"
                        className="btn btn-secondary btn-sm"
                        onClick={() => setEditingPeriodId(null)}
                        disabled={isSubmitting}
                    >
                        Cancel
                    </button>
                </>
            ) : (
                <button
                    type="button"
                    className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900"
                    onClick={() => handleEditPeriod(row)}
                >
                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    <span className="hidden sm:inline">Edit</span>
                </button>
            )}
        </div>
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Clearance Periods"
                    subtitle="Manage clearance periods for each academic term"
                />
            }
        >
            <Head title="Clearance Periods" />

            {/* Create Period Form */}
            <Card title="Create Clearance Period" className="mb-6">
                <form onSubmit={handleCreatePeriod} className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <FormSection label="Term" required error={createErrors.termId}>
                            <Select
                                value={createData.termId}
                                onChange={(e) => setCreateData('termId', e.target.value)}
                                options={periods.map(p => ({ value: p.termId, label: `${p.term?.academicYear?.yearStart}-${p.term?.academicYear?.yearEnd} ${p.term?.semester}` })).filter((v, i, a) => a.findIndex(t => t.value === v.value) === i)}
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
                        <FormSection label="Status" required error={createErrors.periodStatus}>
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
                    <div className="flex justify-end mt-4">
                        <button type="submit" className="btn btn-primary" disabled={isSubmitting}>
                            {isSubmitting ? 'Creating...' : 'Create Period'}
                        </button>
                    </div>
                </form>
            </Card>

            {/* Periods Table */}
            <Card title="Existing Periods">
                {periods.length > 0 ? (
                    <DataTable
                        columns={periodColumns}
                        rows={periods}
                        children={renderActions}
                        emptyMessage="No clearance periods created yet"
                    />
                ) : (
                    <EmptyState
                        title="No clearance periods found"
                        message="Create your first clearance period using the form above."
                    />
                )}
            </Card>
        </AuthenticatedLayout>
    );
}