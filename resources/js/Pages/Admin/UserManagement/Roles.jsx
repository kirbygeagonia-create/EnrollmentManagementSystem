import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useForm, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Pagination, Modal, ConfirmDialog, EmptyState, FormSection } from '@/Components/ui';
import PrimaryButton from '@/Components/PrimaryButton';
import Checkbox from '@/Components/Checkbox';

export default function Roles({ roles, permissions }) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editingRole, setEditingRole] = useState(null);
    const [deletingRole, setDeletingRole] = useState(null);

    const createForm = useForm({
        name: '',
        description: '',
        permissionIds: [],
    });

    const editForm = useForm({
        name: '',
        description: '',
        permissionIds: [],
    });

    const columns = useMemo(() => [
        { key: 'name', label: 'Role Name' },
        { key: 'description', label: 'Description', render: (row) => row.description || '—' },
        { key: 'permissionsCount', label: 'Permissions', render: (row) => row.permissions?.length || 0 },
    ], []);

    const handleCreate = (e) => {
        e.preventDefault();
        createForm.post(route('admin.users.roles.store'), {
            onSuccess: () => {
                createForm.reset();
                setShowCreateModal(false);
            },
        });
    };

    const openEditModal = (role) => {
        editForm.reset({
            name: role.name || '',
            description: role.description || '',
            permissionIds: role.permissions?.map(p => p.id) || [],
        });
        setEditingRole(role);
    };

    const closeEditModal = () => {
        editForm.reset();
        setEditingRole(null);
    };

    const handleEdit = (e) => {
        e.preventDefault();
        if (editingRole) {
            editForm.patch(route('admin.users.roles.update', { role: editingRole.id }), {
                onSuccess: () => closeEditModal(),
            });
        }
    };

    const confirmDelete = (role) => {
        setDeletingRole(role);
    };

    const handleDelete = () => {
        if (deletingRole) {
            router.delete(route('admin.users.roles.destroy', { role: deletingRole.id }), {
                onSuccess: () => setDeletingRole(null),
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
                    title="Roles Management"
                    subtitle="Manage system roles and their permissions"
                    actions={
                        <PrimaryButton onClick={() => { createForm.reset(); setShowCreateModal(true); }}>
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Create Role
                        </PrimaryButton>
                    }
                />
            }
        >
            <Head title="Roles Management" />

            {/* Data Table */}
            <Card>
                {roles?.data?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={roles.data}
                            children={renderActions}
                            emptyMessage="No roles found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={roles} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No roles found"
                        message="No roles have been created yet."
                        actionLabel="Create First Role"
                        onAction={() => { createForm.reset(); setShowCreateModal(true); }}
                    />
                )}
            </Card>

            {/* Create Modal */}
            <Modal show={showCreateModal} onClose={() => { createForm.reset(); setShowCreateModal(false); }} title="Create Role" size="lg">
                <form onSubmit={handleCreate} className="space-y-4">
                    <FormSection label="Role Name" error={createForm.errors.name} required>
                        <input
                            type="text"
                            value={createForm.data.name}
                            onChange={(e) => createForm.setData('name', e.target.value)}
                            className="form-input"
                            required
                            maxLength={100}
                        />
                    </FormSection>
                    <FormSection label="Description" error={createForm.errors.description}>
                        <textarea
                            value={createForm.data.description}
                            onChange={(e) => createForm.setData('description', e.target.value)}
                            className="form-input"
                            rows={3}
                        />
                    </FormSection>
                    <FormSection label="Permissions" error={createForm.errors.permissionIds}>
                        <div className="space-y-2 max-h-64 overflow-y-auto border border-brand-200 rounded-btn p-3">
                            {permissions.map(permission => (
                                <label key={permission.id} className="flex items-center gap-2 cursor-pointer">
                                    <Checkbox
                                        checked={createForm.data.permissionIds?.includes(permission.id)}
                                        onChange={(e) => {
                                            const ids = createForm.data.permissionIds || [];
                                            if (e.target.checked) {
                                                createForm.setData('permissionIds', [...ids, permission.id]);
                                            } else {
                                                createForm.setData('permissionIds', ids.filter(id => id !== permission.id));
                                            }
                                        }}
                                    />
                                    <span className="text-sm text-brand-700">{permission.name}</span>
                                    <span className="text-xs text-brand-400 ml-auto badge badge-neutral">{permission.module}</span>
                                </label>
                            ))}
                        </div>
                    </FormSection>
                    <div className="flex justify-end gap-3 pt-4 border-t border-brand-100">
                        <button type="button" onClick={() => { createForm.reset(); setShowCreateModal(false); }} className="btn btn-secondary" disabled={createForm.processing}>
                            Cancel
                        </button>
                        <PrimaryButton type="submit" disabled={createForm.processing}>
                            {createForm.processing ? 'Creating...' : 'Create Role'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Edit Modal */}
            <Modal show={!!editingRole} onClose={closeEditModal} title="Edit Role" size="lg">
                <form onSubmit={handleEdit} className="space-y-4">
                    <FormSection label="Role Name" error={editForm.errors.name} required>
                        <input
                            type="text"
                            value={editForm.data.name}
                            onChange={(e) => editForm.setData('name', e.target.value)}
                            className="form-input"
                            required
                            maxLength={100}
                        />
                    </FormSection>
                    <FormSection label="Description" error={editForm.errors.description}>
                        <textarea
                            value={editForm.data.description}
                            onChange={(e) => editForm.setData('description', e.target.value)}
                            className="form-input"
                            rows={3}
                        />
                    </FormSection>
                    <FormSection label="Permissions" error={editForm.errors.permissionIds}>
                        <div className="space-y-2 max-h-64 overflow-y-auto border border-brand-200 rounded-btn p-3">
                            {permissions.map(permission => (
                                <label key={permission.id} className="flex items-center gap-2 cursor-pointer">
                                    <Checkbox
                                        checked={editForm.data.permissionIds?.includes(permission.id)}
                                        onChange={(e) => {
                                            const ids = editForm.data.permissionIds || [];
                                            if (e.target.checked) {
                                                editForm.setData('permissionIds', [...ids, permission.id]);
                                            } else {
                                                editForm.setData('permissionIds', ids.filter(id => id !== permission.id));
                                            }
                                        }}
                                    />
                                    <span className="text-sm text-brand-700">{permission.name}</span>
                                    <span className="text-xs text-brand-400 ml-auto badge badge-neutral">{permission.module}</span>
                                </label>
                            ))}
                        </div>
                    </FormSection>
                    <div className="flex justify-end gap-3 pt-4 border-t border-brand-100">
                        <button type="button" onClick={closeEditModal} className="btn btn-secondary" disabled={editForm.processing}>
                            Cancel
                        </button>
                        <PrimaryButton type="submit" disabled={editForm.processing}>
                            {editForm.processing ? 'Saving...' : 'Save Changes'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Delete Confirm Dialog */}
            <ConfirmDialog
                show={!!deletingRole}
                onClose={() => setDeletingRole(null)}
                onConfirm={handleDelete}
                title="Delete Role"
                message={`Are you sure you want to delete "${deletingRole?.name || 'this role'}"? This action cannot be undone.`}
                confirmText="Delete"
                variant="danger"
            />
        </AuthenticatedLayout>
    );
}