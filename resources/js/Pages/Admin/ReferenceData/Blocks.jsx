import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useForm, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Modal, ConfirmDialog, Select, EmptyState, FormSection } from '@/Components/ui';

export default function Blocks({ blocks, courses, terms }) {
    const [search, setSearch] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [editingBlock, setEditingBlock] = useState(null);
    const [deleteConfirm, setDeleteConfirm] = useState(null);

    const form = useForm({
        courseId: '',
        termId: '',
        yearLevel: 1,
        blockName: '',
        maxStudents: 50,
    });

    const columns = useMemo(() => [
        { key: 'blockName', label: 'Name' },
        { key: 'course', label: 'Course', render: (row) => row.course?.courseName || '—' },
        { key: 'term', label: 'Term', render: (row) => row.term?.academicYear?.yearLabel + ' ' + row.term?.semester || '—' },
        { key: 'yearLevel', label: 'Year Level', className: 'text-center' },
        { key: 'maxStudents', label: 'Max Students', className: 'text-center' },
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('admin.blocks.index'), {
            search: search || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const openCreateModal = () => {
        form.reset({ courseId: '', termId: '', yearLevel: 1, blockName: '', maxStudents: 50 });
        setEditingBlock(null);
        setShowModal(true);
    };

    const openEditModal = (block) => {
        form.reset({
            courseId: block.courseId,
            termId: block.termId,
            yearLevel: block.yearLevel,
            blockName: block.blockName,
            maxStudents: block.maxStudents,
        });
        setEditingBlock(block);
        setShowModal(true);
    };

    const closeModal = () => {
        setShowModal(false);
        setEditingBlock(null);
        form.clearErrors();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingBlock) {
            form.put(route('admin.reference-data.blocks.update', editingBlock.blockId), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        } else {
            form.post(route('admin.reference-data.blocks.store'), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        }
    };

    const confirmDelete = (block) => {
        setDeleteConfirm(block);
    };

    const handleDelete = () => {
        if (deleteConfirm) {
            router.delete(route('admin.reference-data.blocks.destroy', deleteConfirm.blockId), {
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
                aria-label="Edit block"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>
            <button
                onClick={() => confirmDelete(row)}
                className="btn btn-ghost btn-sm text-danger-600 hover:text-danger-900"
                aria-label="Delete block"
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
                    title="Blocks"
                    subtitle="Manage student block sections"
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Logo"
                    actions={
                        <button onClick={openCreateModal} className="btn btn-primary">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            New Block
                        </button>
                    }
                />
            }
        >
            <Head title="Blocks" />

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
                {blocks?.data?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={blocks.data}
                            children={renderActions}
                            emptyMessage="No blocks found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={blocks} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No blocks found"
                        message={search ? 'Try adjusting your search to find matching records.' : 'No blocks have been created yet.'}
                        actionLabel={!search ? 'Create First Block' : undefined}
                        onAction={!search ? openCreateModal : undefined}
                    />
                )}
            </Card>

            <Modal
                show={showModal}
                onClose={closeModal}
                title={editingBlock ? 'Edit Block' : 'Create Block'}
                subtitle="Assign a course, term, year level, and max capacity."
                icon={
                    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                }
                size="lg"
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={closeModal} className="btn btn-secondary" disabled={form.processing}>
                            Cancel
                        </button>
                        <button type="submit" form="block-form" className="btn btn-primary" disabled={form.processing}>
                            {form.processing ? 'Saving...' : (editingBlock ? 'Update' : 'Create')}
                        </button>
                    </div>
                }
            >
                <form id="block-form" onSubmit={handleSubmit}>
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
                        <FormSection label="Term" error={form.errors.termId} required>
                            <Select
                                value={form.data.termId}
                                onChange={(e) => form.setData('termId', e.target.value)}
                                options={terms.map(t => ({ value: t.termId, label: `${t.academicYear?.yearLabel} ${t.semester}` }))}
                                placeholder="Select term"
                                className="form-input"
                                error={form.errors.termId}
                                required
                            />
                        </FormSection>
                        <FormSection label="Year Level" error={form.errors.yearLevel} required>
                            <Select
                                value={form.data.yearLevel}
                                onChange={(e) => form.setData('yearLevel', parseInt(e.target.value))}
                                options={[
                                    { value: 1, label: '1st Year' },
                                    { value: 2, label: '2nd Year' },
                                    { value: 3, label: '3rd Year' },
                                    { value: 4, label: '4th Year' },
                                    { value: 5, label: '5th Year' },
                                ]}
                                placeholder="Select year level"
                                className="form-input"
                                error={form.errors.yearLevel}
                                required
                            />
                        </FormSection>
                        <FormSection label="Max Students" error={form.errors.maxStudents} required>
                            <input
                                type="number"
                                min="1"
                                value={form.data.maxStudents}
                                onChange={(e) => form.setData('maxStudents', parseInt(e.target.value) || 1)}
                                className={`form-input ${form.errors.maxStudents ? 'form-input-error' : ''}`}
                                required
                            />
                        </FormSection>
                        <FormSection label="Block Name" error={form.errors.blockName} required>
                            <input
                                type="text"
                                value={form.data.blockName}
                                onChange={(e) => form.setData('blockName', e.target.value)}
                                className={`form-input ${form.errors.blockName ? 'form-input-error' : ''}`}
                                placeholder="e.g., A, B, C, Section 1"
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
                title="Delete Block"
                message={`Are you sure you want to delete "${deleteConfirm?.blockName}"? This action cannot be undone.`}
                confirmText="Delete"
                variant="danger"
            />
        </AuthenticatedLayout>
    );
}