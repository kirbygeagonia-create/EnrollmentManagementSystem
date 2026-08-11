import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useForm, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, Modal, ConfirmDialog, Select, EmptyState, FormSection } from '@/Components/ui';

const typeOptions = [
    { value: '', label: 'All Types' },
    { value: 'lecture', label: 'Lecture' },
    { value: 'lab', label: 'Laboratory' },
    { value: 'both', label: 'Lecture & Lab' },
];

const typeToneMap = {
    lecture: 'info',
    lab: 'warning',
    both: 'success',
};

export default function Subjects({ subjects, subjectTypes }) {
    const [search, setSearch] = useState('');
    const [type, setType] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [editingSubject, setEditingSubject] = useState(null);
    const [deleteConfirm, setDeleteConfirm] = useState(null);

    const form = useForm({
        subjectCode: '',
        subjectName: '',
        lectureUnits: 0,
        labUnits: 0,
        subjectType: 'lecture',
    });

    const columns = useMemo(() => [
        { key: 'subjectCode', label: 'Code', className: 'font-mono text-sm' },
        { key: 'subjectName', label: 'Title' },
        { key: 'subjectType', label: 'Type', render: (row) => (
            <Badge tone={typeToneMap[row.subjectType] || 'neutral'}>
                {row.subjectType?.charAt(0).toUpperCase() + row.subjectType?.slice(1)}
            </Badge>
        )},
        { key: 'lectureUnits', label: 'Lec Units', className: 'text-center' },
        { key: 'labUnits', label: 'Lab Units', className: 'text-center' },
        { key: 'totalUnits', label: 'Total Units', render: (row) => (parseFloat(row.lectureUnits) + parseFloat(row.labUnits)).toFixed(1), className: 'text-center font-medium' },
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (type) params.set('type', type);
        window.location.href = `${window.location.pathname}?${params.toString()}`;
    };

    const openCreateModal = () => {
        form.reset({
            subjectCode: '',
            subjectName: '',
            lectureUnits: 0,
            labUnits: 0,
            subjectType: 'lecture',
        });
        setEditingSubject(null);
        setShowModal(true);
    };

    const openEditModal = (subject) => {
        form.reset({
            subjectCode: subject.subjectCode,
            subjectName: subject.subjectName,
            lectureUnits: subject.lectureUnits,
            labUnits: subject.labUnits,
            subjectType: subject.subjectType,
        });
        setEditingSubject(subject);
        setShowModal(true);
    };

    const closeModal = () => {
        setShowModal(false);
        setEditingSubject(null);
        form.clearErrors();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingSubject) {
            form.put(route('admin.reference-data.subjects.update', editingSubject.subjectId), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        } else {
            form.post(route('admin.reference-data.subjects.store'), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        }
    };

    const confirmDelete = (subject) => {
        setDeleteConfirm(subject);
    };

    const handleDelete = () => {
        if (deleteConfirm) {
            router.delete(route('admin.reference-data.subjects.destroy', deleteConfirm.subjectId), {
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
                aria-label="Edit subject"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>
            <button
                onClick={() => confirmDelete(row)}
                className="btn btn-ghost btn-sm text-danger-600 hover:text-danger-900"
                aria-label="Delete subject"
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
                    title="Subjects"
                    subtitle="Manage individual course subjects"
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Logo"
                    actions={
                        <button onClick={openCreateModal} className="btn btn-primary">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            New Subject
                        </button>
                    }
                />
            }
        >
            <Head title="Subjects" />

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
                <FilterBarField label="Type">
                    <Select
                        value={type}
                        onChange={setType}
                        options={typeOptions}
                        placeholder="All Types"
                        className="form-input"
                    />
                </FilterBarField>
            </FilterBar>

            <Card>
                {subjects?.data?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={subjects.data}
                            children={renderActions}
                            emptyMessage="No subjects found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={subjects} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No subjects found"
                        message={search || type ? 'Try adjusting your filters to find matching records.' : 'No subjects have been created yet.'}
                        actionLabel={!search && !type ? 'Create First Subject' : undefined}
                        onAction={!search && !type ? openCreateModal : undefined}
                    />
                )}
            </Card>

            <Modal
                show={showModal}
                onClose={closeModal}
                title={editingSubject ? 'Edit Subject' : 'Create Subject'}
                subtitle="Define the subject code, type, and unit breakdown."
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
                        <button type="submit" form="subject-form" className="btn btn-primary" disabled={form.processing}>
                            {form.processing ? 'Saving...' : (editingSubject ? 'Update' : 'Create')}
                        </button>
                    </div>
                }
            >
                <form id="subject-form" onSubmit={handleSubmit}>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormSection label="Subject Code" error={form.errors.subjectCode} required>
                            <input
                                type="text"
                                value={form.data.subjectCode}
                                onChange={(e) => form.setData('subjectCode', e.target.value.toUpperCase())}
                                className={`form-input ${form.errors.subjectCode ? 'form-input-error' : ''}`}
                                placeholder="e.g., IT101"
                                required
                            />
                        </FormSection>
                        <FormSection label="Subject Type" error={form.errors.subjectType} required>
                            <Select
                                value={form.data.subjectType}
                                onChange={(e) => form.setData('subjectType', e.target.value)}
                                options={subjectTypes.map(t => ({ value: t.value, label: t.value.charAt(0).toUpperCase() + t.value.slice(1) }))}
                                placeholder="Select type"
                                className="form-input"
                                error={form.errors.subjectType}
                                required
                            />
                        </FormSection>
                        <FormSection label="Subject Name" error={form.errors.subjectName} required>
                            <input
                                type="text"
                                value={form.data.subjectName}
                                onChange={(e) => form.setData('subjectName', e.target.value)}
                                className={`form-input ${form.errors.subjectName ? 'form-input-error' : ''}`}
                                placeholder="e.g., Introduction to Programming"
                                required
                            />
                        </FormSection>
                        <FormSection label="Lecture Units" error={form.errors.lectureUnits} required>
                            <input
                                type="number"
                                step="0.5"
                                min="0"
                                value={form.data.lectureUnits}
                                onChange={(e) => form.setData('lectureUnits', parseFloat(e.target.value) || 0)}
                                className={`form-input ${form.errors.lectureUnits ? 'form-input-error' : ''}`}
                                required
                            />
                        </FormSection>
                        <FormSection label="Lab Units" error={form.errors.labUnits} required>
                            <input
                                type="number"
                                step="0.5"
                                min="0"
                                value={form.data.labUnits}
                                onChange={(e) => form.setData('labUnits', parseFloat(e.target.value) || 0)}
                                className={`form-input ${form.errors.labUnits ? 'form-input-error' : ''}`}
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
                title="Delete Subject"
                message={`Are you sure you want to delete "${deleteConfirm?.subjectName}"? This action cannot be undone.`}
                confirmText="Delete"
                variant="danger"
            />
        </AuthenticatedLayout>
    );
}