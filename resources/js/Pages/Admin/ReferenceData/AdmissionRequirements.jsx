import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useForm, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, Modal, ConfirmDialog, Select, EmptyState, FormSection } from '@/Components/ui';

const appliesToOptions = [
    { value: '', label: 'All Types' },
    { value: 'firstYear', label: 'First Year' },
    { value: 'transferee', label: 'Transferee' },
    { value: 'shifter', label: 'Shifter' },
    { value: 'continuing', label: 'Continuing' },
    { value: 'all', label: 'All' },
];

const appliesToToneMap = {
    firstYear: 'info',
    transferee: 'warning',
    shifter: 'accent',
    continuing: 'success',
    all: 'brand',
};

const statusOptions = [
    { value: '', label: 'All Statuses' },
    { value: 'required', label: 'Required' },
    { value: 'optional', label: 'Optional' },
];

export default function AdmissionRequirements({ requirements, appliesTo }) {
    const [search, setSearch] = useState('');
    const [appliesToFilter, setAppliesToFilter] = useState('');
    const [status, setStatus] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [editingRequirement, setEditingRequirement] = useState(null);
    const [deleteConfirm, setDeleteConfirm] = useState(null);

    const form = useForm({
        requirementName: '',
        appliesTo: 'firstYear',
        isRequired: true,
    });

    const columns = useMemo(() => [
        { key: 'requirementName', label: 'Name' },
        { key: 'appliesTo', label: 'Applies To', render: (row) => (
            <Badge tone={appliesToToneMap[row.appliesTo] || 'neutral'}>
                {row.appliesTo?.charAt(0).toUpperCase() + row.appliesTo?.slice(1).replace(/([A-Z])/g, ' $1')}
            </Badge>
        ), className: 'text-center' },
        { key: 'isRequired', label: 'Status', render: (row) => (
            <Badge tone={row.isRequired ? 'success' : 'neutral'}>
                {row.isRequired ? 'Required' : 'Optional'}
            </Badge>
        ), className: 'text-center' },
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('admin.admission-requirements.index'), {
            search: search || undefined,
            appliesTo: appliesToFilter || undefined,
            status: status || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const openCreateModal = () => {
        form.reset({ requirementName: '', appliesTo: 'firstYear', isRequired: true });
        setEditingRequirement(null);
        setShowModal(true);
    };

    const openEditModal = (req) => {
        form.reset({
            requirementName: req.requirementName,
            appliesTo: req.appliesTo,
            isRequired: req.isRequired,
        });
        setEditingRequirement(req);
        setShowModal(true);
    };

    const closeModal = () => {
        setShowModal(false);
        setEditingRequirement(null);
        form.clearErrors();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (editingRequirement) {
            form.put(route('admin.reference-data.admission-requirements.update', editingRequirement.requirementId), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        } else {
            form.post(route('admin.reference-data.admission-requirements.store'), {
                onSuccess: closeModal,
                preserveScroll: true,
            });
        }
    };

    const confirmDelete = (req) => {
        setDeleteConfirm(req);
    };

    const handleDelete = () => {
        if (deleteConfirm) {
            router.delete(route('admin.reference-data.admission-requirements.destroy', deleteConfirm.requirementId), {
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
                aria-label="Edit requirement"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
            </button>
            <button
                onClick={() => confirmDelete(row)}
                className="btn btn-ghost btn-sm text-danger-600 hover:text-danger-900"
                aria-label="Delete requirement"
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
                    title="Admission Requirements"
                    subtitle="Manage admission requirements by applicant type"
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Logo"
                    actions={
                        <button onClick={openCreateModal} className="btn btn-primary">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            New Requirement
                        </button>
                    }
                />
            }
        >
            <Head title="Admission Requirements" />

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
                <FilterBarField label="Applies To">
                    <Select
                        value={appliesToFilter}
                        onChange={setAppliesToFilter}
                        options={appliesToOptions}
                        placeholder="All Types"
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
                {requirements?.data?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={requirements.data}
                            children={renderActions}
                            emptyMessage="No requirements found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={requirements} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No requirements found"
                        message={search || appliesToFilter || status ? 'Try adjusting your filters to find matching records.' : 'No admission requirements have been created yet.'}
                        actionLabel={!search && !appliesToFilter && !status ? 'Create First Requirement' : undefined}
                        onAction={!search && !appliesToFilter && !status ? openCreateModal : undefined}
                    />
                )}
            </Card>

            <Modal
                show={showModal}
                onClose={closeModal}
                title={editingRequirement ? 'Edit Requirement' : 'Create Requirement'}
                subtitle="Define the requirement name, who it applies to, and whether it is mandatory."
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
                        <button type="submit" form="admission-req-form" className="btn btn-primary" disabled={form.processing}>
                            {form.processing ? 'Saving...' : (editingRequirement ? 'Update' : 'Create')}
                        </button>
                    </div>
                }
            >
                <form id="admission-req-form" onSubmit={handleSubmit}>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormSection label="Requirement Name" error={form.errors.requirementName} required>
                            <input
                                type="text"
                                value={form.data.requirementName}
                                onChange={(e) => form.setData('requirementName', e.target.value)}
                                className={`form-input ${form.errors.requirementName ? 'form-input-error' : ''}`}
                                placeholder="e.g., Form 138, Good Moral Certificate"
                                required
                            />
                        </FormSection>
                        <FormSection label="Applies To" error={form.errors.appliesTo} required>
                            <Select
                                value={form.data.appliesTo}
                                onChange={(e) => form.setData('appliesTo', e.target.value)}
                                options={appliesTo.map(a => ({ value: a.value, label: a.value.charAt(0).toUpperCase() + a.value.slice(1).replace(/([A-Z])/g, ' $1') }))}
                                placeholder="Select applicant type"
                                className="form-input"
                                error={form.errors.appliesTo}
                                required
                            />
                        </FormSection>
                        <div className="md:col-span-2">
                            <label className="flex items-center gap-3 cursor-pointer p-3 rounded-btn border border-brand-200 hover:bg-brand-50/50 transition-colors">
                                <input
                                    type="checkbox"
                                    checked={form.data.isRequired}
                                    onChange={(e) => form.setData('isRequired', e.target.checked)}
                                    className="form-checkbox"
                                />
                                <div>
                                    <span className="text-sm font-medium text-brand-800">Required</span>
                                    <p className="text-xs text-brand-500">If unchecked, this requirement is optional for the selected applicant type.</p>
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
                title="Delete Requirement"
                message={`Are you sure you want to delete "${deleteConfirm?.requirementName}"? This action cannot be undone.`}
                confirmText="Delete"
                variant="danger"
            />
        </AuthenticatedLayout>
    );
}