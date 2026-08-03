import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useForm, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Modal, ConfirmDialog, Select, EmptyState } from '@/Components/ui';

const statusOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

export default function Rooms({ rooms }) {
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [editingRoom, setEditingRoom] = useState(null);
    const [deleteConfirm, setDeleteConfirm] = useState(null);

    const form = useForm({
        roomName: '',
        capacity: 1,
        building: '',
    });

    const columns = useMemo(() => [
        { key: 'roomName', label: 'Name' },
        { key: 'building', label: 'Building', render: (row) => row.building || '—' },
        { key: 'capacity', label: 'Capacity', className: 'text-center' },
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (status) params.set('status', status);
        window.location.href = `${window.location.pathname}?${params.toString()}`;
    };

    const openCreateModal = () => {
        form.reset({ roomName: '', capacity: 1, building: '' });
        setEditingRoom(null);
        setShowModal(true);
    };

    const openEditModal = (room) => {
        form.reset({ roomName: room.roomName, capacity: room.capacity, building: room.building || '' });
        setEditingRoom(room);
        setShowModal(true);
    };

    const closeModal = () => {
        setShowModal(false);
        setEditingRoom(null);
        form.clearErrors();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingRoom) {
            form.put(route('admin.reference-data.rooms.update', editingRoom.roomId), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        } else {
            form.post(route('admin.reference-data.rooms.store'), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        }
    };

    const confirmDelete = (room) => {
        setDeleteConfirm(room);
    };

    const handleDelete = () => {
        if (deleteConfirm) {
            router.delete(route('admin.reference-data.rooms.destroy', deleteConfirm.roomId), {
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
                aria-label="Edit room"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>
            <button
                onClick={() => confirmDelete(row)}
                className="btn btn-ghost btn-sm text-danger-600 hover:text-danger-900"
                aria-label="Delete room"
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
                    title="Rooms"
                    subtitle="Manage classrooms and facilities"
                    actions={
                        <button onClick={openCreateModal} className="btn btn-primary">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            New Room
                        </button>
                    }
                />
            }
        >
            <Head title="Rooms" />

            <FilterBar onSubmit={handleFilter}>
                <FilterBarField label="Search">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by name, building..."
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
                {rooms?.data?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={rooms.data}
                            children={renderActions}
                            emptyMessage="No rooms found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={rooms} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No rooms found"
                        message={search || status ? 'Try adjusting your filters to find matching records.' : 'No rooms have been created yet.'}
                        actionLabel={!search && !status ? 'Create First Room' : undefined}
                        onAction={!search && !status ? openCreateModal : undefined}
                    />
                )}
            </Card>

            <Modal show={showModal} onClose={closeModal} title={editingRoom ? 'Edit Room' : 'Create Room'}>
                <form onSubmit={handleSubmit}>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="form-group">
                            <label className="form-label">Room Name <span className="text-danger-500">*</span></label>
                            <input
                                type="text"
                                value={form.roomName}
                                onChange={(e) => form.setData('roomName', e.target.value)}
                                className={`form-input ${form.errors.roomName ? 'form-input-error' : ''}`}
                                placeholder="e.g., Room 101, Computer Lab A"
                                required
                            />
                            {form.errors.roomName && <p className="form-error">{form.errors.roomName}</p>}
                        </div>
                        <div className="form-group">
                            <label className="form-label">Capacity <span className="text-danger-500">*</span></label>
                            <input
                                type="number"
                                min="1"
                                value={form.capacity}
                                onChange={(e) => form.setData('capacity', parseInt(e.target.value) || 1)}
                                className={`form-input ${form.errors.capacity ? 'form-input-error' : ''}`}
                                required
                            />
                            {form.errors.capacity && <p className="form-error">{form.errors.capacity}</p>}
                        </div>
                        <div className="form-group md:col-span-2">
                            <label className="form-label">Building</label>
                            <input
                                type="text"
                                value={form.building}
                                onChange={(e) => form.setData('building', e.target.value)}
                                className={`form-input ${form.errors.building ? 'form-input-error' : ''}`}
                                placeholder="e.g., Main Building, Engineering Building"
                            />
                            {form.errors.building && <p className="form-error">{form.errors.building}</p>}
                        </div>
                    </div>
                    <div className="flex justify-end gap-3 mt-6">
                        <button type="button" onClick={closeModal} className="btn btn-secondary" disabled={form.processing}>
                            Cancel
                        </button>
                        <button type="submit" className="btn btn-primary" disabled={form.processing}>
                            {form.processing ? 'Saving...' : (editingRoom ? 'Update' : 'Create')}
                        </button>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                show={!!deleteConfirm}
                onClose={() => setDeleteConfirm(null)}
                onConfirm={handleDelete}
                title="Delete Room"
                message={`Are you sure you want to delete "${deleteConfirm?.roomName}"? This action cannot be undone.`}
                confirmText="Delete"
                variant="danger"
            />
        </AuthenticatedLayout>
    );
}