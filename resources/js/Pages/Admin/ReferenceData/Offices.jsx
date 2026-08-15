import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useForm, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Modal, ConfirmDialog, EmptyState, FormSection } from '@/Components/ui';

export default function Offices({ offices }) {
    const [search, setSearch] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [editingOffice, setEditingOffice] = useState(null);
    const [deleteConfirm, setDeleteConfirm] = useState(null);

    const form = useForm({
        officeName: '',
    });

    const columns = useMemo(() => [
        { key: 'officeId', label: 'ID', className: 'font-mono text-sm hidden md:table-cell' },
        { key: 'officeName', label: 'Name' },
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('admin.offices.index'), {
            search: search || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const openCreateModal = () => {
        form.reset({ officeName: '' });
        setEditingOffice(null);
        setShowModal(true);
    };

    const openEditModal = (office) => {
        form.reset({ officeName: office.officeName });
        setEditingOffice(office);
        setShowModal(true);
    };

    const closeModal = () => {
        setShowModal(false);
        setEditingOffice(null);
        form.clearErrors();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingOffice) {
            form.put(route('admin.reference-data.offices.update', editingOffice.officeId), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        } else {
            form.post(route('admin.reference-data.offices.store'), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        }
    };

    const confirmDelete = (office) => {
        setDeleteConfirm(office);
    };

    const handleDelete = () => {
        if (deleteConfirm) {
            router.delete(route('admin.reference-data.offices.destroy', deleteConfirm.officeId), {
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
                aria-label="Edit office"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>
            <button
                onClick={() => confirmDelete(row)}
                className="btn btn-ghost btn-sm text-danger-600 hover:text-danger-900"
                aria-label="Delete office"
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
                    title="Offices"
                    subtitle="Manage administrative offices"
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Logo"
                    actions={
                        <button onClick={openCreateModal} className="btn btn-primary">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            New Office
                        </button>
                    }
                />
            }
        >
            <Head title="Offices" />

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
            </FilterBar>

            <Card>
                {offices?.data?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={offices.data}
                            children={renderActions}
                            emptyMessage="No offices found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={offices} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No offices found"
                        message={search ? 'Try adjusting your search to find matching records.' : 'No offices have been created yet.'}
                        actionLabel={!search ? 'Create First Office' : undefined}
                        onAction={!search ? openCreateModal : undefined}
                    />
                )}
            </Card>

            <Modal
                show={showModal}
                onClose={closeModal}
                title={editingOffice ? 'Edit Office' : 'Create Office'}
                subtitle="Enter the administrative office name."
                icon={
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                }
                size="md"
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={closeModal} className="btn btn-secondary" disabled={form.processing}>
                            Cancel
                        </button>
                        <button type="submit" form="office-form" className="btn btn-primary" disabled={form.processing}>
                            {form.processing ? 'Saving...' : (editingOffice ? 'Update' : 'Create')}
                        </button>
                    </div>
                }
            >
                <form id="office-form" onSubmit={handleSubmit}>
                    <FormSection label="Office Name" error={form.errors.officeName} required>
                        <input
                            type="text"
                            value={form.data.officeName}
                            onChange={(e) => form.setData('officeName', e.target.value)}
                            className={`form-input ${form.errors.officeName ? 'form-input-error' : ''}`}
                            placeholder="e.g., Registrar's Office, Accounting Office"
                            required
                        />
                    </FormSection>
                </form>
            </Modal>

            <ConfirmDialog
                show={!!deleteConfirm}
                onClose={() => setDeleteConfirm(null)}
                onConfirm={handleDelete}
                title="Delete Office"
                message={`Are you sure you want to delete "${deleteConfirm?.officeName}"? This action cannot be undone.`}
                confirmText="Delete"
                variant="danger"
            />
        </AuthenticatedLayout>
    );
}