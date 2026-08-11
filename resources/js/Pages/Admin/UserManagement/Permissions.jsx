import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useForm, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Pagination, Modal, ConfirmDialog, EmptyState, FormSection, Badge } from '@/Components/ui';

export default function Permissions({ permissions }) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editingPermission, setEditingPermission] = useState(null);
    const [deletingPermission, setDeletingPermission] = useState(null);

    const createForm = useForm({
        name: '',
        module: '',
    });

    const editForm = useForm({
        name: '',
        module: '',
    });

    const columns = useMemo(() => [
        { key: 'name', label: 'Permission Name', render: (row) => (
            <span className="font-mono text-sm text-brand-800 font-medium">{row.name}</span>
        )},
        { key: 'module', label: 'Module', render: (row) => (
            <Badge tone="neutral">{row.module}</Badge>
        )},
        { key: 'guard_name', label: 'Guard', render: (row) => (
            <span className="text-xs text-brand-500 font-mono">{row.guard_name || 'web'}</span>
        )},
    ], []);

    const handleCreate = (e) => {
        e.preventDefault();
        createForm.post(route('admin.users.permissions.store'), {
            onSuccess: () => {
                createForm.reset();
                setShowCreateModal(false);
            },
        });
    };

    const openEditModal = (permission) => {
        editForm.reset({
            name: permission.name || '',
            module: permission.module || '',
        });
        setEditingPermission(permission);
    };

    const closeEditModal = () => {
        editForm.reset();
        setEditingPermission(null);
    };

    const handleEdit = (e) => {
        e.preventDefault();
        if (editingPermission) {
            editForm.patch(route('admin.users.permissions.update', { permission: editingPermission.id }), {
                onSuccess: () => closeEditModal(),
            });
        }
    };

    const confirmDelete = (permission) => {
        setDeletingPermission(permission);
    };

    const handleDelete = () => {
        if (deletingPermission) {
            router.delete(route('admin.users.permissions.destroy', { permission: deletingPermission.id }), {
                onSuccess: () => setDeletingPermission(null),
            });
        }
    };

    const renderActions = (row) => (
        <div className="flex items-center gap-1">
            <button
                onClick={() => openEditModal(row)}
                className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900"
                title="Edit"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>
            <button
                onClick={() => confirmDelete(row)}
                className="btn btn-ghost btn-sm text-danger-600 hover:text-danger-900"
                title="Delete"
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
                    title="Permissions Management"
                    subtitle="Define the granular permissions that can be assigned to roles"
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Logo"
                    actions={
                        <button onClick={() => { createForm.reset(); setShowCreateModal(true); }} className="btn btn-primary">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Create Permission
                        </button>
                    }
                />
            }
        >
            <Head title="Permissions Management" />

            {/* Data Table */}
            <Card>
                {permissions?.data?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={permissions.data}
                            children={renderActions}
                            emptyMessage="No permissions found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={permissions} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No permissions found"
                        message="No permissions have been created yet."
                        actionLabel="Create First Permission"
                        onAction={() => { createForm.reset(); setShowCreateModal(true); }}
                    />
                )}
            </Card>

            {/* Create Modal */}
            <Modal
                show={showCreateModal}
                onClose={() => { createForm.reset(); setShowCreateModal(false); }}
                title="Create Permission"
                subtitle="Define a new permission that can be granted to roles."
                icon={
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                }
                size="md"
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={() => { createForm.reset(); setShowCreateModal(false); }} className="btn btn-secondary" disabled={createForm.processing}>
                            Cancel
                        </button>
                        <button type="submit" form="create-perm-form" className="btn btn-primary" disabled={createForm.processing}>
                            {createForm.processing ? 'Creating...' : 'Create Permission'}
                        </button>
                    </div>
                }
            >
                <form id="create-perm-form" onSubmit={handleCreate} className="space-y-4">
                    <FormSection label="Permission Name" error={createForm.errors.name} required>
                        <input
                            type="text"
                            value={createForm.data.name}
                            onChange={(e) => createForm.setData('name', e.target.value)}
                            className="form-input"
                            required
                            maxLength={100}
                            placeholder="e.g., users.view, admissions.create"
                        />
                    </FormSection>
                    <FormSection label="Module" error={createForm.errors.module} required>
                        <input
                            type="text"
                            value={createForm.data.module}
                            onChange={(e) => createForm.setData('module', e.target.value)}
                            className="form-input"
                            required
                            maxLength={100}
                            placeholder="e.g., users, admissions, registrar"
                        />
                    </FormSection>
                </form>
            </Modal>

            {/* Edit Modal */}
            <Modal
                show={!!editingPermission}
                onClose={closeEditModal}
                title="Edit Permission"
                subtitle={editingPermission?.name || ''}
                icon={
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                }
                size="md"
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={closeEditModal} className="btn btn-secondary" disabled={editForm.processing}>
                            Cancel
                        </button>
                        <button type="submit" form="edit-perm-form" className="btn btn-primary" disabled={editForm.processing}>
                            {editForm.processing ? 'Saving...' : 'Save Changes'}
                        </button>
                    </div>
                }
            >
                <form id="edit-perm-form" onSubmit={handleEdit} className="space-y-4">
                    <FormSection label="Permission Name" error={editForm.errors.name} required>
                        <input
                            type="text"
                            value={editForm.data.name}
                            onChange={(e) => editForm.setData('name', e.target.value)}
                            className="form-input"
                            required
                            maxLength={100}
                        />
                    </FormSection>
                    <FormSection label="Module" error={editForm.errors.module} required>
                        <input
                            type="text"
                            value={editForm.data.module}
                            onChange={(e) => editForm.setData('module', e.target.value)}
                            className="form-input"
                            required
                            maxLength={100}
                        />
                    </FormSection>
                </form>
            </Modal>

            {/* Delete Confirm Dialog */}
            <ConfirmDialog
                show={!!deletingPermission}
                onClose={() => setDeletingPermission(null)}
                onConfirm={handleDelete}
                title="Delete Permission"
                message={`Are you sure you want to delete "${deletingPermission?.name || 'this permission'}"? This action cannot be undone.`}
                confirmText="Delete"
                variant="danger"
            />
        </AuthenticatedLayout>
    );
}