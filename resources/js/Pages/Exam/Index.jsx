import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, Select, EmptyState, StatCard } from '@/Components/ui';
import { useState, useMemo } from 'react';

const stageOptions = [
    { value: '', label: 'All Stages' },
    { value: 'entrance', label: 'Entrance' },
    { value: 'retention', label: 'Retention' },
];

const typeOptions = [
    { value: '', label: 'All Types' },
    { value: 'general', label: 'General' },
    { value: 'courseSpecific', label: 'Course Specific' },
];

const stageToneMap = {
    entrance: 'info',
    retention: 'warning',
};

const typeToneMap = {
    general: 'neutral',
    courseSpecific: 'accent',
};

const resultToneMap = {
    pass: 'success',
    fail: 'danger',
};

export default function Index({ exams, filters = {} }) {
    const [search, setSearch] = useState(filters.search || '');
    const [stage, setStage] = useState(filters.stage || '');
    const [type, setType] = useState(filters.type || '');

    const rows = useMemo(() => exams?.data || [], [exams]);

    // Summary tiles derived from the current page
    const stats = useMemo(() => {
        let pass = 0;
        let fail = 0;
        rows.forEach((r) => {
            if (r.examResult === 'pass') pass += 1;
            else if (r.examResult === 'fail') fail += 1;
        });
        return { pass, fail, total: rows.length };
    }, [rows]);

    const columns = useMemo(() => [
        { key: 'studentIdNumber', label: 'School ID', className: 'font-mono text-sm' },
        { key: 'studentName', label: 'Student Name' },
        { key: 'course', label: 'Course', render: (row) => row.course?.courseName || '—' },
        { key: 'examStage', label: 'Stage', render: (row) => (
            <Badge tone={stageToneMap[row.examStage] || 'neutral'}>
                {row.examStage?.charAt(0).toUpperCase() + row.examStage?.slice(1)}
            </Badge>
        )},
        { key: 'examType', label: 'Type', render: (row) => (
            <Badge tone={typeToneMap[row.examType] || 'neutral'}>
                {row.examType === 'courseSpecific' ? 'Course Specific' : row.examType?.charAt(0).toUpperCase() + row.examType?.slice(1)}
            </Badge>
        )},
        { key: 'examResult', label: 'Result', render: (row) => (
            <Badge tone={resultToneMap[row.examResult] || 'neutral'}>
                {row.examResult?.charAt(0).toUpperCase() + row.examResult?.slice(1)}
            </Badge>
        )},
        { key: 'examDate', label: 'Date', render: (row) => row.examDate ? new Date(row.examDate).toLocaleDateString('en-PH') : '—' },
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('exam.index'), {
            search: search || undefined,
            stage: stage || undefined,
            type: type || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const renderActions = (row) => (
        <div className="flex items-center gap-2">
            <Link
                href={route('exam.results', { search: row.student?.schoolIdNumber })}
                className="btn btn-ghost btn-sm text-brand-600 hover:text-brand-900"
            >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span className="hidden sm:inline">View</span>
            </Link>
        </div>
    );

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Guidance Services & Testing Center"
                    subtitle="Administer and record 2-stage entrance exam results (General Guidance & Academic Dept) and retention gates"
                    logo="/images/logos/guidance-office.jpg"
                    logoAlt="SEAIT Guidance Services & Testing Center"
                    phaseBadge="Phase 0.5 & Retention"
                    officeBadge="Office 4 · Guidance & Testing Desk"
                    actions={
                        <Link href={route('exam.create')} className="btn btn-primary">
                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Record Exam
                        </Link>
                    }
                />
            }
        >
            <Head title="Entrance Exams" />

            {/* Summary tiles */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-5">
                <StatCard
                    compact
                    label="Exams on Page"
                    value={stats.total}
                    iconBg="brand"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    }
                />
                <StatCard
                    compact
                    label="Passed"
                    value={stats.pass}
                    iconBg="success"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
                <StatCard
                    compact
                    label="Failed"
                    value={stats.fail}
                    iconBg="danger"
                    icon={
                        <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    }
                />
            </div>

            {/* Filter Bar */}
            <FilterBar onSubmit={handleFilter}>
                <FilterBarField label="Search">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by name, ID number..."
                        className="form-input"
                    />
                </FilterBarField>
                <FilterBarField label="Stage">
                    <Select
                        value={stage}
                        onChange={setStage}
                        options={stageOptions}
                        placeholder="All Stages"
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

            {/* Data Table */}
            <Card>
                {exams?.data?.length > 0 ? (
                    <>
                        <DataTable
                            columns={columns}
                            rows={exams.data}
                            children={renderActions}
                            emptyMessage="No exam records found"
                        />
                        <div className="mt-4">
                            <Pagination paginator={exams} />
                        </div>
                    </>
                ) : (
                    <EmptyState
                        title="No exam records found"
                        message={search || stage || type ? 'Try adjusting your filters to find matching records.' : 'No exam results have been recorded yet.'}
                        actionLabel={!search && !stage && !type ? 'Record First Exam' : undefined}
                        onAction={!search && !stage && !type ? () => router.visit(route('exam.create')) : undefined}
                        icon={
                            <svg className="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        }
                    />
                )}
            </Card>
        </AuthenticatedLayout>
    );
}
