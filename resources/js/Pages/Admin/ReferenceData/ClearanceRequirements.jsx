import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useForm, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Modal, ConfirmDialog, Select, EmptyState, FormSection } from '@/Components/ui';

export default function ClearanceRequirements({ requirements, offices, filters = {} }) {
    const [search, setSearch] = useState(filters.search || '');
    const [showModal, setShowModal] = useState(false);
    const [editingRequirement, setEditingRequirement] = useState(null);
    const [deleteConfirm, setDeleteConfirm] = useState(null);

    const form = useForm({
        officeId: '',
        requirementName: '',
    });

    const columns = useMemo(() => [
        { key: 'office.officeName', label: 'Office', render: (row) => row.office?.officeName || '—' },
        { key: 'requirementName', label: 'Requirement', render: (row) => row.requirementName || (
            <span className="text-warning-700">Not yet worded — the slip prints an office name in place of a requirement</span>
        ) },
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('admin.reference-data.clearance-requirements'), {
            search: search || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const openCreateModal = () => {
        form.setData({ officeId: '', requirementName: '' });
        setEditingRequirement(null);
        setShowModal(true);
    };

    const openEditModal = (req) => {
        form.setData({
            officeId: req.officeId,
            requirementName: req.requirementName || '',
        });
        setEditingRequirement(req);
        setShowModal(true);
    };

    const closeModal = () => {
        setShowModal(false);
        setEditingRequirement(null);
        form.clearErrors();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingRequirement) {
            form.patch(route('admin.reference-data.clearance-requirements.update', editingRequirement.clearanceRequirementId), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        } else {
            form.post(route('admin.reference-data.clearance-requirements.store'), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        }
    };

    const confirmDelete = (req) => {
        setDeleteConfirm(req);
    };

    const handleDelete = () => {
        if (deleteConfirm) {
            router.delete(route('admin.reference-data.clearance-requirements.destroy', deleteConfirm.clearanceRequirementId), {
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
                aria-label="Edit requirement"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>
            <button
                onClick={() => confirmDelete(row)}
                className="btn btn-ghost btn-sm text-danger-600 hover:text-danger-900"
                aria-label="Delete requirement"
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
                    title="Clearance Requirements"
                    subtitle="The obligation lines every clearance slip prints, and the office that signs each one off"
                    actions={
                        <button onClick={openCreateModal} className="btn btn-primary">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            New Requirement
                        </button>
                    }
                />
            }
        >
            <Head title="Clearance Requirements" />

            <FilterBar onSubmit={handleFilter}>
                <FilterBarField label="Search">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by requirement or office name..."
                        className="form-input"
                    />
                </FilterBarField>
            </FilterBar>

            <Card>
                {requirements?.data?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={requirements.data}
                            children={renderActions}
                            emptyMessage="No requirements found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={requirements} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No requirements found"
                        message={search ? 'Try adjusting your search to find matching records.' : 'No clearance requirements have been created yet.'}
                        actionLabel={!search ? 'Create First Requirement' : undefined}
                        onAction={!search ? openCreateModal : undefined}
                    />
                )}
            </Card>

            <Modal
                show={showModal}
                onClose={closeModal}
                title={editingRequirement ? 'Edit Clearance Requirement' : 'Create Clearance Requirement'}
                subtitle="Write the obligation the student must clear, then name the office that signs it off."
                icon={
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                }
                size="md"
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={closeModal} className="btn btn-secondary" disabled={form.processing}>
                            Cancel
                        </button>
                        <button type="submit" form="clearance-req-form" className="btn btn-primary" disabled={form.processing}>
                            {form.processing ? 'Saving...' : (editingRequirement ? 'Update' : 'Create')}
                        </button>
                    </div>
                }
            >
                <form id="clearance-req-form" onSubmit={handleSubmit}>
                    <FormSection label="Requirement" error={form.errors.requirementName} required>
                        <input
                            type="text"
                            value={form.data.requirementName}
                            onChange={(e) => form.setData('requirementName', e.target.value)}
                            className={`form-input ${form.errors.requirementName ? 'form-input-error' : ''}`}
                            placeholder="e.g., Return borrowed books"
                            required
                        />
                    </FormSection>
                    <FormSection label="Office" error={form.errors.officeId} required>
                        <Select
                            value={form.data.officeId}
                            onChange={(v) => form.setData('officeId', v)}
                            options={offices.map(o => ({ value: o.officeId, label: o.officeName }))}
                            placeholder="Select office"
                            className="form-input"
                            error={form.errors.officeId}
                            required
                        />
                    </FormSection>
                </form>
            </Modal>

            <ConfirmDialog
                show={!!deleteConfirm}
                onClose={() => setDeleteConfirm(null)}
                onConfirm={handleDelete}
                title="Delete Requirement"
                message={`Are you sure you want to delete "${deleteConfirm?.requirementName || deleteConfirm?.office?.officeName || 'this requirement'}"? A requirement already signed on a slip cannot be deleted. This action cannot be undone.`}
                confirmText="Delete"
                variant="danger"
            />
        </AuthenticatedLayout>
    );
}