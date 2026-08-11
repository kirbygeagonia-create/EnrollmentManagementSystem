import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, Select, EmptyState, Modal, FormSection, StatCard } from '@/Components/ui';
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

// Modal icon — block section
const BlockIcon = () => (
    <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
    </svg>
);

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
            const enrolled = row.enrolled_subjects_count ?? 0;
            const capacity = row.maxStudents;
            return `${enrolled} / ${capacity}`;
        }},
        { key: 'available', label: 'Available', render: (row) => {
            const enrolled = row.enrolled_subjects_count ?? 0;
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

    // Summary stats
    const totalBlocks = blocks?.total ?? blocks?.data?.length ?? 0;
    const totalCapacity = (blocks?.data || []).reduce((sum, b) => sum + (b.maxStudents || 0), 0);
    const totalEnrolled = (blocks?.data || []).reduce((sum, b) => sum + (b.enrolled_subjects_count ?? 0), 0);
    const totalAvailable = Math.max(0, totalCapacity - totalEnrolled);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Blocking"
                    subtitle="Manage block sections and schedules"
                    logo="/images/logos/seait-logo.png"
                    logoAlt="SEAIT Logo"
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

            <div className="space-y-6">
                {/* Summary Stat Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <StatCard
                        label="Total Blocks"
                        value={totalBlocks}
                        icon={<BlockIcon />}
                        iconBg="seait"
                    />
                    <StatCard
                        label="Total Capacity"
                        value={totalCapacity}
                        icon={
                            <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        }
                        iconBg="brand"
                    />
                    <StatCard
                        label="Enrolled"
                        value={totalEnrolled}
                        icon={
                            <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        }
                        iconBg="info"
                    />
                    <StatCard
                        label="Available Slots"
                        value={totalAvailable}
                        icon={
                            <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        }
                        iconBg={totalAvailable > 0 ? 'success' : 'danger'}
                    />
                </div>

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
            </div>

            {/* Create Block Modal */}
            <Modal
                show={showCreateModal}
                onClose={() => setShowCreateModal(false)}
                title="Create Block"
                subtitle="Define a new block section for a course, term, and year level"
                icon={<BlockIcon />}
                footer={
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={() => setShowCreateModal(false)} className="btn btn-secondary">
                            Cancel
                        </button>
                        <button
                            type="submit"
                            form="create-block-form"
                            disabled={createForm.processing}
                            className="btn btn-primary"
                        >
                            {createForm.processing ? 'Creating...' : 'Create Block'}
                        </button>
                    </div>
                }
            >
                <form id="create-block-form" onSubmit={handleCreate}>
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
