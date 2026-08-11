import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useForm, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Pagination, Modal, ConfirmDialog, EmptyState, FormSection, Badge } from '@/Components/ui';

function PermissionsMatrix({ permissions, selectedIds, onToggle }) {
    const grouped = useMemo(() => {
        const map = {};
        permissions.forEach(p => {
            const mod = p.module || 'General';
            if (!map[mod]) map[mod] = [];
            map[mod].push(p);
        });
        return Object.entries(map).sort((a, b) => a[0].localeCompare(b[0]));
    }, [permissions]);

    return (
        <div className="space-y-4 max-h-72 overflow-y-auto pr-1">
            {grouped.map(([module, perms]) => (
                <div key={module} className="border border-brand-200 rounded-btn overflow-hidden">
                    <div className="px-3 py-2 bg-brand-50 border-b border-brand-200 flex items-center justify-between">
                        <span className="text-xs font-semibold uppercase tracking-wide text-brand-700">{module}</span>
                        <span className="text-xs text-brand-400">{perms.length}</span>
                    </div>
                    <div className="p-3 bg-white space-y-1.5">
                        {perms.map(permission => (
                            <label key={permission.id} className="flex items-center gap-2 cursor-pointer py-1 hover:bg-brand-50/50 px-1 rounded transition-colors">
                                <input
                                    type="checkbox"
                                    checked={selectedIds?.includes(permission.id)}
                                    onChange={(e) => onToggle(permission.id, e.target.checked)}
                                    className="form-checkbox"
                                />
                                <span className="text-sm text-brand-700 font-medium font-mono">{permission.name}</span>
                            </label>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}

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
        { key: 'name', label: 'Role Name', render: (row) => (
            <span className="font-semibold text-brand-900">{row.name}</span>
        )},
        { key: 'description', label: 'Description', render: (row) => row.description || '—' },
        { key: 'permissionsCount', label: 'Permissions', render: (row) => (
            <Badge tone="info">{row.permissions?.length || 0}</Badge>
        ), className: 'text-center' },
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

    const toggleCreatePermission = (id, checked) => {
        const ids = createForm.data.permissionIds || [];
        createForm.setData('permissionIds', checked ? [...ids, id] : ids.filter(x => x !== id));
    };

    const toggleEditPermission = (id, checked) => {
        const ids = editForm.data.permissionIds || [];
        editForm.setData('permissionIds', checked ? [...ids, id] : ids.filter(x => x !== id));
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
                    subtitle="Define system roles and assign granular permissions"
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Logo"
                    actions={
                        <button onClick={() => { createForm.reset(); setShowCreateModal(true); }} className="btn btn-primary">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Create Role
                        </button>
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
            <Modal
                show={showCreateModal}
                onClose={() => { createForm.reset(); setShowCreateModal(false); }}
                title="Create Role"
                subtitle="Name the role and select the permissions it grants."
                icon={
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                }
                size="lg"
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={() => { createForm.reset(); setShowCreateModal(false); }} className="btn btn-secondary" disabled={createForm.processing}>
                            Cancel
                        </button>
                        <button type="submit" form="create-role-form" className="btn btn-primary" disabled={createForm.processing}>
                            {createForm.processing ? 'Creating...' : 'Create Role'}
                        </button>
                    </div>
                }
            >
                <form id="create-role-form" onSubmit={handleCreate} className="space-y-4">
                    <FormSection label="Role Name" error={createForm.errors.name} required>
                        <input
                            type="text"
                            value={createForm.data.name}
                            onChange={(e) => createForm.setData('name', e.target.value)}
                            className="form-input"
                            required
                            maxLength={100}
                            placeholder="e.g., Registrar Staff, Dean"
                        />
                    </FormSection>
                    <FormSection label="Description" error={createForm.errors.description}>
                        <textarea
                            value={createForm.data.description}
                            onChange={(e) => createForm.setData('description', e.target.value)}
                            className="form-input"
                            rows={3}
                            placeholder="Briefly describe what this role is for..."
                        />
                    </FormSection>
                    <FormSection label="Permissions" error={createForm.errors.permissionIds} hint={`${createForm.data.permissionIds?.length || 0} selected`}>
                        <PermissionsMatrix
                            permissions={permissions}
                            selectedIds={createForm.data.permissionIds}
                            onToggle={toggleCreatePermission}
                        />
                    </FormSection>
                </form>
            </Modal>

            {/* Edit Modal */}
            <Modal
                show={!!editingRole}
                onClose={closeEditModal}
                title="Edit Role"
                subtitle={editingRole?.name || ''}
                icon={
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                }
                size="lg"
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={closeEditModal} className="btn btn-secondary" disabled={editForm.processing}>
                            Cancel
                        </button>
                        <button type="submit" form="edit-role-form" className="btn btn-primary" disabled={editForm.processing}>
                            {editForm.processing ? 'Saving...' : 'Save Changes'}
                        </button>
                    </div>
                }
            >
                <form id="edit-role-form" onSubmit={handleEdit} className="space-y-4">
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
                    <FormSection label="Permissions" error={editForm.errors.permissionIds} hint={`${editForm.data.permissionIds?.length || 0} selected`}>
                        <PermissionsMatrix
                            permissions={permissions}
                            selectedIds={editForm.data.permissionIds}
                            onToggle={toggleEditPermission}
                        />
                    </FormSection>
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