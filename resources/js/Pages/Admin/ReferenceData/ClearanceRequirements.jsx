import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useForm, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Modal, ConfirmDialog, Select, EmptyState } from '@/Components/ui';

export default function ClearanceRequirements({ requirements, offices }) {
    const [search, setSearch] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [deleteConfirm, setDeleteConfirm] = useState(null);

    const form = useForm({
        officeId: '',
    });

    const columns = useMemo(() => [
        { key: 'office.officeName', label: 'Office', render: (row) => row.office?.officeName || '—' },
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        window.location.href = `${window.location.pathname}?${params.toString()}`;
    };

    const openCreateModal = () => {
        form.reset({ officeId: '' });
        setShowModal(true);
    };

    const closeModal = () => {
        setShowModal(false);
        form.clearErrors();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        form.post(route('admin.reference-data.clearance-requirements.store'), {
            onSuccess: closeModal,
            preserveScroll: true,
        });
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
                    subtitle="Manage clearance requirements per office"
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
                        placeholder="Search by office name..."
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

            <Modal show={showModal} onClose={closeModal} title="Create Clearance Requirement">
                <form onSubmit={handleSubmit}>
                    <div className="form-group">
                        <label className="form-label">Office <span className="text-danger-500">*</span></label>
                        <Select
                            value={form.officeId}
                            onChange={(e) => form.setData('officeId', e.target.value)}
                            options={offices.map(o => ({ value: o.officeId, label: o.officeName }))}
                            placeholder="Select office"
                            className="form-input"
                            error={form.errors.officeId}
                            required
                        />
                        {form.errors.officeId && <p className="form-error">{form.errors.officeId}</p>}
                    </div>
                    <div className="flex justify-end gap-3 mt-6">
                        <button type="button" onClick={closeModal} className="btn btn-secondary" disabled={form.processing}>
                            Cancel
                        </button>
                        <button type="submit" className="btn btn-primary" disabled={form.processing}>
                            {form.processing ? 'Saving...' : 'Create'}
                        </button>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                show={!!deleteConfirm}
                onClose={() => setDeleteConfirm(null)}
                onConfirm={handleDelete}
                title="Delete Requirement"
                message={`Are you sure you want to delete the clearance requirement for "${deleteConfirm?.office?.officeName || 'this office'}"? This action cannot be undone.`}
                confirmText="Delete"
                variant="danger"
            />
        </AuthenticatedLayout>
    );
}