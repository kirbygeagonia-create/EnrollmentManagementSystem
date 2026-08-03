import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useForm, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Modal, ConfirmDialog, Select, EmptyState } from '@/Components/ui';

export default function Majors({ majors, courses }) {
    const [search, setSearch] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [editingMajor, setEditingMajor] = useState(null);
    const [deleteConfirm, setDeleteConfirm] = useState(null);

    const form = useForm({
        courseId: '',
        majorName: '',
    });

    const columns = useMemo(() => [
        { key: 'majorId', label: 'ID', className: 'font-mono text-sm hidden md:table-cell' },
        { key: 'majorName', label: 'Title' },
        { key: 'course', label: 'Course', render: (row) => row.course?.courseName || '—' },
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        window.location.href = `${window.location.pathname}?${params.toString()}`;
    };

    const openCreateModal = () => {
        form.reset();
        setEditingMajor(null);
        setShowModal(true);
    };

    const openEditModal = (major) => {
        form.reset({
            courseId: major.courseId,
            majorName: major.majorName,
        });
        setEditingMajor(major);
        setShowModal(true);
    };

    const closeModal = () => {
        setShowModal(false);
        setEditingMajor(null);
        form.clearErrors();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingMajor) {
            form.put(route('admin.reference-data.majors.update', editingMajor.majorId), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        } else {
            form.post(route('admin.reference-data.majors.store'), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        }
    };

    const confirmDelete = (major) => {
        setDeleteConfirm(major);
    };

    const handleDelete = () => {
        if (deleteConfirm) {
            router.delete(route('admin.reference-data.majors.destroy', deleteConfirm.majorId), {
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
                aria-label="Edit major"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>
            <button
                onClick={() => confirmDelete(row)}
                className="btn btn-ghost btn-sm text-danger-600 hover:text-danger-900"
                aria-label="Delete major"
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
                    title="Majors"
                    subtitle="Manage specializations within courses"
                    actions={
                        <button onClick={openCreateModal} className="btn btn-primary">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            New Major
                        </button>
                    }
                />
            }
        >
            <Head title="Majors" />

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
                {majors?.data?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={majors.data}
                            children={renderActions}
                            emptyMessage="No majors found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={majors} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No majors found"
                        message={search ? 'Try adjusting your search to find matching records.' : 'No majors have been created yet.'}
                        actionLabel={!search ? 'Create First Major' : undefined}
                        onAction={!search ? openCreateModal : undefined}
                    />
                )}
            </Card>

            <Modal show={showModal} onClose={closeModal} title={editingMajor ? 'Edit Major' : 'Create Major'}>
                <form onSubmit={handleSubmit}>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="form-group md:col-span-2">
                            <label className="form-label">Course <span className="text-danger-500">*</span></label>
                            <Select
                                value={form.courseId}
                                onChange={(e) => form.setData('courseId', e.target.value)}
                                options={courses.map(c => ({ value: c.courseId, label: c.courseName }))}
                                placeholder="Select course"
                                className="form-input"
                                error={form.errors.courseId}
                                required
                            />
                            {form.errors.courseId && <p className="form-error">{form.errors.courseId}</p>}
                        </div>
                        <div className="form-group md:col-span-2">
                            <label className="form-label">Major Name <span className="text-danger-500">*</span></label>
                            <input
                                type="text"
                                value={form.majorName}
                                onChange={(e) => form.setData('majorName', e.target.value)}
                                className={`form-input ${form.errors.majorName ? 'form-input-error' : ''}`}
                                placeholder="e.g., Software Engineering"
                                required
                            />
                            {form.errors.majorName && <p className="form-error">{form.errors.majorName}</p>}
                        </div>
                    </div>
                    <div className="flex justify-end gap-3 mt-6">
                        <button type="button" onClick={closeModal} className="btn btn-secondary" disabled={form.processing}>
                            Cancel
                        </button>
                        <button type="submit" className="btn btn-primary" disabled={form.processing}>
                            {form.processing ? 'Saving...' : (editingMajor ? 'Update' : 'Create')}
                        </button>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                show={!!deleteConfirm}
                onClose={() => setDeleteConfirm(null)}
                onConfirm={handleDelete}
                title="Delete Major"
                message={`Are you sure you want to delete "${deleteConfirm?.majorName}"? This action cannot be undone.`}
                confirmText="Delete"
                variant="danger"
            />
        </AuthenticatedLayout>
    );
}