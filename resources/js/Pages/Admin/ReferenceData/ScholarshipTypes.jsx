import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useForm, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, Modal, ConfirmDialog, Select, EmptyState, FormSection } from '@/Components/ui';

const coverageOptions = [
    { value: '', label: 'All Coverage Types' },
    { value: 'full', label: 'Full' },
    { value: 'partial', label: 'Partial' },
];

const coverageToneMap = {
    full: 'success',
    partial: 'warning',
};

export default function ScholarshipTypes({ types, coverageTypes }) {
    const [search, setSearch] = useState('');
    const [coverage, setCoverage] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [editingType, setEditingType] = useState(null);
    const [deleteConfirm, setDeleteConfirm] = useState(null);

    const form = useForm({
        scholarshipName: '',
        coverageType: 'full',
        coveragePercent: 100,
    });

    const columns = useMemo(() => [
        { key: 'scholarshipName', label: 'Name' },
        { key: 'coverageType', label: 'Coverage', render: (row) => (
            <Badge tone={coverageToneMap[row.coverageType] || 'neutral'}>
                {row.coverageType?.charAt(0).toUpperCase() + row.coverageType?.slice(1)}
            </Badge>
        ), className: 'text-center' },
        { key: 'coveragePercent', label: 'Coverage %', render: (row) => `${parseFloat(row.coveragePercent).toFixed(2)}%`, className: 'text-center font-mono' },
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('admin.scholarship-types.index'), {
            search: search || undefined,
            coverage: coverage || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const openCreateModal = () => {
        form.reset({
            scholarshipName: '',
            coverageType: 'full',
            coveragePercent: 100,
        });
        setEditingType(null);
        setShowModal(true);
    };

    const openEditModal = (type) => {
        form.reset({
            scholarshipName: type.scholarshipName,
            coverageType: type.coverageType,
            coveragePercent: type.coveragePercent,
        });
        setEditingType(type);
        setShowModal(true);
    };

    const closeModal = () => {
        setShowModal(false);
        setEditingType(null);
        form.clearErrors();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingType) {
            form.put(route('admin.reference-data.scholarship-types.update', editingType.scholarshipTypeId), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        } else {
            form.post(route('admin.reference-data.scholarship-types.store'), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        }
    };

    const confirmDelete = (type) => {
        setDeleteConfirm(type);
    };

    const handleDelete = () => {
        if (deleteConfirm) {
            router.delete(route('admin.reference-data.scholarship-types.destroy', deleteConfirm.scholarshipTypeId), {
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
                aria-label="Edit scholarship type"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>
            <button
                onClick={() => confirmDelete(row)}
                className="btn btn-ghost btn-sm text-danger-600 hover:text-danger-900"
                aria-label="Delete scholarship type"
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
                    title="Scholarship Types"
                    subtitle="Manage available scholarship programs"
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Logo"
                    actions={
                        <button onClick={openCreateModal} className="btn btn-primary">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            New Scholarship Type
                        </button>
                    }
                />
            }
        >
            <Head title="Scholarship Types" />

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
                <FilterBarField label="Coverage">
                    <Select
                        value={coverage}
                        onChange={setCoverage}
                        options={coverageOptions}
                        placeholder="All Coverage Types"
                        className="form-input"
                    />
                </FilterBarField>
            </FilterBar>

            <Card>
                {types?.data?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={types.data}
                            children={renderActions}
                            emptyMessage="No scholarship types found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={types} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No scholarship types found"
                        message={search || coverage ? 'Try adjusting your filters to find matching records.' : 'No scholarship types have been created yet.'}
                        actionLabel={!search && !coverage ? 'Create First Scholarship Type' : undefined}
                        onAction={!search && !coverage ? openCreateModal : undefined}
                    />
                )}
            </Card>

            <Modal
                show={showModal}
                onClose={closeModal}
                title={editingType ? 'Edit Scholarship Type' : 'Create Scholarship Type'}
                subtitle="Define the scholarship name, coverage type, and percentage."
                icon={
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                }
                size="lg"
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={closeModal} className="btn btn-secondary" disabled={form.processing}>
                            Cancel
                        </button>
                        <button type="submit" form="scholarship-form" className="btn btn-primary" disabled={form.processing}>
                            {form.processing ? 'Saving...' : (editingType ? 'Update' : 'Create')}
                        </button>
                    </div>
                }
            >
                <form id="scholarship-form" onSubmit={handleSubmit}>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormSection label="Scholarship Name" error={form.errors.scholarshipName} required>
                            <input
                                type="text"
                                value={form.data.scholarshipName}
                                onChange={(e) => form.setData('scholarshipName', e.target.value)}
                                className={`form-input ${form.errors.scholarshipName ? 'form-input-error' : ''}`}
                                placeholder="e.g., Academic Scholarship, Athletic Scholarship"
                                required
                            />
                        </FormSection>
                        <FormSection label="Coverage Type" error={form.errors.coverageType} required>
                            <Select
                                value={form.data.coverageType}
                                onChange={(e) => form.setData('coverageType', e.target.value)}
                                options={coverageTypes.map(c => ({ value: c.value, label: c.value.charAt(0).toUpperCase() + c.value.slice(1) }))}
                                placeholder="Select coverage type"
                                className="form-input"
                                error={form.errors.coverageType}
                                required
                            />
                        </FormSection>
                        <FormSection label="Coverage Percent" error={form.errors.coveragePercent} required>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                value={form.data.coveragePercent}
                                onChange={(e) => form.setData('coveragePercent', parseFloat(e.target.value) || 0)}
                                className={`form-input ${form.errors.coveragePercent ? 'form-input-error' : ''}`}
                                required
                            />
                        </FormSection>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                show={!!deleteConfirm}
                onClose={() => setDeleteConfirm(null)}
                onConfirm={handleDelete}
                title="Delete Scholarship Type"
                message={`Are you sure you want to delete "${deleteConfirm?.scholarshipName}"? This action cannot be undone.`}
                confirmText="Delete"
                variant="danger"
            />
        </AuthenticatedLayout>
    );
}