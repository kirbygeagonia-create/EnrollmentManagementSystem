import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { useForm, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, Select, Modal, ConfirmDialog, EmptyState, FormSection } from '@/Components/ui';
import PrimaryButton from '@/Components/PrimaryButton';

const statusOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

const statusToneMap = {
    active: 'success',
    inactive: 'neutral',
};

const roleToneMap = {
    admin: 'danger',
    dean: 'warning',
    officeHead: 'info',
    programHead: 'accent',
    staff: 'neutral',
};

export default function Index({ users, offices, units, roles, filters = {}, staffRoles, staffStatuses }) {
    const [search, setSearch] = useState(filters.search || '');
    const [officeId, setOfficeId] = useState(filters.officeId || '');
    const [status, setStatus] = useState(filters.status || '');

    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editingUser, setEditingUser] = useState(null);
    const [deletingUser, setDeletingUser] = useState(null);
    const [assigningRolesUser, setAssigningRolesUser] = useState(null);

    const createForm = useForm({
        officeId: '',
        unitId: '',
        employeeNo: '',
        firstName: '',
        middleName: '',
        lastName: '',
        username: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'staff',
        contactNo: '',
        status: 'active',
        roleIds: [],
    });

    const editForm = useForm({
        officeId: '',
        unitId: '',
        employeeNo: '',
        firstName: '',
        middleName: '',
        lastName: '',
        username: '',
        email: '',
        role: 'staff',
        contactNo: '',
        status: 'active',
        roleIds: [],
    });

    const assignRolesForm = useForm({
        roleIds: [],
    });

    const columns = useMemo(() => [
        { key: 'name', label: 'Name', render: (row) => `${row.firstName} ${row.middleName ? row.middleName[0] + '. ' : ''}${row.lastName}` },
        { key: 'username', label: 'Username', className: 'font-mono text-sm' },
        { key: 'office', label: 'Office', render: (row) => row.office?.officeName || '—' },
        { key: 'role', label: 'Role', render: (row) => (
            <Badge tone={roleToneMap[row.role] || 'neutral'} className="role-badge">
                {row.role?.replace(/([A-Z])/g, ' $1') || 'Staff'}
            </Badge>
        )},
        { key: 'status', label: 'Status', render: (row) => (
            <Badge tone={statusToneMap[row.status] || 'neutral'}>
                {row.status?.charAt(0).toUpperCase() + row.status?.slice(1)}
            </Badge>
        )},
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (officeId) params.set('officeId', officeId);
        if (status) params.set('status', status);
        window.location.href = `${window.location.pathname}?${params.toString()}`;
    };

    const openCreateModal = () => {
        createForm.reset();
        setShowCreateModal(true);
    };

    const closeCreateModal = () => {
        createForm.reset();
        setShowCreateModal(false);
    };

    const handleCreate = (e) => {
        e.preventDefault();
        createForm.post(route('admin.users.store'), {
            onSuccess: () => closeCreateModal(),
        });
    };

    const openEditModal = (user) => {
        editForm.reset({
            officeId: user.officeId || '',
            unitId: user.unitId || '',
            employeeNo: user.employeeNo || '',
            firstName: user.firstName || '',
            middleName: user.middleName || '',
            lastName: user.lastName || '',
            username: user.username || '',
            email: user.email || '',
            role: user.role || 'staff',
            contactNo: user.contactNo || '',
            status: user.status || 'active',
            roleIds: user.roles?.map(r => r.id) || [],
        });
        setEditingUser(user);
    };

    const closeEditModal = () => {
        editForm.reset();
        setEditingUser(null);
    };

    const handleEdit = (e) => {
        e.preventDefault();
        if (editingUser) {
            editForm.patch(route('admin.users.update', { user: editingUser.userId }), {
                onSuccess: () => closeEditModal(),
            });
        }
    };

    const confirmDelete = (user) => {
        setDeletingUser(user);
    };

    const handleDelete = () => {
        if (deletingUser) {
            router.delete(route('admin.users.destroy', { user: deletingUser.userId }), {
                onSuccess: () => setDeletingUser(null),
            });
        }
    };

    const openAssignRolesModal = (user) => {
        assignRolesForm.reset({
            roleIds: user.roles?.map(r => r.id) || [],
        });
        setAssigningRolesUser(user);
    };

    const closeAssignRolesModal = () => {
        assignRolesForm.reset();
        setAssigningRolesUser(null);
    };

    const handleAssignRoles = (e) => {
        e.preventDefault();
        if (assigningRolesUser) {
            assignRolesForm.post(route('admin.users.roles.assign', { user: assigningRolesUser.userId }), {
                onSuccess: () => closeAssignRolesModal(),
            });
        }
    };

    const handleToggleStatus = (user) => {
        router.post(route('admin.users.status.toggle', { user: user.userId }));
    };

    const renderActions = (row) => (
        <div className="flex items-center gap-1">
            <Link
                href={route('admin.users.show', { user: row.userId })}
                className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900"
                title="View"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
            </Link>
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
                onClick={() => openAssignRolesModal(row)}
                className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900"
                title="Assign Roles"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
            </button>
            <button
                onClick={() => handleToggleStatus(row)}
                className={`btn btn-ghost btn-sm ${row.status === 'active' ? 'text-success-600 hover:text-success-900' : 'text-warning-600 hover:text-warning-900'}`}
                title={row.status === 'active' ? 'Deactivate' : 'Activate'}
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    {row.status === 'active' ? (
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    ) : (
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7" />
                    )}
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

    const officeOptions = useMemo(() => [
        { value: '', label: 'All Offices' },
        ...offices.map(o => ({ value: o.officeId, label: o.officeName })),
    ], [offices]);

    const staffRoleOptions = useMemo(() => staffRoles.map(r => ({ value: r.value, label: r.value.replace(/([A-Z])/g, ' $1') })), [staffRoles]);
    const staffStatusOptions = useMemo(() => staffStatuses.map(s => ({ value: s.value, label: s.value })), [staffStatuses]);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="User Management"
                    subtitle="Manage staff users, roles, and permissions"
                    actions={
                        <PrimaryButton onClick={openCreateModal}>
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add Staff User
                        </PrimaryButton>
                    }
                />
            }
        >
            <Head title="User Management" />

            {/* Filter Bar */}
            <FilterBar onSubmit={handleFilter}>
                <FilterBarField label="Search">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by name, username, employee no..."
                        className="form-input"
                    />
                </FilterBarField>
                <FilterBarField label="Office">
                    <Select
                        value={officeId}
                        onChange={setOfficeId}
                        options={officeOptions}
                        placeholder="All Offices"
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

            {/* Data Table */}
            <Card>
                {users?.data?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={users.data}
                            children={renderActions}
                            emptyMessage="No users found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={users} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No users found"
                        message={search || officeId || status ? 'Try adjusting your filters to find matching records.' : 'No staff users have been created yet.'}
                        actionLabel={!search && !officeId && !status ? 'Create First User' : undefined}
                        onAction={!search && !officeId && !status ? openCreateModal : undefined}
                    />
                )}
            </Card>

            {/* Create Modal */}
            <Modal show={showCreateModal} onClose={closeCreateModal} title="Create Staff User" size="lg">
                <form onSubmit={handleCreate} className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormSection label="Office" error={createForm.errors.officeId} required>
                            <Select
                                value={createForm.data.officeId}
                                onChange={(e) => createForm.setData('officeId', e.target.value)}
                                options={officeOptions.filter(o => o.value)}
                                placeholder="Select Office"
                                className="form-input"
                                required
                            />
                        </FormSection>
                        <FormSection label="Unit" error={createForm.errors.unitId}>
                            <Select
                                value={createForm.data.unitId}
                                onChange={(e) => createForm.setData('unitId', e.target.value)}
                                options={[{ value: '', label: 'None' }, ...units.map(u => ({ value: u.unitId, label: u.unitName }))]}
                                placeholder="Select Unit"
                                className="form-input"
                            />
                        </FormSection>
                        <FormSection label="Employee No." error={createForm.errors.employeeNo} required>
                            <input
                                type="text"
                                value={createForm.data.employeeNo}
                                onChange={(e) => createForm.setData('employeeNo', e.target.value)}
                                className="form-input"
                                required
                                maxLength={50}
                            />
                        </FormSection>
                        <FormSection label="Username" error={createForm.errors.username} required>
                            <input
                                type="text"
                                value={createForm.data.username}
                                onChange={(e) => createForm.setData('username', e.target.value)}
                                className="form-input"
                                required
                                maxLength={50}
                            />
                        </FormSection>
                        <FormSection label="First Name" error={createForm.errors.firstName} required>
                            <input
                                type="text"
                                value={createForm.data.firstName}
                                onChange={(e) => createForm.setData('firstName', e.target.value)}
                                className="form-input"
                                required
                                maxLength={100}
                            />
                        </FormSection>
                        <FormSection label="Middle Name" error={createForm.errors.middleName}>
                            <input
                                type="text"
                                value={createForm.data.middleName}
                                onChange={(e) => createForm.setData('middleName', e.target.value)}
                                className="form-input"
                                maxLength={100}
                            />
                        </FormSection>
                        <FormSection label="Last Name" error={createForm.errors.lastName} required>
                            <input
                                type="text"
                                value={createForm.data.lastName}
                                onChange={(e) => createForm.setData('lastName', e.target.value)}
                                className="form-input"
                                required
                                maxLength={100}
                            />
                        </FormSection>
                        <FormSection label="Email" error={createForm.errors.email} required>
                            <input
                                type="email"
                                value={createForm.data.email}
                                onChange={(e) => createForm.setData('email', e.target.value)}
                                className="form-input"
                                required
                                maxLength={255}
                            />
                        </FormSection>
                        <FormSection label="Contact No." error={createForm.errors.contactNo}>
                            <input
                                type="text"
                                value={createForm.data.contactNo}
                                onChange={(e) => createForm.setData('contactNo', e.target.value)}
                                className="form-input"
                                maxLength={20}
                            />
                        </FormSection>
                        <FormSection label="Role" error={createForm.errors.role} required>
                            <Select
                                value={createForm.data.role}
                                onChange={(e) => createForm.setData('role', e.target.value)}
                                options={staffRoleOptions}
                                placeholder="Select Role"
                                className="form-input"
                                required
                            />
                        </FormSection>
                        <FormSection label="Status" error={createForm.errors.status} required>
                            <Select
                                value={createForm.data.status}
                                onChange={(e) => createForm.setData('status', e.target.value)}
                                options={staffStatusOptions}
                                placeholder="Select Status"
                                className="form-input"
                                required
                            />
                        </FormSection>
                        <FormSection label="Password" error={createForm.errors.password} required>
                            <input
                                type="password"
                                value={createForm.data.password}
                                onChange={(e) => createForm.setData('password', e.target.value)}
                                className="form-input"
                                required
                                minLength={8}
                            />
                        </FormSection>
                        <FormSection label="Confirm Password" error={createForm.errors.password_confirmation} required>
                            <input
                                type="password"
                                value={createForm.data.password_confirmation}
                                onChange={(e) => createForm.setData('password_confirmation', e.target.value)}
                                className="form-input"
                                required
                            />
                        </FormSection>
                    </div>
                    <div className="flex justify-end gap-3 pt-4 border-t border-brand-100">
                        <button type="button" onClick={closeCreateModal} className="btn btn-secondary" disabled={createForm.processing}>
                            Cancel
                        </button>
                        <PrimaryButton type="submit" disabled={createForm.processing}>
                            {createForm.processing ? 'Creating...' : 'Create User'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Edit Modal */}
            <Modal show={!!editingUser} onClose={closeEditModal} title="Edit Staff User" size="lg">
                <form onSubmit={handleEdit} className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormSection label="Office" error={editForm.errors.officeId} required>
                            <Select
                                value={editForm.data.officeId}
                                onChange={(e) => editForm.setData('officeId', e.target.value)}
                                options={officeOptions.filter(o => o.value)}
                                placeholder="Select Office"
                                className="form-input"
                                required
                            />
                        </FormSection>
                        <FormSection label="Unit" error={editForm.errors.unitId}>
                            <Select
                                value={editForm.data.unitId}
                                onChange={(e) => editForm.setData('unitId', e.target.value)}
                                options={[{ value: '', label: 'None' }, ...units.map(u => ({ value: u.unitId, label: u.unitName }))]}
                                placeholder="Select Unit"
                                className="form-input"
                            />
                        </FormSection>
                        <FormSection label="Employee No." error={editForm.errors.employeeNo} required>
                            <input
                                type="text"
                                value={editForm.data.employeeNo}
                                onChange={(e) => editForm.setData('employeeNo', e.target.value)}
                                className="form-input"
                                required
                                maxLength={50}
                            />
                        </FormSection>
                        <FormSection label="Username" error={editForm.errors.username} required>
                            <input
                                type="text"
                                value={editForm.data.username}
                                onChange={(e) => editForm.setData('username', e.target.value)}
                                className="form-input"
                                required
                                maxLength={50}
                            />
                        </FormSection>
                        <FormSection label="First Name" error={editForm.errors.firstName} required>
                            <input
                                type="text"
                                value={editForm.data.firstName}
                                onChange={(e) => editForm.setData('firstName', e.target.value)}
                                className="form-input"
                                required
                                maxLength={100}
                            />
                        </FormSection>
                        <FormSection label="Middle Name" error={editForm.errors.middleName}>
                            <input
                                type="text"
                                value={editForm.data.middleName}
                                onChange={(e) => editForm.setData('middleName', e.target.value)}
                                className="form-input"
                                maxLength={100}
                            />
                        </FormSection>
                        <FormSection label="Last Name" error={editForm.errors.lastName} required>
                            <input
                                type="text"
                                value={editForm.data.lastName}
                                onChange={(e) => editForm.setData('lastName', e.target.value)}
                                className="form-input"
                                required
                                maxLength={100}
                            />
                        </FormSection>
                        <FormSection label="Email" error={editForm.errors.email} required>
                            <input
                                type="email"
                                value={editForm.data.email}
                                onChange={(e) => editForm.setData('email', e.target.value)}
                                className="form-input"
                                required
                                maxLength={255}
                            />
                        </FormSection>
                        <FormSection label="Contact No." error={editForm.errors.contactNo}>
                            <input
                                type="text"
                                value={editForm.data.contactNo}
                                onChange={(e) => editForm.setData('contactNo', e.target.value)}
                                className="form-input"
                                maxLength={20}
                            />
                        </FormSection>
                        <FormSection label="Role" error={editForm.errors.role} required>
                            <Select
                                value={editForm.data.role}
                                onChange={(e) => editForm.setData('role', e.target.value)}
                                options={staffRoleOptions}
                                placeholder="Select Role"
                                className="form-input"
                                required
                            />
                        </FormSection>
                        <FormSection label="Status" error={editForm.errors.status} required>
                            <Select
                                value={editForm.data.status}
                                onChange={(e) => editForm.setData('status', e.target.value)}
                                options={staffStatusOptions}
                                placeholder="Select Status"
                                className="form-input"
                                required
                            />
                        </FormSection>
                        <FormSection label="Roles (Spatie)" error={editForm.errors.roleIds} className="md:col-span-2">
                            <div className="space-y-2 max-h-48 overflow-y-auto border border-brand-200 rounded-btn p-3">
                                {roles.map(role => (
                                    <label key={role.id} className="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={editForm.data.roleIds?.includes(role.id)}
                                            onChange={(e) => {
                                                const ids = editForm.data.roleIds || [];
                                                if (e.target.checked) {
                                                    editForm.setData('roleIds', [...ids, role.id]);
                                                } else {
                                                    editForm.setData('roleIds', ids.filter(id => id !== role.id));
                                                }
                                            }}
                                            className="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500"
                                        />
                                        <span className="text-sm text-brand-700">{role.name}</span>
                                        {role.description && <span className="text-xs text-brand-400 ml-auto">({role.description})</span>}
                                    </label>
                                ))}
                            </div>
                        </FormSection>
                    </div>
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

            {/* Assign Roles Modal */}
            <Modal show={!!assigningRolesUser} onClose={closeAssignRolesModal} title={`Assign Roles: ${assigningRolesUser ? `${assigningRolesUser.firstName} ${assigningRolesUser.lastName}` : ''}`} size="md">
                <form onSubmit={handleAssignRoles} className="space-y-4">
                    <FormSection label="Roles" error={assignRolesForm.errors.roleIds}>
                        <div className="space-y-2 max-h-64 overflow-y-auto border border-brand-200 rounded-btn p-3">
                            {roles.map(role => (
                                <label key={role.id} className="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={assignRolesForm.data.roleIds?.includes(role.id)}
                                        onChange={(e) => {
                                            const ids = assignRolesForm.data.roleIds || [];
                                            if (e.target.checked) {
                                                assignRolesForm.setData('roleIds', [...ids, role.id]);
                                            } else {
                                                assignRolesForm.setData('roleIds', ids.filter(id => id !== role.id));
                                            }
                                        }}
                                        className="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500"
                                    />
                                    <span className="text-sm text-brand-700">{role.name}</span>
                                    {role.description && <span className="text-xs text-brand-400 ml-auto">({role.description})</span>}
                                </label>
                            ))}
                        </div>
                    </FormSection>
                    <div className="flex justify-end gap-3 pt-4 border-t border-brand-100">
                        <button type="button" onClick={closeAssignRolesModal} className="btn btn-secondary" disabled={assignRolesForm.processing}>
                            Cancel
                        </button>
                        <PrimaryButton type="submit" disabled={assignRolesForm.processing}>
                            {assignRolesForm.processing ? 'Assigning...' : 'Assign Roles'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>

            {/* Delete Confirm Dialog */}
            <ConfirmDialog
                show={!!deletingUser}
                onClose={() => setDeletingUser(null)}
                onConfirm={handleDelete}
                title="Delete Staff User"
                message={`Are you sure you want to delete ${deletingUser ? `${deletingUser.firstName} ${deletingUser.lastName}` : 'this user'}? This action cannot be undone.`}
                confirmText="Delete"
                variant="danger"
            />
        </AuthenticatedLayout>
    );
}