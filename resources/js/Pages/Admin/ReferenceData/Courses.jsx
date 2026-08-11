import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useForm, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, Modal, ConfirmDialog, Select, EmptyState, FormSection } from '@/Components/ui';

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
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Logo"
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

            <Modal
                show={showModal}
                onClose={closeModal}
                title={editingCourse ? 'Edit Course' : 'Create Course'}
                subtitle="Set the course code, name, unit, and exam requirements."
                icon={
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                }
                size="lg"
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={closeModal} className="btn btn-secondary" disabled={form.processing}>
                            Cancel
                        </button>
                        <button type="submit" form="course-form" className="btn btn-primary" disabled={form.processing}>
                            {form.processing ? 'Saving...' : (editingCourse ? 'Update' : 'Create')}
                        </button>
                    </div>
                }
            >
                <form id="course-form" onSubmit={handleSubmit}>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormSection label="Unit" error={form.errors.unitId} required>
                            <Select
                                value={form.data.unitId}
                                onChange={(e) => form.setData('unitId', e.target.value)}
                                options={units.map(u => ({ value: u.unitId, label: u.unitName }))}
                                placeholder="Select unit"
                                className="form-input"
                                error={form.errors.unitId}
                                required
                            />
                        </FormSection>
                        <FormSection label="Course Code" error={form.errors.courseCode} required>
                            <input
                                type="text"
                                value={form.data.courseCode}
                                onChange={(e) => form.setData('courseCode', e.target.value)}
                                className={`form-input ${form.errors.courseCode ? 'form-input-error' : ''}`}
                                placeholder="e.g., BSIT"
                                required
                            />
                        </FormSection>
                        <FormSection label="Course Name" error={form.errors.courseName} required>
                            <input
                                type="text"
                                value={form.data.courseName}
                                onChange={(e) => form.setData('courseName', e.target.value)}
                                className={`form-input ${form.errors.courseName ? 'form-input-error' : ''}`}
                                placeholder="e.g., Bachelor of Science in Information Technology"
                                required
                            />
                        </FormSection>
                        <div className="md:col-span-2 space-y-3 pt-2">
                            <label className="flex items-center gap-3 cursor-pointer p-3 rounded-btn border border-brand-200 hover:bg-brand-50/50 transition-colors">
                                <input
                                    type="checkbox"
                                    checked={form.data.requiresEntranceExam}
                                    onChange={(e) => form.setData('requiresEntranceExam', e.target.checked)}
                                    className="form-checkbox"
                                />
                                <div>
                                    <span className="text-sm font-medium text-brand-800">Requires Entrance Exam</span>
                                    <p className="text-xs text-brand-500">Applicants must pass an entrance exam before admission.</p>
                                </div>
                            </label>
                            <label className="flex items-center gap-3 cursor-pointer p-3 rounded-btn border border-brand-200 hover:bg-brand-50/50 transition-colors">
                                <input
                                    type="checkbox"
                                    checked={form.data.requiresRetentionExam}
                                    onChange={(e) => form.setData('requiresRetentionExam', e.target.checked)}
                                    className="form-checkbox"
                                />
                                <div>
                                    <span className="text-sm font-medium text-brand-800">Requires Retention Exam</span>
                                    <p className="text-xs text-brand-500">Continuing students must pass a retention exam to stay enrolled.</p>
                                </div>
                            </label>
                        </div>
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