import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useForm, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, Modal, ConfirmDialog, Select, EmptyState, FormSection } from '@/Components/ui';

const semesterOptions = [
    { value: '', label: 'All Semesters' },
    { value: '1st', label: '1st Semester' },
    { value: '2nd', label: '2nd Semester' },
    { value: 'Summer', label: 'Summer' },
];

const statusOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

export default function Terms({ terms, years, semesters }) {
    const [search, setSearch] = useState('');
    const [semester, setSemester] = useState('');
    const [status, setStatus] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [editingTerm, setEditingTerm] = useState(null);
    const [deleteConfirm, setDeleteConfirm] = useState(null);

    const form = useForm({
        academicYearId: '',
        semester: '1st',
        startDate: '',
        endDate: '',
    });

    const columns = useMemo(() => [
        { key: 'academicYear', label: 'School Year', render: (row) => row.academicYear?.yearLabel || '—' },
        { key: 'semester', label: 'Semester', render: (row) => (
            <Badge tone="info">{row.semester}</Badge>
        ), className: 'text-center' },
        { key: 'startDate', label: 'Start Date', render: (row) => row.startDate ? new Date(row.startDate).toLocaleDateString('en-PH') : '—', className: 'text-center' },
        { key: 'endDate', label: 'End Date', render: (row) => row.endDate ? new Date(row.endDate).toLocaleDateString('en-PH') : '—', className: 'text-center' },
        { key: 'status', label: 'Status', render: (row) => {
            const now = new Date();
            const start = row.startDate ? new Date(row.startDate) : null;
            const end = row.endDate ? new Date(row.endDate) : null;
            const isActive = start && end && now >= start && now <= end;
            return <Badge tone={isActive ? 'success' : 'neutral'}>{isActive ? 'Active' : 'Inactive'}</Badge>;
        }, className: 'text-center' },
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (semester) params.set('semester', semester);
        if (status) params.set('status', status);
        window.location.href = `${window.location.pathname}?${params.toString()}`;
    };

    const openCreateModal = () => {
        form.reset({
            academicYearId: '',
            semester: '1st',
            startDate: '',
            endDate: '',
        });
        setEditingTerm(null);
        setShowModal(true);
    };

    const openEditModal = (term) => {
        form.reset({
            academicYearId: term.academicYearId,
            semester: term.semester,
            startDate: term.startDate ? term.startDate.split('T')[0] : '',
            endDate: term.endDate ? term.endDate.split('T')[0] : '',
        });
        setEditingTerm(term);
        setShowModal(true);
    };

    const closeModal = () => {
        setShowModal(false);
        setEditingTerm(null);
        form.clearErrors();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingTerm) {
            form.put(route('admin.reference-data.terms.update', editingTerm.termId), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        } else {
            form.post(route('admin.reference-data.terms.store'), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        }
    };

    const confirmDelete = (term) => {
        setDeleteConfirm(term);
    };

    const handleDelete = () => {
        if (deleteConfirm) {
            router.delete(route('admin.reference-data.terms.destroy', deleteConfirm.termId), {
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
                aria-label="Edit term"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>
            <button
                onClick={() => confirmDelete(row)}
                className="btn btn-ghost btn-sm text-danger-600 hover:text-danger-900"
                aria-label="Delete term"
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
                    title="Academic Terms"
                    subtitle="Manage semesters and school years"
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Logo"
                    actions={
                        <button onClick={openCreateModal} className="btn btn-primary">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            New Term
                        </button>
                    }
                />
            }
        >
            <Head title="Academic Terms" />

            <FilterBar onSubmit={handleFilter}>
                <FilterBarField label="Search">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by school year..."
                        className="form-input"
                    />
                </FilterBarField>
                <FilterBarField label="Semester">
                    <Select
                        value={semester}
                        onChange={setSemester}
                        options={semesterOptions}
                        placeholder="All Semesters"
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
                {terms?.data?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={terms.data}
                            children={renderActions}
                            emptyMessage="No terms found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={terms} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No terms found"
                        message={search || semester || status ? 'Try adjusting your filters to find matching records.' : 'No academic terms have been created yet.'}
                        actionLabel={!search && !semester && !status ? 'Create First Term' : undefined}
                        onAction={!search && !semester && !status ? openCreateModal : undefined}
                    />
                )}
            </Card>

            <Modal
                show={showModal}
                onClose={closeModal}
                title={editingTerm ? 'Edit Term' : 'Create Term'}
                subtitle="Define the school year, semester, and active date range."
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
                        <button type="submit" form="term-form" className="btn btn-primary" disabled={form.processing}>
                            {form.processing ? 'Saving...' : (editingTerm ? 'Update' : 'Create')}
                        </button>
                    </div>
                }
            >
                <form id="term-form" onSubmit={handleSubmit}>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormSection label="School Year" error={form.errors.academicYearId} required>
                            <Select
                                value={form.data.academicYearId}
                                onChange={(e) => form.setData('academicYearId', e.target.value)}
                                options={years.map(y => ({ value: y.academicYearId, label: y.yearLabel }))}
                                placeholder="Select school year"
                                className="form-input"
                                error={form.errors.academicYearId}
                                required
                            />
                        </FormSection>
                        <FormSection label="Semester" error={form.errors.semester} required>
                            <Select
                                value={form.data.semester}
                                onChange={(e) => form.setData('semester', e.target.value)}
                                options={semesters.map(s => ({ value: s.value, label: s.value.charAt(0).toUpperCase() + s.value.slice(1) + ' Semester' }))}
                                placeholder="Select semester"
                                className="form-input"
                                error={form.errors.semester}
                                required
                            />
                        </FormSection>
                        <FormSection label="Start Date" error={form.errors.startDate} required>
                            <input
                                type="date"
                                value={form.data.startDate}
                                onChange={(e) => form.setData('startDate', e.target.value)}
                                className={`form-input ${form.errors.startDate ? 'form-input-error' : ''}`}
                                required
                            />
                        </FormSection>
                        <FormSection label="End Date" error={form.errors.endDate} required>
                            <input
                                type="date"
                                value={form.data.endDate}
                                onChange={(e) => form.setData('endDate', e.target.value)}
                                className={`form-input ${form.errors.endDate ? 'form-input-error' : ''}`}
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
                title="Delete Term"
                message={`Are you sure you want to delete "${deleteConfirm?.academicYear?.yearLabel} ${deleteConfirm?.semester}"? This action cannot be undone.`}
                confirmText="Delete"
                variant="danger"
            />
        </AuthenticatedLayout>
    );
}