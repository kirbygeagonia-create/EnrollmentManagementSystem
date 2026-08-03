import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { useForm, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Badge, Modal, ConfirmDialog, Select, EmptyState } from '@/Components/ui';

export default function CurriculumSubjects({ curriculum, subjects, allSubjects, semesters }) {
    const [showModal, setShowModal] = useState(false);
    const [editingSubject, setEditingSubject] = useState(null);
    const [deleteConfirm, setDeleteConfirm] = useState(null);

    const form = useForm({
        subjectId: '',
        prerequisiteSubjectId: '',
        yearLevel: 1,
        semesterOffered: '1st',
    });

    const semesterOptions = semesters.map(s => ({ value: s.value, label: s.value }));

    const columns = useMemo(() => [
        { key: 'subject', label: 'Subject Code', render: (row) => row.subject?.subjectCode || '—', className: 'font-mono text-sm' },
        { key: 'subject', label: 'Subject Title', render: (row) => row.subject?.subjectName || '—' },
        { key: 'yearLevel', label: 'Year Level', className: 'text-center' },
        { key: 'semesterOffered', label: 'Semester', render: (row) => (
            <Badge tone="info">{row.semesterOffered}</Badge>
        ), className: 'text-center' },
        { key: 'subject', label: 'Units', render: (row) => {
            const lec = row.subject?.lectureUnits || 0;
            const lab = row.subject?.labUnits || 0;
            return `${lec} lec / ${lab} lab`;
        }, className: 'text-center' },
        { key: 'prerequisiteSubject', label: 'Prerequisite', render: (row) => row.prerequisiteSubject?.subjectCode || '—', className: 'font-mono text-sm' },
    ], []);

    const openCreateModal = () => {
        form.reset({
            subjectId: '',
            prerequisiteSubjectId: '',
            yearLevel: 1,
            semesterOffered: '1st',
        });
        setEditingSubject(null);
        setShowModal(true);
    };

    const openEditModal = (cs) => {
        form.reset({
            subjectId: cs.subjectId,
            prerequisiteSubjectId: cs.prerequisiteSubjectId || '',
            yearLevel: cs.yearLevel,
            semesterOffered: cs.semesterOffered,
        });
        setEditingSubject(cs);
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
            form.put(route('admin.reference-data.curriculum-subjects.update', editingSubject.curriculumSubjectId), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        } else {
            form.post(route('admin.reference-data.curriculum-subjects.store', curriculum.curriculumId), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        }
    };

    const confirmDelete = (cs) => {
        setDeleteConfirm(cs);
    };

    const handleDelete = () => {
        if (deleteConfirm) {
            router.delete(route('admin.reference-data.curriculum-subjects.destroy', deleteConfirm.curriculumSubjectId), {
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
                aria-label="Edit curriculum subject"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>
            <button
                onClick={() => confirmDelete(row)}
                className="btn btn-ghost btn-sm text-danger-600 hover:text-danger-900"
                aria-label="Delete curriculum subject"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </button>
        </div>
    );

    const curriculumTitle = `${curriculum.course?.courseCode || ''} ${curriculum.major ? `- ${curriculum.major.majorName}` : ''} - ${curriculum.curriculumName}`;

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Curriculum Subjects"
                    subtitle={curriculumTitle}
                    actions={
                        <>
                            <Link href={route('admin.reference-data.curriculums')} className="btn btn-secondary">
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Back to Curriculums
                            </Link>
                            <button onClick={openCreateModal} className="btn btn-primary ml-2">
                                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Add Subject
                            </button>
                        </>
                    }
                />
            }
        >
            <Head title={`Curriculum Subjects - ${curriculumTitle}`} />

            <Card>
                {subjects?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={subjects}
                            children={renderActions}
                            emptyMessage="No subjects in this curriculum"
                        />
                    </>
                ) : (
                    <EmptyState
                        title="No subjects in this curriculum"
                        message="Add subjects to build the curriculum structure."
                        actionLabel="Add First Subject"
                        onAction={openCreateModal}
                    />
                )}
            </Card>

            <Modal show={showModal} onClose={closeModal} title={editingSubject ? 'Edit Curriculum Subject' : 'Add Subject to Curriculum'}>
                <form onSubmit={handleSubmit}>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="form-group md:col-span-2">
                            <label className="form-label">Subject <span className="text-danger-500">*</span></label>
                            <Select
                                value={form.subjectId}
                                onChange={(e) => form.setData('subjectId', e.target.value)}
                                options={allSubjects.map(s => ({ value: s.subjectId, label: `${s.subjectCode} - ${s.subjectName}` }))}
                                placeholder="Select subject"
                                className="form-input"
                                error={form.errors.subjectId}
                                required
                            />
                            {form.errors.subjectId && <p className="form-error">{form.errors.subjectId}</p>}
                        </div>
                        <div className="form-group md:col-span-2">
                            <label className="form-label">Prerequisite Subject</label>
                            <Select
                                value={form.prerequisiteSubjectId}
                                onChange={(e) => form.setData('prerequisiteSubjectId', e.target.value)}
                                options={allSubjects.map(s => ({ value: s.subjectId, label: `${s.subjectCode} - ${s.subjectName}` }))}
                                placeholder="Select prerequisite (optional)"
                                className="form-input"
                                error={form.errors.prerequisiteSubjectId}
                            />
                            {form.errors.prerequisiteSubjectId && <p className="form-error">{form.errors.prerequisiteSubjectId}</p>}
                        </div>
                        <div className="form-group">
                            <label className="form-label">Year Level <span className="text-danger-500">*</span></label>
                            <Select
                                value={form.yearLevel}
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
                            {form.errors.yearLevel && <p className="form-error">{form.errors.yearLevel}</p>}
                        </div>
                        <div className="form-group">
                            <label className="form-label">Semester Offered <span className="text-danger-500">*</span></label>
                            <Select
                                value={form.semesterOffered}
                                onChange={(e) => form.setData('semesterOffered', e.target.value)}
                                options={semesterOptions}
                                placeholder="Select semester"
                                className="form-input"
                                error={form.errors.semesterOffered}
                                required
                            />
                            {form.errors.semesterOffered && <p className="form-error">{form.errors.semesterOffered}</p>}
                        </div>
                    </div>
                    <div className="flex justify-end gap-3 mt-6">
                        <button type="button" onClick={closeModal} className="btn btn-secondary" disabled={form.processing}>
                            Cancel
                        </button>
                        <button type="submit" className="btn btn-primary" disabled={form.processing}>
                            {form.processing ? 'Saving...' : (editingSubject ? 'Update' : 'Add')}
                        </button>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                show={!!deleteConfirm}
                onClose={() => setDeleteConfirm(null)}
                onConfirm={handleDelete}
                title="Delete Curriculum Subject"
                message={`Are you sure you want to remove "${deleteConfirm?.subject?.subjectName}" from this curriculum? This action cannot be undone.`}
                confirmText="Delete"
                variant="danger"
            />
        </AuthenticatedLayout>
    );
}