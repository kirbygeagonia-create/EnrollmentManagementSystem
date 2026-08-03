import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useForm, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, Modal, ConfirmDialog, Select, EmptyState } from '@/Components/ui';

const statusOptions = [
    { value: '', label: 'All Statuses' },
    { value: '1', label: 'Active' },
    { value: '0', label: 'Inactive' },
];

export default function Courses({ courses, units }) {
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [editingCourse, setEditingCourse] = useState(null);
    const [deleteConfirm, setDeleteConfirm] = useState(null);

    const form = useForm({
        unitId: '',
        courseName: '',
        courseCode: '',
        requiresEntranceExam: false,
        requiresRetentionExam: false,
    });

    const columns = useMemo(() => [
        { key: 'courseCode', label: 'Code', className: 'font-mono text-sm' },
        { key: 'courseName', label: 'Title' },
        { key: 'unit', label: 'Unit', render: (row) => row.unit?.unitName || '—' },
        { key: 'requiresEntranceExam', label: 'Entrance Exam', render: (row) => (
            <Badge tone={row.requiresEntranceExam ? 'success' : 'neutral'}>
                {row.requiresEntranceExam ? 'Required' : 'Not Required'}
            </Badge>
        )},
        { key: 'requiresRetentionExam', label: 'Retention Exam', render: (row) => (
            <Badge tone={row.requiresRetentionExam ? 'success' : 'neutral'}>
                {row.requiresRetentionExam ? 'Required' : 'Not Required'}
            </Badge>
        )},
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (status) params.set('status', status);
        window.location.href = `${window.location.pathname}?${params.toString()}`;
    };

    const openCreateModal = () => {
        form.reset();
        setEditingCourse(null);
        setShowModal(true);
    };

    const openEditModal = (course) => {
        form.reset({
            unitId: course.unitId,
            courseName: course.courseName,
            courseCode: course.courseCode,
            requiresEntranceExam: course.requiresEntranceExam,
            requiresRetentionExam: course.requiresRetentionExam,
        });
        setEditingCourse(course);
        setShowModal(true);
    };

    const closeModal = () => {
        setShowModal(false);
        setEditingCourse(null);
        form.clearErrors();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingCourse) {
            form.put(route('admin.reference-data.courses.update', editingCourse.courseId), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        } else {
            form.post(route('admin.reference-data.courses.store'), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        }
    };

    const confirmDelete = (course) => {
        setDeleteConfirm(course);
    };

    const handleDelete = () => {
        if (deleteConfirm) {
            router.delete(route('admin.reference-data.courses.destroy', deleteConfirm.courseId), {
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
                aria-label="Edit course"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>
            <button
                onClick={() => confirmDelete(row)}
                className="btn btn-ghost btn-sm text-danger-600 hover:text-danger-900"
                aria-label="Delete course"
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
                    title="Courses"
                    subtitle="Manage academic programs offered"
                    actions={
                        <button onClick={openCreateModal} className="btn btn-primary">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            New Course
                        </button>
                    }
                />
            }
        >
            <Head title="Courses" />

            <FilterBar onSubmit={handleFilter}>
                <FilterBarField label="Search">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by code, name..."
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
                {courses?.data?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={courses.data}
                            children={renderActions}
                            emptyMessage="No courses found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={courses} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No courses found"
                        message={search || status ? 'Try adjusting your filters to find matching records.' : 'No courses have been created yet.'}
                        actionLabel={!search && !status ? 'Create First Course' : undefined}
                        onAction={!search && !status ? openCreateModal : undefined}
                    />
                )}
            </Card>

            <Modal show={showModal} onClose={closeModal} title={editingCourse ? 'Edit Course' : 'Create Course'}>
                <form onSubmit={handleSubmit}>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="form-group">
                            <label className="form-label">Unit <span className="text-danger-500">*</span></label>
                            <Select
                                value={form.unitId}
                                onChange={(e) => form.setData('unitId', e.target.value)}
                                options={units.map(u => ({ value: u.unitId, label: u.unitName }))}
                                placeholder="Select unit"
                                className="form-input"
                                error={form.errors.unitId}
                                required
                            />
                            {form.errors.unitId && <p className="form-error">{form.errors.unitId}</p>}
                        </div>
                        <div className="form-group">
                            <label className="form-label">Course Code <span className="text-danger-500">*</span></label>
                            <input
                                type="text"
                                value={form.courseCode}
                                onChange={(e) => form.setData('courseCode', e.target.value)}
                                className={`form-input ${form.errors.courseCode ? 'form-input-error' : ''}`}
                                placeholder="e.g., BSIT"
                                required
                            />
                            {form.errors.courseCode && <p className="form-error">{form.errors.courseCode}</p>}
                        </div>
                        <div className="form-group md:col-span-2">
                            <label className="form-label">Course Name <span className="text-danger-500">*</span></label>
                            <input
                                type="text"
                                value={form.courseName}
                                onChange={(e) => form.setData('courseName', e.target.value)}
                                className={`form-input ${form.errors.courseName ? 'form-input-error' : ''}`}
                                placeholder="e.g., Bachelor of Science in Information Technology"
                                required
                            />
                            {form.errors.courseName && <p className="form-error">{form.errors.courseName}</p>}
                        </div>
                        <div className="form-group md:col-span-2">
                            <label className="form-label flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={form.requiresEntranceExam}
                                    onChange={(e) => form.setData('requiresEntranceExam', e.target.checked)}
                                    className="form-checkbox"
                                />
                                Requires Entrance Exam
                            </label>
                        </div>
                        <div className="form-group md:col-span-2">
                            <label className="form-label flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={form.requiresRetentionExam}
                                    onChange={(e) => form.setData('requiresRetentionExam', e.target.checked)}
                                    className="form-checkbox"
                                />
                                Requires Retention Exam
                            </label>
                        </div>
                    </div>
                    <div className="flex justify-end gap-3 mt-6">
                        <button type="button" onClick={closeModal} className="btn btn-secondary" disabled={form.processing}>
                            Cancel
                        </button>
                        <button type="submit" className="btn btn-primary" disabled={form.processing}>
                            {form.processing ? 'Saving...' : (editingCourse ? 'Update' : 'Create')}
                        </button>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                show={!!deleteConfirm}
                onClose={() => setDeleteConfirm(null)}
                onConfirm={handleDelete}
                title="Delete Course"
                message={`Are you sure you want to delete "${deleteConfirm?.courseName}"? This action cannot be undone.`}
                confirmText="Delete"
                variant="danger"
            />
        </AuthenticatedLayout>
    );
}