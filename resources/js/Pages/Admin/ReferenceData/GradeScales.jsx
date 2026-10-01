import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useForm, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, Modal, ConfirmDialog, Select, EmptyState, FormSection, RadioCards } from '@/Components/ui';

const passingFilterOptions = [
    { value: '', label: 'All Bands' },
    { value: '1', label: 'Passing' },
    { value: '0', label: 'Failed' },
];

const passingOptions = [
    { value: 'true', label: 'Passing', tone: 'success' },
    { value: 'false', label: 'Failed', tone: 'danger' },
];

export default function GradeScales({ bands, passingCeiling, hasGradeScale, filters = {} }) {
    const [search, setSearch] = useState(filters.search || '');
    const [isPassing, setIsPassing] = useState(filters.isPassing ?? '');
    const [showModal, setShowModal] = useState(false);
    const [editingBand, setEditingBand] = useState(null);
    const [deleteConfirm, setDeleteConfirm] = useState(null);

    const form = useForm({
        minGrade: '',
        maxGrade: '',
        isPassing: true,
        description: '',
    });

    const columns = useMemo(() => [
        {
            key: 'range',
            label: 'Grade Range',
            render: (row) => `${Number(row.minGrade).toFixed(2)} – ${Number(row.maxGrade).toFixed(2)}`,
            className: 'font-mono',
        },
        {
            key: 'isPassing',
            label: 'Result',
            render: (row) => (
                <Badge tone={row.isPassing ? 'success' : 'danger'}>
                    {row.isPassing ? 'Passing' : 'Failed'}
                </Badge>
            ),
            className: 'text-center',
        },
        { key: 'description', label: 'Description' },
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('admin.reference-data.grade-scale'), {
            search: search || undefined,
            isPassing: isPassing === '' ? undefined : isPassing,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const openCreateModal = () => {
        form.reset({
            minGrade: '',
            maxGrade: '',
            isPassing: true,
            description: '',
        });
        setEditingBand(null);
        setShowModal(true);
    };

    const openEditModal = (band) => {
        form.reset({
            minGrade: Number(band.minGrade),
            maxGrade: Number(band.maxGrade),
            isPassing: !!band.isPassing,
            description: band.description ?? '',
        });
        setEditingBand(band);
        setShowModal(true);
    };

    const closeModal = () => {
        setShowModal(false);
        setEditingBand(null);
        form.clearErrors();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingBand) {
            form.put(route('admin.reference-data.grade-scale.update', editingBand.gradeScaleId), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        } else {
            form.post(route('admin.reference-data.grade-scale.store'), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        }
    };

    const confirmDelete = (band) => {
        setDeleteConfirm(band);
    };

    const handleDelete = () => {
        if (deleteConfirm) {
            router.delete(route('admin.reference-data.grade-scale.destroy', deleteConfirm.gradeScaleId), {
                preserveScroll: true,
            });
            setDeleteConfirm(null);
        }
    };

    const renderActions = (row) => (
        <div className="flex items-center gap-2">
            <button
                onClick={() => openEditModal(row)}
                className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900"
                aria-label="Edit grade band"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>
            <button
                onClick={() => confirmDelete(row)}
                className="btn btn-ghost btn-sm text-danger-600 hover:text-danger-900"
                aria-label="Delete grade band"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a2 2 0 00-2 2v3M4 7h16" />
                </svg>
            </button>
        </div>
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Grade Scale"
                    subtitle="The bands academic standing is derived against"
                    actions={
                        <button onClick={openCreateModal} className="btn btn-primary">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            New Band
                        </button>
                    }
                />
            }
        >
            <Head title="Grade Scale" />

            <Card className="mb-5">
                <div className="flex flex-wrap items-center gap-x-8 gap-y-3">
                    <div>
                        <p className="text-xs font-medium uppercase tracking-wide text-slate-500">Passing ceiling in use</p>
                        <p className="font-mono text-2xl font-semibold text-slate-900">
                            {Number(passingCeiling).toFixed(2)}
                        </p>
                    </div>
                    <p className="text-sm text-slate-600 max-w-2xl">
                        {hasGradeScale
                            ? 'On the Philippine scale lower is better, so any grade worse than this ceiling marks the subject failed — and a student carrying a failed subject is derived as IRREGULAR at the Evaluation desk.'
                            : 'No passing band is on file, so the ceiling has fallen back to the assumed 3.00. Add a passing band to make the derivation official.'}
                    </p>
                </div>
            </Card>

            <FilterBar onSubmit={handleFilter}>
                <FilterBarField label="Search">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by description..."
                        className="form-input"
                    />
                </FilterBarField>
                <FilterBarField label="Result">
                    <Select
                        value={isPassing}
                        onChange={setIsPassing}
                        options={passingFilterOptions}
                        placeholder="All Bands"
                        className="form-input"
                    />
                </FilterBarField>
            </FilterBar>

            <Card>
                {bands?.data?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={bands.data}
                            children={renderActions}
                            emptyMessage="No grade bands found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={bands} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No grade bands found"
                        message={search || isPassing !== ''
                            ? 'Try adjusting your filters to find matching bands.'
                            : 'No grade scale has been configured yet, so standing is derived against the assumed 3.00 ceiling.'}
                        actionLabel={!search && isPassing === '' ? 'Create First Band' : undefined}
                        onAction={!search && isPassing === '' ? openCreateModal : undefined}
                    />
                )}
            </Card>

            <Modal
                show={showModal}
                onClose={closeModal}
                title={editingBand ? 'Edit Grade Band' : 'Create Grade Band'}
                subtitle="Set the inclusive grade range, whether it passes, and its label."
                icon={
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                }
                size="lg"
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={closeModal} className="btn btn-secondary" disabled={form.processing}>
                            Cancel
                        </button>
                        <button type="submit" form="grade-scale-form" className="btn btn-primary" disabled={form.processing}>
                            {form.processing ? 'Saving...' : (editingBand ? 'Update' : 'Create')}
                        </button>
                    </div>
                }
            >
                <form id="grade-scale-form" onSubmit={handleSubmit}>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormSection label="Minimum Grade" error={form.errors.minGrade} required>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                max="9.99"
                                value={form.data.minGrade}
                                onChange={(e) => form.setData('minGrade', parseFloat(e.target.value) || 0)}
                                className={`form-input ${form.errors.minGrade ? 'form-input-error' : ''}`}
                                placeholder="e.g., 1.00"
                                required
                            />
                        </FormSection>
                        <FormSection label="Maximum Grade" error={form.errors.maxGrade} required>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                max="9.99"
                                value={form.data.maxGrade}
                                onChange={(e) => form.setData('maxGrade', parseFloat(e.target.value) || 0)}
                                className={`form-input ${form.errors.maxGrade ? 'form-input-error' : ''}`}
                                placeholder="e.g., 3.00"
                                required
                            />
                        </FormSection>
                        <FormSection label="Result" error={form.errors.isPassing} required>
                            <RadioCards
                                name="isPassing"
                                label="Result"
                                value={form.data.isPassing}
                                onChange={(v) => form.setData('isPassing', v === 'true')}
                                options={passingOptions}
                            />
                        </FormSection>
                        <FormSection label="Description" error={form.errors.description} required>
                            <input
                                type="text"
                                value={form.data.description}
                                onChange={(e) => form.setData('description', e.target.value)}
                                className={`form-input ${form.errors.description ? 'form-input-error' : ''}`}
                                placeholder="e.g., Passing. To be confirmed by the Registrar."
                                required
                            />
                        </FormSection>
                    </div>
                    <p className="mt-4 text-sm text-slate-500">
                        Grades are stored to two decimals, so keep bands contiguous — a gap leaves grades like 2.505 in no band at all.
                    </p>
                </form>
            </Modal>

            <ConfirmDialog
                show={!!deleteConfirm}
                onClose={() => setDeleteConfirm(null)}
                onConfirm={handleDelete}
                title="Delete Grade Band"
                message={`Are you sure you want to delete the band ${deleteConfirm ? `${Number(deleteConfirm.minGrade).toFixed(2)} – ${Number(deleteConfirm.maxGrade).toFixed(2)}` : ''}? Standing derivation will change for grades inside it.`}
                confirmText="Delete"
                variant="danger"
            />
        </AuthenticatedLayout>
    );
}
