import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { PageHeader, Card, DataTable, Pagination, FilterBar, FilterBarField, Badge, Select, EmptyState, StatCard, formatStatusLabel } from '@/Components/ui';
import { useState, useMemo } from 'react';

const typeToneMap = {
    general: 'neutral',
    courseSpecific: 'accent',
};

const resultToneMap = {
    pass: 'success',
    fail: 'danger',
};

const typeLabels = {
    general: 'School Entrance',
    courseSpecific: 'Course-Specific',
};

export default function Index({ exams, filters = {}, can = {} }) {
    const [search, setSearch] = useState(filters.search || '');
    const [type, setType] = useState(filters.type || '');

    // Item 4: recording scopes arrive from the backend — Guidance holds
    // exam.record.general (School Entrance, Stage 1); the owning academic
    // department holds exam.record.courseSpecific (Stage 2). A viewer with
    // neither sees only the transferred passer roster.
    const canGeneral = can.recordGeneral ?? false;
    const canCourseSpecific = can.recordCourseSpecific ?? false;
    const isGuidance = canGeneral && !canCourseSpecific;
    const isDepartment = canCourseSpecific && !canGeneral;

    const rows = useMemo(() => exams?.data || [], [exams]);

    // The Exam module is entrance-only now (retention moved to Academic
    // Evaluation), so the stage filter is gone. The type filter offers only
    // the exams this desk owns.
    const typeOptions = useMemo(() => {
        const options = [{ value: '', label: 'All Types' }];
        if (canGeneral || !isDepartment) options.push({ value: 'general', label: 'School Entrance' });
        if (canCourseSpecific || isGuidance) options.push({ value: 'courseSpecific', label: 'Course-Specific' });
        return options;
    }, [canGeneral, canCourseSpecific, isGuidance, isDepartment]);

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
        { key: 'examType', label: 'Exam', render: (row) => (
            <Badge tone={typeToneMap[row.examType] || 'neutral'}>
                {typeLabels[row.examType] || row.examType}
            </Badge>
        )},
        { key: 'examResult', label: 'Result', render: (row) => (
            <Badge tone={resultToneMap[row.examResult] || 'neutral'}>
                {formatStatusLabel(row.examResult)}
            </Badge>
        )},
        { key: 'examDate', label: 'Date', render: (row) => row.examDate ? new Date(row.examDate).toLocaleDateString('en-PH') : '—' },
    ], []);

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(route('exam.index'), {
            search: search || undefined,
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

    // Item 4: the page frames itself by the viewer's desk. Guidance sees all
    // School Entrance results (pass and failed); the department sees its own
    // course-specific exams plus the transferred passers; everyone else sees
    // only the transferred passers.
    const subtitle = isGuidance
        ? 'School Entrance Examination — Stage 1 (BR9): record every result, pass and failed, and transfer the passers to the academic departments'
        : isDepartment
            ? 'Course-specific entrance examinations — Stage 2 (BR9): your department\'s own exams, plus the School Entrance passers transferred from Guidance'
            : canGeneral
                ? 'School Entrance Examination (Stage 1) and course-specific department exams (Stage 2) — the BR9 two-stage workflow'
                : 'School Entrance Examination passers transferred from Guidance — names and results only';

    // The general (School Entrance) exam is Stage 1 — it is always the first
    // exam, so the empty-state shortcut points there and only for Guidance.
    const hasFilters = Boolean(search || type);

    return (
        <AuthenticatedLayout
            header={
                <PageHeader
                    title="Entrance Examinations"
                    subtitle={subtitle}
                    phaseBadge="Phase 0.5 · BR9"
                    officeBadge={isGuidance ? 'Guidance & Testing Center' : 'Academic Departments'}
                    actions={
                        <div className="flex items-center gap-2">
                            {canCourseSpecific && (
                                <Link href={route('exam.create', { type: 'courseSpecific' })} className="btn btn-secondary">
                                    Record Course-Specific Exam
                                </Link>
                            )}
                            {canGeneral && (
                                <Link href={route('exam.create')} className="btn btn-primary">
                                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Record School Entrance Exam
                                </Link>
                            )}
                        </div>
                    }
                />
            }
        >
            <Head title="Entrance Examinations" />

            {/* Passer transfer note — what crosses the office boundary (BR9) */}
            {(isDepartment || (!canGeneral && !canCourseSpecific)) && (
                <div className="mb-5 flex items-start gap-3 rounded-lg border border-info-200 bg-info-50 px-4 py-3 text-sm text-info-900 dark:border-info-800 dark:bg-info-950/40 dark:text-info-100">
                    <svg className="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>
                        {isDepartment
                            ? 'Rows marked “School Entrance · Pass” are passers transferred from the Guidance & Testing Center — names and results only. Failed School Entrance results stay with Guidance.'
                            : 'This roster lists the School Entrance Examination passers transferred from the Guidance & Testing Center. Failed results remain with Guidance.'}
                    </span>
                </div>
            )}

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
                <FilterBarField label="Exam">
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
                        message={hasFilters ? 'Try adjusting your filters to find matching records.' : 'No exam results have been recorded yet.'}
                        actionLabel={canGeneral && !hasFilters ? 'Record First Exam' : undefined}
                        onAction={canGeneral && !hasFilters ? () => router.visit(route('exam.create')) : undefined}
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
