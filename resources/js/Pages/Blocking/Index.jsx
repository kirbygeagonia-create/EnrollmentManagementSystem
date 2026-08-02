import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, Select, EmptyState, Modal, FormSection } from '@/Components/ui';
import PrimaryButton from '@/Components/PrimaryButton';
import { useState, useMemo } from 'react';

const yearLevelOptions = [
    { value: '', label: 'All Year Levels' },
    { value: '1', label: '1st Year' },
    { value: '2', label: '2nd Year' },
    { value: '3', label: '3rd Year' },
    { value: '4', label: '4th Year' },
    { value: '5', label: '5th Year' },
];

const availableToneMap = {
    true: 'success',
    false: 'danger',
};

export default function Index({ blocks, courses, terms, filters = {} }) {
    const [search, setSearch] = useState('');
    const [courseId, setCourseId] = useState(filters.courseId || '');
    const [termId, setTermId] = useState(filters.termId || '');
    const [yearLevel, setYearLevel] = useState(filters.yearLevel || '');
    const [showCreateModal, setShowCreateModal] = useState(false);

    const createForm = useForm({
        blockName: '',
        courseId: '',
        termId: '',
        yearLevel: '1',
        maxStudents: '',
    });

    const handleCreate = (e) => {
        e.preventDefault();
        createForm.post(route('blocking.store'), {
            onSuccess: () => {
                setShowCreateModal(false);
                createForm.reset();
            },
        });
    };

    const columns = useMemo(() => [
        { key: 'blockName', label: 'Block Name / Section' },
        { key: 'course', label: 'Course', render: (row) => row.course?.courseName || '—' },
        { key: 'term', label: 'Term', render: (row) => {
            if (!row.term) return '—';
            const semester = row.term.semester?.value || row.term.semester;
            const yearLabel = row.term.academicYear?.yearLabel || '';
            return `${semester} ${yearLabel}`.trim();
        }},
        { key: 'yearLevel', label: 'Year Level', render: (row) => `${row.yearLevel}${getYearSuffix(row.yearLevel)}` },
        { key: 'capacity', label: 'Enrolled / Capacity', render: (row) => {
            const enrolled = row.enrolledSubjects?.length || 0;
            const capacity = row.maxStudents;
            return `${enrolled} / ${capacity}`;
        }},
        { key: 'available', label: 'Available', render: (row) => {
            const enrolled = row.enrolledSubjects?.length || 0;
            const capacity = row.maxStudents;
            const available = capacity - enrolled;
            const hasAvailable = available > 0;
            return (
                <Badge tone={availableToneMap[hasAvailable]}>
                    {available} slot{available !== 1 ? 's' : ''}
                </Badge>
            );
        }},
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (courseId) params.set('courseId', courseId);
        if (termId) params.set('termId', termId);
        if (yearLevel) params.set('yearLevel', yearLevel);
        window.location.href = `${window.location.pathname}?${params.toString()}`;
    };

    const renderActions = (row) => (
        <div className="flex items-center gap-2">
            <Link
                href={route('blocking.show', { block: row.blockId })}
                className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                <span className="hidden sm:inline">View</span>
            </Link>
            <Link
                href={route('blocking.print-schedule', { block: row.blockId })}
                className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900"
                target="_blank"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                <span className="hidden sm:inline">Print</span>
            </Link>
        </div>
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Blocking"
                    subtitle="Manage block sections and schedules"
                    actions={
                        <button onClick={() => { createForm.reset(); setShowCreateModal(true); }} className="btn btn-primary">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Create Block
                        </button>
                    }
                />
            }
        >
            <Head title="Blocking" />

            {/* Filter Bar */}
            <FilterBar onSubmit={handleFilter}>
                <FilterBarField label="Search">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by block name..."
                        className="form-input"
                    />
                </FilterBarField>
                <FilterBarField label="Course">
                    <Select
                        value={courseId}
                        onChange={setCourseId}
                        options={courses.map(c => ({ value: c.courseId, label: `${c.courseCode} - ${c.courseName}` }))}
                        placeholder="All Courses"
                        className="form-input"
                    />
                </FilterBarField>
                <FilterBarField label="Term">
                    <Select
                        value={termId}
                        onChange={setTermId}
                        options={terms.map(t => ({ value: t.termId, label: `${t.semester?.value || t.semester} ${t.academicYear?.yearLabel || ''}`.trim() }))}
                        placeholder="All Terms"
                        className="form-input"
                    />
                </FilterBarField>
                <FilterBarField label="Year Level">
                    <Select
                        value={yearLevel}
                        onChange={setYearLevel}
                        options={yearLevelOptions}
                        placeholder="All Year Levels"
                        className="form-input"
                    />
                </FilterBarField>
            </FilterBar>

            {/* Data Table */}
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
                        message={search || courseId || termId || yearLevel ? 'Try adjusting your filters to find matching records.' : 'No blocks have been created yet.'}
                        actionLabel={!search && !courseId && !termId && !yearLevel ? 'Create First Block' : undefined}
                        onAction={!search && !courseId && !termId && !yearLevel ? () => { createForm.reset(); setShowCreateModal(true); } : undefined}
                    />
                )}
            </Card>

            {/* Create Block Modal */}
            <Modal show={showCreateModal} onClose={() => setShowCreateModal(false)} title="Create Block">
                <form onSubmit={handleCreate}>
                    <div className="space-y-4">
                        <FormSection label="Block Name / Section">
                            <input
                                type="text"
                                value={createForm.data.blockName}
                                onChange={(e) => createForm.setData('blockName', e.target.value)}
                                className="form-input"
                                placeholder="e.g. BSIT-1A"
                                required
                            />
                        </FormSection>
                        <FormSection label="Course">
                            <Select
                                value={createForm.data.courseId}
                                onChange={(v) => createForm.setData('courseId', v)}
                                options={courses.map(c => ({ value: c.courseId, label: `${c.courseCode} - ${c.courseName}` }))}
                                placeholder="Select course"
                            />
                        </FormSection>
                        <FormSection label="Term">
                            <Select
                                value={createForm.data.termId}
                                onChange={(v) => createForm.setData('termId', v)}
                                options={terms.map(t => ({ value: t.termId, label: `${t.semester?.value || t.semester} ${t.academicYear?.yearLabel || ''}`.trim() }))}
                                placeholder="Select term"
                            />
                        </FormSection>
                        <FormSection label="Year Level">
                            <Select
                                value={createForm.data.yearLevel}
                                onChange={(v) => createForm.setData('yearLevel', v)}
                                options={yearLevelOptions.filter(o => o.value !== '')}
                            />
                        </FormSection>
                        <FormSection label="Max Students">
                            <input
                                type="number"
                                value={createForm.data.maxStudents}
                                onChange={(e) => createForm.setData('maxStudents', e.target.value)}
                                className="form-input"
                                placeholder="e.g. 40"
                                min="1"
                                required
                            />
                        </FormSection>
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <button type="button" onClick={() => setShowCreateModal(false)} className="btn btn-secondary">
                            Cancel
                        </button>
                        <PrimaryButton type="submit" disabled={createForm.processing}>
                            Create Block
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
        </AuthenticatedLayout>
    );
}

function getYearSuffix(year) {
    if (year === 1) return 'st';
    if (year === 2) return 'nd';
    if (year === 3) return 'rd';
    return 'th';
}