import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useForm, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Modal, ConfirmDialog, Select, EmptyState, FormSection } from '@/Components/ui';

export default function Curriculums({ curriculums, courses, majors }) {
    const [search, setSearch] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [editingCurriculum, setEditingCurriculum] = useState(null);
    const [deleteConfirm, setDeleteConfirm] = useState(null);

    const form = useForm({
        courseId: '',
        majorId: '',
        effectiveYear: '',
        curriculumName: '',
    });

    const columns = useMemo(() => [
        { key: 'curriculumId', label: 'ID', className: 'font-mono text-sm hidden md:table-cell' },
        { key: 'curriculumName', label: 'Title' },
        { key: 'course', label: 'Course', render: (row) => row.course?.courseName || '—' },
        { key: 'major', label: 'Major', render: (row) => row.major?.majorName || '—' },
        { key: 'effectiveYear', label: 'Effective Year', render: (row) => row.effectiveYear ? new Date(row.effectiveYear).getFullYear() : '—' },
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        window.location.href = `${window.location.pathname}?${params.toString()}`;
    };

    const openCreateModal = () => {
        form.reset();
        setEditingCurriculum(null);
        setShowModal(true);
    };

    const openEditModal = (curriculum) => {
        form.reset({
            courseId: curriculum.courseId,
            majorId: curriculum.majorId || '',
            effectiveYear: curriculum.effectiveYear ? curriculum.effectiveYear.split('T')[0] : '',
            curriculumName: curriculum.curriculumName,
        });
        setEditingCurriculum(curriculum);
        setShowModal(true);
    };

    const closeModal = () => {
        setShowModal(false);
        setEditingCurriculum(null);
        form.clearErrors();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingCurriculum) {
            form.put(route('admin.reference-data.curriculums.update', editingCurriculum.curriculumId), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        } else {
            form.post(route('admin.reference-data.curriculums.store'), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        }
    };

    const confirmDelete = (curriculum) => {
        setDeleteConfirm(curriculum);
    };

    const handleDelete = () => {
        if (deleteConfirm) {
            router.delete(route('admin.reference-data.curriculums.destroy', deleteConfirm.curriculumId), {
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
                aria-label="Edit curriculum"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>
            <button
                onClick={() => confirmDelete(row)}
                className="btn btn-ghost btn-sm text-danger-600 hover:text-danger-900"
                aria-label="Delete curriculum"
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
                    title="Curriculums"
                    subtitle="Manage course curriculum structures"
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Logo"
                    actions={
                        <button onClick={openCreateModal} className="btn btn-primary">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            New Curriculum
                        </button>
                    }
                />
            }
        >
            <Head title="Curriculums" />

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
                {curriculums?.data?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={curriculums.data}
                            children={renderActions}
                            emptyMessage="No curriculums found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={curriculums} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No curriculums found"
                        message={search ? 'Try adjusting your search to find matching records.' : 'No curriculums have been created yet.'}
                        actionLabel={!search ? 'Create First Curriculum' : undefined}
                        onAction={!search ? openCreateModal : undefined}
                    />
                )}
            </Card>

            <Modal
                show={showModal}
                onClose={closeModal}
                title={editingCurriculum ? 'Edit Curriculum' : 'Create Curriculum'}
                subtitle="Link a course and major, then name the curriculum version."
                icon={
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                }
                size="lg"
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={closeModal} className="btn btn-secondary" disabled={form.processing}>
                            Cancel
                        </button>
                        <button type="submit" form="curriculum-form" className="btn btn-primary" disabled={form.processing}>
                            {form.processing ? 'Saving...' : (editingCurriculum ? 'Update' : 'Create')}
                        </button>
                    </div>
                }
            >
                <form id="curriculum-form" onSubmit={handleSubmit}>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormSection label="Course" error={form.errors.courseId} required>
                            <Select
                                value={form.data.courseId}
                                onChange={(e) => form.setData('courseId', e.target.value)}
                                options={courses.map(c => ({ value: c.courseId, label: c.courseName }))}
                                placeholder="Select course"
                                className="form-input"
                                error={form.errors.courseId}
                                required
                            />
                        </FormSection>
                        <FormSection label="Major" error={form.errors.majorId}>
                            <Select
                                value={form.data.majorId}
                                onChange={(e) => form.setData('majorId', e.target.value)}
                                options={majors.map(m => ({ value: m.majorId, label: m.majorName }))}
                                placeholder="Select major (optional)"
                                className="form-input"
                                error={form.errors.majorId}
                            />
                        </FormSection>
                        <FormSection label="Effective Year" error={form.errors.effectiveYear} required>
                            <input
                                type="date"
                                value={form.data.effectiveYear}
                                onChange={(e) => form.setData('effectiveYear', e.target.value)}
                                className={`form-input ${form.errors.effectiveYear ? 'form-input-error' : ''}`}
                                required
                            />
                        </FormSection>
                        <FormSection label="Curriculum Name" error={form.errors.curriculumName} required>
                            <input
                                type="text"
                                value={form.data.curriculumName}
                                onChange={(e) => form.setData('curriculumName', e.target.value)}
                                className={`form-input ${form.errors.curriculumName ? 'form-input-error' : ''}`}
                                placeholder="e.g., BSIT Curriculum 2024"
                                required
                            />
                        </FormSection>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                show={!!deleteConfirm}
                onClose={() => setDeleteConfirm(null)}
                onConfirm={handleDelete}
                title="Delete Curriculum"
                message={`Are you sure you want to delete "${deleteConfirm?.curriculumName}"? This action cannot be undone.`}
                confirmText="Delete"
                variant="danger"
            />
        </AuthenticatedLayout>
    );
}