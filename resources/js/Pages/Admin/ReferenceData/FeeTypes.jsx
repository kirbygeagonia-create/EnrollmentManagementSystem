import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useForm, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, Modal, ConfirmDialog, Select, EmptyState } from '@/Components/ui';

const unitBasisOptions = [
    { value: '', label: 'All Bases' },
    { value: 'perUnit', label: 'Per Unit' },
    { value: 'flat', label: 'Flat Rate' },
];

const statusOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

const unitBasisToneMap = {
    perUnit: 'info',
    flat: 'warning',
};

export default function FeeTypes({ feeTypes, unitBases }) {
    const [search, setSearch] = useState('');
    const [unitBasis, setUnitBasis] = useState('');
    const [status, setStatus] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [editingFeeType, setEditingFeeType] = useState(null);
    const [deleteConfirm, setDeleteConfirm] = useState(null);

    const form = useForm({
        feeName: '',
        defaultAmount: 0,
        unitBasis: 'perUnit',
    });

    const columns = useMemo(() => [
        { key: 'feeName', label: 'Name' },
        { key: 'defaultAmount', label: 'Amount', render: (row) => `₱${parseFloat(row.defaultAmount).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`, className: 'text-right font-mono' },
        { key: 'unitBasis', label: 'Unit Basis', render: (row) => (
            <Badge tone={unitBasisToneMap[row.unitBasis] || 'neutral'}>
                {row.unitBasis === 'perUnit' ? 'Per Unit' : 'Flat Rate'}
            </Badge>
        ), className: 'text-center' },
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (unitBasis) params.set('unitBasis', unitBasis);
        if (status) params.set('status', status);
        window.location.href = `${window.location.pathname}?${params.toString()}`;
    };

    const openCreateModal = () => {
        form.reset({
            feeName: '',
            defaultAmount: 0,
            unitBasis: 'perUnit',
        });
        setEditingFeeType(null);
        setShowModal(true);
    };

    const openEditModal = (feeType) => {
        form.reset({
            feeName: feeType.feeName,
            defaultAmount: feeType.defaultAmount,
            unitBasis: feeType.unitBasis,
        });
        setEditingFeeType(feeType);
        setShowModal(true);
    };

    const closeModal = () => {
        setShowModal(false);
        setEditingFeeType(null);
        form.clearErrors();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingFeeType) {
            form.put(route('admin.reference-data.fee-types.update', editingFeeType.feeTypeId), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        } else {
            form.post(route('admin.reference-data.fee-types.store'), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        }
    };

    const confirmDelete = (feeType) => {
        setDeleteConfirm(feeType);
    };

    const handleDelete = () => {
        if (deleteConfirm) {
            router.delete(route('admin.reference-data.fee-types.destroy', deleteConfirm.feeTypeId), {
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
                aria-label="Edit fee type"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>
            <button
                onClick={() => confirmDelete(row)}
                className="btn btn-ghost btn-sm text-danger-600 hover:text-danger-900"
                aria-label="Delete fee type"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </button>
        </div>
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Fee Types"
                    subtitle="Manage tuition and miscellaneous fees"
                    actions={
                        <button onClick={openCreateModal} className="btn btn-primary">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            New Fee Type
                        </button>
                    }
                />
            }
        >
            <Head title="Fee Types" />

            <FilterBar onSubmit={handleFilter}>
                <FilterBarField label="Search">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by name..."
                        className="form-input"
                    />
                </FilterBarField>
                <FilterBarField label="Unit Basis">
                    <Select
                        value={unitBasis}
                        onChange={setUnitBasis}
                        options={unitBasisOptions}
                        placeholder="All Bases"
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

            <Card>
                {feeTypes?.data?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={feeTypes.data}
                            children={renderActions}
                            emptyMessage="No fee types found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={feeTypes} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No fee types found"
                        message={search || unitBasis || status ? 'Try adjusting your filters to find matching records.' : 'No fee types have been created yet.'}
                        actionLabel={!search && !unitBasis && !status ? 'Create First Fee Type' : undefined}
                        onAction={!search && !unitBasis && !status ? openCreateModal : undefined}
                    />
                )}
            </Card>

            <Modal show={showModal} onClose={closeModal} title={editingFeeType ? 'Edit Fee Type' : 'Create Fee Type'}>
                <form onSubmit={handleSubmit}>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="form-group md:col-span-2">
                            <label className="form-label">Fee Name <span className="text-danger-500">*</span></label>
                            <input
                                type="text"
                                value={form.feeName}
                                onChange={(e) => form.setData('feeName', e.target.value)}
                                className={`form-input ${form.errors.feeName ? 'form-input-error' : ''}`}
                                placeholder="e.g., Tuition Fee, Laboratory Fee"
                                required
                            />
                            {form.errors.feeName && <p className="form-error">{form.errors.feeName}</p>}
                        </div>
                        <div className="form-group">
                            <label className="form-label">Default Amount <span className="text-danger-500">*</span></label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={form.defaultAmount}
                                onChange={(e) => form.setData('defaultAmount', parseFloat(e.target.value) || 0)}
                                className={`form-input ${form.errors.defaultAmount ? 'form-input-error' : ''}`}
                                required
                            />
                            {form.errors.defaultAmount && <p className="form-error">{form.errors.defaultAmount}</p>}
                        </div>
                        <div className="form-group">
                            <label className="form-label">Unit Basis <span className="text-danger-500">*</span></label>
                            <Select
                                value={form.unitBasis}
                                onChange={(e) => form.setData('unitBasis', e.target.value)}
                                options={unitBases.map(u => ({ value: u.value, label: u.value === 'perUnit' ? 'Per Unit' : 'Flat Rate' }))}
                                placeholder="Select unit basis"
                                className="form-input"
                                error={form.errors.unitBasis}
                                required
                            />
                            {form.errors.unitBasis && <p className="form-error">{form.errors.unitBasis}</p>}
                        </div>
                    </div>
                    <div className="flex justify-end gap-3 mt-6">
                        <button type="button" onClick={closeModal} className="btn btn-secondary" disabled={form.processing}>
                            Cancel
                        </button>
                        <button type="submit" className="btn btn-primary" disabled={form.processing}>
                            {form.processing ? 'Saving...' : (editingFeeType ? 'Update' : 'Create')}
                        </button>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                show={!!deleteConfirm}
                onClose={() => setDeleteConfirm(null)}
                onConfirm={handleDelete}
                title="Delete Fee Type"
                message={`Are you sure you want to delete "${deleteConfirm?.feeName}"? This action cannot be undone.`}
                confirmText="Delete"
                variant="danger"
            />
        </AuthenticatedLayout>
    );
}